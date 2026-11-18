<?php

namespace App\Services;

use App\Models\Review;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Laravel\Connection;

class MongoReviewRepository
{
    /** @var array<string, array<string, mixed>> */
    private array $documents = [];

    private int $nextId = 1;

    /**
     * @return array{data: array<int, array<string, mixed>>, current_page: int, last_page: int, total: int}
     */
    public function paginate(int $movieId, string $filter = 'all', int $page = 1, int $perPage = 10): array
    {
        if ($this->usesMysql()) {
            $query = Review::query()->where('movie_id', $movieId);
            $this->applyMysqlRatingFilter($query, $filter);
            $paginator = $query->latest()->paginate($perPage, ['*'], 'page', $page);

            return [
                'data' => array_map(fn (Review $review) => $this->normalizeMysqlReview($review), $paginator->items()),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ];
        }

        if ($this->usesArray()) {
            $rows = array_values(array_filter(
                $this->documents,
                fn (array $review) => $review['movie_id'] === $movieId
                    && $review['status'] === 'published'
                    && $this->ratingMatches((int) $review['rating'], $filter),
            ));
            usort($rows, fn (array $left, array $right) => strcmp((string) $right['created_at'], (string) $left['created_at']));
            $total = count($rows);

            return [
                'data' => array_slice($rows, ($page - 1) * $perPage, $perPage),
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'total' => $total,
            ];
        }

        $filterDocument = ['movie_id' => $movieId, 'status' => 'published'];
        if ($filter === '9-10') {
            $filterDocument['rating'] = ['$gte' => 9, '$lte' => 10];
        } elseif ($filter === '7-8') {
            $filterDocument['rating'] = ['$gte' => 7, '$lte' => 8];
        } elseif ($filter === 'under_7') {
            $filterDocument['rating'] = ['$lt' => 7];
        }

        $total = $this->collection()->countDocuments($filterDocument);
        $cursor = $this->collection()->find($filterDocument, [
            'sort' => ['created_at' => -1, '_id' => -1],
            'skip' => ($page - 1) * $perPage,
            'limit' => $perPage,
        ]);

        return [
            'data' => array_map(fn ($document) => $this->normalize($document), $cursor->toArray()),
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'total' => $total,
        ];
    }

    /**
     * @param  array<int, int>  $movieIds
     * @return array<int, array<string, int|float>>
     */
    public function summaries(array $movieIds): array
    {
        $movieIds = array_values(array_unique(array_map('intval', $movieIds)));
        if ($movieIds === []) {
            return [];
        }

        if ($this->usesMysql()) {
            $ratingsByMovie = Review::query()
                ->whereIn('movie_id', $movieIds)
                ->get(['movie_id', 'rating'])
                ->groupBy('movie_id');

            return collect($movieIds)->mapWithKeys(function (int $movieId) use ($ratingsByMovie) {
                $ratings = $ratingsByMovie->get($movieId, collect())->pluck('rating')->map(fn ($rating) => (int) $rating)->all();

                return [$movieId => $this->calculateSummary($ratings)];
            })->all();
        }

        if ($this->usesArray()) {
            $result = [];
            foreach ($movieIds as $movieId) {
                $ratings = array_map(
                    fn (array $review) => (int) $review['rating'],
                    array_filter($this->documents, fn (array $review) => $review['movie_id'] === $movieId && $review['status'] === 'published'),
                );
                $result[$movieId] = $this->calculateSummary($ratings);
            }

            return $result;
        }

        $pipeline = [
            ['$match' => ['movie_id' => ['$in' => $movieIds], 'status' => 'published']],
            ['$group' => [
                '_id' => '$movie_id',
                'total_reviews' => ['$sum' => 1],
                'average_rating' => ['$avg' => '$rating'],
                'count_9_10' => ['$sum' => ['$cond' => [['$gte' => ['$rating', 9]], 1, 0]]],
                'count_7_8' => ['$sum' => ['$cond' => [['$and' => [['$gte' => ['$rating', 7]], ['$lte' => ['$rating', 8]]]], 1, 0]]],
                'count_5_6' => ['$sum' => ['$cond' => [['$and' => [['$gte' => ['$rating', 5]], ['$lte' => ['$rating', 6]]]], 1, 0]]],
                'count_3_4' => ['$sum' => ['$cond' => [['$and' => [['$gte' => ['$rating', 3]], ['$lte' => ['$rating', 4]]]], 1, 0]]],
                'count_1_2' => ['$sum' => ['$cond' => [['$lte' => ['$rating', 2]], 1, 0]]],
            ]],
        ];

        $aggregates = [];
        foreach ($this->collection()->aggregate($pipeline) as $row) {
            $normalized = $this->normalize($row);
            $movieId = (int) $normalized['id'];
            $total = (int) $normalized['total_reviews'];
            $satisfied = (int) $normalized['count_9_10'] + (int) $normalized['count_7_8'];
            $aggregates[$movieId] = [
                'total_reviews' => $total,
                'average_rating' => round((float) $normalized['average_rating'], 1),
                'satisfaction_rate' => $total > 0 ? (int) round(($satisfied / $total) * 100) : 100,
                'count_9_10' => (int) $normalized['count_9_10'],
                'count_7_8' => (int) $normalized['count_7_8'],
                'count_5_6' => (int) $normalized['count_5_6'],
                'count_3_4' => (int) $normalized['count_3_4'],
                'count_1_2' => (int) $normalized['count_1_2'],
            ];
        }

        foreach ($movieIds as $movieId) {
            $aggregates[$movieId] ??= $this->calculateSummary([]);
        }

        return $aggregates;
    }

    /** @return array<string, int|float> */
    public function summary(int $movieId): array
    {
        return $this->summaries([$movieId])[$movieId];
    }

    public function exists(int $movieId, int $userId): bool
    {
        if ($this->usesMysql()) {
            return Review::query()->where('movie_id', $movieId)->where('user_id', $userId)->exists();
        }

        if ($this->usesArray()) {
            return collect($this->documents)->contains(
                fn (array $review) => $review['movie_id'] === $movieId && $review['user_id'] === $userId,
            );
        }

        return $this->collection()->countDocuments(['movie_id' => $movieId, 'user_id' => $userId], ['limit' => 1]) > 0;
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): array
    {
        if ($this->usesMysql()) {
            $review = Review::query()->create([
                'movie_id' => $attributes['movie_id'],
                'user_id' => $attributes['user_id'],
                'rating' => $attributes['rating'],
                'comment' => $attributes['content'] ?? null,
            ]);

            return $this->normalizeMysqlReview($review);
        }

        $now = now();
        $document = [
            'movie_id' => (int) $attributes['movie_id'],
            'user_id' => (int) $attributes['user_id'],
            'order_id' => isset($attributes['order_id']) ? (int) $attributes['order_id'] : null,
            'rating' => (int) $attributes['rating'],
            'content' => $attributes['content'] ?? null,
            'tags' => array_values($attributes['tags'] ?? []),
            'status' => $attributes['status'] ?? 'published',
            'source' => $attributes['source'] ?? 'application',
            'watched_at' => $attributes['watched_at'] ?? null,
            'created_at' => $attributes['created_at'] ?? $now,
            'updated_at' => $attributes['updated_at'] ?? $now,
        ];

        if ($this->usesArray()) {
            if ($this->exists($document['movie_id'], $document['user_id'])) {
                throw new \RuntimeException('Duplicate movie review.');
            }
            $id = (string) $this->nextId++;
            $this->documents[$id] = ['id' => $id, ...$document, 'comment' => $document['content']];

            return $this->documents[$id];
        }

        $stored = $this->toBsonDates($document);
        $result = $this->collection()->insertOne($stored);

        return $this->find((string) $result->getInsertedId());
    }

    public function find(string $reviewId): ?array
    {
        if ($this->usesMysql()) {
            $review = Review::query()->find($reviewId);

            return $review ? $this->normalizeMysqlReview($review) : null;
        }

        if ($this->usesArray()) {
            return $this->documents[$reviewId] ?? null;
        }

        if (! preg_match('/^[a-f0-9]{24}$/i', $reviewId)) {
            return null;
        }

        $document = $this->collection()->findOne(['_id' => new ObjectId($reviewId)]);

        return $document ? $this->normalize($document) : null;
    }

    /** @param array<string, mixed> $attributes */
    public function updateOwned(string $reviewId, int $movieId, int $userId, array $attributes): ?array
    {
        if ($this->usesMysql()) {
            $review = Review::query()->whereKey($reviewId)->where('movie_id', $movieId)->where('user_id', $userId)->first();
            if (! $review) {
                return null;
            }
            $review->update(array_filter([
                'rating' => $attributes['rating'] ?? null,
                'comment' => array_key_exists('content', $attributes) ? $attributes['content'] : null,
            ], fn ($value, $key) => $key === 'comment' ? array_key_exists('content', $attributes) : $value !== null, ARRAY_FILTER_USE_BOTH));

            return $this->normalizeMysqlReview($review->fresh());
        }

        $review = $this->find($reviewId);
        if (! $review || $review['movie_id'] !== $movieId || $review['user_id'] !== $userId) {
            return null;
        }

        $updates = ['updated_at' => now()];
        foreach (['rating', 'content', 'tags'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $updates[$field] = $field === 'rating' ? (int) $attributes[$field] : $attributes[$field];
            }
        }

        if ($this->usesArray()) {
            if (array_key_exists('content', $updates)) {
                $updates['comment'] = $updates['content'];
            }
            $this->documents[$reviewId] = [...$review, ...$updates];

            return $this->documents[$reviewId];
        }

        $this->collection()->updateOne(['_id' => new ObjectId($reviewId)], ['$set' => $this->toBsonDates($updates)]);

        return $this->find($reviewId);
    }

    public function deleteOwned(string $reviewId, int $movieId, int $userId): ?array
    {
        $review = $this->find($reviewId);
        if (! $review || $review['movie_id'] !== $movieId || $review['user_id'] !== $userId) {
            return null;
        }

        if ($this->usesMysql()) {
            Review::query()->whereKey($reviewId)->delete();
        } elseif ($this->usesArray()) {
            unset($this->documents[$reviewId]);
        } else {
            $this->collection()->deleteOne(['_id' => new ObjectId($reviewId)]);
        }

        return $review;
    }

    /** @param array<string, mixed> $attributes */
    public function upsertLegacy(int $legacyId, array $attributes): void
    {
        $attributes['source'] = 'mysql_migration';
        $attributes['legacy_review_id'] = $legacyId;

        if ($this->usesArray()) {
            $existingId = collect($this->documents)->search(
                fn (array $review) => ($review['legacy_review_id'] ?? null) === $legacyId,
            );
            if ($existingId !== false) {
                $this->documents[$existingId] = [...$this->documents[$existingId], ...$attributes];

                return;
            }
            $created = $this->create($attributes);
            $this->documents[$created['id']]['legacy_review_id'] = $legacyId;

            return;
        }

        if ($this->usesMysql()) {
            return;
        }

        $document = $this->toBsonDates([
            ...$attributes,
            'movie_id' => (int) $attributes['movie_id'],
            'user_id' => (int) $attributes['user_id'],
            'order_id' => isset($attributes['order_id']) ? (int) $attributes['order_id'] : null,
            'rating' => (int) $attributes['rating'],
            'tags' => array_values($attributes['tags'] ?? []),
            'status' => $attributes['status'] ?? 'published',
        ]);
        $this->collection()->updateOne(
            ['movie_id' => (int) $attributes['movie_id'], 'user_id' => (int) $attributes['user_id']],
            ['$set' => $document],
            ['upsert' => true],
        );
    }

    public function countSource(string $source): int
    {
        if ($this->usesArray()) {
            return collect($this->documents)->where('source', $source)->count();
        }
        if ($this->usesMysql()) {
            return 0;
        }

        return $this->collection()->countDocuments(['source' => $source]);
    }

    public function deleteSource(string $source): int
    {
        if ($this->usesArray()) {
            $before = count($this->documents);
            $this->documents = array_filter($this->documents, fn (array $review) => ($review['source'] ?? null) !== $source);

            return $before - count($this->documents);
        }
        if ($this->usesMysql()) {
            return 0;
        }

        return $this->collection()->deleteMany(['source' => $source])->getDeletedCount();
    }

    private function collection(): Collection
    {
        $connection = DB::connection((string) config('nosql.mongodb.connection', 'mongodb'));
        if (! $connection instanceof Connection) {
            throw new \RuntimeException('The configured MongoDB connection is invalid.');
        }

        return $connection->getCollection((string) config('nosql.mongodb.review_collection', 'movie_reviews'));
    }

    private function usesArray(): bool
    {
        return config('nosql.mongodb.driver') === 'array';
    }

    private function usesMysql(): bool
    {
        return config('nosql.mongodb.driver') === 'mysql';
    }

    /** @return array<string, int|float> */
    private function calculateSummary(array $ratings): array
    {
        $total = count($ratings);
        $counts = [
            'count_9_10' => count(array_filter($ratings, fn (int $rating) => $rating >= 9)),
            'count_7_8' => count(array_filter($ratings, fn (int $rating) => $rating >= 7 && $rating <= 8)),
            'count_5_6' => count(array_filter($ratings, fn (int $rating) => $rating >= 5 && $rating <= 6)),
            'count_3_4' => count(array_filter($ratings, fn (int $rating) => $rating >= 3 && $rating <= 4)),
            'count_1_2' => count(array_filter($ratings, fn (int $rating) => $rating <= 2)),
        ];

        return [
            'total_reviews' => $total,
            'average_rating' => $total > 0 ? round(array_sum($ratings) / $total, 1) : 0,
            'satisfaction_rate' => $total > 0 ? (int) round((($counts['count_9_10'] + $counts['count_7_8']) / $total) * 100) : 100,
            ...$counts,
        ];
    }

    private function ratingMatches(int $rating, string $filter): bool
    {
        return match ($filter) {
            '9-10' => $rating >= 9,
            '7-8' => $rating >= 7 && $rating <= 8,
            'under_7' => $rating < 7,
            default => true,
        };
    }

    private function applyMysqlRatingFilter($query, string $filter): void
    {
        match ($filter) {
            '9-10' => $query->whereBetween('rating', [9, 10]),
            '7-8' => $query->whereBetween('rating', [7, 8]),
            'under_7' => $query->where('rating', '<', 7),
            default => null,
        };
    }

    /** @return array<string, mixed> */
    private function normalizeMysqlReview(Review $review): array
    {
        return [
            'id' => (string) $review->id,
            'movie_id' => (int) $review->movie_id,
            'user_id' => (int) $review->user_id,
            'order_id' => null,
            'rating' => (int) $review->rating,
            'content' => $review->comment,
            'comment' => $review->comment,
            'tags' => [],
            'status' => 'published',
            'source' => 'mysql',
            'watched_at' => null,
            'created_at' => $review->created_at?->toISOString(),
            'updated_at' => $review->updated_at?->toISOString(),
        ];
    }

    /** @param array<string, mixed> $document */
    private function toBsonDates(array $document): array
    {
        foreach (['watched_at', 'created_at', 'updated_at'] as $field) {
            if (($document[$field] ?? null) instanceof \DateTimeInterface) {
                $document[$field] = new UTCDateTime($document[$field]);
            }
        }

        return $document;
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof ObjectId) {
            return (string) $value;
        }
        if ($value instanceof UTCDateTime) {
            return Carbon::instance($value->toDateTime())->toISOString();
        }
        if (is_object($value) && method_exists($value, 'getArrayCopy')) {
            $value = $value->getArrayCopy();
        } elseif (is_object($value) && method_exists($value, 'toArray')) {
            $value = $value->toArray();
        }
        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[$key === '_id' ? 'id' : $key] = $this->normalize($item);
            }
            if (array_key_exists('content', $normalized)) {
                $normalized['comment'] = $normalized['content'];
            }

            return $normalized;
        }

        return $value;
    }
}

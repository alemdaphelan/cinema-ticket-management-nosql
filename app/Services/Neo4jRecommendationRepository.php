<?php

namespace App\Services;

use App\Events\ActivityOccurred;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class Neo4jRecommendationRepository
{
    /** @var array<int, array<string, mixed>> */
    private array $users = [];

    /** @var array<int, array<string, mixed>> */
    private array $movies = [];

    /** @var array<int, list<string>> */
    private array $genres = [];

    /** @var array<string, array<string, mixed>> */
    private array $relationships = [];

    public function setupSchema(): void
    {
        if ($this->usesArray()) {
            return;
        }

        $this->run([
            ['statement' => 'CREATE CONSTRAINT user_id_unique IF NOT EXISTS FOR (user:User) REQUIRE user.user_id IS UNIQUE'],
            ['statement' => 'CREATE CONSTRAINT movie_id_unique IF NOT EXISTS FOR (movie:Movie) REQUIRE movie.movie_id IS UNIQUE'],
            ['statement' => 'CREATE CONSTRAINT genre_name_unique IF NOT EXISTS FOR (genre:Genre) REQUIRE genre.name IS UNIQUE'],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $users
     * @param  list<array<string, mixed>>  $movies
     */
    public function syncBase(array $users, array $movies): void
    {
        if ($this->usesArray()) {
            foreach ($users as $user) {
                $this->users[(int) $user['user_id']] = $user;
            }
            foreach ($movies as $movie) {
                $movieId = (int) $movie['movie_id'];
                $this->movies[$movieId] = $movie;
                $this->genres[$movieId] = array_values(array_unique($movie['genres'] ?? []));
            }

            return;
        }

        $this->run([
            [
                'statement' => 'UNWIND $users AS row MERGE (user:User {user_id: row.user_id}) SET user.name = row.name, user.role = row.role, user.updated_at = datetime()',
                'parameters' => ['users' => $users],
            ],
            [
                'statement' => 'UNWIND $movies AS row MERGE (movie:Movie {movie_id: row.movie_id}) SET movie.title = row.title, movie.status = row.status, movie.poster_url = row.poster_url, movie.release_date = row.release_date, movie.updated_at = datetime() WITH movie, row OPTIONAL MATCH (movie)-[old:IN_GENRE]->(:Genre) DELETE old WITH movie, row UNWIND row.genres AS genre_name MERGE (genre:Genre {name: genre_name}) MERGE (movie)-[:IN_GENRE]->(genre)',
                'parameters' => ['movies' => $movies],
            ],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $bookings
     * @param  list<array<string, mixed>>  $ratings
     */
    public function syncRelationships(array $bookings, array $ratings): void
    {
        if ($this->usesArray()) {
            foreach ($bookings as $booking) {
                $key = $this->relationshipKey((int) $booking['user_id'], 'BOOKED', (int) $booking['movie_id']);
                $this->relationships[$key] = [
                    'user_id' => (int) $booking['user_id'],
                    'movie_id' => (int) $booking['movie_id'],
                    'type' => 'BOOKED',
                    'count' => (int) ($booking['count'] ?? 1),
                    'last_event_id' => (string) ($booking['last_event_id'] ?? ''),
                ];
            }
            foreach ($ratings as $rating) {
                $key = $this->relationshipKey((int) $rating['user_id'], 'RATED', (int) $rating['movie_id']);
                $this->relationships[$key] = [
                    'user_id' => (int) $rating['user_id'],
                    'movie_id' => (int) $rating['movie_id'],
                    'type' => 'RATED',
                    'rating' => (int) $rating['rating'],
                    'last_event_id' => (string) ($rating['last_event_id'] ?? ''),
                ];
            }

            return;
        }

        $this->run([
            [
                'statement' => 'UNWIND $rows AS row MATCH (user:User {user_id: row.user_id}), (movie:Movie {movie_id: row.movie_id}) MERGE (user)-[rel:BOOKED]->(movie) SET rel.count = row.count, rel.last_at = datetime(row.last_at), rel.last_event_id = row.last_event_id',
                'parameters' => ['rows' => $bookings],
            ],
            [
                'statement' => 'UNWIND $rows AS row MATCH (user:User {user_id: row.user_id}), (movie:Movie {movie_id: row.movie_id}) MERGE (user)-[rel:RATED]->(movie) SET rel.rating = row.rating, rel.at = datetime(row.at), rel.last_event_id = row.last_event_id',
                'parameters' => ['rows' => $ratings],
            ],
        ]);
    }

    public function project(ActivityOccurred $event): void
    {
        if (! $event->userId || ! $event->movieId) {
            return;
        }

        $type = match ($event->eventType) {
            'MOVIE_VIEWED' => 'VIEWED',
            'ORDER_PAID' => 'BOOKED',
            'REVIEW_CREATED', 'REVIEW_UPDATED', 'REVIEW_DELETED' => 'RATED',
            default => null,
        };
        if (! $type) {
            return;
        }

        if ($this->usesArray()) {
            $key = $this->relationshipKey($event->userId, $type, $event->movieId);
            if ($event->eventType === 'REVIEW_DELETED') {
                unset($this->relationships[$key]);

                return;
            }
            $current = $this->relationships[$key] ?? [];
            if (($current['last_event_id'] ?? null) === $event->eventId) {
                return;
            }
            $this->relationships[$key] = [
                ...$current,
                'user_id' => $event->userId,
                'movie_id' => $event->movieId,
                'type' => $type,
                'count' => in_array($type, ['VIEWED', 'BOOKED'], true) ? ((int) ($current['count'] ?? 0) + 1) : 1,
                'rating' => $type === 'RATED' ? (int) ($event->metadata['rating'] ?? 0) : null,
                'last_event_id' => $event->eventId,
            ];

            return;
        }

        if ($event->eventType === 'REVIEW_DELETED') {
            $this->run([[
                'statement' => 'MATCH (:User {user_id: $user_id})-[rel:RATED]->(:Movie {movie_id: $movie_id}) DELETE rel',
                'parameters' => ['user_id' => $event->userId, 'movie_id' => $event->movieId],
            ]]);

            return;
        }

        $relationship = match ($type) {
            'VIEWED' => 'MERGE (user)-[rel:VIEWED]->(movie) ON CREATE SET rel.count = 0 WITH rel WHERE rel.last_event_id IS NULL OR rel.last_event_id <> $event_id SET rel.count = coalesce(rel.count, 0) + 1, rel.last_at = datetime($occurred_at), rel.last_event_id = $event_id',
            'BOOKED' => 'MERGE (user)-[rel:BOOKED]->(movie) ON CREATE SET rel.count = 0 WITH rel WHERE rel.last_event_id IS NULL OR rel.last_event_id <> $event_id SET rel.count = coalesce(rel.count, 0) + 1, rel.last_at = datetime($occurred_at), rel.last_event_id = $event_id',
            default => 'MERGE (user)-[rel:RATED]->(movie) SET rel.rating = $rating, rel.at = datetime($occurred_at), rel.last_event_id = $event_id',
        };
        $this->run([[
            'statement' => 'MATCH (user:User {user_id: $user_id}), (movie:Movie {movie_id: $movie_id}) '.$relationship,
            'parameters' => [
                'user_id' => $event->userId,
                'movie_id' => $event->movieId,
                'event_id' => $event->eventId,
                'occurred_at' => $event->occurredAt ?? now()->utc()->toIso8601String(),
                'rating' => (int) ($event->metadata['rating'] ?? 0),
            ],
        ]]);
    }

    /** @return list<array<string, mixed>> */
    public function similarMovies(int $movieId, int $limit = 8): array
    {
        if ($this->usesArray()) {
            if (! isset($this->movies[$movieId])) {
                return [];
            }
            $sourceGenres = $this->genres[$movieId] ?? [];
            $sourceUsers = $this->interactingUsers($movieId);
            $result = [];
            foreach ($this->movies as $candidateId => $movie) {
                if ($candidateId === $movieId || ($movie['status'] ?? null) === 'stopped') {
                    continue;
                }
                $sharedGenres = count(array_intersect($sourceGenres, $this->genres[$candidateId] ?? []));
                $sharedUsers = count(array_intersect($sourceUsers, $this->interactingUsers($candidateId)));
                if ($sharedGenres + $sharedUsers === 0) {
                    continue;
                }
                $result[] = [...$movie, 'score' => $sharedGenres * 3 + $sharedUsers * 2, 'shared_genres' => $sharedGenres, 'shared_users' => $sharedUsers];
            }

            return $this->rank($result, $limit);
        }

        return $this->rows('MATCH (source:Movie {movie_id: $movie_id})-[:IN_GENRE]->(genre:Genre)<-[:IN_GENRE]-(candidate:Movie) WHERE candidate.movie_id <> source.movie_id AND candidate.status <> \'stopped\' OPTIONAL MATCH (user:User)-[:BOOKED|VIEWED|RATED]->(source) OPTIONAL MATCH (user)-[:BOOKED|VIEWED|RATED]->(candidate) WITH candidate, count(DISTINCT genre) AS shared_genres, count(DISTINCT user) AS shared_users WITH candidate, shared_genres, shared_users, shared_genres * 3.0 + shared_users * 2.0 AS score RETURN candidate.movie_id AS movie_id, candidate.title AS title, candidate.status AS status, candidate.poster_url AS poster_url, score, shared_genres, shared_users ORDER BY score DESC, movie_id ASC LIMIT $limit', [
            'movie_id' => $movieId,
            'limit' => $limit,
        ]);
    }

    /** @return array{strategy: string, data: list<array<string, mixed>>} */
    public function recommendations(int $userId, int $limit = 10, ?string $genre = null): array
    {
        if ($this->usesArray()) {
            $booked = $this->movieIdsForUser($userId, 'BOOKED');
            $interacted = array_values(array_unique([...$booked, ...$this->movieIdsForUser($userId, 'VIEWED'), ...$this->movieIdsForUser($userId, 'RATED')]));
            if ($interacted === []) {
                return ['strategy' => 'cold_start_popular', 'data' => $this->popularArray($booked, $limit, $genre)];
            }
            $preferred = [];
            foreach ($interacted as $movieId) {
                foreach ($this->genres[$movieId] ?? [] as $name) {
                    $preferred[$name] = ($preferred[$name] ?? 0) + 1;
                }
            }
            $rows = [];
            foreach ($this->movies as $movieId => $movie) {
                if (in_array($movieId, $booked, true) || ($movie['status'] ?? null) === 'stopped') {
                    continue;
                }
                $genreScore = array_sum(array_map(fn (string $name): int => $preferred[$name] ?? 0, $this->genres[$movieId] ?? []));
                $collaborative = count(array_intersect($this->interactingUsers($movieId), $this->similarUsers($userId, $interacted)));
                $score = $genreScore * 3 + $collaborative * 2;
                if ($score > 0) {
                    $rows[] = [...$movie, 'score' => $score, 'genre_affinity' => $genreScore, 'similar_users' => $collaborative];
                }
            }

            return ['strategy' => 'personalized_graph', 'data' => $this->rank($rows, $limit)];
        }

        $rows = $this->rows('MATCH (user:User {user_id: $user_id})-[:BOOKED|VIEWED|RATED]->(history:Movie)-[:IN_GENRE]->(genre:Genre)<-[:IN_GENRE]-(candidate:Movie) WHERE candidate.status <> \'stopped\' AND NOT (user)-[:BOOKED]->(candidate) OPTIONAL MATCH (peer:User)-[:BOOKED|VIEWED|RATED]->(history) OPTIONAL MATCH (peer)-[:BOOKED|VIEWED|RATED]->(candidate) WITH candidate, count(DISTINCT genre) AS genre_affinity, count(DISTINCT peer) AS similar_users WITH candidate, genre_affinity, similar_users, genre_affinity * 3.0 + similar_users * 2.0 AS score RETURN candidate.movie_id AS movie_id, candidate.title AS title, candidate.status AS status, candidate.poster_url AS poster_url, score, genre_affinity, similar_users ORDER BY score DESC, movie_id ASC LIMIT $limit', [
            'user_id' => $userId,
            'limit' => $limit,
        ]);
        if ($rows !== []) {
            return ['strategy' => 'personalized_graph', 'data' => $rows];
        }

        return ['strategy' => 'cold_start_popular', 'data' => $this->rows('MATCH (candidate:Movie) WHERE candidate.status <> \'stopped\' AND ($genre IS NULL OR EXISTS { MATCH (candidate)-[:IN_GENRE]->(:Genre {name: $genre}) }) OPTIONAL MATCH (:User)-[interaction:BOOKED|VIEWED|RATED]->(candidate) WITH candidate, sum(CASE type(interaction) WHEN \'BOOKED\' THEN 5.0 WHEN \'RATED\' THEN 3.0 ELSE 1.0 END) AS score RETURN candidate.movie_id AS movie_id, candidate.title AS title, candidate.status AS status, candidate.poster_url AS poster_url, coalesce(score, 0) AS score ORDER BY score DESC, movie_id ASC LIMIT $limit', [
            'genre' => $genre,
            'limit' => $limit,
        ])];
    }

    /** @return array<string, int> */
    public function stats(): array
    {
        if ($this->usesArray()) {
            return [
                'users' => count($this->users),
                'movies' => count($this->movies),
                'genres' => count(array_unique(array_merge(...array_values($this->genres ?: [[]])))),
                'in_genre' => array_sum(array_map('count', $this->genres)),
                'viewed' => count(array_filter($this->relationships, fn (array $row): bool => $row['type'] === 'VIEWED')),
                'booked' => count(array_filter($this->relationships, fn (array $row): bool => $row['type'] === 'BOOKED')),
                'rated' => count(array_filter($this->relationships, fn (array $row): bool => $row['type'] === 'RATED')),
            ];
        }

        return $this->rows('MATCH (node) OPTIONAL MATCH ()-[relationship]->() RETURN count(DISTINCT CASE WHEN node:User THEN node END) AS users, count(DISTINCT CASE WHEN node:Movie THEN node END) AS movies, count(DISTINCT CASE WHEN node:Genre THEN node END) AS genres, count(DISTINCT CASE WHEN type(relationship) = \'IN_GENRE\' THEN relationship END) AS in_genre, count(DISTINCT CASE WHEN type(relationship) = \'VIEWED\' THEN relationship END) AS viewed, count(DISTINCT CASE WHEN type(relationship) = \'BOOKED\' THEN relationship END) AS booked, count(DISTINCT CASE WHEN type(relationship) = \'RATED\' THEN relationship END) AS rated')[0] ?? [];
    }

    public function deleteMovie(int $movieId): void
    {
        if ($this->usesArray()) {
            unset($this->movies[$movieId], $this->genres[$movieId]);
            $this->relationships = array_filter($this->relationships, fn (array $row): bool => $row['movie_id'] !== $movieId);

            return;
        }
        $this->run([['statement' => 'MATCH (movie:Movie {movie_id: $movie_id}) DETACH DELETE movie', 'parameters' => ['movie_id' => $movieId]]]);
    }

    /** @return list<array<string, mixed>> */
    private function popularArray(array $booked, int $limit, ?string $genre): array
    {
        $rows = [];
        foreach ($this->movies as $movieId => $movie) {
            if (in_array($movieId, $booked, true) || ($movie['status'] ?? null) === 'stopped' || ($genre && ! in_array($genre, $this->genres[$movieId] ?? [], true))) {
                continue;
            }
            $score = 0;
            foreach ($this->relationships as $relationship) {
                if ($relationship['movie_id'] === $movieId) {
                    $score += match ($relationship['type']) {
                        'BOOKED' => 5,
                        'RATED' => 3,
                        default => 1,
                    };
                }
            }
            $rows[] = [...$movie, 'score' => $score];
        }

        return $this->rank($rows, $limit);
    }

    /** @return list<int> */
    private function interactingUsers(int $movieId): array
    {
        return array_values(array_unique(array_map(
            fn (array $row): int => $row['user_id'],
            array_filter($this->relationships, fn (array $row): bool => $row['movie_id'] === $movieId),
        )));
    }

    /** @return list<int> */
    private function movieIdsForUser(int $userId, string $type): array
    {
        return array_values(array_unique(array_map(
            fn (array $row): int => $row['movie_id'],
            array_filter($this->relationships, fn (array $row): bool => $row['user_id'] === $userId && $row['type'] === $type),
        )));
    }

    /** @param list<int> $history @return list<int> */
    private function similarUsers(int $userId, array $history): array
    {
        $users = [];
        foreach ($history as $movieId) {
            $users = [...$users, ...$this->interactingUsers($movieId)];
        }

        return array_values(array_diff(array_unique($users), [$userId]));
    }

    /** @param list<array<string, mixed>> $rows @return list<array<string, mixed>> */
    private function rank(array $rows, int $limit): array
    {
        usort($rows, fn (array $left, array $right): int => [$right['score'], -$right['movie_id']] <=> [$left['score'], -$left['movie_id']]);

        return array_slice($rows, 0, $limit);
    }

    private function relationshipKey(int $userId, string $type, int $movieId): string
    {
        return "{$userId}:{$type}:{$movieId}";
    }

    private function usesArray(): bool
    {
        return config('nosql.neo4j.driver') === 'array';
    }

    /** @param array<string, mixed> $parameters @return list<array<string, mixed>> */
    private function rows(string $statement, array $parameters = []): array
    {
        $response = $this->run([['statement' => $statement, 'parameters' => $parameters]])[0] ?? ['columns' => [], 'data' => []];
        $columns = $response['columns'] ?? [];

        return array_map(fn (array $item): array => array_combine($columns, $item['row']) ?: [], $response['data'] ?? []);
    }

    /** @param list<array<string, mixed>> $statements @return list<array<string, mixed>> */
    private function run(array $statements): array
    {
        $payload = ['statements' => array_map(function (array $statement): array {
            if (($statement['parameters'] ?? null) === []) {
                unset($statement['parameters']);
            }

            return [...$statement, 'resultDataContents' => ['row']];
        }, $statements)];
        $response = $this->request()
            ->withHeader('Content-Type', 'application/json')
            ->send('POST', rtrim((string) config('nosql.neo4j.http_url'), '/').'/db/neo4j/tx/commit', [
                'body' => json_encode($payload, JSON_THROW_ON_ERROR),
            ])
            ->throw()
            ->json();
        if (($response['errors'] ?? []) !== []) {
            throw new RuntimeException('Neo4j query failed: '.($response['errors'][0]['message'] ?? 'unknown error'));
        }

        return $response['results'] ?? [];
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->withBasicAuth((string) config('nosql.neo4j.username'), (string) config('nosql.neo4j.password'))
            ->connectTimeout((int) config('nosql.neo4j.connect_timeout_seconds', 5))
            ->timeout((int) config('nosql.neo4j.request_timeout_seconds', 15));
    }
}

<?php

namespace App\Services;

use App\Events\ActivityOccurred;
use App\Events\ReviewChanged;

class ReviewChangePublisher
{
    public function __construct(
        private RedisQueryCache $cache,
        private RedisMovieLeaderboard $leaderboard,
    ) {}

    /** @param array<string, mixed> $review */
    public function publish(string $action, array $review): void
    {
        $movieId = (int) $review['movie_id'];
        $this->cache->invalidate(['movies:list', "movies:detail:{$movieId}"]);
        if ($action === 'created') {
            $this->leaderboard->increment($movieId, (float) config('nosql.redis.leaderboard_weights.review', 3));
        }
        $reviewChanged = new ReviewChanged($action, $review);
        event($reviewChanged);
        event(new ActivityOccurred(
            'REVIEW_'.strtoupper($action),
            isset($review['user_id']) ? (int) $review['user_id'] : null,
            $movieId,
            null,
            ['review_id' => $review['id'] ?? null, 'rating' => $review['rating'] ?? null],
            'user',
            'review',
            isset($review['id']) ? (string) $review['id'] : null,
            eventId: $reviewChanged->eventId,
        ));
    }
}

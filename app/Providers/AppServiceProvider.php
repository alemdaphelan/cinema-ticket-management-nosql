<?php

namespace App\Providers;

use App\Events\ActivityOccurred;
use App\Events\ReviewChanged;
use App\Listeners\PersistCassandraActivity;
use App\Listeners\PersistReviewOutbox;
use App\Listeners\ProjectNeo4jActivity;
use App\Services\CassandraActivityRepository;
use App\Services\MongoChatSessionService;
use App\Services\MongoOutboxService;
use App\Services\MongoReviewRepository;
use App\Services\Neo4jRecommendationRepository;
use App\Services\RedisMovieLeaderboard;
use App\Services\RedisQueryCache;
use App\Services\RedisSeatHoldService;
use App\Services\ReviewChangePublisher;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RedisQueryCache::class);
        $this->app->singleton(RedisSeatHoldService::class);
        $this->app->singleton(RedisMovieLeaderboard::class);
        $this->app->singleton(MongoReviewRepository::class);
        $this->app->singleton(MongoChatSessionService::class);
        $this->app->singleton(MongoOutboxService::class);
        $this->app->singleton(ReviewChangePublisher::class);
        $this->app->singleton(CassandraActivityRepository::class);
        $this->app->singleton(Neo4jRecommendationRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(ReviewChanged::class, PersistReviewOutbox::class);
        Event::listen(ActivityOccurred::class, PersistCassandraActivity::class);
        Event::listen(ActivityOccurred::class, ProjectNeo4jActivity::class);
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Review;
use App\Services\MongoReviewRepository;
use Illuminate\Console\Command;

class MongoMigrateReviews extends Command
{
    protected $signature = 'nosql:mongo-migrate-reviews {--rollback : Remove documents copied from the legacy MySQL reviews table}';

    protected $description = 'Copy legacy MySQL reviews to MongoDB with an auditable rollback path';

    public function __construct(private MongoReviewRepository $reviews)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('rollback')) {
            $deleted = $this->reviews->deleteSource('mysql_migration');
            $this->info("Rolled back {$deleted} migrated review documents. The MySQL source table was not changed.");

            return self::SUCCESS;
        }

        $sourceCount = Review::query()->count();
        $before = $this->reviews->countSource('mysql_migration');
        $processed = 0;

        Review::query()->orderBy('id')->chunkById(100, function ($legacyReviews) use (&$processed) {
            foreach ($legacyReviews as $review) {
                $order = Order::query()
                    ->where('user_id', $review->user_id)
                    ->whereIn('status', ['paid', 'completed'])
                    ->whereHas('show', fn ($query) => $query->where('movie_id', $review->movie_id))
                    ->with('show:id,movie_id,start_time')
                    ->latest('id')
                    ->first();

                $this->reviews->upsertLegacy((int) $review->id, [
                    'movie_id' => (int) $review->movie_id,
                    'user_id' => (int) $review->user_id,
                    'order_id' => $order?->id,
                    'rating' => (int) $review->rating,
                    'content' => $review->comment,
                    'tags' => ['legacy', 'mysql'],
                    'status' => 'published',
                    'watched_at' => $order?->show?->start_time,
                    'created_at' => $review->created_at,
                    'updated_at' => $review->updated_at,
                ]);
                $processed++;
            }
        });

        $after = $this->reviews->countSource('mysql_migration');
        $this->table(['Metric', 'Count'], [
            ['MySQL source rows', $sourceCount],
            ['Migrated before', $before],
            ['Rows processed', $processed],
            ['Migrated after', $after],
        ]);

        if ($after !== $sourceCount) {
            $this->error('Source and migrated counts do not match. Run rollback or inspect duplicate movie/user pairs.');

            return self::FAILURE;
        }

        $this->info('Review migration completed. The MySQL source table remains intact for rollback.');

        return self::SUCCESS;
    }
}

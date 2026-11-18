<?php

namespace App\Console\Commands;

use App\Events\ReviewChanged;
use App\Services\MongoChatSessionService;
use App\Services\MongoOutboxService;
use App\Services\MongoReviewRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Laravel\Connection;

class MongoPhaseFourSmoke extends Command
{
    protected $signature = 'nosql:mongo-smoke';

    protected $description = 'Run phase 4 checks against a real MongoDB database';

    public function __construct(
        private MongoReviewRepository $reviews,
        private MongoChatSessionService $chat,
        private MongoOutboxService $outbox,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (config('nosql.mongodb.driver') !== 'mongodb') {
            $this->error('NOSQL_MONGODB_DRIVER must be mongodb for the smoke test.');

            return self::FAILURE;
        }

        if (Artisan::call('nosql:mongo-setup') !== self::SUCCESS) {
            $this->error(Artisan::output());

            return self::FAILURE;
        }

        $connection = DB::connection((string) config('nosql.mongodb.connection', 'mongodb'));
        if (! $connection instanceof Connection) {
            throw new \RuntimeException('The configured MongoDB connection is invalid.');
        }
        $database = $connection->getDatabase();
        $reviewCollection = $database->getCollection((string) config('nosql.mongodb.review_collection'));
        $chatCollection = $database->getCollection((string) config('nosql.mongodb.chat_collection'));
        $outboxCollection = $database->getCollection((string) config('nosql.mongodb.outbox_collection'));

        $token = (string) now()->getTimestampMs();
        $movieId = 900000000 + random_int(1, 99999);
        $sessionId = "phase4-smoke-{$token}";
        $reviewIds = [];

        try {
            $first = $this->reviews->create([
                'movie_id' => $movieId,
                'user_id' => $movieId + 1,
                'rating' => 9,
                'content' => 'Phase 4 smoke review one',
                'source' => 'phase4-smoke',
            ]);
            $second = $this->reviews->create([
                'movie_id' => $movieId,
                'user_id' => $movieId + 2,
                'rating' => 7,
                'content' => 'Phase 4 smoke review two',
                'source' => 'phase4-smoke',
            ]);
            $reviewIds = [$first['id'], $second['id']];
            $summary = $this->reviews->summary($movieId);

            $duplicateBlocked = false;
            try {
                $this->reviews->create([
                    'movie_id' => $movieId,
                    'user_id' => $movieId + 1,
                    'rating' => 8,
                    'content' => 'Duplicate',
                    'source' => 'phase4-smoke',
                ]);
            } catch (\Throwable) {
                $duplicateBlocked = true;
            }

            $validationBlocked = false;
            try {
                $reviewCollection->insertOne([
                    'movie_id' => $movieId,
                    'user_id' => $movieId + 99,
                    'rating' => 99,
                    'status' => 'published',
                    'source' => 'phase4-smoke',
                    'created_at' => new UTCDateTime(now()),
                    'updated_at' => new UTCDateTime(now()),
                ]);
            } catch (\Throwable) {
                $validationBlocked = true;
            }

            $chat = $this->chat->append($sessionId, null, 'user', 'Xin chào MongoDB');
            $chat = $this->chat->append($sessionId, null, 'assistant', 'Lịch sử đã được lưu');

            $event = new ReviewChanged('created', $first);
            $this->outbox->record($event);
            $outboxRecorded = $outboxCollection->countDocuments(['event_id' => $event->eventId]) === 1;

            $indexNames = collect($reviewCollection->listIndexes())->pluck('name');
            $chatIndexNames = collect($chatCollection->listIndexes())->pluck('name');
            $checks = [
                ['Aggregation', $summary['total_reviews'] === 2 && $summary['average_rating'] === 8.0 ? 'PASS' : 'FAIL'],
                ['Unique movie/user', $duplicateBlocked ? 'PASS' : 'FAIL'],
                ['JSON schema validation', $validationBlocked ? 'PASS' : 'FAIL'],
                ['Chat history', count($chat['messages']) === 2 ? 'PASS' : 'FAIL'],
                ['TTL index', $chatIndexNames->contains('ttl_chat_sessions') ? 'PASS' : 'FAIL'],
                ['Review indexes', $indexNames->contains('uniq_movie_user') && $indexNames->contains('movie_status_rating_created') ? 'PASS' : 'FAIL'],
                ['Integration outbox', $outboxRecorded ? 'PASS' : 'FAIL'],
            ];
            $this->table(['Check', 'Result'], $checks);

            if (collect($checks)->contains(fn (array $check) => $check[1] !== 'PASS')) {
                return self::FAILURE;
            }

            $this->info('MongoDB phase 4 smoke tests passed.');

            return self::SUCCESS;
        } finally {
            $reviewCollection->deleteMany(['source' => 'phase4-smoke']);
            $chatCollection->deleteMany(['session_id' => $sessionId]);
            $outboxCollection->deleteMany(['aggregate_id' => ['$in' => array_map('strval', $reviewIds)]]);
        }
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use MongoDB\Database;
use MongoDB\Driver\Exception\CommandException;
use MongoDB\Laravel\Connection;

class MongoPhaseFourSetup extends Command
{
    protected $signature = 'nosql:mongo-setup';

    protected $description = 'Create or update MongoDB collections, validation rules and indexes for phase 4';

    public function handle(): int
    {
        if (config('nosql.mongodb.driver') === 'array') {
            $this->info('MongoDB setup skipped for the in-memory test driver.');

            return self::SUCCESS;
        }

        $database = $this->database();
        $definitions = $this->definitions();
        $existing = iterator_to_array($database->listCollectionNames());

        foreach ($definitions as $name => $definition) {
            if (! in_array($name, $existing, true)) {
                $database->createCollection($name, [
                    'validator' => $definition['validator'],
                    'validationLevel' => 'strict',
                    'validationAction' => 'error',
                ]);
            } else {
                try {
                    $database->command([
                        'collMod' => $name,
                        'validator' => $definition['validator'],
                        'validationLevel' => 'strict',
                        'validationAction' => 'error',
                    ]);
                } catch (CommandException $exception) {
                    if ($exception->getCode() !== 13) {
                        throw $exception;
                    }

                    // The least-privilege application role (readWrite) may create
                    // indexes but cannot alter an existing collection validator.
                    // Validators are installed by the privileged init script.
                }
            }

            $collection = $database->getCollection($name);
            foreach ($definition['indexes'] as $keys => $options) {
                $collection->createIndex(json_decode($keys, true, 512, JSON_THROW_ON_ERROR), $options);
            }
        }

        $this->table(
            ['Collection', 'Validation', 'Indexes'],
            collect($definitions)->map(fn (array $definition, string $name) => [
                $name,
                in_array($name, $existing, true) ? 'kept/updated by init' : 'strict/error',
                count($definition['indexes']),
            ])->values()->all(),
        );
        $this->info('MongoDB phase 4 collections are ready.');

        return self::SUCCESS;
    }

    private function database(): Database
    {
        $connection = DB::connection((string) config('nosql.mongodb.connection', 'mongodb'));
        if (! $connection instanceof Connection) {
            throw new \RuntimeException('The configured MongoDB connection is invalid.');
        }

        return $connection->getDatabase();
    }

    /** @return array<string, array{validator: array<string, mixed>, indexes: array<string, array<string, mixed>>}> */
    private function definitions(): array
    {
        return [
            (string) config('nosql.mongodb.review_collection', 'movie_reviews') => [
                'validator' => ['$jsonSchema' => [
                    'bsonType' => 'object',
                    'required' => ['movie_id', 'user_id', 'rating', 'status', 'source', 'created_at', 'updated_at'],
                    'properties' => [
                        'movie_id' => ['bsonType' => ['int', 'long']],
                        'user_id' => ['bsonType' => ['int', 'long']],
                        'order_id' => ['bsonType' => ['int', 'long', 'null']],
                        'legacy_review_id' => ['bsonType' => ['int', 'long', 'null']],
                        'rating' => ['bsonType' => 'int', 'minimum' => 1, 'maximum' => 10],
                        'content' => ['bsonType' => ['string', 'null'], 'maxLength' => 1000],
                        'tags' => ['bsonType' => 'array', 'items' => ['bsonType' => 'string']],
                        'status' => ['enum' => ['published', 'hidden']],
                        'source' => ['bsonType' => 'string'],
                        'watched_at' => ['bsonType' => ['date', 'null']],
                        'created_at' => ['bsonType' => 'date'],
                        'updated_at' => ['bsonType' => 'date'],
                    ],
                ]],
                'indexes' => [
                    json_encode(['movie_id' => 1, 'user_id' => 1], JSON_THROW_ON_ERROR) => ['unique' => true, 'name' => 'uniq_movie_user'],
                    json_encode(['movie_id' => 1, 'status' => 1, 'rating' => -1, 'created_at' => -1], JSON_THROW_ON_ERROR) => ['name' => 'movie_status_rating_created'],
                    json_encode(['source' => 1, 'legacy_review_id' => 1], JSON_THROW_ON_ERROR) => ['name' => 'source_legacy_review'],
                ],
            ],
            (string) config('nosql.mongodb.chat_collection', 'chat_sessions') => [
                'validator' => ['$jsonSchema' => [
                    'bsonType' => 'object',
                    'required' => ['session_id', 'user_id', 'messages', 'context_version', 'created_at', 'updated_at', 'expires_at'],
                    'properties' => [
                        'session_id' => ['bsonType' => 'string'],
                        'user_id' => ['bsonType' => ['int', 'long', 'null']],
                        'messages' => ['bsonType' => 'array', 'items' => [
                            'bsonType' => 'object',
                            'required' => ['role', 'content', 'at'],
                            'properties' => [
                                'role' => ['enum' => ['user', 'assistant', 'system']],
                                'content' => ['bsonType' => 'string', 'maxLength' => 8000],
                                'at' => ['bsonType' => 'date'],
                            ],
                        ]],
                        'context_version' => ['bsonType' => 'int', 'minimum' => 1],
                        'created_at' => ['bsonType' => 'date'],
                        'updated_at' => ['bsonType' => 'date'],
                        'expires_at' => ['bsonType' => 'date'],
                    ],
                ]],
                'indexes' => [
                    json_encode(['session_id' => 1], JSON_THROW_ON_ERROR) => ['unique' => true, 'name' => 'uniq_session'],
                    json_encode(['expires_at' => 1], JSON_THROW_ON_ERROR) => ['expireAfterSeconds' => 0, 'name' => 'ttl_chat_sessions'],
                ],
            ],
            (string) config('nosql.mongodb.outbox_collection', 'integration_outbox') => [
                'validator' => ['$jsonSchema' => [
                    'bsonType' => 'object',
                    'required' => ['event_id', 'event_type', 'aggregate_id', 'payload', 'targets', 'occurred_at', 'published_at', 'attempts'],
                    'properties' => [
                        'event_id' => ['bsonType' => 'string'],
                        'event_type' => ['bsonType' => 'string'],
                        'aggregate_id' => ['bsonType' => 'string'],
                        'payload' => ['bsonType' => 'object'],
                        'targets' => ['bsonType' => 'array', 'items' => ['enum' => ['cassandra', 'neo4j']]],
                        'occurred_at' => ['bsonType' => 'date'],
                        'published_at' => ['bsonType' => ['date', 'null']],
                        'attempts' => ['bsonType' => 'int', 'minimum' => 0],
                        'last_error' => ['bsonType' => ['string', 'null']],
                    ],
                ]],
                'indexes' => [
                    json_encode(['event_id' => 1], JSON_THROW_ON_ERROR) => ['unique' => true, 'name' => 'uniq_event'],
                    json_encode(['published_at' => 1, 'occurred_at' => 1], JSON_THROW_ON_ERROR) => ['name' => 'pending_events'],
                ],
            ],
        ];
    }
}

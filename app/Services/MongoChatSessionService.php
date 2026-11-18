<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Laravel\Connection;

class MongoChatSessionService
{
    /** @var array<string, array<string, mixed>> */
    private array $sessions = [];

    /** @return array<string, mixed> */
    public function append(string $sessionId, ?int $userId, string $role, string $content): array
    {
        $existing = $this->find($sessionId);
        if ($existing && $existing['user_id'] !== null && $existing['user_id'] !== $userId) {
            throw new \DomainException('Phiên hội thoại không thuộc về người dùng hiện tại.');
        }

        $now = now();
        $expiresAt = $now->copy()->addHours((int) config('nosql.mongodb.chat_ttl_hours', 24));
        $message = ['role' => $role, 'content' => $content, 'at' => $now->toISOString()];

        if ($this->usesArray()) {
            $session = $existing ?? [
                'session_id' => $sessionId,
                'user_id' => $userId,
                'messages' => [],
                'context_version' => 1,
                'created_at' => $now->toISOString(),
            ];
            $session['messages'][] = $message;
            $session['messages'] = array_slice($session['messages'], -50);
            $session['updated_at'] = $now->toISOString();
            $session['expires_at'] = $expiresAt->toISOString();
            $this->sessions[$sessionId] = $session;

            return $session;
        }

        $this->collection()->updateOne(
            ['session_id' => $sessionId],
            [
                '$setOnInsert' => [
                    'session_id' => $sessionId,
                    'user_id' => $userId,
                    'context_version' => 1,
                    'created_at' => new UTCDateTime($now),
                ],
                '$set' => [
                    'updated_at' => new UTCDateTime($now),
                    'expires_at' => new UTCDateTime($expiresAt),
                ],
                '$push' => [
                    'messages' => [
                        '$each' => [[...$message, 'at' => new UTCDateTime($now)]],
                        '$slice' => -50,
                    ],
                ],
            ],
            ['upsert' => true],
        );

        return $this->find($sessionId) ?? [];
    }

    /** @return array<string, mixed>|null */
    public function find(string $sessionId): ?array
    {
        if ($this->usesArray()) {
            return $this->sessions[$sessionId] ?? null;
        }

        $session = $this->collection()->findOne(['session_id' => $sessionId]);

        return $session ? $this->normalize($session) : null;
    }

    public function deleteSource(string $source): int
    {
        if ($this->usesArray()) {
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

        return $connection->getCollection((string) config('nosql.mongodb.chat_collection', 'chat_sessions'));
    }

    private function usesArray(): bool
    {
        return config('nosql.mongodb.driver') === 'array';
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof UTCDateTime) {
            return Carbon::instance($value->toDateTime())->toISOString();
        }
        if (is_object($value) && method_exists($value, 'getArrayCopy')) {
            $value = $value->getArrayCopy();
        } elseif (is_object($value) && method_exists($value, 'toArray')) {
            $value = $value->toArray();
        }
        if (is_array($value)) {
            return array_map(fn ($item) => $this->normalize($item), $value);
        }

        return $value;
    }
}

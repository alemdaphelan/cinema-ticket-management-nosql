<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ActivityOccurred
{
    use Dispatchable, SerializesModels;

    public readonly string $eventId;

    /** @param array<string, mixed> $metadata */
    public function __construct(
        public readonly string $eventType,
        public readonly ?int $userId = null,
        public readonly ?int $movieId = null,
        public readonly ?int $orderId = null,
        public readonly array $metadata = [],
        public readonly string $actorType = 'user',
        public readonly string $aggregateType = 'activity',
        public readonly ?string $aggregateId = null,
        public readonly ?string $occurredAt = null,
        ?string $eventId = null,
    ) {
        $this->eventId = $eventId ?? (string) Str::uuid();
    }
}

<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ReviewChanged
{
    use Dispatchable, SerializesModels;

    public readonly string $eventId;

    /** @param array<string, mixed> $review */
    public function __construct(
        public readonly string $action,
        public readonly array $review,
        ?string $eventId = null,
    ) {
        $this->eventId = $eventId ?? (string) Str::uuid();
    }
}

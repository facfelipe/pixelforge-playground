<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Pixelforge\HandlePixelforgeWebhook;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Applies a verified Pixelforge webhook off the request so we can 204 quickly.
 */
class ProcessPixelforgeWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $eventId,
        public string $eventType,
        public array $payload,
    ) {}

    public function handle(HandlePixelforgeWebhook $handler): void
    {
        $handler->handle($this->eventId, $this->eventType, $this->payload);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('pixelforge.webhook.job_failed', [
            'event_id' => $this->eventId,
            'event_type' => $this->eventType,
            'message' => $exception?->getMessage(),
        ]);
    }
}

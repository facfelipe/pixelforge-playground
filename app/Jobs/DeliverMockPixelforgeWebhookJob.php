<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Pixelforge\HandlePixelforgeWebhook;
use App\Pixelforge\WebhookSignature;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

/**
 * Delivers a signed mock Pixelforge webhook through the same HandlePixelforgeWebhook
 * path the HTTP controller uses. Only used when PIXELFORGE_DRIVER=mock.
 */
class DeliverMockPixelforgeWebhookJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $generationObject
     */
    public function __construct(
        public string $eventType,
        public array $generationObject,
        public bool $deliverTwice = false,
    ) {}

    public function handle(HandlePixelforgeWebhook $handler): void
    {
        $eventId = 'evt_mock_'.str_replace('-', '', (string) Str::uuid());

        $this->deliver($handler, $eventId);

        if ($this->deliverTwice) {
            // Same event id twice — exercises webhook dedupe.
            $this->deliver($handler, $eventId);
        }
    }

    private function deliver(HandlePixelforgeWebhook $handler, string $eventId): void
    {
        $payload = [
            'id' => $eventId,
            'object' => 'event',
            'type' => $this->eventType,
            'created_at' => now()->utc()->toIso8601String(),
            'data' => [
                'object' => $this->generationObject,
            ],
        ];

        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = time();
        $secret = (string) config('pixelforge.webhook_secret');

        // Sign like Pixelforge would (kept for parity / debugging); handler is invoked directly.
        WebhookSignature::header($raw, $timestamp, $secret);

        $handler->handle($eventId, $this->eventType, $payload);
    }
}

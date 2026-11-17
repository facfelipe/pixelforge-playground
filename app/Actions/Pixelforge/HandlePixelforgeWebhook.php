<?php

declare(strict_types=1);

namespace App\Actions\Pixelforge;

use App\Actions\Generation\ApplyPixelforgeGeneration;
use App\Models\Generation;
use App\Models\PixelforgeWebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

final class HandlePixelforgeWebhook
{
    public function __construct(
        private readonly ApplyPixelforgeGeneration $apply,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(string $eventId, string $type, array $payload): void
    {
        if ($type === 'ping') {
            $this->recordEvent($eventId, $type);

            Log::info('pixelforge.webhook.ping', [
                'event_id' => $eventId,
            ]);

            return;
        }

        if (! $this->recordEvent($eventId, $type)) {
            Log::info('pixelforge.webhook.duplicate', [
                'event_id' => $eventId,
                'event_type' => $type,
            ]);

            return;
        }

        $object = $payload['data']['object'] ?? null;

        if (! is_array($object) || ! isset($object['status'])) {
            Log::warning('pixelforge.webhook.malformed', [
                'event_id' => $eventId,
                'event_type' => $type,
            ]);

            return;
        }

        $generation = $this->findGeneration($object);

        if ($generation === null) {
            Log::warning('pixelforge.webhook.unmatched', [
                'event_id' => $eventId,
                'event_type' => $type,
                'pixelforge_id' => $object['id'] ?? null,
                'local_id' => is_array($object['metadata'] ?? null)
                    ? ($object['metadata']['local_id'] ?? null)
                    : null,
            ]);

            return;
        }

        Log::info('pixelforge.webhook.applying', [
            'event_id' => $eventId,
            'event_type' => $type,
            'generation_uuid' => $generation->uuid,
            'pixelforge_id' => $generation->pixelforge_id ?? ($object['id'] ?? null),
            'provider_status' => $object['status'],
        ]);

        $this->apply->handle($generation, $object);
    }

    private function recordEvent(string $eventId, string $type): bool
    {
        try {
            PixelforgeWebhookEvent::query()->create([
                'event_id' => $eventId,
                'type' => $type,
                'processed_at' => now(),
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function findGeneration(array $object): ?Generation
    {
        $pixelforgeId = isset($object['id']) ? (string) $object['id'] : null;
        $localId = null;

        if (isset($object['metadata']) && is_array($object['metadata'])) {
            $localId = isset($object['metadata']['local_id'])
                ? (string) $object['metadata']['local_id']
                : null;
        }

        if ($pixelforgeId !== null && $pixelforgeId !== '') {
            $byProvider = Generation::query()->where('pixelforge_id', $pixelforgeId)->first();

            if ($byProvider !== null) {
                return $byProvider;
            }
        }

        if ($localId !== null && $localId !== '') {
            return Generation::query()->where('uuid', $localId)->first();
        }

        return null;
    }
}

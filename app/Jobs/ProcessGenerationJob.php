<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Generation\ApplyPixelforgeGeneration;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Pixelforge\Contracts\PixelforgeClient;
use App\Pixelforge\Exceptions\PixelforgeException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Submits a local generation to Pixelforge, then schedules poll fallback.
 * Mock driver also schedules signed webhook deliveries from the client.
 */
class ProcessGenerationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $generationId) {}

    public function handle(PixelforgeClient $client, ApplyPixelforgeGeneration $apply): void
    {
        $generation = Generation::query()->find($this->generationId);

        if ($generation === null || $generation->status->isTerminal()) {
            return;
        }

        if ($generation->pixelforge_id !== null) {
            Log::info('pixelforge.process.already_submitted', [
                'generation_uuid' => $generation->uuid,
                'pixelforge_id' => $generation->pixelforge_id,
            ]);

            PollPixelforgeGenerationJob::dispatch($generation->id)
                ->delay(now()->addSeconds((int) config('pixelforge.poll_delay_seconds', 15)));

            return;
        }

        try {
            $remote = $client->createGeneration(
                [
                    'prompt' => $generation->prompt,
                    'size' => $generation->size,
                    'quality' => $generation->quality,
                    'reference_images' => $generation->reference_images ?? [],
                    'metadata' => [
                        'local_id' => $generation->uuid,
                    ],
                ],
                $generation->uuid,
            );
        } catch (PixelforgeException $exception) {
            Log::warning('pixelforge.process.create_rejected', [
                'generation_uuid' => $generation->uuid,
                'error_code' => $exception->errorCode,
                'status_code' => $exception->statusCode,
                'message' => $exception->getMessage(),
            ]);

            $apply->handle($generation, [
                'id' => $generation->pixelforge_id,
                'status' => GenerationStatus::Failed->value,
                'error' => [
                    'code' => $exception->errorCode,
                    'message' => $exception->getMessage(),
                ],
            ]);

            return;
        }

        $generation->forceFill([
            'pixelforge_id' => (string) $remote['id'],
            'rates' => is_array($remote['rates'] ?? null)
                ? $remote['rates']
                : $generation->rates,
        ])->save();

        Log::info('pixelforge.process.created', [
            'generation_uuid' => $generation->uuid,
            'pixelforge_id' => $generation->pixelforge_id,
            'provider_status' => $remote['status'] ?? null,
        ]);

        // Webhooks (mock or real) drive status/billing. Poll is the safety net.
        PollPixelforgeGenerationJob::dispatch($generation->id)
            ->delay(now()->addSeconds((int) config('pixelforge.poll_delay_seconds', 15)));
    }
}

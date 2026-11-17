<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Generation\ApplyPixelforgeGeneration;
use App\Models\Generation;
use App\Pixelforge\Contracts\PixelforgeClient;
use App\Pixelforge\Exceptions\PixelforgeException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Backup path when webhooks are delayed or missed. Safe with charge-once apply.
 */
class PollPixelforgeGenerationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $generationId,
        public int $attempt = 1,
    ) {}

    public function handle(PixelforgeClient $client, ApplyPixelforgeGeneration $apply): void
    {
        $generation = Generation::query()->find($this->generationId);

        if ($generation === null) {
            return;
        }

        if ($generation->status->isTerminal()) {
            Log::debug('pixelforge.poll.already_terminal', [
                'generation_uuid' => $generation->uuid,
                'pixelforge_id' => $generation->pixelforge_id,
                'status' => $generation->status->value,
                'attempt' => $this->attempt,
            ]);

            return;
        }

        if ($generation->pixelforge_id === null) {
            Log::info('pixelforge.poll.waiting_for_id', [
                'generation_uuid' => $generation->uuid,
                'attempt' => $this->attempt,
            ]);

            $this->retryLater($generation);

            return;
        }

        Log::info('pixelforge.poll.fetching', [
            'generation_uuid' => $generation->uuid,
            'pixelforge_id' => $generation->pixelforge_id,
            'attempt' => $this->attempt,
            'local_status' => $generation->status->value,
        ]);

        try {
            $remote = $client->getGeneration($generation->pixelforge_id);
        } catch (PixelforgeException $exception) {
            Log::warning('pixelforge.poll.fetch_failed', [
                'generation_uuid' => $generation->uuid,
                'pixelforge_id' => $generation->pixelforge_id,
                'attempt' => $this->attempt,
                'error_code' => $exception->errorCode,
            ]);

            $this->retryLater($generation);

            return;
        }

        $apply->handle($generation, $remote);

        $generation->refresh();

        if (! $generation->status->isTerminal()) {
            Log::info('pixelforge.poll.still_pending', [
                'generation_uuid' => $generation->uuid,
                'pixelforge_id' => $generation->pixelforge_id,
                'status' => $generation->status->value,
                'attempt' => $this->attempt,
            ]);

            $this->retryLater($generation);
        }
    }

    private function retryLater(Generation $generation): void
    {
        $max = (int) config('pixelforge.poll_max_attempts', 12);

        if ($this->attempt >= $max) {
            Log::warning('pixelforge.poll.max_attempts', [
                'generation_uuid' => $generation->uuid,
                'pixelforge_id' => $generation->pixelforge_id,
                'attempt' => $this->attempt,
            ]);

            return;
        }

        self::dispatch($this->generationId, $this->attempt + 1)
            ->delay(now()->addSeconds((int) config('pixelforge.poll_retry_seconds', 10)));
    }
}

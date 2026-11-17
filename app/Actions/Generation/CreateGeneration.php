<?php

declare(strict_types=1);

namespace App\Actions\Generation;

use App\Actions\Billing\EstimateCost;
use App\Actions\Pixelforge\FetchPixelforgePricing;
use App\Enums\GenerationStatus;
use App\Jobs\ProcessGenerationJob;
use App\Models\Generation;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateGeneration
{
    public function __construct(
        private readonly EstimateCost $estimateCost,
        private readonly FetchPixelforgePricing $fetchPixelforgePricing,
    ) {}

    /**
     * @param  array{prompt: string, size: string, quality: string, reference_images?: list<string>}  $data
     */
    public function handle(User $user, array $data): Generation
    {
        $referenceImages = array_values($data['reference_images'] ?? []);
        $pricing = $this->fetchPixelforgePricing->handle();
        $estimate = $this->estimateCost->handle(
            $data['prompt'],
            $data['size'],
            $data['quality'],
            $referenceImages,
            $pricing,
        );

        $generation = DB::transaction(function () use ($user, $data, $referenceImages, $estimate): Generation {
            /** @var User $lockedUser */
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if (Money::lessThan((string) $lockedUser->credit_balance, $estimate['estimated_user_cost_max'])) {
                Log::info('pixelforge.create.gate_blocked', [
                    'user_id' => $lockedUser->id,
                    'balance' => (string) $lockedUser->credit_balance,
                    'estimated_user_cost_max' => $estimate['estimated_user_cost_max'],
                    'size' => $data['size'],
                    'quality' => $data['quality'],
                ]);

                throw ValidationException::withMessages([
                    'credits' => 'Not enough credits for this generation (up to $'
                        .Money::formatDisplay($estimate['estimated_user_cost_max'])
                        .'). Add credits.',
                ]);
            }

            return Generation::query()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $lockedUser->id,
                'status' => GenerationStatus::Queued,
                'prompt' => $data['prompt'],
                'size' => $data['size'],
                'quality' => $data['quality'],
                'reference_images' => $referenceImages,
                'estimated_provider_cost' => $estimate['estimated_provider_cost'],
                'estimated_user_cost' => $estimate['estimated_user_cost'],
                'estimated_user_cost_max' => $estimate['estimated_user_cost_max'],
                'rates' => $estimate['rates'],
                'pixelforge_id' => null,
            ]);
        });

        ProcessGenerationJob::dispatch($generation->id);

        Log::info('pixelforge.create.queued', [
            'generation_uuid' => $generation->uuid,
            'generation_id' => $generation->id,
            'user_id' => $generation->user_id,
            'estimated_user_cost' => (string) $generation->estimated_user_cost,
            'estimated_user_cost_max' => (string) $generation->estimated_user_cost_max,
            'size' => $generation->size,
            'quality' => $generation->quality,
        ]);

        return $generation->fresh();
    }
}

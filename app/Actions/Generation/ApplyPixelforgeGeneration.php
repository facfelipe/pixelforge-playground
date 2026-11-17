<?php

declare(strict_types=1);

namespace App\Actions\Generation;

use App\Actions\Billing\CalculateActualCost;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ApplyPixelforgeGeneration
{
    public function __construct(
        private readonly CalculateActualCost $calculateActualCost,
    ) {}

    /**
     * Apply a Pixelforge generation snapshot (from mock, poll, or webhook).
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(Generation $generation, array $payload): Generation
    {
        return DB::transaction(function () use ($generation, $payload): Generation {
            /** @var Generation $locked */
            $locked = Generation::query()
                ->whereKey($generation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $nextStatus = GenerationStatus::from((string) $payload['status']);
            $fromStatus = $locked->status;

            if ($locked->status->isTerminal()) {
                Log::info('pixelforge.apply.ignored_terminal', [
                    'generation_uuid' => $locked->uuid,
                    'pixelforge_id' => $locked->pixelforge_id,
                    'status' => $locked->status->value,
                    'ignored_status' => $nextStatus->value,
                ]);

                return $locked;
            }

            if (! $locked->status->canTransitionTo($nextStatus) && $locked->status !== $nextStatus) {
                Log::info('pixelforge.apply.ignored_transition', [
                    'generation_uuid' => $locked->uuid,
                    'pixelforge_id' => $locked->pixelforge_id,
                    'from' => $locked->status->value,
                    'to' => $nextStatus->value,
                ]);

                return $locked;
            }

            if ($locked->pixelforge_id === null && isset($payload['id'])) {
                $locked->pixelforge_id = (string) $payload['id'];
            }

            $locked->status = $nextStatus;

            if ($nextStatus === GenerationStatus::Succeeded) {
                $this->finalizeSuccess($locked, $payload);
            }

            if ($nextStatus === GenerationStatus::Failed) {
                $this->finalizeFailure($locked, $payload);
            }

            $locked->save();

            Log::info('pixelforge.apply.updated', [
                'generation_uuid' => $locked->uuid,
                'pixelforge_id' => $locked->pixelforge_id,
                'from' => $fromStatus->value,
                'to' => $locked->status->value,
                'actual_user_cost' => $locked->actual_user_cost !== null
                    ? (string) $locked->actual_user_cost
                    : null,
                'error_code' => $locked->error_code,
            ]);

            return $locked->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function finalizeSuccess(Generation $generation, array $payload): void
    {
        $generation->image_url = $payload['image_url'] ?? null;
        $expires = $payload['image_url_expires_at'] ?? null;
        $generation->image_url_expires_at = is_string($expires) ? $expires : $expires;
        $generation->usage = $payload['usage'] ?? null;
        $generation->rates = $payload['rates'] ?? $generation->rates;
        $generation->error_code = null;
        $generation->error_message = null;

        if ($generation->isCharged()) {
            Log::info('pixelforge.apply.charge_skipped', [
                'generation_uuid' => $generation->uuid,
                'pixelforge_id' => $generation->pixelforge_id,
                'actual_user_cost' => (string) $generation->actual_user_cost,
            ]);

            return;
        }

        $usage = $payload['usage'] ?? null;
        $rates = $payload['rates'] ?? $generation->rates;

        if (! is_array($usage) || ! is_array($rates)) {
            return;
        }

        $costs = $this->calculateActualCost->handle($usage, $rates);
        $generation->actual_provider_cost = $costs['provider_cost'];
        $generation->actual_user_cost = $costs['user_cost'];

        $user = $generation->user()->lockForUpdate()->firstOrFail();
        $user->credit_balance = Money::sub((string) $user->credit_balance, $costs['user_cost']);
        $user->save();

        Log::info('pixelforge.apply.charged', [
            'generation_uuid' => $generation->uuid,
            'pixelforge_id' => $generation->pixelforge_id,
            'user_id' => $user->id,
            'provider_cost' => $costs['provider_cost'],
            'user_cost' => $costs['user_cost'],
            'balance_after' => (string) $user->credit_balance,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function finalizeFailure(Generation $generation, array $payload): void
    {
        if (! $generation->isCharged()) {
            $generation->actual_provider_cost = Money::zero();
            $generation->actual_user_cost = Money::zero();
        }

        $error = $payload['error'] ?? [];
        $generation->error_code = is_array($error) ? ($error['code'] ?? 'generation_failed') : 'generation_failed';
        $generation->error_message = is_array($error)
            ? ($error['message'] ?? 'Generation failed.')
            : 'Generation failed.';
        $generation->image_url = null;
        $generation->usage = null;

        Log::info('pixelforge.apply.failed_zero_charge', [
            'generation_uuid' => $generation->uuid,
            'pixelforge_id' => $generation->pixelforge_id,
            'error_code' => $generation->error_code,
        ]);
    }
}

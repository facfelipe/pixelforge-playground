<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class BuildUsageReport
{
    /**
     * @return array{
     *     from: string,
     *     to: string,
     *     summary: array{
     *         user_spend: string,
     *         provider_cost: string,
     *         profit: string,
     *         user_spend_display: string,
     *         provider_cost_display: string,
     *         profit_display: string
     *     },
     *     byDay: list<array<string, mixed>>,
     *     byUser: list<array<string, mixed>>
     * }
     */
    public function handle(CarbonInterface $from, CarbonInterface $to): array
    {
        $base = Generation::query()
            ->where('generations.status', GenerationStatus::Succeeded)
            ->whereNotNull('generations.actual_user_cost')
            ->whereBetween('generations.updated_at', [$from, $to]);

        $totals = (clone $base)
            ->selectRaw('COALESCE(SUM(generations.actual_user_cost), 0) as user_spend')
            ->selectRaw('COALESCE(SUM(generations.actual_provider_cost), 0) as provider_cost')
            ->first();

        $userSpend = Money::normalize((string) ($totals->user_spend ?? '0'));
        $providerCost = Money::normalize((string) ($totals->provider_cost ?? '0'));
        $profit = Money::sub($userSpend, $providerCost);

        $byDay = (clone $base)
            ->selectRaw('DATE(generations.updated_at) as day')
            ->selectRaw('COUNT(*) as generations_count')
            ->selectRaw('COALESCE(SUM(generations.actual_user_cost), 0) as user_spend')
            ->selectRaw('COALESCE(SUM(generations.actual_provider_cost), 0) as provider_cost')
            ->groupBy(DB::raw('DATE(generations.updated_at)'))
            ->orderBy('day')
            ->get()
            ->map(fn ($row): array => $this->moneyRow([
                'day' => $row->day,
                'generations' => (int) $row->generations_count,
            ], (string) $row->user_spend, (string) $row->provider_cost))
            ->all();

        $byUser = (clone $base)
            ->join('users', 'users.id', '=', 'generations.user_id')
            ->select('users.id', 'users.name', 'users.email')
            ->selectRaw('COUNT(*) as generations_count')
            ->selectRaw('COALESCE(SUM(generations.actual_user_cost), 0) as user_spend')
            ->selectRaw('COALESCE(SUM(generations.actual_provider_cost), 0) as provider_cost')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('user_spend')
            ->get()
            ->map(fn ($row): array => $this->moneyRow([
                'user_id' => $row->id,
                'name' => $row->name,
                'email' => $row->email,
                'generations' => (int) $row->generations_count,
            ], (string) $row->user_spend, (string) $row->provider_cost))
            ->all();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'summary' => [
                'user_spend' => $userSpend,
                'provider_cost' => $providerCost,
                'profit' => $profit,
                'user_spend_display' => Money::formatDisplay($userSpend),
                'provider_cost_display' => Money::formatDisplay($providerCost),
                'profit_display' => Money::formatDisplay($profit),
            ],
            'byDay' => $byDay,
            'byUser' => $byUser,
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private function moneyRow(array $base, string $userSpendRaw, string $providerCostRaw): array
    {
        $spend = Money::normalize($userSpendRaw);
        $provider = Money::normalize($providerCostRaw);
        $profit = Money::sub($spend, $provider);

        return [
            ...$base,
            'user_spend' => $spend,
            'provider_cost' => $provider,
            'profit' => $profit,
            'user_spend_display' => Money::formatDisplay($spend),
            'provider_cost_display' => Money::formatDisplay($provider),
            'profit_display' => Money::formatDisplay($profit),
        ];
    }
}

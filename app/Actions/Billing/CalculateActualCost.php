<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Support\Money;

final class CalculateActualCost
{
    /**
     * @param  array{input_text_tokens: int, input_image_tokens: int, output_tokens: int, reasoning_tokens: int}  $usage
     * @param  array{input_text_tokens: string, input_image_tokens: string, output_tokens: string, reasoning_tokens: string}  $rates
     * @return array{provider_cost: string, user_cost: string}
     */
    public function handle(array $usage, array $rates): array
    {
        $providerCost = Money::add(
            Money::add(
                Money::costForTokens((int) $usage['input_text_tokens'], $rates['input_text_tokens']),
                Money::costForTokens((int) $usage['input_image_tokens'], $rates['input_image_tokens']),
            ),
            Money::add(
                Money::costForTokens((int) $usage['output_tokens'], $rates['output_tokens']),
                Money::costForTokens((int) $usage['reasoning_tokens'], $rates['reasoning_tokens']),
            ),
        );

        return [
            'provider_cost' => $providerCost,
            'user_cost' => Money::mul($providerCost, (string) config('billing.markup')),
        ];
    }
}

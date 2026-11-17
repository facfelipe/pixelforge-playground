<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Pixelforge\PricingCatalog;
use App\Support\Money;
use InvalidArgumentException;

final class EstimateCost
{
    /**
     * @param  list<string>  $referenceImages
     * @return array{
     *     estimated_provider_cost: string,
     *     estimated_user_cost: string,
     *     estimated_provider_cost_max: string,
     *     estimated_user_cost_max: string,
     *     estimated_tokens: array{input_text: int, input_image: int, output: int, reasoning: int},
     *     max_tokens: array{input_text: int, input_image: int, output: int, reasoning: int},
     * }
     */
    public function handle(string $prompt, string $size, string $quality, array $referenceImages = []): array
    {
        $rates = PricingCatalog::rates();
        $tokensConfig = PricingCatalog::tokens();

        if (! isset($tokensConfig['output'][$quality][$size])) {
            throw new InvalidArgumentException("Unsupported size/quality [{$size}/{$quality}].");
        }

        if (! isset($tokensConfig['reasoning'][$quality])) {
            throw new InvalidArgumentException("Unsupported quality [{$quality}].");
        }

        $chars = mb_strlen($prompt);
        $perText = (int) $tokensConfig['characters_per_text_token'];
        $textEstimated = (int) ceil($chars / $perText);
        $textMax = (int) ceil($textEstimated * (1 + (float) $tokensConfig['text_token_variance']));

        $imageTokens = count($referenceImages) * (int) $tokensConfig['per_reference_image'];
        $outputTokens = (int) $tokensConfig['output'][$quality][$size];
        $reasoningMin = (int) $tokensConfig['reasoning'][$quality]['min'];
        $reasoningMax = (int) $tokensConfig['reasoning'][$quality]['max'];
        $reasoningEstimated = (int) floor(($reasoningMin + $reasoningMax) / 2);

        $estimatedTokens = [
            'input_text' => $textEstimated,
            'input_image' => $imageTokens,
            'output' => $outputTokens,
            'reasoning' => $reasoningEstimated,
        ];

        $maxTokens = [
            'input_text' => $textMax,
            'input_image' => $imageTokens,
            'output' => $outputTokens,
            'reasoning' => $reasoningMax,
        ];

        $estimatedProviderCost = $this->providerCost($estimatedTokens, $rates);
        $maxProviderCost = $this->providerCost($maxTokens, $rates);
        $markup = (string) config('billing.markup');

        return [
            'estimated_provider_cost' => $estimatedProviderCost,
            'estimated_user_cost' => Money::mul($estimatedProviderCost, $markup),
            'estimated_provider_cost_max' => $maxProviderCost,
            'estimated_user_cost_max' => Money::mul($maxProviderCost, $markup),
            'estimated_tokens' => $estimatedTokens,
            'max_tokens' => $maxTokens,
        ];
    }

    /**
     * @param  array{input_text: int, input_image: int, output: int, reasoning: int}  $tokens
     * @param  array{input_text_tokens: string, input_image_tokens: string, output_tokens: string, reasoning_tokens: string}  $rates
     */
    private function providerCost(array $tokens, array $rates): string
    {
        return Money::add(
            Money::add(
                Money::costForTokens($tokens['input_text'], $rates['input_text_tokens']),
                Money::costForTokens($tokens['input_image'], $rates['input_image_tokens']),
            ),
            Money::add(
                Money::costForTokens($tokens['output'], $rates['output_tokens']),
                Money::costForTokens($tokens['reasoning'], $rates['reasoning_tokens']),
            ),
        );
    }
}

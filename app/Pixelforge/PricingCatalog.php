<?php

declare(strict_types=1);

namespace App\Pixelforge;

/**
 * Static snapshot of Pixelforge pricing/token tables (from their docs).
 * Used for estimates and the mock driver. Live HTTP should prefer
 * GET /v1/pricing and the generation's frozen rates for actuals.
 */
final class PricingCatalog
{
    /**
     * @return array{input_text_tokens: string, input_image_tokens: string, output_tokens: string, reasoning_tokens: string}
     */
    public static function rates(): array
    {
        return [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ];
    }

    /**
     * @return array{
     *     characters_per_text_token: int,
     *     text_token_variance: float,
     *     per_reference_image: int,
     *     output: array<string, array<string, int>>,
     *     reasoning: array<string, array{min: int, max: int}>
     * }
     */
    public static function tokens(): array
    {
        return [
            'characters_per_text_token' => 4,
            'text_token_variance' => 0.15,
            'per_reference_image' => 765,
            'output' => [
                'low' => [
                    '1024x1024' => 280,
                    '1536x1024' => 420,
                    '1024x1536' => 420,
                ],
                'medium' => [
                    '1024x1024' => 1100,
                    '1536x1024' => 1650,
                    '1024x1536' => 1650,
                ],
                'high' => [
                    '1024x1024' => 4300,
                    '1536x1024' => 6450,
                    '1024x1536' => 6450,
                ],
            ],
            'reasoning' => [
                'low' => ['min' => 0, 'max' => 500],
                'medium' => ['min' => 200, 'max' => 2000],
                'high' => ['min' => 1000, 'max' => 8000],
            ],
        ];
    }
}

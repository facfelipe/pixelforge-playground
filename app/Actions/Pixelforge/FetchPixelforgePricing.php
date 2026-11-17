<?php

declare(strict_types=1);

namespace App\Actions\Pixelforge;

use App\Pixelforge\Contracts\PixelforgeClient;
use App\Pixelforge\PricingCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class FetchPixelforgePricing
{
    private const CACHE_KEY = 'pixelforge.pricing.snapshot';

    public function __construct(
        private readonly PixelforgeClient $client,
    ) {}

    /**
     * @return array{
     *     rates: array{input_text_tokens: string, input_image_tokens: string, output_tokens: string, reasoning_tokens: string},
     *     tokens: array<string, mixed>
     * }
     */
    public function handle(): array
    {
        /** @var array{rates: array<string, string>, tokens: array<string, mixed>}|null $cached */
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached) && $this->isValidSnapshot($cached)) {
            return $cached;
        }

        try {
            $pricing = $this->client->getPricing();
            $snapshot = $this->normalize($pricing);

            if ($snapshot === null) {
                Log::warning('pixelforge.pricing.invalid_payload');

                return $this->fallback();
            }

            Cache::put(
                self::CACHE_KEY,
                $snapshot,
                now()->addSeconds(max(1, (int) config('pixelforge.pricing_cache_seconds', 300))),
            );

            return $snapshot;
        } catch (Throwable $exception) {
            Log::warning('pixelforge.pricing.fetch_failed', [
                'message' => $exception->getMessage(),
            ]);

            return $this->fallback();
        }
    }

    /**
     * @param  array<string, mixed>  $pricing
     * @return array{rates: array<string, string>, tokens: array<string, mixed>}|null
     */
    private function normalize(array $pricing): ?array
    {
        $rates = $pricing['rates'] ?? null;
        $tokens = $pricing['tokens'] ?? null;

        if (! is_array($rates) || ! is_array($tokens)) {
            return null;
        }

        $snapshot = [
            'rates' => $rates,
            'tokens' => $tokens,
        ];

        return $this->isValidSnapshot($snapshot) ? $snapshot : null;
    }

    /**
     * @param  array{rates?: mixed, tokens?: mixed}  $snapshot
     */
    private function isValidSnapshot(array $snapshot): bool
    {
        $rates = $snapshot['rates'] ?? null;
        $tokens = $snapshot['tokens'] ?? null;

        if (! is_array($rates) || ! is_array($tokens)) {
            return false;
        }

        foreach (['input_text_tokens', 'input_image_tokens', 'output_tokens', 'reasoning_tokens'] as $key) {
            if (! isset($rates[$key]) || ! is_numeric($rates[$key])) {
                return false;
            }
        }

        return isset(
            $tokens['characters_per_text_token'],
            $tokens['text_token_variance'],
            $tokens['per_reference_image'],
            $tokens['output'],
            $tokens['reasoning'],
        ) && is_array($tokens['output']) && is_array($tokens['reasoning']);
    }

    /**
     * @return array{rates: array<string, string>, tokens: array<string, mixed>}
     */
    private function fallback(): array
    {
        return [
            'rates' => PricingCatalog::rates(),
            'tokens' => PricingCatalog::tokens(),
        ];
    }
}

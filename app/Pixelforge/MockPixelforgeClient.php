<?php

declare(strict_types=1);

namespace App\Pixelforge;

use App\Jobs\DeliverMockPixelforgeWebhookJob;
use App\Pixelforge\Contracts\PixelforgeClient;
use App\Pixelforge\Exceptions\PixelforgeException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class MockPixelforgeClient implements PixelforgeClient
{
    private const CACHE_PREFIX = 'pixelforge:mock:generation:';

    /**
     * {@inheritdoc}
     */
    public function createGeneration(array $params, string $idempotencyKey): array
    {
        $prompt = (string) $params['prompt'];
        $normalized = mb_strtolower($prompt);

        if (str_contains($normalized, '[blocked]')) {
            throw new PixelforgeException(
                'content_policy_violation',
                'Prompt rejected by content policy (mock [blocked]).',
                400,
            );
        }

        if (str_contains($normalized, '[overloaded]')) {
            throw new PixelforgeException(
                'overloaded',
                'Pixelforge is temporarily overloaded (mock [overloaded]).',
                503,
            );
        }

        $cacheKey = self::CACHE_PREFIX.'idem:'.$idempotencyKey;
        $existingId = Cache::get($cacheKey);

        if (is_string($existingId)) {
            return $this->getGeneration($existingId);
        }

        $id = 'gen_mock_'.str_replace('-', '', (string) Str::uuid());
        $rates = PricingCatalog::rates();
        $now = now()->utc()->toIso8601String();

        $generation = [
            'id' => $id,
            'object' => 'generation',
            'status' => 'queued',
            'prompt' => $prompt,
            'size' => $params['size'],
            'quality' => $params['quality'],
            'reference_images' => array_values($params['reference_images'] ?? []),
            'metadata' => $params['metadata'] ?? [],
            'image_url' => null,
            'image_url_expires_at' => null,
            'usage' => null,
            'rates' => $rates,
            'error' => null,
            'created_at' => $now,
            'started_at' => null,
            'completed_at' => null,
        ];

        $this->store($generation);
        Cache::put($cacheKey, $id, now()->addDay());

        $this->scheduleLifecycle($generation, $normalized);

        return $generation;
    }

    /**
     * {@inheritdoc}
     */
    public function getGeneration(string $id): array
    {
        $generation = Cache::get(self::CACHE_PREFIX.$id);

        if (! is_array($generation)) {
            throw new PixelforgeException('not_found', 'Generation not found.', 404);
        }

        return $generation;
    }

    /**
     * {@inheritdoc}
     */
    public function getPricing(): array
    {
        return [
            'object' => 'pricing',
            'rates' => PricingCatalog::rates(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getUsage(?string $start = null, ?string $end = null): array
    {
        return [
            'object' => 'usage',
            'start' => $start,
            'end' => $end,
            'data' => [],
            'total' => [
                'generations' => 0,
                'input_text_tokens' => 0,
                'input_image_tokens' => 0,
                'output_tokens' => 0,
                'reasoning_tokens' => 0,
                'cost_usd' => '0.000000000',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $generation
     */
    private function store(array $generation): void
    {
        Cache::put(self::CACHE_PREFIX.$generation['id'], $generation, now()->addDay());
    }

    /**
     * @param  array<string, mixed>  $generation
     */
    private function scheduleLifecycle(array $generation, string $normalizedPrompt): void
    {
        $duplicate = str_contains($normalizedPrompt, '[duplicate]');
        $fail = str_contains($normalizedPrompt, '[fail]');
        $slow = str_contains($normalizedPrompt, '[slow]');

        $processing = $generation;
        $processing['status'] = 'processing';
        $processing['started_at'] = now()->utc()->toIso8601String();
        $this->store($processing);

        DeliverMockPixelforgeWebhookJob::dispatch(
            'generation.processing',
            $processing,
            $duplicate,
        );

        $terminal = $processing;

        if ($fail) {
            $terminal['status'] = 'failed';
            $terminal['completed_at'] = now()->utc()->toIso8601String();
            $terminal['error'] = [
                'code' => 'generation_failed',
                'message' => 'Mock failure triggered by [fail] in the prompt.',
            ];
        } else {
            $usage = $this->buildUsage($generation);
            $terminal['status'] = 'succeeded';
            $terminal['completed_at'] = now()->utc()->toIso8601String();
            $terminal['usage'] = $usage;
            $terminal['image_url'] = 'https://placehold.co/'
                .$generation['size']
                .'/png?text='.urlencode(mb_substr((string) $generation['prompt'], 0, 40));
            $terminal['image_url_expires_at'] = now()->addDay()->utc()->toIso8601String();
            $terminal['error'] = null;
        }

        $this->store($terminal);

        $job = DeliverMockPixelforgeWebhookJob::dispatch(
            $fail ? 'generation.failed' : 'generation.succeeded',
            $terminal,
            $duplicate,
        );

        if ($slow) {
            $job->delay(now()->addSeconds(2));
        }
    }

    /**
     * @param  array<string, mixed>  $generation
     * @return array{input_text_tokens: int, input_image_tokens: int, output_tokens: int, reasoning_tokens: int, total_tokens: int}
     */
    private function buildUsage(array $generation): array
    {
        $tokens = PricingCatalog::tokens();
        $chars = mb_strlen((string) $generation['prompt']);
        $text = (int) ceil($chars / (int) $tokens['characters_per_text_token']);
        $jitter = random_int(-15, 15) / 100;
        $text = max(1, (int) round($text * (1 + $jitter)));

        $image = count($generation['reference_images'] ?? []) * (int) $tokens['per_reference_image'];
        $output = (int) $tokens['output'][$generation['quality']][$generation['size']];
        $reasoningRange = $tokens['reasoning'][$generation['quality']];
        $reasoning = random_int((int) $reasoningRange['min'], (int) $reasoningRange['max']);

        return [
            'input_text_tokens' => $text,
            'input_image_tokens' => $image,
            'output_tokens' => $output,
            'reasoning_tokens' => $reasoning,
            'total_tokens' => $text + $image + $output + $reasoning,
        ];
    }
}

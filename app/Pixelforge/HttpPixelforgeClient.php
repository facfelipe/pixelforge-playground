<?php

declare(strict_types=1);

namespace App\Pixelforge;

use App\Pixelforge\Contracts\PixelforgeClient;
use App\Pixelforge\Exceptions\PixelforgeException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Real Pixelforge HTTP client. Enable with PIXELFORGE_DRIVER=http once
 * PIXELFORGE_API_KEY (and webhook tunnel) are configured.
 */
final class HttpPixelforgeClient implements PixelforgeClient
{
    /**
     * {@inheritdoc}
     */
    public function createGeneration(array $params, string $idempotencyKey): array
    {
        return $this->request(
            fn (PendingRequest $http): mixed => $http
                ->withHeaders(['Idempotency-Key' => $idempotencyKey])
                ->post('/generations', $params),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getGeneration(string $id): array
    {
        return $this->request(
            fn (PendingRequest $http): mixed => $http->get("/generations/{$id}"),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getPricing(): array
    {
        return $this->request(
            fn (PendingRequest $http): mixed => $http->get('/pricing'),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getUsage(?string $start = null, ?string $end = null): array
    {
        return $this->request(
            fn (PendingRequest $http): mixed => $http->get('/usage', array_filter([
                'start' => $start,
                'end' => $end,
            ])),
        );
    }

    /**
     * @param  callable(PendingRequest): mixed  $callback
     * @return array<string, mixed>
     */
    private function request(callable $callback): array
    {
        $apiKey = config('pixelforge.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new PixelforgeException(
                'misconfigured',
                'PIXELFORGE_API_KEY is required when PIXELFORGE_DRIVER=http.',
                500,
            );
        }

        try {
            /** @var array<string, mixed> $body */
            $body = $callback(
                Http::baseUrl((string) config('pixelforge.base_url'))
                    ->acceptJson()
                    ->asJson()
                    ->withToken($apiKey)
                    ->throw()
            )->json();

            return $body;
        } catch (RequestException $exception) {
            $json = $exception->response?->json() ?? [];
            $error = is_array($json['error'] ?? null) ? $json['error'] : [];

            throw new PixelforgeException(
                (string) ($error['code'] ?? 'request_failed'),
                (string) ($error['message'] ?? $exception->getMessage()),
                $exception->response?->status() ?? 500,
            );
        }
    }
}

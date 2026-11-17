<?php

declare(strict_types=1);

namespace App\Pixelforge\Contracts;

interface PixelforgeClient
{
    /**
     * @param  array{
     *     prompt: string,
     *     size: string,
     *     quality: string,
     *     reference_images?: list<string>,
     *     metadata?: array<string, string>
     * }  $params
     * @return array<string, mixed>
     */
    public function createGeneration(array $params, string $idempotencyKey): array;

    /**
     * @return array<string, mixed>
     */
    public function getGeneration(string $id): array;

    /**
     * @return array{object: string, rates: array<string, string>}
     */
    public function getPricing(): array;

    /**
     * @return array<string, mixed>
     */
    public function getUsage(?string $start = null, ?string $end = null): array;
}

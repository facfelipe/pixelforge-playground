<?php

declare(strict_types=1);

use App\Actions\Generation\ApplyPixelforgeGeneration;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\PixelforgeWebhookEvent;
use App\Models\User;
use App\Pixelforge\PricingCatalog;
use App\Pixelforge\WebhookSignature;
use App\Support\Money;

function signedWebhookPayload(array $payload, ?int $timestamp = null): array
{
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);
    $timestamp ??= time();
    $secret = (string) config('pixelforge.webhook_secret');

    return [
        'raw' => $raw,
        'headers' => [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PIXELFORGE_SIGNATURE' => WebhookSignature::header($raw, $timestamp, $secret),
            'HTTP_PIXELFORGE_EVENT_ID' => $payload['id'],
            'HTTP_PIXELFORGE_EVENT_TYPE' => $payload['type'],
        ],
    ];
}

it('rejects webhooks with an invalid signature', function () {
    $payload = [
        'id' => 'evt_bad',
        'object' => 'event',
        'type' => 'ping',
        'created_at' => now()->toIso8601String(),
        'data' => ['object' => []],
    ];

    $this->call(
        'POST',
        route('webhooks.pixelforge'),
        content: json_encode($payload),
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PIXELFORGE_SIGNATURE' => 't='.time().',v1=deadbeef',
            'HTTP_PIXELFORGE_EVENT_ID' => 'evt_bad',
            'HTTP_PIXELFORGE_EVENT_TYPE' => 'ping',
        ],
    )->assertStatus(400);
});

it('rejects webhooks with an expired timestamp', function () {
    $payload = [
        'id' => 'evt_old',
        'object' => 'event',
        'type' => 'ping',
        'created_at' => now()->toIso8601String(),
        'data' => ['object' => []],
    ];

    $signed = signedWebhookPayload($payload, time() - 10_000);

    $this->call(
        'POST',
        route('webhooks.pixelforge'),
        content: $signed['raw'],
        server: $signed['headers'],
    )->assertStatus(400);
});

it('accepts a signed ping and records the event', function () {
    $payload = [
        'id' => 'evt_ping_1',
        'object' => 'event',
        'type' => 'ping',
        'created_at' => now()->toIso8601String(),
        'data' => ['object' => ['ok' => true]],
    ];

    $signed = signedWebhookPayload($payload);

    $this->call(
        'POST',
        route('webhooks.pixelforge'),
        content: $signed['raw'],
        server: $signed['headers'],
    )->assertNoContent();

    expect(PixelforgeWebhookEvent::query()->where('event_id', 'evt_ping_1')->exists())->toBeTrue();
});

it('dedupes duplicate event ids and charges only once', function () {
    $user = User::factory()->create(['credit_balance' => '10.000000000']);

    $generation = Generation::factory()->create([
        'user_id' => $user->id,
        'status' => GenerationStatus::Processing,
        'pixelforge_id' => 'gen_webhook_dup',
        'rates' => PricingCatalog::rates(),
        'actual_user_cost' => null,
    ]);

    $object = [
        'id' => 'gen_webhook_dup',
        'object' => 'generation',
        'status' => 'succeeded',
        'usage' => [
            'input_text_tokens' => 10,
            'input_image_tokens' => 0,
            'output_tokens' => 1000,
            'reasoning_tokens' => 100,
            'total_tokens' => 1110,
        ],
        'rates' => PricingCatalog::rates(),
        'image_url' => 'https://placehold.co/1024x1024/png',
        'image_url_expires_at' => now()->addDay()->toIso8601String(),
        'metadata' => ['local_id' => $generation->uuid],
    ];

    $payload = [
        'id' => 'evt_dup_same',
        'object' => 'event',
        'type' => 'generation.succeeded',
        'created_at' => now()->toIso8601String(),
        'data' => ['object' => $object],
    ];

    $signed = signedWebhookPayload($payload);

    $this->call(
        'POST',
        route('webhooks.pixelforge'),
        content: $signed['raw'],
        server: $signed['headers'],
    )->assertNoContent();

    $balanceAfterFirst = (string) $user->fresh()->credit_balance;

    $this->call(
        'POST',
        route('webhooks.pixelforge'),
        content: $signed['raw'],
        server: $signed['headers'],
    )->assertNoContent();

    expect(PixelforgeWebhookEvent::query()->where('event_id', 'evt_dup_same')->count())->toBe(1)
        ->and((string) $user->fresh()->credit_balance)->toBe($balanceAfterFirst)
        ->and($generation->fresh()->status)->toBe(GenerationStatus::Succeeded);
});

it('ignores out-of-order processing after success', function () {
    $user = User::factory()->create(['credit_balance' => '10.000000000']);

    $generation = Generation::factory()->create([
        'user_id' => $user->id,
        'status' => GenerationStatus::Queued,
        'pixelforge_id' => 'gen_ooo',
        'rates' => PricingCatalog::rates(),
    ]);

    app(ApplyPixelforgeGeneration::class)->handle($generation, [
        'id' => 'gen_ooo',
        'status' => 'succeeded',
        'usage' => [
            'input_text_tokens' => 5,
            'input_image_tokens' => 0,
            'output_tokens' => 500,
            'reasoning_tokens' => 50,
            'total_tokens' => 555,
        ],
        'rates' => PricingCatalog::rates(),
        'image_url' => 'https://placehold.co/1.png',
    ]);

    $balance = (string) $user->fresh()->credit_balance;

    $payload = [
        'id' => 'evt_ooo_processing',
        'object' => 'event',
        'type' => 'generation.processing',
        'created_at' => now()->toIso8601String(),
        'data' => [
            'object' => [
                'id' => 'gen_ooo',
                'status' => 'processing',
                'metadata' => ['local_id' => $generation->uuid],
            ],
        ],
    ];

    $signed = signedWebhookPayload($payload);

    $this->call(
        'POST',
        route('webhooks.pixelforge'),
        content: $signed['raw'],
        server: $signed['headers'],
    )->assertNoContent();

    expect($generation->fresh()->status)->toBe(GenerationStatus::Succeeded)
        ->and((string) $user->fresh()->credit_balance)->toBe($balance)
        ->and(Money::compare($balance, '10.000000000'))->toBe(-1);
});

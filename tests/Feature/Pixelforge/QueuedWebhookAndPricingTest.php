<?php

declare(strict_types=1);

use App\Actions\Billing\EstimateCost;
use App\Actions\Pixelforge\FetchPixelforgePricing;
use App\Jobs\ProcessPixelforgeWebhookJob;
use App\Models\PixelforgeWebhookEvent;
use App\Pixelforge\Contracts\PixelforgeClient;
use App\Pixelforge\Exceptions\PixelforgeException;
use App\Pixelforge\PricingCatalog;
use App\Pixelforge\WebhookSignature;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

it('queues verified webhook handling and returns 204', function () {
    Queue::fake();

    $payload = [
        'id' => 'evt_queued_ping',
        'object' => 'event',
        'type' => 'ping',
        'created_at' => now()->toIso8601String(),
        'data' => ['object' => ['ok' => true]],
    ];

    $raw = json_encode($payload, JSON_THROW_ON_ERROR);
    $timestamp = time();
    $secret = (string) config('pixelforge.webhook_secret');

    $this->call(
        'POST',
        route('webhooks.pixelforge'),
        content: $raw,
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PIXELFORGE_SIGNATURE' => WebhookSignature::header($raw, $timestamp, $secret),
            'HTTP_PIXELFORGE_EVENT_ID' => 'evt_queued_ping',
            'HTTP_PIXELFORGE_EVENT_TYPE' => 'ping',
        ],
    )->assertNoContent();

    Queue::assertPushed(ProcessPixelforgeWebhookJob::class, function (ProcessPixelforgeWebhookJob $job): bool {
        return $job->eventId === 'evt_queued_ping'
            && $job->eventType === 'ping'
            && $job->tries === 3;
    });

    expect(PixelforgeWebhookEvent::query()->count())->toBe(0);
});

it('loads estimate pricing through the Pixelforge client cache', function () {
    Cache::flush();

    $client = Mockery::mock(PixelforgeClient::class);
    $client->shouldReceive('getPricing')
        ->once()
        ->andReturn([
            'object' => 'pricing',
            'rates' => PricingCatalog::rates(),
            'tokens' => PricingCatalog::tokens(),
        ]);

    $this->app->instance(PixelforgeClient::class, $client);

    $first = app(FetchPixelforgePricing::class)->handle();
    $second = app(FetchPixelforgePricing::class)->handle();

    expect($first['rates'])->toBe(PricingCatalog::rates())
        ->and($second['rates'])->toBe($first['rates']);

    $estimate = app(EstimateCost::class)->handle('Hello', '1024x1024', 'low', [], $first);

    expect($estimate['estimated_user_cost'])->toBeString()
        ->and($estimate['rates'])->toBe(PricingCatalog::rates());
});

it('does not cache catalog fallback when pricing fetch fails', function () {
    Cache::flush();

    $client = Mockery::mock(PixelforgeClient::class);
    $client->shouldReceive('getPricing')
        ->twice()
        ->andThrow(new PixelforgeException('request_failed', 'down', 500));

    $this->app->instance(PixelforgeClient::class, $client);

    $first = app(FetchPixelforgePricing::class)->handle();
    $second = app(FetchPixelforgePricing::class)->handle();

    expect($first['rates'])->toBe(PricingCatalog::rates())
        ->and($second['rates'])->toBe(PricingCatalog::rates())
        ->and(Cache::has('pixelforge.pricing.snapshot'))->toBeFalse();
});

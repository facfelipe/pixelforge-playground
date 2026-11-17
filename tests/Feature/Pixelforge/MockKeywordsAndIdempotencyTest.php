<?php

declare(strict_types=1);

use App\Enums\GenerationStatus;
use App\Jobs\DeliverMockPixelforgeWebhookJob;
use App\Jobs\PollPixelforgeGenerationJob;
use App\Models\Generation;
use App\Models\User;
use App\Pixelforge\Contracts\PixelforgeClient;
use Illuminate\Support\Facades\Bus;

it('marks failed without charge when prompt contains [overloaded]', function () {
    $user = User::factory()->create([
        'credit_balance' => '10.000000000',
    ]);

    $this->actingAs($user)
        ->post(route('studio.store'), [
            'prompt' => 'Busy now [overloaded]',
            'size' => '1024x1024',
            'quality' => 'low',
        ])
        ->assertRedirect(route('studio'));

    $generation = Generation::query()->first();

    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->error_code)->toBe('overloaded')
        ->and((string) $generation->actual_user_cost)->toBe('0.000000000')
        ->and((string) $user->fresh()->credit_balance)->toBe('10.000000000');
});

it('delays the terminal mock webhook when prompt contains [slow]', function () {
    Bus::fake([
        DeliverMockPixelforgeWebhookJob::class,
        PollPixelforgeGenerationJob::class,
    ]);

    $user = User::factory()->create([
        'credit_balance' => '10.000000000',
    ]);

    $this->actingAs($user)
        ->post(route('studio.store'), [
            'prompt' => 'Take your time [slow]',
            'size' => '1024x1024',
            'quality' => 'low',
        ])
        ->assertRedirect(route('studio'));

    Bus::assertDispatched(DeliverMockPixelforgeWebhookJob::class, function (DeliverMockPixelforgeWebhookJob $job): bool {
        return $job->eventType === 'generation.processing' && $job->delay === null;
    });

    Bus::assertDispatched(DeliverMockPixelforgeWebhookJob::class, function (DeliverMockPixelforgeWebhookJob $job): bool {
        return $job->eventType === 'generation.succeeded' && $job->delay !== null;
    });
});

it('returns the same generation for a repeated idempotency key', function () {
    Bus::fake([
        DeliverMockPixelforgeWebhookJob::class,
        PollPixelforgeGenerationJob::class,
    ]);

    /** @var PixelforgeClient $client */
    $client = app(PixelforgeClient::class);

    $params = [
        'prompt' => 'Idempotent lighthouse',
        'size' => '1024x1024',
        'quality' => 'low',
        'reference_images' => [],
        'metadata' => ['local_id' => 'local-uuid-1'],
    ];

    $first = $client->createGeneration($params, 'idem-key-abc');
    $second = $client->createGeneration($params, 'idem-key-abc');

    // Same provider id; second call returns the cached object (may already be terminal).
    expect($second['id'])->toBe($first['id'])
        ->and($first['status'])->toBe('queued');

    // Lifecycle webhooks scheduled only on the first create.
    Bus::assertDispatchedTimes(DeliverMockPixelforgeWebhookJob::class, 2);
});

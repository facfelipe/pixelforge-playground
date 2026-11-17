<?php

declare(strict_types=1);

use App\Actions\Generation\ApplyPixelforgeGeneration;
use App\Enums\GenerationStatus;
use App\Jobs\DeliverMockPixelforgeWebhookJob;
use App\Jobs\PollPixelforgeGenerationJob;
use App\Jobs\ProcessGenerationJob;
use App\Models\Generation;
use App\Models\PixelforgeWebhookEvent;
use App\Models\User;
use App\Pixelforge\Contracts\PixelforgeClient;
use App\Support\Money;
use Illuminate\Support\Facades\Bus;

it('applies a terminal provider state when webhooks never run', function () {
    Bus::fake([
        DeliverMockPixelforgeWebhookJob::class,
        PollPixelforgeGenerationJob::class,
    ]);

    $user = User::factory()->create([
        'credit_balance' => '10.000000000',
    ]);

    $this->actingAs($user)
        ->post(route('studio.store'), [
            'prompt' => 'Poll-only success path',
            'size' => '1024x1024',
            'quality' => 'low',
        ])
        ->assertRedirect(route('studio'));

    $generation = Generation::query()->first();

    expect($generation->status)->toBe(GenerationStatus::Queued)
        ->and($generation->pixelforge_id)->toStartWith('gen_mock_')
        ->and(PixelforgeWebhookEvent::query()->count())->toBe(0);

    Bus::assertDispatched(DeliverMockPixelforgeWebhookJob::class);
    Bus::assertDispatched(PollPixelforgeGenerationJob::class);

    app(PollPixelforgeGenerationJob::class, [
        'generationId' => $generation->id,
    ])->handle(
        app(PixelforgeClient::class),
        app(ApplyPixelforgeGeneration::class),
    );

    $generation->refresh();
    $user->refresh();

    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->actual_user_cost)->not->toBeNull()
        ->and($generation->image_url)->not->toBeNull()
        ->and(Money::compare((string) $user->credit_balance, '10.000000000'))->toBe(-1)
        ->and(PixelforgeWebhookEvent::query()->count())->toBe(0);
});

it('does nothing when the generation is already terminal', function () {
    $user = User::factory()->create([
        'credit_balance' => '10.000000000',
    ]);

    $generation = Generation::factory()->create([
        'user_id' => $user->id,
        'status' => GenerationStatus::Succeeded,
        'pixelforge_id' => 'gen_already_done',
        'actual_user_cost' => '0.050000000',
        'actual_provider_cost' => '0.040000000',
    ]);

    app(PollPixelforgeGenerationJob::class, [
        'generationId' => $generation->id,
    ])->handle(
        app(PixelforgeClient::class),
        app(ApplyPixelforgeGeneration::class),
    );

    expect((string) $user->fresh()->credit_balance)->toBe('10.000000000')
        ->and((string) $generation->fresh()->actual_user_cost)->toBe('0.050000000');
});

it('process job schedules poll without re-creating when pixelforge_id exists', function () {
    Bus::fake([
        PollPixelforgeGenerationJob::class,
    ]);

    $user = User::factory()->create([
        'credit_balance' => '10.000000000',
    ]);

    $generation = Generation::factory()->create([
        'user_id' => $user->id,
        'status' => GenerationStatus::Queued,
        'pixelforge_id' => 'gen_existing',
        'prompt' => 'Already submitted',
    ]);

    app(ProcessGenerationJob::class, [
        'generationId' => $generation->id,
    ])->handle(
        app(PixelforgeClient::class),
        app(ApplyPixelforgeGeneration::class),
    );

    Bus::assertDispatched(PollPixelforgeGenerationJob::class, function (PollPixelforgeGenerationJob $job) use ($generation): bool {
        return $job->generationId === $generation->id;
    });

    expect($generation->fresh()->status)->toBe(GenerationStatus::Queued);
});

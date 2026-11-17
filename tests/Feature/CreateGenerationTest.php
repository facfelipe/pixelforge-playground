<?php

declare(strict_types=1);

use App\Actions\Generation\ApplyPixelforgeGeneration;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Queue;

it('creates a generation, completes via mock job, and debits actual once', function () {
    $user = User::factory()->create([
        'credit_balance' => '10.000000000',
    ]);

    $this->actingAs($user)
        ->post(route('studio.store'), [
            'prompt' => 'A calm lake at dusk [succeed]',
            'size' => '1024x1024',
            'quality' => 'low',
            'reference_images' => [],
        ])
        ->assertRedirect(route('studio'));

    $generation = Generation::query()->first();
    expect($generation)->not->toBeNull()
        ->and($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->actual_user_cost)->not->toBeNull();

    $user->refresh();
    expect(Money::compare((string) $user->credit_balance, '10.000000000'))->toBe(-1);
});

it('charges nothing when the mock generation fails', function () {
    $user = User::factory()->create([
        'credit_balance' => '10.000000000',
    ]);

    $this->actingAs($user)
        ->post(route('studio.store'), [
            'prompt' => 'Broken prompt [fail]',
            'size' => '1024x1024',
            'quality' => 'medium',
        ])
        ->assertRedirect(route('studio'));

    $generation = Generation::query()->first();
    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and((string) $generation->actual_user_cost)->toBe('0.000000000');

    $user->refresh();
    expect((string) $user->credit_balance)->toBe('10.000000000');
});

it('blocks create when balance is below max estimate', function () {
    Queue::fake();

    $user = User::factory()->create([
        'credit_balance' => '0.000001000',
    ]);

    $this->actingAs($user)
        ->post(route('studio.store'), [
            'prompt' => 'Expensive high quality image',
            'size' => '1536x1024',
            'quality' => 'high',
        ])
        ->assertSessionHasErrors('credits');

    expect(Generation::query()->count())->toBe(0);
});

it('does not double-charge when apply success runs twice', function () {
    $user = User::factory()->create([
        'credit_balance' => '10.000000000',
    ]);

    $this->actingAs($user)
        ->post(route('studio.store'), [
            'prompt' => 'Idempotent charge',
            'size' => '1024x1024',
            'quality' => 'low',
        ]);

    $generation = Generation::query()->first();
    $balanceAfterFirst = (string) $user->fresh()->credit_balance;
    $actual = (string) $generation->actual_user_cost;

    app(ApplyPixelforgeGeneration::class)->handle($generation, [
        'id' => $generation->pixelforge_id,
        'status' => 'succeeded',
        'usage' => $generation->usage,
        'rates' => $generation->rates,
        'image_url' => $generation->image_url,
    ]);

    expect((string) $user->fresh()->credit_balance)->toBe($balanceAfterFirst)
        ->and((string) $generation->fresh()->actual_user_cost)->toBe($actual);
});

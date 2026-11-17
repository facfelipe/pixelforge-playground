<?php

declare(strict_types=1);

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\PixelforgeWebhookEvent;
use App\Models\User;
use App\Support\Money;

it('completes through client create + mock webhook apply path', function () {
    $user = User::factory()->create([
        'credit_balance' => '10.000000000',
    ]);

    $this->actingAs($user)
        ->post(route('studio.store'), [
            'prompt' => 'Pipeline success scene',
            'size' => '1024x1024',
            'quality' => 'low',
        ])
        ->assertRedirect(route('studio'));

    $generation = Generation::query()->first();

    expect($generation)->not->toBeNull()
        ->and($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->pixelforge_id)->toStartWith('gen_mock_')
        ->and($generation->actual_user_cost)->not->toBeNull()
        ->and(PixelforgeWebhookEvent::query()->count())->toBeGreaterThanOrEqual(2);

    expect(Money::compare((string) $user->fresh()->credit_balance, '10.000000000'))->toBe(-1);
});

it('fails with zero charge when prompt contains [fail]', function () {
    $user = User::factory()->create([
        'credit_balance' => '10.000000000',
    ]);

    $this->actingAs($user)
        ->post(route('studio.store'), [
            'prompt' => 'Broken [fail]',
            'size' => '1024x1024',
            'quality' => 'medium',
        ])
        ->assertRedirect(route('studio'));

    $generation = Generation::query()->first();

    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and((string) $generation->actual_user_cost)->toBe('0.000000000')
        ->and((string) $user->fresh()->credit_balance)->toBe('10.000000000');
});

it('marks failed without charge when prompt contains [blocked]', function () {
    $user = User::factory()->create([
        'credit_balance' => '10.000000000',
    ]);

    $this->actingAs($user)
        ->post(route('studio.store'), [
            'prompt' => 'Nope [blocked]',
            'size' => '1024x1024',
            'quality' => 'low',
        ])
        ->assertRedirect(route('studio'));

    $generation = Generation::query()->first();

    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->error_code)->toBe('content_policy_violation')
        ->and((string) $generation->actual_user_cost)->toBe('0.000000000')
        ->and((string) $user->fresh()->credit_balance)->toBe('10.000000000');
});

it('dedupes when [duplicate] delivers each webhook twice', function () {
    $user = User::factory()->create([
        'credit_balance' => '10.000000000',
    ]);

    $this->actingAs($user)
        ->post(route('studio.store'), [
            'prompt' => 'Twice please [duplicate]',
            'size' => '1024x1024',
            'quality' => 'low',
        ])
        ->assertRedirect(route('studio'));

    $generation = Generation::query()->first();

    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and(PixelforgeWebhookEvent::query()->count())->toBe(2);

    $balance = (string) $user->fresh()->credit_balance;
    expect(Money::compare($balance, '10.000000000'))->toBe(-1);
});

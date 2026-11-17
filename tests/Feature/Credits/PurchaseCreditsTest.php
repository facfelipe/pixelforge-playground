<?php

declare(strict_types=1);

use App\Models\User;

it('adds a $5 credit pack to the balance', function () {
    $user = User::factory()->create([
        'credit_balance' => '10.000000000',
    ]);

    $this->actingAs($user)
        ->post(route('credits.store'))
        ->assertRedirect(route('credits.index'));

    expect((string) $user->fresh()->credit_balance)->toBe('15.000000000');
});

it('can purchase the credit pack more than once', function () {
    $user = User::factory()->create([
        'credit_balance' => '10.000000000',
    ]);

    $this->actingAs($user)->post(route('credits.store'));
    $this->actingAs($user)->post(route('credits.store'));

    expect((string) $user->fresh()->credit_balance)->toBe('20.000000000');
});

it('shows the credits page with balance and pack size', function () {
    $user = User::factory()->create([
        'credit_balance' => '7.500000000',
    ]);

    $this->actingAs($user)
        ->get(route('credits.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Credits')
            ->where('balance', '7.500000000')
            ->where('pack', '5.000000000'));
});

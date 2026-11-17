<?php

declare(strict_types=1);

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;

it('returns typical and max estimate over http', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('studio.estimate'), [
            'prompt' => 'A quiet harbor at dusk',
            'size' => '1024x1024',
            'quality' => 'medium',
            'reference_images' => [],
        ])
        ->assertOk()
        ->assertJsonStructure([
            'estimated_user_cost',
            'estimated_user_cost_max',
            'estimated_user_cost_display',
            'estimated_user_cost_max_display',
        ])
        ->assertJson(fn ($json) => $json
            ->whereType('estimated_user_cost', 'string')
            ->whereType('estimated_user_cost_max', 'string')
            ->etc());
});

it('rejects invalid estimate payloads', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('studio.estimate'), [
            'prompt' => 'Nope',
            'size' => '999x999',
            'quality' => 'ultra',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['size', 'quality']);
});

it('flags hasActiveGenerations when queued or processing rows exist', function () {
    $user = User::factory()->create();

    Generation::factory()->create([
        'user_id' => $user->id,
        'status' => GenerationStatus::Processing,
    ]);

    $this->actingAs($user)
        ->get(route('studio'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Studio')
            ->where('hasActiveGenerations', true)
            ->has('generations', 1));
});

it('clears hasActiveGenerations when all generations are terminal', function () {
    $user = User::factory()->create();

    Generation::factory()->create([
        'user_id' => $user->id,
        'status' => GenerationStatus::Succeeded,
        'actual_user_cost' => '0.100000000',
    ]);

    Generation::factory()->create([
        'user_id' => $user->id,
        'status' => GenerationStatus::Failed,
        'actual_user_cost' => '0.000000000',
    ]);

    $this->actingAs($user)
        ->get(route('studio'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Studio')
            ->where('hasActiveGenerations', false)
            ->has('generations', 2));
});

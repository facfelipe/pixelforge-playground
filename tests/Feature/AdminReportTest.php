<?php

declare(strict_types=1);

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;

it('forbids non-admins from the usage report', function () {
    $user = User::factory()->create([
        'email' => 'user@example.com',
    ]);

    $this->actingAs($user)
        ->get(route('admin.report'))
        ->assertForbidden();
});

it('shows spend provider and profit for admins', function () {
    $admin = User::factory()->create([
        'email' => 'admin@test.com',
    ]);

    Generation::factory()->create([
        'user_id' => $admin->id,
        'status' => GenerationStatus::Succeeded,
        'actual_provider_cost' => '0.080000000',
        'actual_user_cost' => '0.100000000',
        'updated_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.report'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Report')
            ->where('summary.user_spend', '0.100000000')
            ->where('summary.provider_cost', '0.080000000')
            ->where('summary.profit', '0.020000000')
            ->has('byDay', 1)
            ->has('byUser', 1));
});

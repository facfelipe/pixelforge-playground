<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Pixelforge\PricingCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Generation>
 */
class GenerationFactory extends Factory
{
    protected $model = Generation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'pixelforge_id' => 'mock_'.Str::random(16),
            'status' => GenerationStatus::Queued,
            'prompt' => fake()->sentence(),
            'size' => '1024x1024',
            'quality' => 'medium',
            'reference_images' => [],
            'estimated_provider_cost' => '0.050000000',
            'estimated_user_cost' => '0.062500000',
            'estimated_user_cost_max' => '0.080000000',
            'rates' => PricingCatalog::rates(),
        ];
    }
}

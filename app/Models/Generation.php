<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GenerationStatus;
use Database\Factories\GenerationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Generation extends Model
{
    /** @use HasFactory<GenerationFactory> */
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'pixelforge_id',
        'status',
        'prompt',
        'size',
        'quality',
        'reference_images',
        'estimated_provider_cost',
        'estimated_user_cost',
        'estimated_user_cost_max',
        'actual_provider_cost',
        'actual_user_cost',
        'rates',
        'usage',
        'image_url',
        'image_url_expires_at',
        'error_code',
        'error_message',
    ];

    protected static function booting(): void
    {
        static::creating(function (Generation $generation): void {
            if (empty($generation->uuid)) {
                $generation->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GenerationStatus::class,
            'reference_images' => 'array',
            'rates' => 'array',
            'usage' => 'array',
            'estimated_provider_cost' => 'decimal:9',
            'estimated_user_cost' => 'decimal:9',
            'estimated_user_cost_max' => 'decimal:9',
            'actual_provider_cost' => 'decimal:9',
            'actual_user_cost' => 'decimal:9',
            'image_url_expires_at' => 'datetime',
        ];
    }

    public function isCharged(): bool
    {
        return $this->actual_user_cost !== null;
    }
}

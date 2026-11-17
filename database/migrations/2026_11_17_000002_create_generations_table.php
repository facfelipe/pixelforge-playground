<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('pixelforge_id')->nullable()->unique();
            $table->string('status', 32);
            $table->text('prompt');
            $table->string('size', 32);
            $table->string('quality', 16);
            $table->json('reference_images');
            $table->decimal('estimated_provider_cost', 28, 9);
            $table->decimal('estimated_user_cost', 28, 9);
            $table->decimal('estimated_user_cost_max', 28, 9);
            $table->decimal('actual_provider_cost', 28, 9)->nullable();
            $table->decimal('actual_user_cost', 28, 9)->nullable();
            $table->json('rates')->nullable();
            $table->json('usage')->nullable();
            $table->text('image_url')->nullable();
            $table->timestamp('image_url_expires_at')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};

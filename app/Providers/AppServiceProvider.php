<?php

namespace App\Providers;

use App\Pixelforge\Contracts\PixelforgeClient;
use App\Pixelforge\HttpPixelforgeClient;
use App\Pixelforge\MockPixelforgeClient;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PixelforgeClient::class, function (): PixelforgeClient {
            return match (config('pixelforge.driver')) {
                'mock' => $this->app->make(MockPixelforgeClient::class),
                'http' => $this->app->make(HttpPixelforgeClient::class),
                default => throw new InvalidArgumentException(
                    'Unsupported PIXELFORGE_DRIVER: '.config('pixelforge.driver'),
                ),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}

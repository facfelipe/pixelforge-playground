<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Driver
    |--------------------------------------------------------------------------
    |
    | mock — in-process Pixelforge API + signed mock webhook deliveries
    | http — real HTTP client (wire credentials + tunnel for the last stage)
    |
    */

    'driver' => env('PIXELFORGE_DRIVER', 'mock'),

    'base_url' => env('PIXELFORGE_BASE_URL', 'https://pixelforge.promptedit.dev/v1'),

    'api_key' => env('PIXELFORGE_API_KEY'),

    'webhook_secret' => env('PIXELFORGE_WEBHOOK_SECRET', 'whsec_mock_local_secret'),

    'webhook_tolerance_seconds' => (int) env('PIXELFORGE_WEBHOOK_TOLERANCE', 300),

    /*
    |--------------------------------------------------------------------------
    | Polling fallback
    |--------------------------------------------------------------------------
    |
    | Dispatched after create. With the sync queue + mock webhooks, the
    | generation is usually already terminal before the poll runs.
    |
    */

    'poll_delay_seconds' => (int) env('PIXELFORGE_POLL_DELAY', 15),

    'poll_retry_seconds' => (int) env('PIXELFORGE_POLL_RETRY', 10),

    'poll_max_attempts' => (int) env('PIXELFORGE_POLL_MAX_ATTEMPTS', 12),

    'pricing_cache_seconds' => (int) env('PIXELFORGE_PRICING_CACHE', 300),

];

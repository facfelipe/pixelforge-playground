<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPixelforgeWebhookJob;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

final class PixelforgeWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        $eventId = (string) ($request->header('Pixelforge-Event-Id') ?: ($payload['id'] ?? ''));
        $type = (string) ($request->header('Pixelforge-Event-Type') ?: ($payload['type'] ?? ''));

        if ($eventId === '' || $type === '') {
            abort(400, 'Missing Pixelforge event headers.');
        }

        Log::info('pixelforge.webhook.received', [
            'event_id' => $eventId,
            'event_type' => $type,
            'delivery_attempt' => $request->header('Pixelforge-Delivery-Attempt'),
        ]);

        ProcessPixelforgeWebhookJob::dispatch($eventId, $type, $payload);

        return response()->noContent();
    }
}

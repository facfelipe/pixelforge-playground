<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Pixelforge\WebhookSignature;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class VerifyPixelforgeWebhookSignature
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $payload = $request->getContent();
        $header = (string) $request->header('Pixelforge-Signature', '');
        $secret = (string) config('pixelforge.webhook_secret');
        $tolerance = (int) config('pixelforge.webhook_tolerance_seconds', 300);

        if (! WebhookSignature::isValid($payload, $header, $secret, $tolerance)) {
            Log::warning('pixelforge.webhook.invalid_signature', [
                'event_id' => $request->header('Pixelforge-Event-Id'),
                'event_type' => $request->header('Pixelforge-Event-Type'),
            ]);

            abort(400, 'Invalid Pixelforge signature.');
        }

        return $next($request);
    }
}

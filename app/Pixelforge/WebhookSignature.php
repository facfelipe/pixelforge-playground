<?php

declare(strict_types=1);

namespace App\Pixelforge;

final class WebhookSignature
{
    /**
     * @return array{t: int, v1: string}|null
     */
    public static function parse(string $header): ?array
    {
        $parts = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[$key] = $value;
        }

        if (! isset($parts['t'], $parts['v1']) || $parts['t'] === '' || $parts['v1'] === '') {
            return null;
        }

        return [
            't' => (int) $parts['t'],
            'v1' => $parts['v1'],
        ];
    }

    public static function sign(string $payload, int $timestamp, string $secret): string
    {
        return hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);
    }

    public static function header(string $payload, int $timestamp, string $secret): string
    {
        return 't='.$timestamp.',v1='.self::sign($payload, $timestamp, $secret);
    }

    public static function isValid(
        string $payload,
        string $header,
        string $secret,
        int $toleranceSeconds = 300,
        ?int $now = null,
    ): bool {
        $parsed = self::parse($header);

        if ($parsed === null) {
            return false;
        }

        $expected = self::sign($payload, $parsed['t'], $secret);

        if (! hash_equals($expected, $parsed['v1'])) {
            return false;
        }

        $now ??= time();

        return abs($now - $parsed['t']) <= $toleranceSeconds;
    }
}

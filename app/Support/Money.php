<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Exact decimal money helpers (9 fractional places). Never use floats for billing.
 */
final class Money
{
    public const SCALE = 9;

    public static function normalize(string $amount): string
    {
        if (! is_numeric($amount)) {
            throw new InvalidArgumentException("Invalid money amount [{$amount}].");
        }

        return bcadd($amount, '0', self::SCALE);
    }

    public static function add(string $a, string $b): string
    {
        return bcadd(self::normalize($a), self::normalize($b), self::SCALE);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub(self::normalize($a), self::normalize($b), self::SCALE);
    }

    public static function mul(string $a, string $b): string
    {
        return bcmul(self::normalize($a), self::normalize($b), self::SCALE);
    }

    public static function div(string $a, string $b): string
    {
        if (bccomp(self::normalize($b), '0', self::SCALE) === 0) {
            throw new InvalidArgumentException('Division by zero.');
        }

        return bcdiv(self::normalize($a), self::normalize($b), self::SCALE);
    }

    public static function compare(string $a, string $b): int
    {
        return bccomp(self::normalize($a), self::normalize($b), self::SCALE);
    }

    public static function lessThan(string $a, string $b): bool
    {
        return self::compare($a, $b) < 0;
    }

    public static function zero(): string
    {
        return self::normalize('0');
    }

    /**
     * Cost for token count at $ per 1M tokens.
     */
    public static function costForTokens(int $tokens, string $ratePerMillion): string
    {
        return self::mul((string) $tokens, self::div($ratePerMillion, '1000000'));
    }

    /**
     * UI-only formatting. Always 6 fractional digits so every screen matches.
     * Billing math stays at {@see SCALE} (9) — never use this for charges/gates.
     */
    public static function formatDisplay(string $amount): string
    {
        return self::roundHalfUp(self::normalize($amount), 6);
    }

    private static function roundHalfUp(string $amount, int $places): string
    {
        $negative = self::compare($amount, '0') < 0;
        $abs = $negative ? self::mul($amount, '-1') : $amount;
        $factor = bcpow('10', (string) $places, 0);
        $scaled = bcmul($abs, $factor, self::SCALE);
        $rounded = bcadd($scaled, '0.5', 0);
        $result = bcdiv($rounded, $factor, $places);

        return $negative ? '-'.$result : $result;
    }
}

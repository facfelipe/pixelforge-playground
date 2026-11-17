<?php

declare(strict_types=1);

use App\Support\Money;

it('formats display money with a fixed six decimal places', function () {
    expect(Money::formatDisplay('0.072200000'))->toBe('0.072200')
        ->and(Money::formatDisplay('0.072187500'))->toBe('0.072188')
        ->and(Money::formatDisplay('10'))->toBe('10.000000')
        ->and(Money::formatDisplay('0.084809375'))->toBe('0.084809');
});

it('keeps full scale for billing math', function () {
    expect(Money::mul('0.067850000', '1.25'))->toBe('0.084812500');
});

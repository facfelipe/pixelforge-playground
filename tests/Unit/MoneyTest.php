<?php

declare(strict_types=1);

use App\Support\Money;

it('formats display money with a fixed six decimal places by default', function () {
    expect(Money::formatDisplay('0.072200000'))->toBe('0.072200')
        ->and(Money::formatDisplay('0.072187500'))->toBe('0.072188')
        ->and(Money::formatDisplay('10'))->toBe('10.000000')
        ->and(Money::formatDisplay('0.084809375'))->toBe('0.084809');
});

it('formats wallet display money with two decimal places', function () {
    expect(Money::formatDisplay('5.000000000', 2))->toBe('5.00')
        ->and(Money::formatDisplay('7.500000000', 2))->toBe('7.50')
        ->and(Money::formatDisplay('4.904650000', 2))->toBe('4.90');
});

it('keeps full scale for billing math', function () {
    expect(Money::mul('0.067850000', '1.25'))->toBe('0.084812500');
});

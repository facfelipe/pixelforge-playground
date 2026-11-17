<?php

declare(strict_types=1);

use App\Actions\Billing\EstimateCost;
use App\Support\Money;

it('estimates typical and max user costs with markup', function () {
    $estimate = app(EstimateCost::class)->handle(
        str_repeat('a', 400),
        '1536x1024',
        'high',
        ['https://example.com/a.png', 'https://example.com/b.png'],
    );

    expect(Money::compare($estimate['estimated_user_cost_max'], $estimate['estimated_user_cost']))->toBe(1)
        ->and($estimate['estimated_tokens']['output'])->toBe(6450)
        ->and($estimate['max_tokens']['input_image'])->toBe(1530)
        ->and($estimate['max_tokens']['reasoning'])->toBe(8000);
});

it('matches the docs worked-example maximum provider cost', function () {
    // 115 text, 1530 image, 6450 output, 8000 reasoning at documented rates
    $provider = Money::add(
        Money::add(
            Money::costForTokens(115, '5.00'),
            Money::costForTokens(1530, '10.00'),
        ),
        Money::add(
            Money::costForTokens(6450, '40.00'),
            Money::costForTokens(8000, '12.50'),
        ),
    );

    expect($provider)->toBe('0.373875000');
});

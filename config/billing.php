<?php

declare(strict_types=1);

return [

    'markup' => '1.25',

    'starting_balance' => '10.000000000',

    'credit_pack' => '5.000000000',

    'admin_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ADMIN_EMAILS', 'admin@test.com'))
    ))),

];

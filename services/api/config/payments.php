<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Solana Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the external Solana worker service that handles
    | blockchain transactions.
    |
    */

    'solana_worker_url' => env('SOLANA_WORKER_URL', 'http://localhost:8080'),
    'solana_worker_timeout' => env('SOLANA_WORKER_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Token Configuration
    |--------------------------------------------------------------------------
    |
    | Solana token mint address and default currency settings.
    |
    */

    'token_mint' => env('TOKEN_MINT'),
    'default_currency' => env('DEFAULT_CURRENCY', 'MYXN'),
];

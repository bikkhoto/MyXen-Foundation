<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Solana RPC Configuration
    |--------------------------------------------------------------------------
    */

    'rpc_url' => env('SOLANA_RPC_URL', 'https://api.mainnet-beta.solana.com'),
    
    'network' => env('SOLANA_NETWORK', 'mainnet-beta'),

    /*
    |--------------------------------------------------------------------------
    | MYXN Token Configuration
    |--------------------------------------------------------------------------
    */

    'myxn_token_mint' => env('MYXN_TOKEN_MINT', ''),
    
    'myxn_decimals' => env('MYXN_DECIMALS', 9),

    /*
    |--------------------------------------------------------------------------
    | Transaction Settings
    |--------------------------------------------------------------------------
    */

    'confirmation_timeout' => env('SOLANA_CONFIRMATION_TIMEOUT', 30),
    
    'max_retries' => env('SOLANA_MAX_RETRIES', 3),

    /*
    |--------------------------------------------------------------------------
    | Fee Settings
    |--------------------------------------------------------------------------
    */

    'transaction_fee_percentage' => env('TRANSACTION_FEE_PERCENTAGE', 0.5),
    
    'min_transaction_amount' => env('MIN_TRANSACTION_AMOUNT', 0.01),

];

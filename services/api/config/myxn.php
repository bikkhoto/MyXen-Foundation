<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MYXN Token Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the MYXN token on Solana blockchain.
    |
    */

    'token' => [
        'mint' => env('MYXN_TOKEN_MINT', '6S4eDdYXABgtmuk3waLM63U2KHgExcD9mco7MuyG9f5G'),
        'decimals' => env('MYXN_TOKEN_DECIMALS', 9),
        'symbol' => 'MYXN',
        'name' => 'MyXen Token',
    ],

    /*
    |--------------------------------------------------------------------------
    | Network Configuration
    |--------------------------------------------------------------------------
    |
    | Solana network settings
    |
    */

    'network' => env('SOLANA_NETWORK', 'mainnet-beta'),
    'rpc_url' => env('SOLANA_RPC_URL', 'https://api.mainnet-beta.solana.com'),

    /*
    |--------------------------------------------------------------------------
    | Official Service Wallets
    |--------------------------------------------------------------------------
    |
    | Specific wallets for different organizational services.
    | Each wallet has a designated purpose and funding rules.
    |
    */

    'wallets' => [
        'treasury' => [
            'address' => env('MYXN_TREASURY_WALLET', 'Azvjj21uXQzHbM9VHhyDfdbj14HD8Tef7ZuC1p7sEMk9'),
            'name' => 'Treasury Wallet',
            'description' => 'Main treasury - All funds flow through here',
            'auto_funding' => false,
        ],

        'mint' => [
            'address' => env('MYXN_MINT_WALLET', '6S4eDdYXABgtmuk3waLM63U2KHgExcD9mco7MuyG9f5G'),
            'name' => 'Core Token Mint',
            'description' => 'Core Token Mint Authority Wallet',
            'auto_funding' => false,
        ],

        'burn' => [
            'address' => env('MYXN_BURN_WALLET', 'HuyT8sNPJMnh9vgJ43PXU4TY696WTWSdh1LBX53ZVox9'),
            'name' => 'Burn Wallet',
            'description' => 'Official on-chain burn wallet - From Treasury manually',
            'auto_funding' => false,
            'funding_source' => 'treasury',
        ],

        'charity' => [
            'address' => env('MYXN_CHARITY_WALLET', 'DDoiUCeoUNHHCV5sLT3rgFjLpLUM76tLCUMnAg52o8vK'),
            'name' => 'Charity Wallet',
            'description' => 'Charity funds - From Treasury + Platform fee charity portion',
            'auto_funding' => true,
            'funding_source' => 'treasury',
            'platform_fee_percentage' => 5, // 5% of platform fees go to charity
        ],

        'hr' => [
            'address' => env('MYXN_HR_WALLET', 'Hv8QBqqSfD4nC6N8qZBo7iJE9QiHLnoXJ6sV2hk1XpoR'),
            'name' => 'HR Wallet',
            'description' => 'HR & Organization costs - From Treasury + Platform fees',
            'auto_funding' => true,
            'funding_source' => 'treasury',
            'platform_fee_percentage' => 10, // 10% of platform fees go to HR
        ],

        'marketing' => [
            'address' => env('MYXN_MARKETING_WALLET', '4egNUZa2vNBwmc633GAjworDPEJD2F1HK6pSvMnC3WSv'),
            'name' => 'Marketing Wallet',
            'description' => 'On-chain marketing spending - From Treasury manually',
            'auto_funding' => false,
            'funding_source' => 'treasury',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Financial Programs Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for various financial programs using MYXN tokens.
    |
    */

    'programs' => [
        'presale' => [
            'enabled' => env('MYXN_PRESALE_ENABLED', true),
            'price_usd' => env('MYXN_PRESALE_PRICE_USD', 0.007),
            'max_per_wallet_usd' => env('MYXN_PRESALE_MAX_PER_WALLET_USD', 2000),
            'min_purchase_usd' => env('MYXN_PRESALE_MIN_PURCHASE_USD', 10),
        ],

        'staking' => [
            'enabled' => env('MYXN_STAKING_ENABLED', false),
            'apy_percentage' => env('MYXN_STAKING_APY', 12),
            'min_stake_amount' => env('MYXN_STAKING_MIN', 1000),
            'lock_period_days' => env('MYXN_STAKING_LOCK_DAYS', 30),
        ],

        'rewards' => [
            'enabled' => env('MYXN_REWARDS_ENABLED', true),
            'referral_percentage' => env('MYXN_REFERRAL_REWARD', 5),
            'signup_bonus' => env('MYXN_SIGNUP_BONUS', 100),
        ],

        'burn' => [
            'enabled' => env('MYXN_BURN_ENABLED', true),
            'quarterly_burn_percentage' => env('MYXN_QUARTERLY_BURN', 2),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Platform Fees Distribution
    |--------------------------------------------------------------------------
    |
    | How platform fees are distributed among service wallets.
    |
    */

    'fee_distribution' => [
        'charity' => 5,      // 5% to charity
        'hr' => 10,          // 10% to HR
        'marketing' => 15,   // 15% to marketing
        'treasury' => 70,    // 70% to treasury
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracing Configuration
    |--------------------------------------------------------------------------
    |
    | OpenTelemetry tracing configuration
    |
    */

    'tracing' => [
        'enabled' => env('MYXN_TRACING_ENABLED', true),
        'service_name' => env('OTEL_SERVICE_NAME', 'myxn-financial-service'),
        'endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT', 'http://localhost:4318'),
        'sampler_ratio' => env('OTEL_SAMPLER_RATIO', 1.0),
    ],
];

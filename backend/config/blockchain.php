<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Blockchain Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for blockchain integrations.
    | Currently focused on Solana network for MyXenPay ecosystem.
    |
    */

    'solana' => [
        /*
         * Solana RPC endpoint URL
         * Options: mainnet-beta, testnet, devnet, or custom RPC provider
         */
        'rpc_url' => env('SOLANA_RPC_URL', 'https://api.devnet.solana.com'),

        /*
         * Network identifier
         * Options: mainnet-beta, testnet, devnet
         */
        'network' => env('SOLANA_NETWORK', 'devnet'),

        /*
         * WebSocket endpoint for real-time updates
         */
        'ws_url' => env('SOLANA_WS_URL', 'wss://api.devnet.solana.com'),

        /*
         * MYXN Token Configuration (SPL Token)
         */
        'myxn_token' => [
            'mint_address' => env('MYXN_MINT_ADDRESS', ''),
            'decimals' => (int) env('MYXN_DECIMALS', 9),
        ],

        /*
         * Transaction Configuration
         */
        'transaction' => [
            'confirmation_timeout' => (int) env('SOLANA_TX_TIMEOUT', 60),
            'commitment' => env('SOLANA_COMMITMENT', 'confirmed'),
        ],
    ],

    /*
     * Hot Wallet Configuration
     * WARNING: In production, use HSM or secure key management
     */
    'hot_wallet' => [
        'enabled' => env('HOT_WALLET_ENABLED', false),
        // TODO: Implement secure key storage (KMS, HSM, Vault)
    ],

];

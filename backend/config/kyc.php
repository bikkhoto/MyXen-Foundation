<?php

return [

    /*
    |--------------------------------------------------------------------------
    | KYC Provider Configuration
    |--------------------------------------------------------------------------
    */

    'provider' => env('KYC_PROVIDER', 'internal'),

    /*
    |--------------------------------------------------------------------------
    | KYC Levels
    |--------------------------------------------------------------------------
    */

    'levels' => [
        0 => [
            'name' => 'Unverified',
            'daily_limit' => 100,
            'monthly_limit' => 500,
            'required_documents' => [],
        ],
        1 => [
            'name' => 'Basic',
            'daily_limit' => 1000,
            'monthly_limit' => 5000,
            'required_documents' => ['id_card', 'selfie'],
        ],
        2 => [
            'name' => 'Verified',
            'daily_limit' => 10000,
            'monthly_limit' => 50000,
            'required_documents' => ['id_card', 'selfie', 'proof_of_address'],
        ],
        3 => [
            'name' => 'Premium',
            'daily_limit' => 100000,
            'monthly_limit' => 500000,
            'required_documents' => ['id_card', 'selfie', 'proof_of_address', 'bank_statement'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Document Types
    |--------------------------------------------------------------------------
    */

    'document_types' => [
        'id_card' => 'National ID Card',
        'passport' => 'Passport',
        'drivers_license' => 'Driver\'s License',
        'selfie' => 'Selfie with ID',
        'proof_of_address' => 'Proof of Address',
        'bank_statement' => 'Bank Statement',
        'university_id' => 'University ID Card',
    ],

    /*
    |--------------------------------------------------------------------------
    | Verification Settings
    |--------------------------------------------------------------------------
    */

    'auto_approve' => env('KYC_AUTO_APPROVE', false),
    
    'document_expiry_days' => env('KYC_DOCUMENT_EXPIRY_DAYS', 365),

];

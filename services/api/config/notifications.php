<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Notification Max Attempts
    |--------------------------------------------------------------------------
    |
    | The maximum number of attempts to send a notification before marking
    | it as failed. Applies to all notification channels.
    |
    */

    'max_attempts' => env('NOTIFICATION_MAX_ATTEMPTS', 3),

    /*
    |--------------------------------------------------------------------------
    | Notification API Key
    |--------------------------------------------------------------------------
    |
    | API key for authenticating internal services that create notification
    | events. This key should be provided in the X-API-KEY header.
    |
    */

    'api_key' => env('NOTIFICATION_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | SMS Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for SMS notifications via Twilio.
    |
    */

    'sms' => [
        'provider' => 'twilio', // Currently only Twilio is supported

        'twilio' => [
            'account_sid' => env('TWILIO_SID'),
            'auth_token' => env('TWILIO_TOKEN'),
            'from_number' => env('TWILIO_FROM'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Telegram Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Telegram notifications via Bot API.
    |
    */

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    ],

];

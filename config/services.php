<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | M-Pesa Daraja API (Safaricom Kenya)
    |--------------------------------------------------------------------------
    |
    | STK Push (CustomerPayBillOnline) — customer payment via M-Pesa prompt.
    | B2C (Business to Customer)        — payout to doctor.
    |
    | Get credentials: https://developer.safaricom.co.ke
    |   - Sandbox: free test credentials
    |   - Live:    production credentials from your Daraja app
    |
    | MPESA_CALLBACK_URL and MPESA_QUEUE_TIMEOUT_URL must be publicly
    | reachable. Use ngrok during local development:
    |     ngrok http 8000
    | then set MPESA_CALLBACK_URL=https://<random>.ngrok-free.app/api/v1/mpesa/stk/result
    |
    */

    'mpesa' => [
        'environment'         => env('MPESA_ENVIRONMENT', 'sandbox'),
        'consumer_key'        => env('MPESA_CONSUMER_KEY'),
        'consumer_secret'     => env('MPESA_CONSUMER_SECRET'),

        // STK Push (LNM Online)
        'shortcode'           => env('MPESA_SHORTCODE', '174379'),
        'passkey'             => env('MPESA_PASSKEY'),
        'callback_url'        => env('MPESA_CALLBACK_URL'),
        'queue_timeout_url'   => env('MPESA_QUEUE_TIMEOUT_URL'),

        // B2C (payouts to doctors)
        'b2c_shortcode'       => env('MPESA_B2C_SHORTCODE', env('MPESA_SHORTCODE', '174379')),
        'initiator_name'      => env('MPESA_INITIATOR_NAME', 'apitest'),
        'security_credential' => env('MPESA_SECURITY_CREDENTIAL'),
        'b2c_result_url'      => env('MPESA_B2C_RESULT_URL'),

        // Simulation override (dev only)
        'simulation_secret'   => env('MPESA_SIMULATION_SECRET'),
    ],

];

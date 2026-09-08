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
        'environment' => env('MPESA_ENVIRONMENT', 'sandbox'),
        'consumer_key' => env('MPESA_CONSUMER_KEY'),
        'consumer_secret' => env('MPESA_CONSUMER_SECRET'),

        // STK Push (LNM Online)
        'shortcode' => env('MPESA_SHORTCODE', '174379'),
        'passkey' => env('MPESA_PASSKEY'),
        'callback_url' => env('MPESA_CALLBACK_URL'),
        'queue_timeout_url' => env('MPESA_QUEUE_TIMEOUT_URL'),

        // B2C (payouts to doctors)
        'b2c_shortcode' => env('MPESA_B2C_SHORTCODE', env('MPESA_SHORTCODE', '174379')),
        'initiator_name' => env('MPESA_INITIATOR_NAME', 'apitest'),
        'security_credential' => env('MPESA_SECURITY_CREDENTIAL'),
        'b2c_result_url' => env('MPESA_B2C_RESULT_URL'),

        // Simulation override (dev only)
        'simulation_secret' => env('MPESA_SIMULATION_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment webhooks
    |--------------------------------------------------------------------------
    |
    | WEBHOOK_SECRET     – shared secret used to compute the `X-Webhook-Signature`
    |                      header (HMAC-SHA256 of the raw request body). When set,
    |                      the VerifyWebhookSignature middleware enforces it.
    |
    | MPESA_CALLER_IPS   – comma-separated allow-list of Safaricom callback IPs.
    |                      Enforced when set (Daraja does not sign callback bodies).
    |                      E.g. "212.49.82.70,212.49.83.74,212.49.87.28"
    |
    */

    'webhook' => [
        'secret' => env('WEBHOOK_SECRET'),
        'mpesa_caller_ips' => env('MPESA_CALLER_IPS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment simulation endpoint
    |--------------------------------------------------------------------------
    |
    | PAYMENT_SIMULATION_ENABLED (default false) exposes POST /api/payments/simulate,
    | which marks an appointment payment as confirmed without a real provider.
    | Only ever enable this in non-production environments.
    |
    */

    'payments' => [
        'simulation_enabled' => env('PAYMENT_SIMULATION_ENABLED', false),

        // Phase 23: platform fee charged on patient->facility payments.
        // A persisted "platform_fees" row takes precedence when present.
        'platform_fee_type' => env('PLATFORM_FEE_TYPE', 1), // 1 = percentage, 2 = fixed
        'platform_fee_percent' => env('PLATFORM_FEE_PERCENT', 5.0), // 5.0 = 5%
        'platform_fee_fixed' => env('PLATFORM_FEE_FIXED', 0), // KES when fee_type = 2

        // Phase 23: stale pending payments are EXPIRED after this many minutes.
        'pending_expiry_minutes' => env('PAYMENT_PENDING_EXPIRY_MINUTES', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduling
    |--------------------------------------------------------------------------
    |
    | Cross-facility scheduling rules. transition_buffer_minutes is the minimum
    | gap required between a doctor's sessions across DIFFERENT facilities to
    | account for travel/transition time. Never hard-coded.
    |
    */

    'scheduling' => [
        'transition_buffer_minutes' => env('SCHEDULING_TRANSITION_BUFFER_MINUTES', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Facility subscriptions (Phase 24)
    |--------------------------------------------------------------------------
    |
    | Business rules for the facility billing-plan system. Never hard-code
    | prices or limits in application logic — they live on the plans table and
    | remain editable by the super-admin at runtime.
    |
    | gateway               "manual" skips real charging (payment.confirm marks
    |                       the subscription active). Future: "mpesa"/"stripe".
    | trial_days            default trial length when a plan does not override.
    | grace_period_days     how long a PAST_DUE subscription stays entitled
    |                       before becoming SUSPENDED.
    | suspended_auto_expire how many days after suspension a subscription is
    |                       moved to EXPIRED (null = never auto-expired).
    | annual_discount_percent  informational default shown on the matrix.
    | bookings_enforcement  "off" shows usage only (never blocks patient care);
    |                       "block" refuses new bookings at the plan ceiling.
    | upgrade_immediate     upgrades take effect instantly (prevailing default).
    | cancel_at_period_end  cancellations keep entitlements until next billing.
    | expiry_restrictions   configured actions blocked when a subscription is
    |                       suspended/expired. new_doctors blocks invites/joins.
    |
    */

    'subscriptions' => [
        'gateway' => env('SUBSCRIPTION_GATEWAY', 'manual'),
        'trial_days' => (int) env('SUBSCRIPTION_TRIAL_DAYS', 7),
        'grace_period_days' => (int) env('SUBSCRIPTION_GRACE_PERIOD_DAYS', 3),
        'suspended_auto_expire_days' => env('SUBSCRIPTION_SUSPENDED_AUTO_EXPIRE_DAYS', 30),
        'annual_discount_percent' => (float) env('SUBSCRIPTION_ANNUAL_DISCOUNT_PERCENT', 10),
        'bookings_enforcement' => env('SUBSCRIPTION_BOOKINGS_ENFORCEMENT', 'off'),
        'upgrade_immediate' => (bool) env('SUBSCRIPTION_UPGRADE_IMMEDIATE', true),
        'cancel_at_period_end' => (bool) env('SUBSCRIPTION_CANCEL_AT_PERIOD_END', true),
        'staff_role_exclusions' => [],
        'expiry_restrictions' => [
            'new_doctors' => (bool) env('SUBSCRIPTION_EXPIRY_RESTRICT_NEW_DOCTORS', true),
            'new_staff' => (bool) env('SUBSCRIPTION_EXPIRY_RESTRICT_NEW_STAFF', true),
            'new_locations' => (bool) env('SUBSCRIPTION_EXPIRY_RESTRICT_NEW_LOCATIONS', true),
            'new_sessions' => (bool) env('SUBSCRIPTION_EXPIRY_RESTRICT_NEW_SESSIONS', false),
            'new_bookings' => (bool) env('SUBSCRIPTION_EXPIRY_RESTRICT_NEW_BOOKINGS', false),
        ],
    ],

];

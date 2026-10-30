<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'ebay' => [
        'environment' => env('EBAY_ENVIRONMENT', 'sandbox'),
        'client_id' => env('EBAY_CLIENT_ID'),
        'client_secret' => env('EBAY_CLIENT_SECRET'),
        'ru_name' => env('EBAY_RU_NAME'),
        'marketplace_id' => env('EBAY_MARKETPLACE_ID', 'EBAY_US'),
        'deletion_verification_token' => env('EBAY_DELETION_VERIFICATION_TOKEN'),
        'api_url' => env('EBAY_ENVIRONMENT', 'sandbox') === 'production' ? 'https://api.ebay.com' : 'https://api.sandbox.ebay.com',
        'auth_url' => env('EBAY_ENVIRONMENT', 'sandbox') === 'production' ? 'https://auth.ebay.com' : 'https://auth.sandbox.ebay.com',
        'web_url' => env('EBAY_ENVIRONMENT', 'sandbox') === 'production' ? 'https://www.ebay.com' : 'https://sandbox.ebay.com',
    ],

    'psa' => [
        'token' => env('PSA_API_TOKEN'),
        'url' => env('PSA_API_URL', 'https://api.psacard.com/publicapi'),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5-5'),
    ],

];

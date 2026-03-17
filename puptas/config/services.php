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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Identity Provider (IDP) OAuth2 Configuration
    |--------------------------------------------------------------------------
    */
    'idp' => [
        'base_url' => env('IDP_BASE_URL', 'https://identity-provider.isaxbsit2027.com'),
        'client_id' => env('IDP_CLIENT_ID'),
        'client_secret' => env('IDP_CLIENT_SECRET'),
        'redirect_uri' => env('IDP_REDIRECT_URI'),
    ],

    'external_api' => [
        'token' => env('EXTERNAL_API_TOKEN'),
        'daily_limit' => (int) env('EXTERNAL_API_DAILY_LIMIT', 200),
        'minute_limit' => (int) env('EXTERNAL_API_MINUTE_LIMIT', 20),
    ],

];

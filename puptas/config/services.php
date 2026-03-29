<?php

return [

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

    'idp' => [
        'base_url' => env('IDP_BASE_URL', 'https://identity-provider.isaxbsit2027.com'),
        'authorize_path' => env('IDP_AUTHORIZE_PATH', '/authorize'),
        'token_path' => env('IDP_TOKEN_PATH', '/api/v1/auth/token'),
        'user_path' => env('IDP_USER_PATH', '/api/v1/me'),
        'client_id' => env('IDP_CLIENT_ID'),
        'client_secret' => env('IDP_CLIENT_SECRET'),
        'redirect_uri' => env('IDP_REDIRECT_URI'),
        'scope' => env('IDP_SCOPE', 'openid profile email'),
    ],

    'external_api' => [
        'token' => env('EXTERNAL_API_TOKEN'),
        'second_limit' => (int) env('EXTERNAL_API_SECOND_LIMIT', 5),
        'daily_limit' => (int) env('EXTERNAL_API_DAILY_LIMIT', 200),
        'minute_limit' => (int) env('EXTERNAL_API_MINUTE_LIMIT', 20),
    ],

];

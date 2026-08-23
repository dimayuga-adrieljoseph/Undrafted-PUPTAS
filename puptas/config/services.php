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
        'key' => env('RESEND_API_KEY'),
        'webhook_secret' => env('RESEND_WEBHOOK_SECRET'),
    ],

    'idp' => [
        'base_url' => env('IDP_BASE_URL', 'https://identity-provider.isaxbsit2027.com'),
        'authorize_path' => env('IDP_AUTHORIZE_PATH', '/api/v1/auth/authorize'),
        'token_path' => env('IDP_TOKEN_PATH', '/api/v1/auth/token'),
        'user_path' => env('IDP_USER_PATH', '/api/v1/me'),
        'client_id' => env('IDP_CLIENT_ID'),
        'client_secret' => env('IDP_CLIENT_SECRET'),
        'redirect_uri' => env('IDP_REDIRECT_URI'),
        'scope' => env('IDP_SCOPE', 'openid profile email'),
        'allowed_ips' => env('IDP_ALLOWED_IPS'),
        'health_check_enabled' => (bool) env('IDP_HEALTH_CHECK_ENABLED', true),
    ],

    'deepseek' => [
        'key' => env('DEEPSEEK_API_KEY'),
    ],

    'sar' => [
        'template_filename' => env('SAR_TEMPLATE_FILENAME', 'SAR-FORM_TEMPLATE-2.pdf'),
    ],

    'external_api' => [
        'token' => env('EXTERNAL_API_TOKEN'),
        'second_limit' => (int) env('EXTERNAL_API_SECOND_LIMIT', 5),
        'daily_limit' => (int) env('EXTERNAL_API_DAILY_LIMIT', 200),
        'minute_limit' => (int) env('EXTERNAL_API_MINUTE_LIMIT', 20),
    ],

    'external_program_api' => [
        'token' => env('EXTERNAL_PROGRAM_API_TOKEN'),
        'daily_limit' => (int) env('EXTERNAL_PROGRAM_API_DAILY_LIMIT', 50),
    ],

    'external_medical_api' => [
        'token' => env('EXTERNAL_MEDICAL_API_TOKEN'),
        'second_limit' => (int) env('EXTERNAL_MEDICAL_API_SECOND_LIMIT', 5),
        'minute_limit' => (int) env('EXTERNAL_MEDICAL_API_MINUTE_LIMIT', 80),
        'daily_limit' => (int) env('EXTERNAL_MEDICAL_API_DAILY_LIMIT', 1500),
    ],

    'gemini' => [
        'key'   => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
    ],

    'openrouter' => [
        'key' => env('OPENROUTER_API_KEY'),
        'endpoint' => env('OPENROUTER_ENDPOINT', 'https://openrouter.ai/api/v1/chat/completions'),
        'model' => env('OPENROUTER_MODEL', 'google/gemini-flash-1.5'),
    ],

    'medical_webhook' => [
        'secret' => env('MEDICAL_WEBHOOK_SECRET'),
    ],

    'docling' => [
        'url'     => env('DOCLING_URL', ''),
        'api_key' => env('DOCLING_API_KEY'),
        'timeout' => (int) env('DOCLING_TIMEOUT', 60),
    ],

    'chatwoot' => [
        'access_token' => env('CHATWOOT_ACCESS_TOKEN'),
        'secret_key' => env('CHATWOOT_SECRET_KEY'),
        'base_url' => env('CHATWOOT_BASE_URL'),
        'website_token' => env('CHATWOOT_WEBSITE_TOKEN'),
        'hmac_token' => env('CHATWOOT_HMAC_TOKEN'),
    ],

    // Credentials used by the local/staging-only setup routes in routes/web.php.
    // These exist solely to seed disposable test accounts and must never be
    // echoed back in an HTTP response body.
    'test_setup' => [
        'applicant_password' => env('TEST_SETUP_APPLICANT_PASSWORD', 'password123'),
        'staff_passwords' => [
            'evaluator' => env('TEST_SETUP_EVALUATOR_PASSWORD', 'Evaluator4321!'),
            'interviewer' => env('TEST_SETUP_INTERVIEWER_PASSWORD', 'Interviewer4321!'),
            'admin' => env('TEST_SETUP_ADMIN_PASSWORD', 'UGCA4zWe1K7Sfl'),
            'registrar' => env('TEST_SETUP_REGISTRAR_PASSWORD', 'rKuFYl4jMmTI8&'),
        ],
    ],
];

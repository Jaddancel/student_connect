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

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'superadmin_data_sync' => [
        'key' => env('SUPERADMIN_DATA_SYNC_KEY'),
    ],

    'ocr' => [
        'url' => env('OCR_SERVICE_URL', 'http://ocr:5000'),
        'timeout' => (int) env('OCR_TIMEOUT', 60),
    ],

    'llm' => [
        'url' => env('LLM_SERVICE_URL', 'http://ollama:11434'),
        'timeout' => (int) env('LLM_TIMEOUT', 180),
        'model' => env('LLM_MODEL', 'qwen3.5:9b-q4_K_M'),
    ],

    // Google OAuth for the admin/officer "Sign in with Google" pre-fill. See
    // GOOGLE-AUTH-SETUP.md. Left blank, the feature is inert (button errors gracefully).
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

];

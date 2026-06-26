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

    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://ollama:11434'),
        'model' => env('OLLAMA_MODEL', 'phi4-mini'),
        'timeout' => (int) env('OLLAMA_TIMEOUT', 120),
        // Context window for the small model — long forms truncate below this.
        'num_ctx' => (int) env('OLLAMA_NUM_CTX', 8192),
        // Keep the model resident so cold-start load doesn't blow the timeout.
        'keep_alive' => env('OLLAMA_KEEP_ALIVE', '10m'),
        // Kill-switch: false = pure deterministic extraction, no LLM refinement.
        'refine' => (bool) env('OLLAMA_REFINE_FIELDS', true),
    ],

];

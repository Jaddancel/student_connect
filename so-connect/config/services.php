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

    // Host-native Ollama vision model (qwen2.5vl:3b) dedicated to reading
    // handwritten scans of printed request forms. This is separate from the
    // Gemini chat assistant and the PaddleOCR ID/waiver scanner. The host must
    // run `ollama serve` with the model pulled; from inside Sail the daemon is
    // reached via host.docker.internal.
    'document_vision' => [
        'provider' => env('DOCUMENT_VISION_PROVIDER', 'ollama'),
        'url' => env('DOCUMENT_VISION_URL', 'http://host.docker.internal:11434'),
        'model' => env('DOCUMENT_VISION_MODEL', 'qwen2.5vl:3b'),
        'timeout' => (int) env('DOCUMENT_VISION_TIMEOUT', 180),
        'confidence_threshold' => (float) env('DOCUMENT_VISION_CONFIDENCE_THRESHOLD', 0.55),
        'max_image_edge' => (int) env('DOCUMENT_VISION_MAX_IMAGE_EDGE', 1600),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'url' => env('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 30),
        'model' => env('GEMINI_MODEL', 'gemini-3.8-flash'),
    ],

    // Google OAuth for the admin/officer "Sign in with Google" pre-fill. See
    // GOOGLE-AUTH-SETUP.md. Left blank, the feature is inert (button errors gracefully).
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

];

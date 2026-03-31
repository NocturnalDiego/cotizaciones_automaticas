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

    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
        'model' => env('OLLAMA_MODEL', 'llama3:latest'),
        'timeout' => (int) env('OLLAMA_TIMEOUT', 120),
        'system_prompt' => env('OLLAMA_SYSTEM_PROMPT', 'Eres un asistente en espanol para apoyar cotizaciones empresariales.'),
    ],

    'telegram' => [
        'base_url' => env('TELEGRAM_BASE_URL', 'https://api.telegram.org'),
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'allowed_chat_ids' => array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            explode(',', (string) env('TELEGRAM_ALLOWED_CHAT_IDS', ''))
        ))),
        'polling_timeout' => (int) env('TELEGRAM_POLLING_TIMEOUT', 25),
    ],

];

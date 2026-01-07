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
        'bot_token' => env('SLACK_BOT_TOKEN', env('SLACK_BOT_USER_OAUTH_TOKEN')),
        'webhook_url' => env('SLACK_WEBHOOK_URL'),
        'default_channel' => env('SLACK_DEFAULT_CHANNEL', '#parts-alerts'),
        'rate_limit' => [
            'enabled' => env('SLACK_RATE_LIMIT', true),
            'requests_per_second' => (int) env('SLACK_RATE_LIMIT_RPS', 1),
        ],
    ],

    'google' => [
        'key_file_path' => env('GOOGLE_APPLICATION_CREDENTIALS'),
        'drive' => [
            'root_folder_id' => env('GOOGLE_DRIVE_ROOT_FOLDER_ID'),
        ],
        'sheets' => [
            'default_spreadsheet_id' => env('GOOGLE_SHEETS_DEFAULT_ID'),
        ],
        'gmail' => [
            'shared_mailbox' => env('GOOGLE_GMAIL_SHARED_MAILBOX'),
            'attachments_folder_id' => env('GOOGLE_GMAIL_ATTACHMENTS_FOLDER_ID'),
        ],
    ],

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from' => env('TWILIO_FROM'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
        'max_tokens' => (int) env('GEMINI_MAX_TOKENS', 8192),
        'temperature' => (float) env('GEMINI_TEMPERATURE', 0.7),
    ],

    'python' => [
        'path' => env('PYTHON_PATH', '/usr/bin/python3'),
        'timeout' => (int) env('PYTHON_TIMEOUT', 120),
        'max_rows' => (int) env('PYTHON_CSV_MAX_ROWS', 5000),
    ],

];

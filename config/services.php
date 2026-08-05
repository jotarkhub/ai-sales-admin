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

    // Shared secret HMAC untuk memverifikasi request dari Google Apps Script.
    // Lihat App\Http\Middleware\VerifyLeadIntakeSignature.
    'lead_intake' => [
        'secret' => env('LEAD_INTAKE_SECRET'),
    ],

    // WhatsApp Business Cloud API (Fase 3). 'provider' HARUS 'fake' kecuali di production
    // dengan kredensial asli sudah diisi — ditegakkan App\Support\ProviderGuard saat boot.
    'whatsapp' => [
        'provider' => env('WHATSAPP_PROVIDER', 'fake'),
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v20.0'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    ],

];

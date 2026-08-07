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

    // WhatsApp Business Cloud API. 'provider' HARUS 'fake' kecuali di production dengan
    // kredensial asli sudah diisi — ditegakkan App\Support\ProviderGuard saat boot.
    //
    // SEJAK FASE 8b/8c: token, phone_number_id, verify_token, app_secret TIDAK LAGI di sini —
    // tiap bisnis (klien) punya App Meta & nomor sendiri, disimpan terenkripsi per bisnis di
    // tabel integration_credentials lewat panel platform owner. Lihat
    // App\Services\WhatsApp\WhatsAppCredentialResolver. Yang tersisa di sini cuma hal yang
    // benar-benar sama untuk semua bisnis: saklar fake/nyata & versi Graph API.
    'whatsapp' => [
        'provider' => env('WHATSAPP_PROVIDER', 'fake'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v20.0'),
    ],

    // OpenAI Conversation Engine (Fase 4). Sama seperti whatsapp di atas — 'provider' HARUS
    // 'fake' kecuali production dengan kredensial asli, ditegakkan App\Support\ProviderGuard.
    'ai' => [
        'provider' => env('AI_PROVIDER', 'fake'),
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

];

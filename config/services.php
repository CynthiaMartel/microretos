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

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
    ],

    'cloudinary' => [
        'cloud_name'    => env('CLOUDINARY_CLOUD_NAME'),
        'api_key'       => env('CLOUDINARY_API_KEY'),
        'api_secret'    => env('CLOUDINARY_API_SECRET'),
        'folder'        => env('CLOUDINARY_FOLDER', 'dualab/recursos'),
        'upload_max_mb' => (int) env('UPLOAD_MAX_SIZE_MB', 20),
    ],

    'empresas' => [
        'access_password' => env('EMPRESAS_ACCESS_PASSWORD'),
    ],

    'admin' => [
        'email'    => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    // Contraseñas de las cuentas de prueba del DatabaseSeeder. Sin valor, el seeder
    // no crea esas cuentas (nunca se usan contraseñas escritas en el código).
    'seed' => [
        'docente_password' => env('SEED_DOCENTE_PASSWORD'),
        'empresa_password' => env('SEED_EMPRESA_PASSWORD'),
    ],

];

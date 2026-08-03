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

    /*
    | SMS and WhatsApp providers are optional. Leave driver/api_key empty to keep
    | notification logs in pending status without failing clinical workflows.
    */
    'sms' => [
        'driver' => env('SMS_DRIVER'),
        'api_key' => env('SMS_API_KEY'),
        'api_secret' => env('SMS_API_SECRET'),
        'from' => env('SMS_FROM'),
        'endpoint' => env('SMS_ENDPOINT'),
    ],

    'whatsapp' => [
        'driver' => env('WHATSAPP_DRIVER'),
        'api_key' => env('WHATSAPP_API_KEY'),
        'api_secret' => env('WHATSAPP_API_SECRET'),
        'from' => env('WHATSAPP_FROM'),
        'endpoint' => env('WHATSAPP_ENDPOINT'),
    ],

];

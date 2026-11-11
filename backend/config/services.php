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

    'photon' => [
        'url' => env('PHOTON_API_URL', 'https://photon.komoot.io/api'),
        'reverse_url' => env('PHOTON_REVERSE_API_URL', 'https://photon.komoot.io/reverse'),
        'reverse_lookups_per_minute' => env('PHOTON_REVERSE_LOOKUPS_PER_MINUTE', 60),
    ],

    'routing' => [
        'url' => env('ROUTING_API_URL', 'https://api.heigit.org/openrouteservice/v2/directions/driving-car'),
        'api_key' => env('ROUTING_API_KEY'),
        'timeout' => (int) env('ROUTING_TIMEOUT', 10),
    ],
];

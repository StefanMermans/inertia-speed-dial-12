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

    'anilist' => [
        'client_id' => env('ANILIST_CLIENT_ID'),
        'client_secret' => env('ANILIST_CLIENT_SECRET'),
    ],

    'tmdb' => [
        'api_key' => env('TMDB_API_KEY'),
        'api_read_access_token' => env('TMDB_API_READ_ACCESS_TOKEN'),
        'base_url' => 'https://api.themoviedb.org',
    ],

    'trakt' => [
        'client_id' => env('TRAKT_CLIENT_ID'),
        'client_secret' => env('TRAKT_CLIENT_SECRET'),
        'base_url' => 'https://api.trakt.tv',
    ],

];

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

    'vkid' => [
        'client_id'     => env('VKID_CLIENT_ID'),
        'client_secret' => env('VKID_CLIENT_SECRET'),
        'redirect'      => env('VKID_REDIRECT_URI'),
        'scopes'        => env('VKID_SCOPES', ['email']),
        'pkce_ttl'      => env('VKID_PKCE_TTL', 10),
        'cache_store'   => env('VKID_CACHE_STORE', 'redis'),
        'cache_prefix'  => env('VKID_CACHE_PREFIX', 'socialite:vkid:pkce:'),
        'api_version'   => env('VKID_API_VERSION', '5.199'),
    ],

];

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

    /**
     * VK ID (OAuth 2.1 + PKCE) provider configuration.
     *
     * client_id      — Your VK ID application ID (from the developer dashboard at id.vk.ru).
     * client_secret  — Application secret key. Store this securely in your .env file.
     * redirect       — Full callback URL that exactly matches the redirect URI in VK settings.
     *
     * scopes         — OAuth scopes requested from the user. Default: ['email'].
     *                  Business accounts may also request 'phone' if permission is granted.
     *                  ⚠️ When using .env, the value is a string — it’s safer to define
     *                  the array directly here in the config file.
     *
     * pkce_ttl       — Lifetime (in minutes) of the PKCE code_verifier.
     *                  If the user waits longer than this on the VK permission screen,
     *                  the authorization will expire.
     *
     * cache_store    — Cache store used for PKCE verifier storage (e.g., 'redis').
     *                  Must exist in config/cache.php. If left empty, the default store is used.
     *
     * cache_prefix   — Prefix for PKCE cache keys.
     *
     * api_version    — VK API version used for requests (default: '5.199').
     */
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

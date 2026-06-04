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
    |--------------------------------------------------------------------------
    | Taqat SSO (OIDC Identity Provider)
    |--------------------------------------------------------------------------
    |
    | "Sign in with Taqat" — Authorization Code + PKCE flow against the Taqat
    | identity provider. The client_secret is a real secret: keep it only in
    | the environment, never in source control. Endpoints are discovered from
    | {issuer}/.well-known/openid-configuration at runtime.
    |
    */
    'taqat_sso' => [
        'issuer' => env('TAQAT_SSO_ISSUER'),
        'client_id' => env('TAQAT_SSO_CLIENT_ID', 'taqat-space'),
        'client_secret' => env('TAQAT_SSO_CLIENT_SECRET'),
        'redirect_uri' => env('TAQAT_SSO_REDIRECT_URI'),
    ],

];

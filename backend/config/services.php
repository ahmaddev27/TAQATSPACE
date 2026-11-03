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
        // Where the IdP returns the browser after RP-initiated single logout.
        // Must be pre-registered with the provider. Defaults (in the service)
        // to the frontend login page when left unset.
        'post_logout_redirect_uri' => env('TAQAT_SSO_POST_LOGOUT_REDIRECT_URI'),
        // Explicit IdP logout/end-session URL. Set this when the provider's
        // discovery document does NOT advertise an end_session_endpoint (e.g.
        // Laravel Passport), so single logout still works.
        'end_session_endpoint' => env('TAQAT_SSO_END_SESSION_URL'),
        // When the IdP asserts MFA (`amr` contains "mfa"), skip the local 2FA
        // challenge. Pending confirmation from the identity provider.
        'trust_mfa_claim' => env('TAQAT_SSO_TRUST_MFA', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Firebase (FCM push + Firestore custom tokens)
    |--------------------------------------------------------------------------
    |
    | Service-account credentials for the realtime milestone. Every field is
    | OPTIONAL: when the service account is absent the FirebaseService reports
    | "not configured" and all push/token operations become safe no-ops — the
    | app runs normally without Firebase wired up.
    |
    | Provide the three service-account fields directly, OR point
    | FIREBASE_CREDENTIALS at a service-account JSON file (its values fill any
    | field left blank). The private key is a real secret: keep it in the
    | environment only, never in source control. In .env the multi-line key is
    | stored on one line with literal "\n" sequences (wrap it in double quotes).
    |
    */
    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'client_email' => env('FIREBASE_CLIENT_EMAIL'),
        'private_key' => env('FIREBASE_PRIVATE_KEY'),
        // Optional absolute/relative path to a service-account JSON file. Used
        // to fill any of the three fields above that are left unset.
        'credentials' => env('FIREBASE_CREDENTIALS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | MikroTik routers (PUSH model)
    |--------------------------------------------------------------------------
    |
    | Public base URL routers call (install.rsc, scripts, sync). Pinned to the
    | fixed sync domain so a later infrastructure move never needs a reinstall;
    | override per environment (e.g. staging).
    |
    */
    'router' => [
        'sync_url' => env('ROUTER_SYNC_URL', 'https://sync.taqat.space'),
    ],

];

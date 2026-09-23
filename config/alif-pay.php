<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | "test"       -> https://test-web.alif.tj
    | "production" -> https://web.alif.tj
    |
    */
    'environment' => env('ALIF_PAY_ENVIRONMENT', 'test'),

    /*
    |--------------------------------------------------------------------------
    | Terminal credentials
    |--------------------------------------------------------------------------
    |
    | Issued by Alif when your terminal is provisioned. The terminal id is sent
    | as the `key` field; the password is used (never sent) to derive the HMAC
    | SHA256 authorization token.
    |
    */
    'terminal_id' => env('ALIF_PAY_TERMINAL_ID', ''),
    'terminal_password' => env('ALIF_PAY_TERMINAL_PASSWORD', ''),

    /*
    |--------------------------------------------------------------------------
    | Default URLs
    |--------------------------------------------------------------------------
    |
    | Used when a request does not specify its own. callback_url receives the
    | server-to-server webhook; return_url is where the customer's browser is
    | sent after the payment form.
    |
    */
    'callback_url' => env('ALIF_PAY_CALLBACK_URL'),
    'return_url' => env('ALIF_PAY_RETURN_URL'),

    /*
    |--------------------------------------------------------------------------
    | HTTP timeout (seconds)
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('ALIF_PAY_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Provider endpoint credentials
    |--------------------------------------------------------------------------
    |
    | Only for the provider protocol, where Alif calls your server with check /
    | pay / status requests and authorizes with HTTP Basic. Leave empty if you
    | only accept payments.
    |
    */
    'provider_login' => env('ALIF_PAY_PROVIDER_LOGIN'),
    'provider_password' => env('ALIF_PAY_PROVIDER_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Base URL override (advanced)
    |--------------------------------------------------------------------------
    |
    | Leave empty to use the environment default above.
    |
    */
    'base_url' => env('ALIF_PAY_BASE_URL'),
];

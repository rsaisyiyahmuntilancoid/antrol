<?php

return [
    'api_url' => env('AUTH_API_URL', 'https://backend-idrg.rsam.co.id'),
    'login_rate_limit' => env('AUTH_LOGIN_RATE_LIMIT', 5),    // per minute per IP
    'api_rate_limit' => env('AUTH_API_RATE_LIMIT', 120),      // per minute per IP

    /*
    |--------------------------------------------------------------------------
    | Supervisor (SPV) Local Bypass Credentials
    |--------------------------------------------------------------------------
    |
    | Kredensial untuk bypass otentikasi supervisor secara internal tanpa
    | memanggil server SSO eksternal. Diatur melalui environment variables (.env).
    |
    */
    'spv_username' => env('AUTH_SPV_USERNAME', 'spv'),
    'spv_password' => env('AUTH_SPV_PASSWORD'),
    'spv_token' => env('AUTH_SPV_TOKEN'),
];

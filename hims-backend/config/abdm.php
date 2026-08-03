<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ABDM M1 gateway
    |--------------------------------------------------------------------------
    |
    | Sandbox-ready gate. When enabled=false, M1 APIs return 503.
    | When enabled=true without client credentials, the simulated provider is used
    | (documented DEMO OTP) so hospital workflows can be exercised offline.
    | When credentials are set, HTTP calls go to the configured ABDM gateway base URL.
    |
    */
    'enabled' => (bool) env('ABDM_ENABLED', false),
    'mode' => env('ABDM_MODE', 'auto'), // auto | http | simulated
    'base_url' => rtrim((string) env('ABDM_BASE_URL', 'https://dev.abdm.gov.in'), '/'),
    'client_id' => env('ABDM_CLIENT_ID'),
    'client_secret' => env('ABDM_CLIENT_SECRET'),
    'cm_id' => env('ABDM_CM_ID', 'sbx'),
    'facility_id' => env('ABDM_FACILITY_ID'),
    'timeout_seconds' => (int) env('ABDM_TIMEOUT_SECONDS', 20),
    'demo_otp' => env('ABDM_DEMO_OTP', '123456'),
];

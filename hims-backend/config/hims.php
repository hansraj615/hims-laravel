<?php

return [
    'frontend_url' => env('FRONTEND_URL', env('APP_URL', 'http://localhost:5173')),
    'auth' => [
        'demo_otp_enabled' => (bool) env('DEMO_OTP_ENABLED', false),
        'demo_otp_codes' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('DEMO_OTP_CODES', env('DEMO_OTP_CODE', '')))
        ))),
        'otp_ttl_minutes' => (int) env('OTP_TTL_MINUTES', 5),
        'otp_length' => (int) env('OTP_LENGTH', 6),
        'max_login_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
        'lockout_minutes' => (int) env('LOGIN_LOCKOUT_MINUTES', 15),
    ],
];

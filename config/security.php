<?php

return [
    'admin_email' => env('ADMIN_EMAIL'),
    'admin_password' => env('ADMIN_PASSWORD'),
    'admin_name' => env('ADMIN_NAME', 'Administrator'),
    'password_min_length' => max(10, (int) env('PASSWORD_MIN_LENGTH', 10)),
    'check_compromised' => (bool) env('PASSWORD_CHECK_COMPROMISED', true),
    'invitation_expiry_minutes' => max(1, (int) env('INVITATION_EXPIRY_MINUTES', 1440)),
];

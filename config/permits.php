<?php

return [
    // The cabinet may adjust the horizon and time to its own procedures.
    'reminder_days' => (int) env('PERMIT_REMINDER_DAYS', 7),
    'reminder_time' => env('PERMIT_REMINDER_TIME', '08:00'),
    'timezone' => env('PERMIT_TIMEZONE', 'Africa/Tunis'),
];

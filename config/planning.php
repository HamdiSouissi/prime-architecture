<?php

return [
    'timezone' => env('PLANNING_TIMEZONE', 'Africa/Tunis'),
    'weekly_capacity_hours' => (float) env('PLANNING_WEEKLY_CAPACITY_HOURS', 40),
    'gantt_max_items' => (int) env('PLANNING_GANTT_MAX_ITEMS', 500),
    'daily_reminder_time' => env('PLANNING_REMINDER_TIME', '08:00'),
    'milestone_reminder_days' => (int) env('PLANNING_MILESTONE_REMINDER_DAYS', 7),
];

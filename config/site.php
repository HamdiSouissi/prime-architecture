<?php

return [
    // Private disk shared with the document library; nothing is web-accessible.
    'disk' => env('SITE_PHOTO_DISK', 'documents'),
    'photo_max_kb' => (int) env('SITE_PHOTO_MAX_KB', 8192),
    // Stored photos are re-encoded, which also strips EXIF (including GPS).
    'photo_max_dimension' => 1600,
    'photo_quality' => 82,
    'thumbnail_dimension' => 400,
    'thumbnail_quality' => 78,
    'per_page' => 20,
    // Indicative advance attributed to each task status; a convention, not a rule.
    'progress_factors' => [
        'todo' => 0.0,
        'in_progress' => 0.5,
        'blocked' => 0.25,
        'in_review' => 0.75,
        'done' => 1.0,
    ],
];

<?php

return [
    'initial_status' => env('PROJECT_INITIAL_STATUS', 'sketch'),
    // Both placeholders are mandatory; sequence padding is a minimum, not a limit.
    'reference_format' => env('PROJECT_REFERENCE_FORMAT', '{year}-{sequence}'),
    'reference_padding' => (int) env('PROJECT_REFERENCE_PADDING', 3),
    'reference_timezone' => env('PROJECT_REFERENCE_TIMEZONE', 'Africa/Tunis'),
    // Syntactic check only. Confirm this structure with the relevant authority.
    'tax_id_pattern' => env('CLIENT_TAX_ID_PATTERN', '/^[0-9]{7}\/[A-Z]\/[A-Z]\/[A-Z]\/[0-9]{3}$/D'),
    'active_statuses' => ['sketch', 'preliminary_design', 'detailed_design', 'permit', 'tender', 'construction', 'handover'],
];

<?php

// Permissions cover the phase 2 shell only; business permissions belong to later phases.
return [
    'roles' => [
        'admin' => ['dashboard.internal.view', 'users.manage', 'activity.view'],
        'architect' => ['dashboard.internal.view'],
        'collaborator' => ['dashboard.internal.view'],
        'client' => ['dashboard.client.view'],
        'contractor' => ['dashboard.client.view'],
    ],
];

<?php

return [
    'roles' => [
        'admin' => ['dashboard.internal.view', 'users.manage', 'activity.view', 'clients.view', 'clients.create', 'clients.update', 'clients.archive', 'clients.restore', 'projects.view', 'projects.create', 'projects.update', 'projects.archive', 'projects.restore', 'projects.change_status', 'projects.manage_team', 'projects.notes'],
        'architect' => ['dashboard.internal.view', 'clients.view', 'clients.create', 'clients.update', 'clients.archive', 'clients.restore', 'projects.view', 'projects.create', 'projects.update', 'projects.archive', 'projects.restore', 'projects.change_status', 'projects.manage_team', 'projects.notes'],
        'collaborator' => ['dashboard.internal.view', 'projects.view', 'projects.update', 'projects.change_status', 'projects.notes'],
        'client' => ['dashboard.client.view', 'projects.view'],
        'contractor' => ['dashboard.client.view'],
    ],
];

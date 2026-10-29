<?php

return [
    'roles' => [
        'admin' => ['dashboard.internal.view', 'users.manage', 'activity.view', 'clients.view', 'clients.create', 'clients.update', 'clients.archive', 'clients.restore', 'projects.view', 'projects.create', 'projects.update', 'projects.archive', 'projects.restore', 'projects.change_status', 'projects.manage_team', 'projects.notes', 'documents.view', 'documents.upload', 'documents.update', 'documents.delete', 'documents.restore', 'documents.download', 'documents.manage_categories', 'tasks.view', 'tasks.create', 'tasks.update', 'tasks.assign', 'tasks.delete', 'milestones.manage', 'planning.manage_templates', 'planning.manage_calendar', 'planning.view_workload'],
        'architect' => ['dashboard.internal.view', 'clients.view', 'clients.create', 'clients.update', 'clients.archive', 'clients.restore', 'projects.view', 'projects.create', 'projects.update', 'projects.archive', 'projects.restore', 'projects.change_status', 'projects.manage_team', 'projects.notes', 'documents.view', 'documents.upload', 'documents.update', 'documents.delete', 'documents.download', 'tasks.view', 'tasks.create', 'tasks.update', 'tasks.assign', 'tasks.delete', 'milestones.manage', 'planning.manage_templates', 'planning.view_workload'],
        'collaborator' => ['dashboard.internal.view', 'projects.view', 'projects.update', 'projects.change_status', 'projects.notes', 'documents.view', 'documents.upload', 'documents.update', 'documents.download', 'tasks.view', 'tasks.create', 'tasks.update', 'tasks.assign'],
        'client' => ['dashboard.client.view', 'projects.view', 'documents.view', 'documents.download', 'tasks.view'],
        'contractor' => ['dashboard.client.view'],
    ],
];

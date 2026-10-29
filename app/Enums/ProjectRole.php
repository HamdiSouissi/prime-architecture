<?php

namespace App\Enums;

enum ProjectRole: string
{
    case Lead = 'lead';
    case Collaborator = 'collaborator';
    case Viewer = 'viewer';
}

<?php

namespace App\Enums;

enum SnagSeverity: string
{
    case Minor = 'minor';
    case Major = 'major';
    case Critical = 'critical';
}

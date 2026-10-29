<?php

namespace App\Enums;

enum FeeMode: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
}

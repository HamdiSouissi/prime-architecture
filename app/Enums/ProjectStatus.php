<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Sketch = 'sketch';
    case PreliminaryDesign = 'preliminary_design';
    case DetailedDesign = 'detailed_design';
    case Permit = 'permit';
    case Tender = 'tender';
    case Construction = 'construction';
    case Handover = 'handover';
    case Completed = 'completed';
    case OnHold = 'on_hold';
    case Cancelled = 'cancelled';
}

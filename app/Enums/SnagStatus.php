<?php

namespace App\Enums;

enum SnagStatus: string
{
    case Open = 'open';
    case Fixed = 'fixed';
    case Validated = 'validated';

    /** Only a fixed item can be validated, and a validated item is final. */
    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Open => $target === self::Fixed,
            self::Fixed => $target === self::Validated,
            self::Validated => false,
        };
    }
}

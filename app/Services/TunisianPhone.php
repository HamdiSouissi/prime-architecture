<?php

namespace App\Services;

class TunisianPhone
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $compact = preg_replace('/[\s().-]+/u', '', trim($value));
        if (! is_string($compact) || ! preg_match('/^(?:\+216)?([0-9]{8})$/D', $compact, $matches)) {
            return null;
        }

        return '+216'.$matches[1];
    }
}

<?php

namespace App\Services;

use InvalidArgumentException;

/** Decimal-only arithmetic; never convert monetary values or rates to float. */
class BillingDecimal
{
    public const MAX_AMOUNT = '999999999999.999';

    public static function validate(mixed $value, int $scale, string $max): string
    {
        if (! is_string($value) || strlen($value) > 32 || ! preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,'.$scale.'})?$/D', $value) || bccomp($value, $max, $scale) > 0) {
            throw new InvalidArgumentException('Invalid decimal value.');
        }

        return $value;
    }

    /** Round non-negative numbers half-up to a millime. */
    public static function round(string $value): string
    {
        if (bccomp($value, '0', 12) < 0) {
            throw new InvalidArgumentException('Negative values are not supported.');
        }
        $rounded = bcadd($value, '0.0005', 3);
        if (bccomp($rounded, self::MAX_AMOUNT, 3) > 0) {
            throw new InvalidArgumentException('Amount exceeds storage precision.');
        }

        return $rounded;
    }
}

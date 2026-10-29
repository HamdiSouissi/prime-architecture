<?php

namespace App\Services;

use InvalidArgumentException;

class FeeCalculator
{
    public function calculate(string $mode, string $value, ?string $worksAmount): string
    {
        if ($mode === 'fixed') {
            return BillingDecimal::round(BillingDecimal::validate($value, 3, BillingDecimal::MAX_AMOUNT));
        }
        if ($mode !== 'percentage' || $worksAmount === null) {
            throw new InvalidArgumentException('Invalid fee mode or works amount.');
        }
        $rate = BillingDecimal::validate($value, 4, '100');
        $base = BillingDecimal::validate($worksAmount, 3, BillingDecimal::MAX_AMOUNT);

        return BillingDecimal::round(bcdiv(bcmul($base, $rate, 12), '100', 12));
    }
}

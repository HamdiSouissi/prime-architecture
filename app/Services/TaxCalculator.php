<?php

namespace App\Services;

use InvalidArgumentException;

class TaxCalculator
{
    /**
     * Values must be decimal strings. Discounts are applied per line, then a
     * proportional document discount; each line and each VAT-rate group is
     * rounded half-up to 0.001 TND. Totals are exact decimal strings.
     *
     * @param list<array{quantity: string, unit_price: string, discount_percent: string, tax_rate: string}> $lines
     * @return array<string, mixed>
     */
    public function calculate(array $lines, string $discountPercent, string $stamp, string $withholdingRate, string $withholdingBase, bool $includeStamp): array
    {
        if (count($lines) > 500 || ! in_array($withholdingBase, ['ht', 'ttc'], true)) {
            throw new InvalidArgumentException('Invalid calculation parameters.');
        }
        $discountPercent = BillingDecimal::validate($discountPercent, 4, '100');
        $stamp = BillingDecimal::validate($stamp, 3, BillingDecimal::MAX_AMOUNT);
        $withholdingRate = BillingDecimal::validate($withholdingRate, 4, '100');
        $gross = '0.000';
        $totalHt = '0.000';
        $groups = [];
        $resultLines = [];
        foreach ($lines as $line) {
            $quantity = BillingDecimal::validate($line['quantity'] ?? null, 6, '999999999.999999');
            $price = BillingDecimal::validate($line['unit_price'] ?? null, 3, BillingDecimal::MAX_AMOUNT);
            $discount = BillingDecimal::validate($line['discount_percent'] ?? null, 4, '100');
            $rate = BillingDecimal::validate($line['tax_rate'] ?? null, 4, '100');
            $raw = BillingDecimal::round(bcmul($quantity, $price, 12));
            $afterLineDiscount = BillingDecimal::round(bcdiv(bcmul($raw, bcsub('100', $discount, 4), 12), '100', 12));
            $net = BillingDecimal::round(bcdiv(bcmul($afterLineDiscount, bcsub('100', $discountPercent, 4), 12), '100', 12));
            $gross = BillingDecimal::round(bcadd($gross, $raw, 12));
            $totalHt = BillingDecimal::round(bcadd($totalHt, $net, 12));
            $key = bcadd($rate, '0', 4);
            $groups[$key] = bcadd($groups[$key] ?? '0.000', $net, 3);
            $resultLines[] = ['gross' => $raw, 'total_ht' => $net, 'tax_rate' => $key];
        }
        ksort($groups, SORT_STRING);
        $taxes = [];
        $totalTax = '0.000';
        foreach ($groups as $rate => $base) {
            $tax = BillingDecimal::round(bcdiv(bcmul($base, (string) $rate, 12), '100', 12));
            $taxes[] = ['rate' => (string) $rate, 'base' => $base, 'amount' => $tax];
            $totalTax = BillingDecimal::round(bcadd($totalTax, $tax, 12));
        }
        $beforeStamp = BillingDecimal::round(bcadd($totalHt, $totalTax, 12));
        $totalTtc = BillingDecimal::round(bcadd($beforeStamp, $stamp, 12));
        $base = $withholdingBase === 'ht' ? $totalHt : $beforeStamp;
        if ($includeStamp) {
            $base = BillingDecimal::round(bcadd($base, $stamp, 12));
        }
        $withholding = BillingDecimal::round(bcdiv(bcmul($base, $withholdingRate, 12), '100', 12));
        if (bccomp($withholding, $totalTtc, 3) > 0) {
            throw new InvalidArgumentException('Withholding exceeds total.');
        }

        return ['lines' => $resultLines, 'gross' => $gross, 'discount' => bcsub($gross, $totalHt, 3), 'total_ht' => $totalHt, 'taxes' => $taxes, 'total_tax' => $totalTax, 'stamp' => bcadd($stamp, '0', 3), 'withholding_base' => $base, 'withholding' => $withholding, 'total_ttc' => $totalTtc, 'net_due' => bcsub($totalTtc, $withholding, 3)];
    }
}

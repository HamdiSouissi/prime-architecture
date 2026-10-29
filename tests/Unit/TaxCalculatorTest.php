<?php

namespace Tests\Unit;

use App\Services\FeeCalculator;
use App\Services\TaxCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TaxCalculatorTest extends TestCase
{
    public function test_multi_rate_discounts_stamp_and_withholding_are_exact(): void
    {
        $lines = [
            ['quantity' => '1.500000', 'unit_price' => '100.000', 'discount_percent' => '10.0000', 'tax_rate' => '19.0000'],
            ['quantity' => '1', 'unit_price' => '10.000', 'discount_percent' => '0', 'tax_rate' => '7.0000'],
        ];
        $result = (new TaxCalculator)->calculate($lines, '0', '1.000', '1.0000', 'ht', false);
        $this->assertSame('160.000', $result['gross']);
        $this->assertSame('15.000', $result['discount']);
        $this->assertSame('145.000', $result['total_ht']);
        $this->assertSame('26.350', $result['total_tax']);
        $this->assertSame('1.450', $result['withholding']);
        $this->assertSame('172.350', $result['total_ttc']);
        $this->assertSame('170.900', $result['net_due']);
        $this->assertCount(2, $result['taxes']);
    }

    public function test_half_millimes_round_up_and_document_discount_is_per_line(): void
    {
        $line = ['quantity' => '1', 'unit_price' => '0.005', 'discount_percent' => '0', 'tax_rate' => '10'];
        $result = (new TaxCalculator)->calculate([$line], '0', '0', '0', 'ttc', false);
        $this->assertSame('0.001', $result['total_tax']);
        $this->assertSame('0.006', $result['total_ttc']);
        $result = (new TaxCalculator)->calculate([$line], '10', '0', '0', 'ht', false);
        $this->assertSame('0.005', $result['total_ht']);
    }

    public function test_empty_zero_and_withholding_base_with_stamp(): void
    {
        $calculator = new TaxCalculator;
        $this->assertSame('0.000', $calculator->calculate([], '0', '0', '0', 'ht', false)['net_due']);
        $line = ['quantity' => '1', 'unit_price' => '100.000', 'discount_percent' => '0', 'tax_rate' => '19'];
        $result = $calculator->calculate([$line], '0', '1.000', '10', 'ttc', true);
        $this->assertSame('120.000', $result['withholding_base']);
        $this->assertSame('12.000', $result['withholding']);
        $this->assertSame('108.000', $result['net_due']);
    }

    public function test_rejects_floats_invalid_rates_and_overflow(): void
    {
        $calculator = new TaxCalculator;
        foreach ([
            ['quantity' => 1.0, 'unit_price' => '1', 'discount_percent' => '0', 'tax_rate' => '19'],
            ['quantity' => '1', 'unit_price' => '1', 'discount_percent' => '101', 'tax_rate' => '19'],
            ['quantity' => '2', 'unit_price' => '999999999999.999', 'discount_percent' => '0', 'tax_rate' => '0'],
        ] as $line) {
            try {
                $calculator->calculate([$line], '0', '0', '0', 'ht', false);
                $this->fail('Invalid value accepted.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_tax_is_rounded_once_per_rate_group(): void
    {
        $line = ['quantity' => '1', 'unit_price' => '0.005', 'discount_percent' => '0', 'tax_rate' => '10'];
        $result = (new TaxCalculator)->calculate([$line, $line], '0', '0', '0', 'ht', false);
        $this->assertSame('0.010', $result['total_ht']);
        $this->assertSame('0.001', $result['total_tax']);
        $this->assertSame('0.011', $result['total_ttc']);
    }

    public function test_maximum_storage_amount_and_zero_fee_are_exact(): void
    {
        $line = ['quantity' => '1', 'unit_price' => '999999999999.999', 'discount_percent' => '0', 'tax_rate' => '0'];
        $result = (new TaxCalculator)->calculate([$line], '0', '0', '0', 'ht', false);
        $this->assertSame('999999999999.999', $result['total_ttc']);
        $this->assertSame('0.000', (new FeeCalculator)->calculate('percentage', '0', '999999999999.999'));
    }

    public function test_rejects_excessive_input_lengths(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new FeeCalculator)->calculate('fixed', str_repeat('9', 10000), null);
    }

    public function test_fees_support_fixed_and_percentage_modes(): void
    {
        $calculator = new FeeCalculator;
        $this->assertSame('1250.000', $calculator->calculate('fixed', '1250.000', null));
        $this->assertSame('1234.568', $calculator->calculate('percentage', '2.5', '49382.700'));
        $this->expectException(InvalidArgumentException::class);
        $calculator->calculate('percentage', '101', '100.000');
    }
}

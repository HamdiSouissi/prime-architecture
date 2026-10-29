<?php

namespace Tests\Feature;

use App\Services\DocumentNumberGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class BillingNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_counter_rolls_back_and_is_independent_by_year_and_type(): void
    {
        DB::table('billing_number_formats')->insert([
            ['document_type' => 'quote', 'effective_from' => '2020-01-01', 'format' => 'DEV-{year}-{sequence}', 'padding' => 4],
            ['document_type' => 'invoice', 'effective_from' => '2020-01-01', 'format' => 'FAC-{year}-{sequence}', 'padding' => 4],
        ]);
        $generator = app(DocumentNumberGenerator::class);
        $date = CarbonImmutable::parse('2026-11-02');
        $this->assertSame('DEV-2026-0001', DB::transaction(fn () => $generator->next('quote', $date)));
        try {
            DB::transaction(function () use ($generator, $date) {
                $generator->next('quote', $date);
                throw new RuntimeException('Abort issuance');
            });
        } catch (RuntimeException) {
            // The counter must be rolled back with the failed issuance.
        }
        $this->assertSame('DEV-2026-0002', DB::transaction(fn () => $generator->next('quote', $date)));
        $this->assertSame('DEV-2027-0001', DB::transaction(fn () => $generator->next('quote', $date->addYear())));
        $this->assertSame('FAC-2026-0001', DB::transaction(fn () => $generator->next('invoice', $date)));
    }

    public function test_missing_configuration_is_refused(): void
    {
        $this->expectException(LogicException::class);
        DB::transaction(fn () => app(DocumentNumberGenerator::class)->next('invoice', CarbonImmutable::parse('2026-01-01')));
    }
}

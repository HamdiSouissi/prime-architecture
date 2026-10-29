<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\QuoteStatus;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\QuoteService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TaxSettingsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class BillingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_invoice_payment_and_frozen_data(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00', 'Africa/Tunis'));
        $this->seed([RolePermissionSeeder::class, TaxSettingsSeeder::class]);
        $actor = User::factory()->create();
        $actor->assignRole('architect');
        $project = Project::factory()->create();
        DB::table('billing_profiles')->insert(['effective_from' => '2026-01-01', 'name' => 'Cabinet']);
        $input = ['document_date' => '2026-11-02', 'valid_until' => '2026-11-30', 'discount_percent' => '0', 'lines' => [['description' => 'Design', 'unit' => null, 'quantity' => '1', 'unit_price' => '100.000', 'discount_percent' => '0', 'tax_rate' => '19.0000']]];
        $quote = app(QuoteService::class)->create($project, $actor, $input);
        $this->assertSame('119.000', $quote->total_ttc);
        try {
            app(QuoteService::class)->issue($quote, $actor);
            $this->fail('An unverified fiscal configuration must not permit emission.');
        } catch (ValidationException) {
            $this->assertNull($quote->fresh()->number);
        }
        DB::table('tax_settings')->update(['is_verified' => true]);
        $quote = app(QuoteService::class)->issue($quote, $actor);
        $this->assertSame('DEV-2026-0001', $quote->number);
        $this->assertSame('100.000', $quote->snapshot['totals']['total_ht']);
        try {
            $quote->update(['total_ht' => '1.000']);
            $this->fail('Issued quote must be immutable.');
        } catch (LogicException) {
            $this->assertSame('100.000', $quote->fresh()->total_ht);
        }
        $quote = app(QuoteService::class)->decide($quote->fresh(), $actor, QuoteStatus::Accepted);
        $invoice = app(InvoiceService::class)->fromQuote($quote, $actor, '2026-11-30');
        $invoice = app(InvoiceService::class)->issue($invoice, $actor);
        $this->assertSame('FAC-2026-0001', $invoice->number);
        $this->assertSame('119.000', $invoice->total_ttc);
        DB::table('tax_rates')->where('rate', '19.0000')->update(['rate' => '20.0000']);
        $this->assertSame('19.0000', $invoice->fresh()->snapshot['lines'][0]['tax_rate']);
        $invoice = app(InvoiceService::class)->pay($invoice, $actor, ['amount' => '19.000', 'payment_date' => '2026-11-02', 'method' => 'cash']);
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->status);
        try {
            app(InvoiceService::class)->pay($invoice, $actor, ['amount' => '100.001', 'payment_date' => '2026-11-02', 'method' => 'cash']);
            $this->fail('Overpayment must be rejected.');
        } catch (ValidationException) {
            $this->assertSame('100.000', app(InvoiceService::class)->balance($invoice));
        }
        try {
            app(InvoiceService::class)->cancel($invoice, $actor, 'Refund not defined');
            $this->fail('An invoice with payments cannot be cancelled.');
        } catch (AuthorizationException) {
            $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->fresh()->status);
        }
        $invoice = app(InvoiceService::class)->pay($invoice, $actor, ['amount' => '100.000', 'payment_date' => '2026-11-02', 'method' => 'bank_transfer']);
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        try {
            $invoice->delete();
            $this->fail('An issued invoice cannot be deleted.');
        } catch (LogicException) {
            $this->assertNotNull(Invoice::find($invoice->id));
        }
    }

    public function test_unpaid_invoice_can_only_be_cancelled_with_integral_credit_note(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00', 'Africa/Tunis'));
        $this->seed([RolePermissionSeeder::class, TaxSettingsSeeder::class]);
        $actor = User::factory()->create();
        $actor->assignRole('architect');
        $project = Project::factory()->create();
        DB::table('billing_profiles')->insert(['effective_from' => '2026-01-01', 'name' => 'Cabinet']);
        DB::table('tax_settings')->update(['is_verified' => true]);
        $input = ['document_date' => '2026-11-02', 'due_date' => '2026-11-30', 'discount_percent' => '0', 'lines' => [['description' => 'Design', 'quantity' => '1', 'unit_price' => '100.000', 'discount_percent' => '0', 'tax_rate' => '19.0000']]];
        $invoice = app(InvoiceService::class)->create($project, $actor, $input);
        $invoice = app(InvoiceService::class)->issue($invoice, $actor);
        $invoice = app(InvoiceService::class)->cancel($invoice, $actor, 'Client request');
        $this->assertSame(InvoiceStatus::Cancelled, $invoice->status);
        $credit = DB::table('credit_notes')->where('invoice_id', $invoice->id)->first();
        $this->assertSame('AVR-2026-0001', $credit->number);
        $this->assertSame('119.000', json_decode($credit->snapshot, true)['invoice']['totals']['total_ttc']);
    }
}

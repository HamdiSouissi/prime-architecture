<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Models\FeeSchedule;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quote;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TaxSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BillingWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_workflow_recalculates_server_side_and_rejects_external_access(): void
    {
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00', 'Africa/Tunis'));
        $this->seed([RolePermissionSeeder::class, TaxSettingsSeeder::class]);
        DB::table('tax_settings')->update(['is_verified' => true]);
        DB::table('billing_profiles')->insert(['effective_from' => '2026-01-01', 'name' => 'Cabinet']);
        $architect = User::factory()->create();
        $architect->assignRole('architect');
        $project = Project::factory()->create();
        $input = ['document_date' => '2026-11-02', 'valid_until' => '2026-11-30', 'discount_percent' => '0', 'total_ttc' => '0.001', 'lines' => [['description' => 'Architecture', 'quantity' => '1', 'unit_price' => '100.000', 'discount_percent' => '0', 'tax_rate' => '19.0000']]];
        $this->actingAs($architect)->get(route('billing.index'))->assertOk();
        $this->get(route('billing.quotes.create', $project))->assertOk();
        $this->postJson(route('billing.quotes.preview', $project), $input)->assertOk()->assertJsonPath('total_ttc', '119.000');
        $this->post(route('billing.quotes.store', $project), $input)->assertSessionHasNoErrors();
        $quote = Quote::firstOrFail();
        $this->assertSame('119.000', $quote->total_ttc);
        $this->get(route('billing.quotes.show', $quote))->assertOk()->assertSee('Architecture');
        $this->post(route('billing.quotes.issue', $quote))->assertSessionHasNoErrors();
        config(['billing.pdf_token' => str_repeat('a', 32), 'billing.pdf_url' => 'http://pdf:3000']);
        Http::fake(['http://pdf:3000/render' => Http::response('%PDF-1.7 test', 200, ['Content-Type' => 'application/pdf'])]);
        $this->get(route('billing.quotes.pdf', [$quote, 'ar']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        Http::assertSent(fn ($request) => str_contains($request->body(), 'lang=\"ar\"') && str_contains($request->body(), 'dir=\"rtl\"'));

        $this->put(route('billing.quotes.update', $quote), $input)->assertForbidden();
        $this->post(route('billing.quotes.decide', $quote), ['status' => 'accepted'])->assertSessionHasNoErrors();
        $this->assertSame(QuoteStatus::Accepted, $quote->fresh()->status);
        $this->post(route('billing.quotes.duplicate', $quote))->assertSessionHasNoErrors();
        $copy = Quote::whereKeyNot($quote->id)->firstOrFail();
        $this->assertNull($copy->number);
        $this->assertSame(QuoteStatus::Draft, $copy->status);
        $this->post(route('billing.quotes.convert', $quote), ['due_date' => '2026-11-30'])->assertSessionHasNoErrors();
        $invoice = Invoice::firstOrFail();
        $this->get(route('billing.invoices.show', $invoice))->assertOk();
        $this->delete(route('billing.invoices.destroy', $invoice))->assertRedirect(route('billing.index'));
        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
        $this->post(route('billing.quotes.convert', $quote), ['due_date' => '2026-11-30'])->assertSessionHasNoErrors();
        $invoice = Invoice::firstOrFail();
        $this->post(route('billing.invoices.issue', $invoice))->assertSessionHasNoErrors();
        $this->delete(route('billing.invoices.destroy', $invoice))->assertForbidden();
        $this->post(route('billing.invoices.pay', $invoice), ['amount' => '119.000', 'payment_date' => '2026-11-02', 'method' => 'cash'])->assertSessionHasNoErrors();
        $payment = DB::table('payments')->where('invoice_id', $invoice->id)->first();
        $this->get(route('billing.invoices.receipt-pdf', [$invoice, $payment->id, 'fr']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('billing.invoices.receipt-pdf', [$invoice, $payment->id + 10000, 'fr']))->assertNotFound();
        $external = User::factory()->create();
        $external->assignRole('client');
        $project->client->update(['user_id' => $external->id]);
        $this->actingAs($external)->get(route('billing.index'))->assertForbidden();
        $this->get(route('billing.quotes.show', $quote))->assertForbidden();
        $this->get(route('billing.quotes.pdf', [$quote, 'ar']))->assertForbidden();
        $this->get(route('billing.invoices.show', $invoice))->assertForbidden();
        $this->postJson(route('billing.quotes.preview', $project), $input)->assertForbidden();
    }

    public function test_only_admin_can_create_versioned_financial_settings(): void
    {
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00', 'Africa/Tunis'));
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('documents');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $architect = User::factory()->create();
        $architect->assignRole('architect');
        $data = ['effective_from' => '2026-11-02', 'stamp_amount' => '1.000', 'stamp_on_invoice' => '1', 'verified' => '1', 'rates' => [['label_fr' => 'TVA', 'label_ar' => 'أداء', 'rate' => '19.0000']]];
        $this->actingAs($architect)->get(route('billing.settings.index'))->assertForbidden();
        $this->post(route('billing.settings.tax'), $data)->assertForbidden();
        $this->actingAs($admin)->get(route('billing.settings.index'))->assertOk();
        $this->post(route('billing.settings.tax'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tax_settings', ['is_verified' => true, 'stamp_on_invoice' => true]);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $logo = UploadedFile::fake()->createWithContent('logo.png', $png);
        $this->post(route('billing.settings.profile'), ['effective_from' => '2026-11-02', 'name' => 'Cabinet', 'logo' => $logo])->assertSessionHasNoErrors();
        $profile = DB::table('billing_profiles')->first();
        Storage::disk('documents')->assertExists($profile->logo_path);
        $invalidLogo = UploadedFile::fake()->createWithContent('logo.png', 'not a png');
        $this->post(route('billing.settings.profile'), ['effective_from' => '2026-11-02', 'name' => 'Cabinet', 'logo' => $invalidLogo])->assertSessionHasErrors('logo');
        $oversizedLogo = UploadedFile::fake()->createWithContent('logo.png', substr_replace($png, pack('N', 4096), 16, 4));
        $this->post(route('billing.settings.profile'), ['effective_from' => '2026-11-02', 'name' => 'Cabinet', 'logo' => $oversizedLogo])->assertSessionHasErrors('logo');
        $this->post(route('billing.settings.format'), ['effective_from' => '2026-11-02', 'document_type' => 'quote', 'format' => 'DEV-{year}-{sequence}', 'padding' => '4'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('billing_number_formats', ['document_type' => 'quote', 'format' => 'DEV-{year}-{sequence}']);
        $project = Project::factory()->create();
        $input = ['document_date' => '2026-11-02', 'valid_until' => null, 'discount_percent' => '0', 'lines' => [['description' => 'Design', 'quantity' => '1', 'unit_price' => '10.000', 'discount_percent' => '0', 'tax_rate' => '19.0000']]];
        $this->post(route('billing.quotes.store', $project), $input)->assertSessionHasNoErrors();
        $quote = Quote::firstOrFail();
        $this->post(route('billing.quotes.issue', $quote))->assertSessionHasNoErrors();
        $snapshotLogo = $quote->fresh()->snapshot['profile']['logo'];
        $this->assertStringStartsWith('data:image/png;base64,', $snapshotLogo);
        Storage::disk('documents')->put($profile->logo_path, 'changed');
        $this->assertSame($snapshotLogo, $quote->fresh()->snapshot['profile']['logo']);

        unset($data['verified']);
        $this->post(route('billing.settings.tax'), $data)->assertSessionHasErrors('verified');
    }

    public function test_fee_schedules_are_project_scoped_and_exact(): void
    {
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
        $architect = User::factory()->create();
        $architect->assignRole('architect');
        $project = Project::factory()->create();
        $other = Project::factory()->create();
        $this->actingAs($architect)->get(route('billing.fees.index', $project))->assertOk();
        $data = ['phase' => 'Étude', 'mode' => 'percentage', 'value' => '2.5', 'works_amount' => '1000.000'];
        $this->post(route('billing.fees.store', $project), $data)->assertSessionHasNoErrors();
        $fee = FeeSchedule::firstOrFail();
        $this->get(route('billing.fees.index', $project))->assertSee('25.000');
        $this->put(route('billing.fees.update', [$other, $fee->id]), $data)->assertNotFound();
        $this->post(route('billing.fees.store', $project), [...$data, 'value' => '101'])->assertSessionHasErrors('value');
        $this->post(route('billing.fees.store', $project), ['phase' => 'Projet important', 'mode' => 'fixed', 'value' => '999999999999.999'])->assertSessionHasNoErrors();
        $this->get(route('billing.fees.index', $project))->assertSee('999999999999.999');
        $client = User::factory()->create();
        $client->assignRole('client');
        $this->actingAs($client)->get(route('billing.fees.index', $project))->assertForbidden();
        $this->post(route('billing.fees.store', $project), $data)->assertForbidden();
    }

    public function test_tax_settings_preserve_dynamic_input_after_validation_error(): void
    {
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin)->from(route('billing.settings.index'))->post(route('billing.settings.tax'), [
            'effective_from' => today()->toDateString(), 'stamp_amount' => '2.500', 'verified' => '1',
            'rates' => [['label_fr' => 'TauxTest', 'label_ar' => 'اختبار', 'rate' => '101']],
            'withholdings' => [['label_fr' => 'RetenueTest', 'label_ar' => 'اقتطاع', 'rate' => '1', 'base' => 'ttc', 'includes_stamp' => '1']],
        ])->assertSessionHasErrors('rates')->assertRedirect(route('billing.settings.index'));
        $this->get(route('billing.settings.index'))->assertOk()->assertSee('TauxTest')->assertSee('RetenueTest')->assertSee('2.500')->assertSee('includesStamp');
    }

    public function test_fiscal_settings_never_include_stamp_in_withholding_and_disclose_e_invoicing_limit(): void
    {
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00', 'Africa/Tunis'));
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin)->get(route('billing.settings.index'))->assertOk()
            ->assertSee(__('phase6.e_invoicing_title'))->assertSee(__('phase6.withholding_base_hint'));
        $this->post(route('billing.settings.tax'), [
            'effective_from' => '2026-11-02', 'stamp_amount' => '1.000', 'stamp_on_invoice' => '1', 'verified' => '1',
            'rates' => [['label_fr' => 'TVA 19', 'label_ar' => 'أداء 19', 'rate' => '19.0000']],
            'withholdings' => [['label_fr' => 'Honoraires TTC', 'label_ar' => 'أتعاب', 'rate' => '3.0000', 'base' => 'ttc', 'includes_stamp' => '1']],
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('withholding_rates', ['rate' => '3.0000', 'base' => 'ttc', 'includes_stamp' => false]);
    }
}

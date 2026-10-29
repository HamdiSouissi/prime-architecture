<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\MeasurementLine;
use App\Models\PriceItem;
use App\Models\Project;
use App\Models\Quote;
use App\Services\QuoteService;
use Carbon\CarbonImmutable;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TaxSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MeasurementWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_and_measurements_use_current_prices_and_enforce_project_rights(): void
    {
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
        $project = Project::factory()->create(['client_id' => Client::factory()->create()->id]);
        $architect = User::factory()->create();
        $architect->assignRole('architect');
        $project->members()->attach($architect->id, ['project_role' => 'collaborator']);
        $data = ['category' => 'Maçonnerie', 'name' => 'Mur', 'unit' => 'm²', 'unit_price' => '12.500'];
        $this->get(route('prices.index'))->assertRedirect();
        $this->actingAs($architect)->post(route('prices.store'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $item = PriceItem::query()->firstOrFail();
        $this->post(route('measurement.store', $project), ['price_item_id' => $item->id, 'quantity' => '2'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('measurement.index', $project))->assertOk()->assertSee('25.000');
        $data['unit_price'] = '15.000';
        $this->put(route('prices.update', $item), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('measurement.index', $project))->assertOk()->assertSee('30.000');
        $this->assertSame(2, $item->prices()->count());

        foreach (['collaborator', 'client', 'contractor'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            $this->actingAs($user)->get(route('prices.index'))->assertForbidden();
            $this->actingAs($user)->get(route('measurement.index', $project))->assertForbidden();
            $this->actingAs($user)->post(route('prices.store'), $data)->assertForbidden();
        }
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin)->get(route('prices.index'))->assertOk();
        $this->assertSame(1, MeasurementLine::query()->count());
    }
    public function test_transfer_creates_and_appends_drafts_but_never_changes_issued_quotes(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00', 'Africa/Tunis'));
        $this->seed([RolePermissionSeeder::class, TaxSettingsSeeder::class]);
        $actor = User::factory()->create();
        $actor->assignRole('architect');
        $project = Project::factory()->create();
        DB::table('billing_profiles')->insert(['effective_from' => '2026-01-01', 'name' => 'Cabinet']);
        $this->actingAs($actor)->post(route('prices.store'), ['category' => 'A', 'name' => 'Mur', 'unit' => 'm', 'unit_price' => '10.000'])->assertRedirect();
        $item = PriceItem::query()->firstOrFail();
        $this->post(route('measurement.store', $project), ['price_item_id' => $item->id, 'quantity' => '2'])->assertRedirect();
        $this->post(route('measurement.transfer', $project), ['tax_rate' => '19.0000'])->assertRedirect();
        $quote = Quote::query()->firstOrFail();
        $this->assertSame('20.000', $quote->total_ht);
        $this->post(route('measurement.transfer', $project), ['quote_id' => $quote->id, 'tax_rate' => '19.0000'])->assertRedirect();
        $this->assertSame(2, $quote->lines()->count());
        DB::table('tax_settings')->update(['is_verified' => true]);
        app(QuoteService::class)->issue($quote->fresh(), $actor);
        $this->post(route('measurement.transfer', $project), ['quote_id' => $quote->id, 'tax_rate' => '19.0000'])->assertForbidden();
        $this->assertSame(2, $quote->lines()->count());
        $this->assertSame('40.000', $quote->fresh()->total_ht);
    }
}

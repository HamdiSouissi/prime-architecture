<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Delegation;
use App\Models\Governorate;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\GeographySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class GeographySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_governorate_has_the_expected_number_of_unique_bilingual_delegations(): void
    {
        $this->seed(GeographySeeder::class);
        $counts = [
            'tunis' => 21, 'ariana' => 7, 'ben-arous' => 12, 'manouba' => 8,
            'nabeul' => 16, 'zaghouan' => 6, 'bizerte' => 14, 'beja' => 9,
            'jendouba' => 9, 'kef' => 12, 'siliana' => 11, 'sousse' => 16,
            'monastir' => 13, 'mahdia' => 13, 'sfax' => 16, 'kairouan' => 13,
            'kasserine' => 13, 'sidi-bouzid' => 14, 'gabes' => 13, 'medenine' => 9,
            'tataouine' => 8, 'gafsa' => 13, 'tozeur' => 6, 'kebili' => 7,
        ];
        $governorates = Governorate::with('delegations')->get()->keyBy('key');
        $this->assertCount(24, $governorates);
        $this->assertDatabaseCount('delegations', 279);
        foreach ($counts as $key => $count) {
            $delegations = $governorates[$key]->delegations;
            $this->assertCount($count, $delegations, $key);
            $this->assertSame($count, $delegations->pluck('key')->unique()->count(), $key);
            $this->assertSame($count, $delegations->pluck('name_fr')->unique()->count(), $key);
            $this->assertSame($count, $delegations->pluck('name_ar')->unique()->count(), $key);
            foreach ($delegations as $delegation) {
                $this->assertNotEmpty(trim($delegation->name_fr));
                $this->assertNotEmpty(trim($delegation->name_ar));
            }
        }
        $this->assertDatabaseHas('delegations', ['key' => 'tn-01-medina', 'name_fr' => 'La Médina', 'name_ar' => 'المدينة']);
        $this->assertDatabaseHas('delegations', ['key' => 'tn-19-gabes-medina', 'name_fr' => 'Gabès Médina', 'name_ar' => 'قابس المدينة']);
        $this->assertDatabaseHas('delegations', ['key' => 'tn-18-essaida', 'name_ar' => 'السعيدة']);
    }

    public function test_reseeding_preserves_existing_ids_and_custom_delegations(): void
    {
        $tunis = Governorate::create(['key' => 'tunis', 'name_fr' => 'Tunis', 'name_ar' => 'تونس']);
        $carthage = Delegation::create(['key' => 'tn-01-carthage', 'governorate_id' => $tunis->id, 'name_fr' => 'Ancien nom', 'name_ar' => 'قديم']);
        $custom = Delegation::create(['key' => 'custom-existing', 'governorate_id' => $tunis->id, 'name_fr' => 'Ancienne entrée', 'name_ar' => 'مدخل قديم']);
        $client = Client::factory()->create(['governorate_id' => $tunis->id, 'delegation_id' => $carthage->id]);
        $project = Project::factory()->create(['client_id' => $client->id, 'governorate_id' => $tunis->id, 'delegation_id' => $carthage->id]);
        $this->seed(GeographySeeder::class);
        $this->seed(GeographySeeder::class);
        $this->assertDatabaseCount('delegations', 280);
        $this->assertSame($carthage->id, $client->fresh()->delegation_id);
        $this->assertSame($carthage->id, $project->fresh()->delegation_id);
        $this->assertSame('Carthage', $carthage->fresh()->name_fr);
        $this->assertDatabaseHas('delegations', ['id' => $custom->id, 'key' => 'custom-existing']);
    }

    public function test_conflicting_governorate_for_an_existing_key_fails_without_reassigning_it(): void
    {
        $gabes = Governorate::create(['key' => 'gabes', 'name_fr' => 'Gabès', 'name_ar' => 'قابس']);
        $existing = Delegation::create(['key' => 'tn-01-carthage', 'governorate_id' => $gabes->id, 'name_fr' => 'Conflit', 'name_ar' => 'تعارض']);
        try {
            $this->seed(GeographySeeder::class);
            $this->fail('A conflicting delegation key must stop the seed.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('tn-01-carthage', $exception->getMessage());
        }
        $this->assertDatabaseHas('delegations', ['id' => $existing->id, 'governorate_id' => $gabes->id, 'name_fr' => 'Conflit']);
        $this->assertDatabaseCount('delegations', 1);
    }

    public function test_forms_receive_bilingual_lists_and_server_rejects_cross_governorate_delegations(): void
    {
        $this->withoutVite();
        $this->seed([GeographySeeder::class, RolePermissionSeeder::class]);
        $architect = User::factory()->create();
        $architect->assignRole('architect');
        $tunis = Governorate::where('key', 'tunis')->firstOrFail();
        $ariana = Governorate::where('key', 'ariana')->firstOrFail();
        $carthage = Delegation::where('key', 'tn-01-carthage')->firstOrFail();
        $response = $this->actingAs($architect)->withSession(['locale' => 'fr'])->get(route('clients.create'));
        $response->assertOk()->assertSee('Carthage')->assertSee('data-governorate-id=', false);
        $response->assertViewHas('governorates', fn ($governorates) => $governorates->firstWhere('key', 'tunis')->delegations->contains('name_ar', 'قرطاج'));
        $this->withSession(['locale' => 'ar'])->get(route('projects.create'))->assertOk()->assertSee('lang="ar" dir="rtl"', false)->assertSee('data-governorate-id=', false);
        $this->post(route('clients.store'), ['type' => 'individual', 'name' => 'Test', 'governorate_id' => $ariana->id, 'delegation_id' => $carthage->id])->assertSessionHasErrors('delegation_id');
        $this->post(route('clients.store'), ['type' => 'individual', 'name' => 'Test valide', 'governorate_id' => $tunis->id, 'delegation_id' => $carthage->id])->assertRedirect();
        $this->assertDatabaseHas('clients', ['name' => 'Test valide', 'governorate_id' => $tunis->id, 'delegation_id' => $carthage->id]);
        $client = Client::where('name', 'Test valide')->firstOrFail();
        $project = ['client_id' => $client->id, 'name' => 'Projet à Carthage', 'project_type' => 'villa', 'lead_architect_id' => $architect->id, 'governorate_id' => $ariana->id, 'delegation_id' => $carthage->id];
        $this->post(route('projects.store'), $project)->assertSessionHasErrors('delegation_id');
        $project['governorate_id'] = $tunis->id;
        $this->post(route('projects.store'), $project)->assertRedirect();
        $this->assertDatabaseHas('projects', ['name' => 'Projet à Carthage', 'governorate_id' => $tunis->id, 'delegation_id' => $carthage->id]);
    }
}

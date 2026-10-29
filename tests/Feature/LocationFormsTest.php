<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Delegation;
use App\Models\Governorate;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\GeographySeeder;
use Database\Seeders\RolePermissionSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LocationFormsTest extends TestCase
{
    use RefreshDatabase;

    private User $architect;

    private Governorate $tunis;

    private Governorate $ariana;

    private Delegation $carthage;

    private Delegation $arianaVille;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([GeographySeeder::class, RolePermissionSeeder::class]);
        $this->architect = User::factory()->create();
        $this->architect->assignRole('architect');
        $this->actingAs($this->architect);
        $this->tunis = Governorate::where('key', 'tunis')->firstOrFail();
        $this->ariana = Governorate::where('key', 'ariana')->firstOrFail();
        $this->carthage = Delegation::where('key', 'tn-01-carthage')->firstOrFail();
        $this->arianaVille = Delegation::where('key', 'tn-02-ariana-ville')->firstOrFail();
    }

    private function assertSelected(TestResponse $response, string $field, int $value): void
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        $this->assertSame(1, $xpath->query('//select[@name="'.$field.'"]/option[@value="'.$value.'" and @selected]')->length);
    }

    public function test_edit_forms_render_existing_location_as_selected_server_side(): void
    {
        $client = Client::factory()->create(['governorate_id' => $this->tunis->id, 'delegation_id' => $this->carthage->id]);
        $project = Project::factory()->create(['client_id' => $client->id, 'governorate_id' => $this->ariana->id, 'delegation_id' => $this->arianaVille->id]);
        foreach ([[$client, 'clients.edit', $this->tunis, $this->carthage], [$project, 'projects.edit', $this->ariana, $this->arianaVille]] as [$model, $route, $governorate, $delegation]) {
            $response = $this->get(route($route, $model))->assertOk()->assertSee('data-governorate-id=', false);
            $this->assertSelected($response, 'governorate_id', $governorate->id);
            $this->assertSelected($response, 'delegation_id', $delegation->id);
        }
    }

    public function test_validation_failure_preserves_the_location_for_client_and_project(): void
    {
        $client = Client::factory()->create();
        $this->from(route('clients.create'))->post(route('clients.store'), ['type' => 'individual', 'name' => 'Test', 'national_id' => '123', 'governorate_id' => $this->tunis->id, 'delegation_id' => $this->carthage->id])->assertRedirect(route('clients.create'))->assertSessionHasErrors('national_id');
        $clientForm = $this->get(route('clients.create'))->assertOk();
        $this->assertSelected($clientForm, 'governorate_id', $this->tunis->id);
        $this->assertSelected($clientForm, 'delegation_id', $this->carthage->id);
        $this->from(route('projects.create'))->post(route('projects.store'), ['name' => '', 'client_id' => $client->id, 'lead_architect_id' => $this->architect->id, 'project_type' => 'villa', 'governorate_id' => $this->ariana->id, 'delegation_id' => $this->arianaVille->id])->assertRedirect(route('projects.create'))->assertSessionHasErrors('name');
        $projectForm = $this->get(route('projects.create'))->assertOk();
        $this->assertSelected($projectForm, 'governorate_id', $this->ariana->id);
        $this->assertSelected($projectForm, 'delegation_id', $this->arianaVille->id);
    }

    public function test_client_and_project_can_clear_their_previously_saved_location(): void
    {
        $client = Client::factory()->create(['governorate_id' => $this->tunis->id, 'delegation_id' => $this->carthage->id]);
        $project = Project::factory()->create(['client_id' => $client->id, 'governorate_id' => $this->tunis->id, 'delegation_id' => $this->carthage->id]);
        $this->put(route('clients.update', $client), ['type' => 'individual', 'name' => $client->name, 'governorate_id' => '', 'delegation_id' => ''])->assertSessionHasNoErrors()->assertRedirect();
        $this->put(route('projects.update', $project), ['name' => $project->name, 'client_id' => $client->id, 'project_type' => 'villa', 'governorate_id' => '', 'delegation_id' => ''])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNull($client->fresh()->governorate_id);
        $this->assertNull($client->fresh()->delegation_id);
        $this->assertNull($project->fresh()->governorate_id);
        $this->assertNull($project->fresh()->delegation_id);
    }

    public function test_non_scalar_governorate_is_rejected_without_server_error(): void
    {
        $client = Client::factory()->create();
        $this->from(route('clients.create'))->post(route('clients.store'), ['type' => 'individual', 'name' => 'Test', 'governorate_id' => [$this->tunis->id], 'delegation_id' => $this->carthage->id])->assertRedirect(route('clients.create'))->assertSessionHasErrors('governorate_id');
        $this->get(route('clients.create'))->assertOk();
        $this->from(route('projects.create'))->post(route('projects.store'), ['name' => 'Test', 'client_id' => $client->id, 'lead_architect_id' => $this->architect->id, 'project_type' => 'villa', 'governorate_id' => [$this->tunis->id], 'delegation_id' => $this->carthage->id])->assertRedirect(route('projects.create'))->assertSessionHasErrors('governorate_id');
        $this->get(route('projects.create'))->assertOk();
    }
}

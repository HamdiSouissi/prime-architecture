<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectAdministration;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhaseThreeAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function project(User $architect, Client $client, string $name): Project
    {
        return app(ProjectAdministration::class)->create(['client_id' => $client->id, 'name' => $name, 'project_type' => 'villa', 'lead_architect_id' => $architect->id], $architect);
    }

    public function test_architect_can_create_search_filter_and_change_project_status(): void
    {
        $architect = $this->user('architect');
        $this->actingAs($architect)->post(route('clients.store'), ['type' => 'individual', 'name' => 'Amel Test', 'national_id' => '12345678', 'phone' => '20 123 456'])->assertRedirect();
        $client = Client::where('name', 'Amel Test')->firstOrFail();
        $this->assertSame('+21620123456', $client->phone);
        $this->post(route('projects.store'), ['name' => 'Villa Amel', 'client_id' => $client->id, 'lead_architect_id' => $architect->id, 'project_type' => 'villa'])->assertRedirect();
        $project = Project::where('name', 'Villa Amel')->firstOrFail();
        $this->get(route('projects.index', ['search' => $project->reference, 'status' => 'sketch', 'project_type' => 'villa']))->assertOk()->assertSee('Villa Amel');
        $this->post(route('projects.status', $project), ['status' => 'permit', 'comment' => 'Dossier en cours'])->assertRedirect();
        $this->get(route('projects.show', $project))->assertOk()->assertSee('Dossier en cours');
        $this->assertDatabaseHas('project_status_history', ['project_id' => $project->id, 'from_status' => 'sketch', 'to_status' => 'permit']);
        $this->assertDatabaseHas('activity_log', ['event' => 'project.status_changed', 'subject_id' => $project->id]);
    }

    public function test_collaborator_is_scoped_in_list_and_direct_url_and_viewer_is_read_only(): void
    {
        $architect = $this->user('architect');
        $collaborator = $this->user('collaborator');
        $client = Client::factory()->create();
        $visible = $this->project($architect, $client, 'Visible project');
        $hidden = $this->project($architect, $client, 'Hidden project');
        $visible->members()->attach($collaborator->id, ['project_role' => 'viewer']);
        $this->actingAs($collaborator)->get(route('projects.index'))->assertOk()->assertSee('Visible project')->assertDontSee('Hidden project');
        $this->get(route('projects.show', $hidden))->assertForbidden();
        $this->get(route('projects.edit', $visible))->assertForbidden();
        $this->get(route('clients.index'))->assertForbidden();
        $visible->members()->updateExistingPivot($collaborator->id, ['project_role' => 'collaborator']);
        $this->get(route('projects.edit', $visible))->assertOk();
        $this->post(route('projects.status', $visible), ['status' => 'construction'])->assertRedirect();
        $this->post(route('projects.status', $hidden), ['status' => 'construction'])->assertForbidden();
    }

    public function test_client_only_sees_linked_projects_and_cannot_see_or_add_internal_notes(): void
    {
        $architect = $this->user('architect');
        $user = $this->user('client');
        $ownClient = Client::factory()->create(['user_id' => $user->id]);
        $otherClient = Client::factory()->create();
        $own = $this->project($architect, $ownClient, 'Own project');
        $other = $this->project($architect, $otherClient, 'Other private project');
        $own->notes()->create(['user_id' => $architect->id, 'body' => 'SECRET INTERNAL NOTE']);
        $this->actingAs($user)->get(route('projects.index'))->assertOk()->assertSee('Own project')->assertDontSee('Other private project');
        $this->get(route('projects.show', $own))->assertOk()->assertDontSee('SECRET INTERNAL NOTE')->assertDontSee(__('phase3.history'));
        $this->get(route('projects.show', $other))->assertForbidden();
        $this->post(route('projects.notes.store', $own), ['body' => 'Not allowed'])->assertForbidden();
        $this->get(route('clients.index'))->assertForbidden();
    }

    public function test_contractor_cannot_access_clients_or_projects(): void
    {
        $architect = $this->user('architect');
        $project = $this->project($architect, Client::factory()->create(), 'Private project');
        $this->actingAs($this->user('contractor'))->get(route('projects.index'))->assertForbidden();
        $this->get(route('projects.show', $project))->assertForbidden();
        $this->get(route('clients.index'))->assertForbidden();
    }

    public function test_validation_and_contact_primary_uniqueness(): void
    {
        $architect = $this->user('architect');
        $this->actingAs($architect)->post(route('clients.store'), ['type' => 'individual', 'name' => 'Test', 'national_id' => '123', 'phone' => '+3312345678'])->assertSessionHasErrors(['national_id', 'phone']);
        $this->post(route('clients.store'), ['type' => 'company', 'name' => 'Test', 'tax_id' => 'invalid'])->assertSessionHasErrors('tax_id');
        $this->post(route('clients.store'), ['type' => 'company', 'name' => 'Company', 'tax_id' => '1234567/a/a/m/000'])->assertRedirect();
        $company = Client::where('name', 'Company')->firstOrFail();
        $this->assertSame('1234567/A/A/M/000', $company->tax_id);
        $this->post(route('clients.contacts.store', $company), ['name' => 'First', 'is_primary' => 1])->assertRedirect();
        $this->post(route('clients.contacts.store', $company), ['name' => 'Second', 'is_primary' => 1])->assertRedirect();
        $this->assertSame(1, $company->contacts()->where('is_primary', true)->count());
        $this->assertDatabaseHas('client_contacts', ['client_id' => $company->id, 'name' => 'Second', 'is_primary' => 1]);
    }

    public function test_project_list_query_count_does_not_grow_with_rows(): void
    {
        $architect = $this->user('architect');
        $client = Client::factory()->create();
        $this->project($architect, $client, 'Project first');
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });
        $this->actingAs($architect)->get(route('projects.index'))->assertOk(); // Warm authorization caches.
        $queries = [];
        $this->get(route('projects.index'))->assertOk();
        $oneRowQueries = count($queries);
        for ($i = 0; $i < 14; $i++) {
            $this->project($architect, $client, 'Project '.$i);
        }
        $queries = [];
        $this->get(route('projects.index'))->assertOk();
        $this->assertLessThanOrEqual($oneRowQueries + 1, count($queries), 'Project list should not issue queries per row.');
    }
}

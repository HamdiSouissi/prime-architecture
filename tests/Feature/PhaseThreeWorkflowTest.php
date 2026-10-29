<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Delegation;
use App\Models\Governorate;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectAdministration;
use Database\Seeders\PhaseThreeDemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhaseThreeWorkflowTest extends TestCase
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

    private function project(User $actor, Client $client): Project
    {
        return app(ProjectAdministration::class)->create(['name' => 'Maison Tunis', 'client_id' => $client->id, 'project_type' => 'villa', 'lead_architect_id' => $actor->id], $actor);
    }

    public function test_client_cascade_archive_and_individual_project_restore(): void
    {
        $actor = $this->user('architect');
        $client = Client::factory()->create();
        $project = $this->project($actor, $client);
        $this->actingAs($actor)->delete(route('clients.destroy', $client))->assertRedirect();
        $this->assertSoftDeleted('clients', ['id' => $client->id]);
        $this->assertSoftDeleted('projects', ['id' => $project->id]);
        $this->post(route('projects.restore', $project))->assertNotFound();
        $this->post(route('clients.restore', $client))->assertRedirect();
        $this->assertSoftDeleted('projects', ['id' => $project->id]);
        $this->post(route('projects.restore', $project))->assertRedirect();
        $this->assertNotSoftDeleted('projects', ['id' => $project->id]);
    }

    public function test_team_lead_and_note_permissions(): void
    {
        $architect = $this->user('architect');
        $otherArchitect = $this->user('architect');
        $collaborator = $this->user('collaborator');
        $project = $this->project($architect, Client::factory()->create());
        $this->actingAs($architect)->post(route('projects.members.store', $project), ['user_id' => $collaborator->id, 'project_role' => 'collaborator'])->assertRedirect();
        $this->post(route('projects.lead', $project), ['lead_architect_id' => $otherArchitect->id])->assertRedirect();
        $this->assertDatabaseHas('project_user', ['project_id' => $project->id, 'user_id' => $otherArchitect->id, 'project_role' => 'lead']);
        $this->assertSame($otherArchitect->id, $project->fresh()->lead_architect_id);
        $this->actingAs($collaborator)->post(route('projects.notes.store', $project), ['body' => 'Note privée'])->assertRedirect();
        $note = $project->notes()->firstOrFail();
        $this->actingAs($otherArchitect)->delete(route('projects.notes.destroy', [$project, $note]))->assertForbidden();
        $this->actingAs($collaborator)->delete(route('projects.notes.destroy', [$project, $note]))->assertRedirect();
        $this->assertSoftDeleted('project_notes', ['id' => $note->id]);
    }

    public function test_location_must_match_governorate_and_forms_render_in_both_languages(): void
    {
        $actor = $this->user('architect');
        $north = Governorate::create(['key' => 'test-north', 'name_fr' => 'Nord', 'name_ar' => 'شمال']);
        $south = Governorate::create(['key' => 'test-south', 'name_fr' => 'Sud', 'name_ar' => 'جنوب']);
        $delegation = Delegation::create(['key' => 'test-north-one', 'governorate_id' => $north->id, 'name_fr' => 'Nord-1', 'name_ar' => 'شمال-1']);
        $this->actingAs($actor)->post(route('clients.store'), ['type' => 'individual', 'name' => 'Test', 'governorate_id' => $south->id, 'delegation_id' => $delegation->id])->assertSessionHasErrors('delegation_id');
        $this->withSession(['locale' => 'fr'])->get(route('clients.create'))->assertOk()->assertSee('Nord-1')->assertSee('lang="fr" dir="ltr"', false);
        $this->withSession(['locale' => 'ar'])->get(route('projects.create'))->assertOk()->assertSee('lang="ar" dir="rtl"', false)->assertSee('الولاية');
    }

    public function test_main_phase_three_pages_render_in_french_and_arabic(): void
    {
        $actor = $this->user('architect');
        $client = Client::factory()->create();
        $project = $this->project($actor, $client);
        $urls = [
            route('dashboard'), route('clients.index'), route('clients.create'), route('clients.show', $client), route('clients.edit', $client),
            route('projects.index'), route('projects.index', ['view' => 'kanban']), route('projects.create'), route('projects.show', $project), route('projects.edit', $project),
        ];
        foreach (['fr' => 'ltr', 'ar' => 'rtl'] as $locale => $direction) {
            foreach ($urls as $url) {
                $this->actingAs($actor)->withSession(['locale' => $locale])->get($url)->assertOk()->assertSee('lang="'.$locale.'" dir="'.$direction.'"', false)->assertDontSee('phase3.');
            }
        }
    }

    public function test_demo_seeder_is_repeatable_and_spans_several_statuses(): void
    {
        $this->user('architect');
        $this->seed(PhaseThreeDemoSeeder::class);
        $this->seed(PhaseThreeDemoSeeder::class);
        $this->assertSame(15, Client::count());
        $this->assertSame(30, Project::count());
        $this->assertGreaterThan(1, Project::query()->distinct()->count('status'));
    }

    public function test_client_list_does_not_issue_queries_per_row(): void
    {
        $actor = $this->user('architect');
        Client::factory()->count(15)->create();
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });
        $this->actingAs($actor)->get(route('clients.index'))->assertOk();
        $this->assertLessThan(30, count($queries));
    }

    public function test_admin_can_manage_projects_and_sort_by_client_name(): void
    {
        $admin = $this->user('admin');
        $z = Client::factory()->create(['name' => 'Zina Client']);
        $a = Client::factory()->create(['name' => 'Amel Client']);
        $this->project($admin, $z);
        $project = $this->project($admin, $a);
        $this->actingAs($admin)->get(route('projects.index', ['sort' => 'client_name', 'direction' => 'asc']))->assertOk()->assertSeeInOrder(['Amel Client', 'Zina Client']);
        $this->delete(route('projects.destroy', $project))->assertRedirect();
        $this->assertSoftDeleted('projects', ['id' => $project->id]);
        $this->post(route('projects.restore', $project))->assertRedirect();
        $this->assertNotSoftDeleted('projects', ['id' => $project->id]);
    }
}

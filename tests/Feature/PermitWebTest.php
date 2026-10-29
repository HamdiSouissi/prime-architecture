<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Document;
use App\Models\PermitDossier;
use App\Models\PermitStep;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermitWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_permits_are_scoped_and_only_architects_manage_them(): void
    {
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
        $project = Project::factory()->create(['client_id' => Client::factory()->create()->id]);
        $other = Project::factory()->create();
        $architect = User::factory()->create();
        $architect->assignRole('architect');
        $collaborator = User::factory()->create();
        $collaborator->assignRole('collaborator');
        $project->members()->attach($collaborator->id, ['project_role' => 'collaborator']);
        $data = ['title' => 'Permis A', 'permit_type' => 'Construction', 'status' => 'À préparer'];
        $this->get(route('permits.index', $project))->assertRedirect();
        $this->actingAs($architect)->post(route('permits.store', $project), $data)->assertRedirect()->assertSessionHasNoErrors();
        $dossier = PermitDossier::query()->firstOrFail();
        $this->post(route('permits.steps.store', [$project, $dossier]), ['label' => 'Plan', 'due_on' => '2027-01-01'])->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('permits.show', [$project, $dossier]))->assertOk()->assertSee('Plan');
        $this->get(route('permits.show', [$other, $dossier]))->assertNotFound();
        $this->actingAs($collaborator)->get(route('permits.show', [$project, $dossier]))->assertOk();
        $this->post(route('permits.store', $project), $data)->assertForbidden();
        $this->get(route('permits.index', $other))->assertForbidden();
        foreach (['client', 'contractor'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            $this->actingAs($user)->get(route('permits.index', $project))->assertForbidden();
        }
    }

    public function test_attached_documents_must_belong_to_the_same_project(): void
    {
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
        $architect = User::factory()->create();
        $architect->assignRole('architect');
        $project = Project::factory()->create();
        $other = Project::factory()->create();
        $dossier = PermitDossier::create(['project_id' => $project->id, 'created_by' => $architect->id, 'title' => 'Dossier', 'permit_type' => 'Permis', 'status' => 'En cours']);
        $allowed = Document::factory()->create(['project_id' => $project->id, 'title' => 'Plan local']);
        $foreign = Document::factory()->create(['project_id' => $other->id, 'title' => 'Plan ailleurs']);
        $this->actingAs($architect)->post(route('permits.documents.store', [$project, $dossier]), ['document_id' => $foreign->id])->assertNotFound();
        $this->post(route('permits.documents.store', [$project, $dossier]), ['document_id' => $allowed->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('permits.documents.store', [$project, $dossier]), ['document_id' => $allowed->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, $dossier->documents()->count());
        $this->get(route('permits.show', [$project, $dossier]))->assertOk()->assertSee('Plan local')->assertDontSee('Plan ailleurs');
    }

    public function test_reminders_are_daily_deduplicated_and_follow_current_access(): void
    {
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
        config(['permits.reminder_days' => 3]);
        $architect = User::factory()->create();
        $architect->assignRole('architect');
        $member = User::factory()->create();
        $member->assignRole('collaborator');
        $outsider = User::factory()->create();
        $outsider->assignRole('collaborator');
        $project = Project::factory()->create();
        $project->members()->attach($member->id, ['project_role' => 'collaborator']);
        $dossier = PermitDossier::create(['project_id' => $project->id, 'created_by' => $architect->id, 'title' => 'Permis privé', 'permit_type' => 'Permis', 'status' => 'En cours']);
        $step = PermitStep::create(['permit_dossier_id' => $dossier->id, 'label' => 'Dossier confidentiel', 'due_on' => now()->addDay()->toDateString()]);
        PermitStep::create(['permit_dossier_id' => $dossier->id, 'label' => 'Terminé', 'due_on' => now()->toDateString(), 'completed_on' => now()->toDateString()]);
        PermitStep::create(['permit_dossier_id' => $dossier->id, 'label' => 'Lointain', 'due_on' => now()->addDays(10)->toDateString()]);
        $this->artisan('permits:remind')->assertSuccessful();
        $this->artisan('permits:remind')->assertSuccessful();
        $this->assertSame(1, $member->notifications()->count());
        $this->assertSame([$step->id], $member->notifications()->firstOrFail()->data['permit_step_ids']);
        $this->assertSame(0, $outsider->notifications()->count());
        $this->actingAs($member)->get(route('planning.notifications'))->assertOk()->assertSee('Dossier confidentiel')->assertDontSee('Terminé');
        $project->members()->detach($member->id);
        $this->get(route('planning.notifications'))->assertOk()->assertDontSee('Dossier confidentiel');
    }
}

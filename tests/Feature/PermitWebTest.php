<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\PermitDossier;
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
}

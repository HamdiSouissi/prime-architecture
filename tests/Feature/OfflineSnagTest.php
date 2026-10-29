<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfflineSnagTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_is_idempotent_and_bound_to_authorized_user_and_project(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $member = User::factory()->create();
        $member->assignRole('collaborator');
        $project = Project::factory()->create();
        $project->members()->attach($member->id, ['project_role' => 'collaborator']);
        $other = Project::factory()->create();
        $payload = ['operation_id' => (string) Str::uuid(), 'owner_id' => $member->id, 'title' => 'Fissure', 'severity' => 'major', 'internal_notes' => 'Privé'];
        $this->actingAs($member)->getJson(route('offline.projects'))->assertOk();
        $id = $this->postJson(route('offline.snags.store', $project), $payload)->assertOk()->json('id');
        $this->postJson(route('offline.snags.store', $project), $payload)->assertOk()->assertJsonPath('id', $id);
        $this->postJson(route('offline.snags.store', $project), array_replace($payload, ['title' => 'Autre']))->assertStatus(409);
        $this->postJson(route('offline.snags.store', $other), $payload)->assertForbidden();
        $this->postJson(route('offline.snags.store', $project), array_replace($payload, ['owner_id' => $member->id + 1]))->assertForbidden();
        $this->assertDatabaseCount('snags', 1);
        $this->assertDatabaseHas('snags', ['id' => $id, 'project_id' => $project->id, 'internal_notes' => 'Privé']);
    }
}

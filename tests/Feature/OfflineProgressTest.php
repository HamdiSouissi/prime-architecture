<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\SiteProgressUpdate;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfflineProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_retries_are_idempotent_and_new_server_progress_wins_conflicts(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $actor = User::factory()->create();
        $actor->assignRole('architect');
        $project = Project::factory()->create();
        $payload = ['operation_id' => (string) Str::uuid(), 'owner_id' => $actor->id, 'base_revision' => null, 'recorded_on' => today()->toDateString(), 'percent' => '25.00', 'note' => 'Local'];
        $this->actingAs($actor)->getJson(route('offline.projects'))->assertOk()->assertJsonPath('owner_id', $actor->id);
        $first = $this->postJson(route('offline.progress.store', $project), $payload)->assertOk()->json('id');
        $this->postJson(route('offline.progress.store', $project), $payload)->assertOk()->assertJsonPath('id', $first);
        $this->assertSame(1, SiteProgressUpdate::query()->count());
        $this->postJson(route('offline.progress.store', $project), array_replace($payload, ['note' => 'Changed']))->assertStatus(409);
        $this->postJson(route('offline.progress.store', $project), array_replace($payload, ['operation_id' => (string) Str::uuid()]))->assertStatus(409);
        $this->postJson(route('offline.progress.store', $project), array_replace($payload, ['operation_id' => (string) Str::uuid(), 'base_revision' => $first]))->assertOk();
        $this->assertSame(2, SiteProgressUpdate::query()->count());
        $this->postJson(route('offline.progress.store', $project), array_replace($payload, ['owner_id' => $actor->id + 1]))->assertForbidden();
    }

    public function test_only_authorized_staff_can_synchronize(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $project = Project::factory()->create();
        foreach (['client', 'contractor'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            $this->actingAs($user)->getJson(route('offline.projects'))->assertForbidden();
            $this->postJson(route('offline.progress.store', $project), [])->assertForbidden();
        }
        $this->assertDatabaseCount('offline_operations', 0);
    }
}

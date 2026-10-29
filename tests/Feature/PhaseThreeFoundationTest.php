<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Models\Client;
use App\Models\User;
use App\Services\ClientAdministration;
use App\Services\ProjectAdministration;
use App\Services\ProjectReferenceGenerator;
use App\Services\TunisianPhone;
use Database\Seeders\GeographySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhaseThreeFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_reference_sequence_is_transactional_and_resets_each_year(): void
    {
        $generator = app(ProjectReferenceGenerator::class);
        $this->travelTo(now('Africa/Tunis')->setDate(2026, 6, 1));
        $this->assertSame('2026-001', DB::transaction(fn () => $generator->next()));
        $this->assertSame('2026-002', DB::transaction(fn () => $generator->next()));
        $this->travelTo(now('Africa/Tunis')->setDate(2027, 6, 1));
        $this->assertSame('2027-001', DB::transaction(fn () => $generator->next()));
    }

    public function test_geography_seeding_is_idempotent(): void
    {
        $this->seed(GeographySeeder::class);
        $this->seed(GeographySeeder::class);
        $this->assertDatabaseCount('governorates', 24);
        $this->assertDatabaseCount('delegations', 279);
    }

    public function test_phone_normalization_rejects_other_country_codes(): void
    {
        $this->assertSame('+21620123456', TunisianPhone::normalize('20 123 456'));
        $this->assertSame('+21620123456', TunisianPhone::normalize('+216 20 123 456'));
        $this->assertNull(TunisianPhone::normalize('+33 20 123 456'));
    }

    public function test_creation_status_history_and_cascade_archive(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole('architect');
        $client = Client::factory()->create();
        $project = app(ProjectAdministration::class)->create(['client_id' => $client->id, 'name' => 'Villa', 'project_type' => 'villa', 'lead_architect_id' => $actor->id], $actor);
        $this->assertStringEndsWith('-001', $project->reference);
        $this->assertDatabaseHas('project_user', ['project_id' => $project->id, 'user_id' => $actor->id, 'project_role' => 'lead']);
        app(ProjectAdministration::class)->changeStatus($project, $actor, ProjectStatus::Permit, 'Consultation');
        $this->assertDatabaseHas('project_status_history', ['project_id' => $project->id, 'from_status' => 'sketch', 'to_status' => 'permit', 'comment' => 'Consultation']);
        app(ClientAdministration::class)->archive($client, $actor);
        $this->assertSoftDeleted('clients', ['id' => $client->id]);
        $this->assertSoftDeleted('projects', ['id' => $project->id]);
        app(ClientAdministration::class)->restore($client, $actor);
        $this->assertNotSoftDeleted('clients', ['id' => $client->id]);
        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }
}

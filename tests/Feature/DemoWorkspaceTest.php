<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\DemoWorkspaceSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TaxSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Tests\TestCase;

class DemoWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_accounts_and_access_are_explicit_and_do_not_overwrite_existing_data(): void
    {
        config(['demo.password' => 'local-demo-password-123']);
        $this->seed([RolePermissionSeeder::class, TaxSettingsSeeder::class]);
        $this->seed(DemoWorkspaceSeeder::class);
        $this->assertDatabaseCount('users', 5);
        $this->assertDatabaseCount('clients', 15);
        $this->assertDatabaseCount('projects', 30);
        $this->assertDatabaseHas('quotes', ['status' => 'draft']);
        $this->assertDatabaseHas('invoices', ['status' => 'draft']);
        $admin = User::where('email', 'demo-admin@example.test')->firstOrFail();
        $client = User::where('email', 'demo-client@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('local-demo-password-123', $admin->password));
        $this->assertTrue($client->hasRole('client'));
        $clientRecord = Client::where('email', 'demo-client-1@example.test')->firstOrFail();
        $this->assertDatabaseHas('client_portal_access', ['client_id' => $clientRecord->id, 'user_id' => $client->id, 'financial_access' => true]);
        $this->assertCount(2, Project::query()->visibleTo($client)->get());
        config(['demo.allow_existing_data' => true]);
        $this->expectException(LogicException::class);
        $this->seed(DemoWorkspaceSeeder::class);
    }

    public function test_demo_rejects_short_password(): void
    {
        config(['demo.password' => 'short']);
        $this->expectException(LogicException::class);
        $this->seed(DemoWorkspaceSeeder::class);
    }
    public function test_demo_can_be_added_without_changing_existing_records_after_explicit_acknowledgement(): void
    {
        $this->seed([RolePermissionSeeder::class, TaxSettingsSeeder::class]);
        $existing = User::factory()->create(['name' => 'Existing administrator']);
        $existing->assignRole('admin');
        $client = Client::factory()->create(['name' => 'Existing client']);
        $project = Project::factory()->create(['client_id' => $client->id]);
        config(['demo.password' => 'local-demo-password-123', 'demo.allow_existing_data' => true]);
        $this->seed(DemoWorkspaceSeeder::class);
        $this->assertDatabaseHas('users', ['id' => $existing->id, 'name' => 'Existing administrator']);
        $this->assertDatabaseHas('clients', ['id' => $client->id, 'name' => 'Existing client']);
        $this->assertDatabaseCount('projects', 31);
        $collaborator = User::where('email', 'demo-collaborator@example.test')->firstOrFail();
        $this->assertFalse($project->members()->where('users.id', $collaborator->id)->exists());
        $this->assertFalse(Project::query()->visibleTo($collaborator)->whereKey($project->id)->exists());
        $this->assertTrue(Project::query()->visibleTo(User::where('email', 'demo-admin@example.test')->firstOrFail())->whereKey($project->id)->exists());
    }

    public function test_existing_data_needs_acknowledgement_and_email_collisions_are_not_overwritten(): void
    {
        $this->seed([RolePermissionSeeder::class, TaxSettingsSeeder::class]);
        $existing = User::factory()->create(['email' => 'demo-admin@example.test']);
        config(['demo.password' => 'local-demo-password-123', 'demo.allow_existing_data' => false]);
        try {
            $this->seed(DemoWorkspaceSeeder::class);
            $this->fail('Expected explicit acknowledgement.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('DEMO_ALLOW_EXISTING_DATA', $exception->getMessage());
        }
        config(['demo.allow_existing_data' => true]);
        try {
            $this->seed(DemoWorkspaceSeeder::class);
            $this->fail('Expected collision to be rejected.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('already exists', $exception->getMessage());
        }
        $this->assertDatabaseCount('users', 1);
        $this->assertSame($existing->id, User::where('email', 'demo-admin@example.test')->firstOrFail()->id);
        $this->assertDatabaseCount('clients', 0);
    }
}

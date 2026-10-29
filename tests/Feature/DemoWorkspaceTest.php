<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\DemoWorkspaceSeeder;
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
        $this->seed(DemoWorkspaceSeeder::class);
        $this->assertDatabaseCount('users', 5);
        $this->assertDatabaseCount('clients', 15);
        $this->assertDatabaseCount('projects', 30);
        $admin = User::where('email', 'demo-admin@example.test')->firstOrFail();
        $client = User::where('email', 'demo-client@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('local-demo-password-123', $admin->password));
        $this->assertTrue($client->hasRole('client'));
        $clientRecord = Client::where('email', 'demo-client-1@example.test')->firstOrFail();
        $this->assertDatabaseHas('client_portal_access', ['client_id' => $clientRecord->id, 'user_id' => $client->id, 'financial_access' => true]);
        $this->assertCount(2, Project::query()->visibleTo($client)->get());
        $this->expectException(LogicException::class);
        $this->seed(DemoWorkspaceSeeder::class);
    }

    public function test_demo_rejects_short_password(): void
    {
        config(['demo.password' => 'short']);
        $this->expectException(LogicException::class);
        $this->seed(DemoWorkspaceSeeder::class);
    }
}

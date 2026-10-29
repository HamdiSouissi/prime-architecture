<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RealisticDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class RealisticDemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_realistic_dataset_links_users_projects_calendar_and_demo_finances(): void
    {
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        config(['demo.password' => 'local-demo-password-123']);
        $this->seed(RealisticDemoSeeder::class);
        $this->assertDatabaseCount('clients', 15);
        $this->assertDatabaseCount('projects', 30);
        $this->assertDatabaseCount('tasks', 150);
        $this->assertDatabaseCount('milestones', 30);
        $this->assertDatabaseCount('quotes', 31);
        $this->assertDatabaseCount('invoices', 31);
        $this->assertDatabaseCount('payments', 20);
        $this->assertDatabaseHas('tasks', ['status' => 'done']);
        $this->assertDatabaseHas('tasks', ['status' => 'todo']);
        $this->assertDatabaseHas('invoices', ['status' => 'paid']);
        $this->assertDatabaseHas('invoices', ['status' => 'partially_paid']);
        $this->assertDatabaseHas('tax_settings', ['seed_key' => 'indicative-initial', 'is_verified' => false]);
        $project = Project::query()->orderBy('id')->firstOrFail();
        $quote = Quote::where('project_id', $project->id)->whereNotNull('issued_at')->firstOrFail();
        $invoice = Invoice::where('project_id', $project->id)->whereNotNull('issued_at')->firstOrFail();
        $this->assertTrue($quote->snapshot['demo_only']);
        $this->assertTrue($invoice->snapshot['demo_only']);
        $this->assertStringStartsWith('DEMO-', $quote->number);
        $this->assertNotNull($project->description);
        $this->assertCount(5, Task::where('project_id', $project->id)->get());
        $admin = User::where('email', 'demo-admin@example.test')->firstOrFail();
        $client = User::where('email', 'demo-client@example.test')->firstOrFail();
        $collaborator = User::where('email', 'demo-collaborator@example.test')->firstOrFail();
        $this->actingAs($admin)->get(route('planning.index', [$project, 'view' => 'calendar']))->assertOk()->assertSee('Démo — Plans et coordination');
        $this->actingAs($collaborator)->get(route('planning.index', [$project, 'view' => 'calendar']))->assertOk();
        $this->actingAs($client)->get(route('portal.finance', $project))->assertOk()->assertSee($quote->number)->assertSee($invoice->number);
        $this->assertSame(2, Project::query()->visibleTo($client)->count());
        $this->expectException(LogicException::class);
        $this->seed(RealisticDemoSeeder::class);
    }

    public function test_realistic_demo_refuses_existing_business_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        Client::factory()->create();
        config(['demo.password' => 'local-demo-password-123', 'demo.allow_existing_data' => true]);
        $this->expectException(LogicException::class);
        $this->seed(RealisticDemoSeeder::class);
    }
}

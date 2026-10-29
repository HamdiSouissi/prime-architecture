<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\PlanningService;
use App\Services\ProjectAdministration;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\WorkingCalendarSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PlanningWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_access_rules_prevent_idor_and_client_data_leaks(): void
    {
        $this->withoutVite();
        $this->seed([RolePermissionSeeder::class, WorkingCalendarSeeder::class]);
        Notification::fake();
        $architect = User::factory()->create();
        $architect->assignRole('architect');
        $project = app(ProjectAdministration::class)->create(['name' => 'Project', 'project_type' => 'villa', 'client_id' => Client::factory()->create()->id, 'lead_architect_id' => $architect->id], $architect);
        $client = User::factory()->create();
        $client->assignRole('client');
        $project->client->update(['user_id' => $client->id]);
        $member = User::factory()->create();
        $member->assignRole('collaborator');
        $project->members()->attach($member->id, ['project_role' => 'collaborator']);
        $viewer = User::factory()->create();
        $viewer->assignRole('collaborator');
        $project->members()->attach($viewer->id, ['project_role' => 'viewer']);
        $service = app(PlanningService::class);
        $visible = $service->createTask($project, $architect, ['title' => 'Public task', 'status' => 'todo', 'priority' => 'normal', 'visible_to_client' => true, 'estimated_hours' => 9876, 'start_date' => '2026-11-02', 'due_date' => '2026-11-03'], [$member->id]);
        $private = $service->createTask($project, $architect, ['title' => 'Private task', 'status' => 'todo', 'priority' => 'normal', 'start_date' => '2026-11-02', 'due_date' => '2026-11-03'], []);
        $visible->comments()->create(['user_id' => $architect->id, 'body' => 'SECRET COMMENT']);
        Milestone::factory()->create(['project_id' => $project->id, 'title' => 'Public milestone', 'notes' => 'SECRET MILESTONE']);
        $this->actingAs($client)->get(route('planning.index', $project))->assertOk()->assertSee('Public task')->assertSee('Public milestone')->assertDontSee('Private task')->assertDontSee('SECRET MILESTONE');
        $this->get(route('planning.tasks.show', [$project, $visible]))->assertOk()->assertDontSee('SECRET COMMENT')->assertDontSee('9876');
        $this->get(route('planning.tasks.show', [$project, $private]))->assertForbidden();
        $this->get(route('planning.gantt', $project))->assertOk()->assertDontSee('Private task')->assertDontSee('9876')->assertDontSee('SECRET COMMENT');
        $this->post(route('planning.tasks.comments.store', [$project, $visible]), ['body' => 'Client comment'])->assertForbidden();
        $this->actingAs($viewer)->post(route('planning.tasks.status', [$project, $visible]), ['status' => 'done'])->assertForbidden();
        $this->actingAs($member)->post(route('planning.tasks.status', [$project, $private]), ['status' => 'done'])->assertForbidden();
        $this->post(route('planning.tasks.status', [$project, $visible]), ['status' => 'done'])->assertSessionHasNoErrors();
        $this->assertNotNull($visible->fresh()->completed_at);
        $outsider = User::factory()->create();
        $outsider->assignRole('collaborator');
        $this->actingAs($outsider)->get(route('planning.index', $project))->assertForbidden();
        $contractor = User::factory()->create();
        $contractor->assignRole('contractor');
        $this->actingAs($contractor)->get(route('planning.index', $project))->assertForbidden();
        $other = Project::factory()->create();
        $this->actingAs($architect)->get(route('planning.tasks.show', [$other, $visible]))->assertForbidden();
    }

    public function test_web_preview_does_not_move_until_confirmed(): void
    {
        $this->withoutVite();
        $this->seed([RolePermissionSeeder::class, WorkingCalendarSeeder::class]);
        $architect = User::factory()->create();
        $architect->assignRole('architect');
        $project = app(ProjectAdministration::class)->create(['name' => 'Project', 'project_type' => 'villa', 'client_id' => Client::factory()->create()->id, 'lead_architect_id' => $architect->id], $architect);
        $task = Task::factory()->create(['project_id' => $project->id, 'start_date' => '2026-11-02', 'due_date' => '2026-11-03']);
        $response = $this->actingAs($architect)->post(route('planning.tasks.move.preview', [$project, $task]), ['start_date' => '2026-11-03', 'due_date' => '2026-11-04'])->assertOk();
        $this->assertSame('2026-11-02', $task->fresh()->start_date->toDateString());
        $token = $response->viewData('preview')['token'];
        $this->post(route('planning.tasks.move.confirm', [$project, $task]), ['start_date' => '2026-11-03', 'due_date' => '2026-11-04', 'token' => $token])->assertSessionHasNoErrors();
        $this->assertSame('2026-11-03', $task->fresh()->start_date->toDateString());
    }
    public function test_my_tasks_are_grouped_by_due_date(): void
    {
        $this->withoutVite();
        $this->seed([RolePermissionSeeder::class, WorkingCalendarSeeder::class]);
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-04 10:00:00', 'Africa/Tunis'));
        $architect = User::factory()->create();
        $architect->assignRole('architect');
        $project = app(ProjectAdministration::class)->create(['name' => 'Project', 'project_type' => 'villa', 'client_id' => Client::factory()->create()->id, 'lead_architect_id' => $architect->id], $architect);
        $service = app(PlanningService::class);
        foreach (['Late' => '2026-11-03', 'Today' => '2026-11-04', 'Week' => '2026-11-06', 'Later' => '2026-11-10'] as $title => $date) {
            $service->createTask($project, $architect, ['title' => $title, 'status' => 'todo', 'priority' => 'normal', 'due_date' => $date], [$architect->id]);
        }
        $groups = $this->actingAs($architect)->get(route('planning.my-tasks'))->assertOk()->viewData('groups');
        $this->assertSame('Late', $groups['overdue']->first()->title);
        $this->assertSame('Today', $groups['today']->first()->title);
        $this->assertSame('Week', $groups['this_week']->first()->title);
        $this->assertSame('Later', $groups['later']->first()->title);
    }
}

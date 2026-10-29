<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Milestone;
use App\Models\PlanningTemplate;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\PlanningService;
use App\Services\ProjectAdministration;
use Carbon\CarbonImmutable;
use Database\Seeders\PlanningTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\WorkingCalendarSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
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
        $viewerComment = $visible->comments()->create(['user_id' => $viewer->id, 'body' => 'Existing viewer comment']);
        $this->actingAs($viewer)->delete(route('planning.tasks.comments.destroy', [$project, $visible, $viewerComment]))->assertForbidden();
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
        $this->travelTo(CarbonImmutable::parse('2026-11-04 10:00:00', 'Africa/Tunis'));
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

    public function test_calendar_permissions_and_workload_allocation(): void
    {
        $this->withoutVite();
        $this->seed([RolePermissionSeeder::class, WorkingCalendarSeeder::class]);
        Notification::fake();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $architect = User::factory()->create();
        $architect->assignRole('architect');
        $collaborator = User::factory()->create();
        $collaborator->assignRole('collaborator');
        $project = app(ProjectAdministration::class)->create(['name' => 'Project', 'project_type' => 'villa', 'client_id' => Client::factory()->create()->id, 'lead_architect_id' => $architect->id], $architect);
        $project->members()->attach($collaborator->id, ['project_role' => 'collaborator']);
        $this->actingAs($architect)->get(route('planning.calendar-admin'))->assertForbidden();
        $this->actingAs($admin)->put(route('planning.calendar.days'), [])->assertSessionHasErrors('working_days');
        $this->put(route('planning.calendar.days'), ['working_days' => [1, 2, 3, 4, 5, 6]])->assertSessionHasNoErrors();
        $this->post(route('planning.calendar.holidays.store'), ['date' => '2026-11-03', 'name_fr' => 'Test', 'name_ar' => 'اختبار'])->assertSessionHasNoErrors();
        $task = app(PlanningService::class)->createTask($project, $architect, ['title' => 'Hours', 'status' => 'todo', 'priority' => 'normal', 'estimated_hours' => 40, 'start_date' => '2026-11-02', 'due_date' => '2026-11-07'], [$architect->id, $collaborator->id]);
        $response = $this->actingAs($architect)->get(route('planning.workload', [$project, 'start' => '2026-11-02', 'weeks' => 1]))->assertOk();
        $hours = $response->viewData('hours');
        $this->assertEqualsWithDelta(40, $hours[$architect->id]['2026-11-02'], 0.01);
        $this->assertEqualsWithDelta(40, $hours[$collaborator->id]['2026-11-02'], 0.01);
        $this->actingAs($collaborator)->get(route('planning.workload', $project))->assertForbidden();
        $this->assertSame('Hours', $task->title);
    }

    public function test_template_preview_is_invalidated_when_an_item_changes(): void
    {
        $this->withoutVite();
        $this->seed([RolePermissionSeeder::class, WorkingCalendarSeeder::class, PlanningTemplateSeeder::class]);
        $architect = User::factory()->create();
        $architect->assignRole('architect');
        $project = app(ProjectAdministration::class)->create(['name' => 'Project', 'project_type' => 'villa', 'client_id' => Client::factory()->create()->id, 'lead_architect_id' => $architect->id], $architect);
        $template = PlanningTemplate::where('key', 'demo-villa')->firstOrFail();
        $item = $template->items()->firstOrFail();
        $response = $this->actingAs($architect)->post(route('planning.templates.preview', [$project, $template]), ['start' => '2026-11-02', 'locale' => 'fr'])->assertOk();
        $token = $response->viewData('preview')['token'];
        $this->assertSame(0, $project->tasks()->count());
        $this->put(route('planning.templates.items.update', [$template, $item]), ['title_fr' => 'Nouveau titre', 'title_ar' => $item->title_ar, 'type' => $item->type, 'offset_workdays' => $item->offset_workdays, 'duration_workdays' => $item->duration_workdays, 'sort_order' => $item->sort_order])->assertSessionHasNoErrors();
        $this->post(route('planning.templates.apply', [$project, $template]), ['start' => '2026-11-02', 'locale' => 'fr', 'token' => $token])->assertSessionHasErrors('token');
        $this->assertSame(0, $project->tasks()->count());
    }

    public function test_archived_tasks_can_be_restored_and_reassigned(): void
    {
        $this->withoutVite();
        $this->seed([RolePermissionSeeder::class, WorkingCalendarSeeder::class]);
        Notification::fake();
        $architect = User::factory()->create();
        $architect->assignRole('architect');
        $member = User::factory()->create();
        $member->assignRole('collaborator');
        $project = app(ProjectAdministration::class)->create(['name' => 'Project', 'project_type' => 'villa', 'client_id' => Client::factory()->create()->id, 'lead_architect_id' => $architect->id], $architect);
        $project->members()->attach($member->id, ['project_role' => 'collaborator']);
        $task = app(PlanningService::class)->createTask($project, $architect, ['title' => 'Task', 'status' => 'todo', 'priority' => 'normal'], [$member->id]);
        $this->actingAs($architect)->delete(route('planning.tasks.destroy', [$project, $task]))->assertSessionHasNoErrors();
        $this->assertSoftDeleted($task);
        $this->delete(route('projects.members.destroy', [$project, $member]))->assertSessionHasErrors('member');
        $this->post(route('planning.tasks.restore', [$project, $task]))->assertSessionHasNoErrors();
        app(PlanningService::class)->updateTask($task->fresh(), $architect, [], []);
        $this->delete(route('projects.members.destroy', [$project, $member]))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('project_user', ['project_id' => $project->id, 'user_id' => $member->id]);
    }

    public function test_template_application_requires_permissions_for_each_created_kind(): void
    {
        $this->withoutVite();
        $this->seed([RolePermissionSeeder::class, WorkingCalendarSeeder::class, PlanningTemplateSeeder::class]);
        $architect = User::factory()->create();
        $architect->assignRole('architect');
        $project = app(ProjectAdministration::class)->create(['name' => 'Project', 'project_type' => 'villa', 'client_id' => Client::factory()->create()->id, 'lead_architect_id' => $architect->id], $architect);
        $template = PlanningTemplate::where('key', 'demo-villa')->firstOrFail();
        $role = Role::findByName('architect');
        $role->revokePermissionTo('tasks.create');
        $this->actingAs($architect->fresh())->post(route('planning.templates.preview', [$project, $template]), ['start' => '2026-11-02', 'locale' => 'fr'])->assertForbidden();
        $role->givePermissionTo('tasks.create');
        $role->revokePermissionTo('milestones.manage');
        $this->actingAs($architect->fresh())->post(route('planning.templates.preview', [$project, $template]), ['start' => '2026-11-02', 'locale' => 'fr'])->assertForbidden();
        $this->assertSame(0, $project->tasks()->count());
        $this->assertSame(0, $project->milestones()->count());
    }

    public function test_milestones_are_paginated_without_silently_losing_later_items(): void
    {
        $this->withoutVite();
        $this->seed([RolePermissionSeeder::class, WorkingCalendarSeeder::class]);
        $architect = User::factory()->create();
        $architect->assignRole('architect');
        $project = app(ProjectAdministration::class)->create(['name' => 'Project', 'project_type' => 'villa', 'client_id' => Client::factory()->create()->id, 'lead_architect_id' => $architect->id], $architect);
        Milestone::factory()->count(51)->create(['project_id' => $project->id]);
        $response = $this->actingAs($architect)->get(route('planning.index', $project))->assertOk();
        $this->assertSame(51, $response->viewData('milestones')->total());
        $this->assertCount(50, $response->viewData('milestones')->items());
        $second = $this->get(route('planning.index', [$project, 'milestones_page' => 2]))->assertOk();
        $this->assertCount(1, $second->viewData('milestones')->items());
    }
}

<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Milestone;
use App\Models\Task;
use App\Models\User;
use App\Notifications\PlanningDigestNotification;
use App\Notifications\PlanningEventNotification;
use App\Services\PlanningService;
use App\Services\ProjectAdministration;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\WorkingCalendarSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlanningNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminders_have_the_right_recipients_and_are_idempotent(): void
    {
        $this->seed([RolePermissionSeeder::class, WorkingCalendarSeeder::class]);
        Notification::fake();
        $architect = User::factory()->create();
        $architect->assignRole('architect');
        $collaborator = User::factory()->create();
        $collaborator->assignRole('collaborator');
        $outsider = User::factory()->create();
        $outsider->assignRole('collaborator');
        $project = app(ProjectAdministration::class)->create(['name' => 'Test', 'project_type' => 'villa', 'client_id' => Client::factory()->create()->id, 'lead_architect_id' => $architect->id], $architect);
        $project->members()->attach($collaborator->id, ['project_role' => 'collaborator']);
        $task = app(PlanningService::class)->createTask($project, $architect, ['title' => 'Tomorrow', 'status' => 'todo', 'priority' => 'normal', 'due_date' => now(config('planning.timezone'))->addDay()->toDateString()], [$collaborator->id]);
        Notification::assertSentTo($collaborator, PlanningEventNotification::class);
        Milestone::factory()->create(['project_id' => $project->id, 'due_date' => now(config('planning.timezone'))->addDays(2)->toDateString()]);
        $this->artisan('planning:remind')->assertSuccessful();
        $this->artisan('planning:remind')->assertSuccessful();
        Notification::assertSentTo($collaborator, PlanningDigestNotification::class, function ($notification) use ($task) {
            return count($notification->tasks) === 1 && $notification->tasks[0]['id'] === $task->id && count($notification->milestones) === 1;
        });
        Notification::assertSentToTimes($collaborator, PlanningDigestNotification::class, 1);
        Notification::assertNotSentTo($outsider, PlanningDigestNotification::class);
        $this->assertDatabaseHas('planning_daily_digests', ['user_id' => $collaborator->id, 'status' => 'sent']);
        $collaborator->forceFill(['planning_email_enabled' => false])->save();
        $notification = new PlanningDigestNotification(now()->toDateString(), [], []);
        $this->assertSame(['database'], $notification->via($collaborator));
    }

    public function test_notification_centre_is_private_and_respects_current_task_access(): void
    {
        $this->withoutVite();
        $this->seed([RolePermissionSeeder::class, WorkingCalendarSeeder::class]);
        $architect = User::factory()->create();
        $architect->assignRole('architect');
        $member = User::factory()->create();
        $member->assignRole('collaborator');
        $other = User::factory()->create();
        $other->assignRole('collaborator');
        $project = app(ProjectAdministration::class)->create(['name' => 'Project', 'project_type' => 'villa', 'client_id' => Client::factory()->create()->id, 'lead_architect_id' => $architect->id], $architect);
        $project->members()->attach($member->id, ['project_role' => 'collaborator']);
        $task = Task::factory()->create(['project_id' => $project->id, 'title' => 'Private title']);
        $id = (string) Str::uuid();
        $member->notifications()->create(['id' => $id, 'type' => PlanningEventNotification::class, 'data' => ['kind' => 'assigned', 'project_id' => $project->id, 'task_id' => $task->id]]);
        $this->actingAs($other)->post(route('planning.notifications.read', $id))->assertNotFound();
        $this->actingAs($member)->get(route('planning.notifications'))->assertOk()->assertSee('Private title');
        $digest = new PlanningDigestNotification(now()->toDateString(), [['id' => $task->id, 'project_id' => $project->id]], []);
        $this->assertStringContainsString('Private title', implode(' ', $digest->toMail($member)->introLines));
        $this->post(route('planning.notifications.preference'), ['planning_email_enabled' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse($member->fresh()->planning_email_enabled);
        $project->members()->detach($member->id);
        $this->get(route('planning.notifications'))->assertOk()->assertDontSee('Private title');
        $this->assertStringNotContainsString('Private title', implode(' ', $digest->toMail($member)->introLines));
        $this->post(route('planning.notifications.read', $id))->assertRedirect();
        $this->assertNotNull($member->notifications()->findOrFail($id)->read_at);
    }

    public function test_followers_receive_status_and_comment_events(): void
    {
        $this->seed([RolePermissionSeeder::class, WorkingCalendarSeeder::class]);
        Notification::fake();
        $architect = User::factory()->create();
        $architect->assignRole('architect');
        $member = User::factory()->create();
        $member->assignRole('collaborator');
        $project = app(ProjectAdministration::class)->create(['name' => 'Project', 'project_type' => 'villa', 'client_id' => Client::factory()->create()->id, 'lead_architect_id' => $architect->id], $architect);
        $project->members()->attach($member->id, ['project_role' => 'collaborator']);
        $service = app(PlanningService::class);
        $task = $service->createTask($project, $architect, ['title' => 'Task', 'status' => 'todo', 'priority' => 'normal'], [$member->id]);
        $service->updateTask($task, $architect, ['status' => 'in_progress'], null);
        $this->actingAs($architect)->post(route('planning.tasks.comments.store', [$project, $task]), ['body' => 'New comment'])->assertSessionHasNoErrors();
        Notification::assertSentTo($member, PlanningEventNotification::class, fn ($notification) => $notification->kind === 'assigned');
        Notification::assertSentTo($member, PlanningEventNotification::class, fn ($notification) => $notification->kind === 'status');
        Notification::assertSentTo($member, PlanningEventNotification::class, fn ($notification) => $notification->kind === 'comment');
        $this->actingAs($member)->delete(route('planning.tasks.unfollow', [$project, $task]))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('task_followers', ['task_id' => $task->id, 'user_id' => $member->id]);
    }
}

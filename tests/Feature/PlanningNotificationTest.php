<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Milestone;
use App\Models\User;
use App\Notifications\PlanningDigestNotification;
use App\Notifications\PlanningEventNotification;
use App\Services\PlanningService;
use App\Services\ProjectAdministration;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\WorkingCalendarSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
}

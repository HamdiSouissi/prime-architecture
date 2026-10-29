<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\PlanningService;
use App\Services\PlanningTemplateService;
use App\Models\PlanningTemplate;
use App\Services\ProjectAdministration;
use App\Services\WorkingCalendar;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\PlanningTemplateSeeder;
use Database\Seeders\WorkingCalendarSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PlanningFoundationTest extends TestCase
{
    use RefreshDatabase;

    private User $architect;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, WorkingCalendarSeeder::class]);
        $this->architect = User::factory()->create();
        $this->architect->assignRole('architect');
        $this->project = app(ProjectAdministration::class)->create(['name' => 'Test', 'project_type' => 'villa', 'client_id' => Client::factory()->create()->id, 'lead_architect_id' => $this->architect->id], $this->architect);
    }

    public function test_calendar_observes_weekends_holidays_and_changes(): void
    {
        $start = CarbonImmutable::parse('2026-10-30');
        $calendar = new WorkingCalendar;
        $this->assertSame('2026-11-02', $calendar->addWorkingDays($start, 1)->toDateString());
        DB::table('holidays')->insert(['date' => '2026-11-02', 'name_fr' => 'Test', 'name_ar' => 'اختبار', 'is_recurring_yearly' => false]);
        $calendar = new WorkingCalendar;
        $this->assertSame('2026-11-03', $calendar->addWorkingDays($start, 1)->toDateString());
        $this->assertSame(2, $calendar->workingDaysInclusive($start, CarbonImmutable::parse('2026-11-03')));
        DB::table('working_calendar_days')->where('iso_weekday', 6)->update(['is_working' => true]);
        $this->assertSame('2026-10-31', (new WorkingCalendar)->addWorkingDays($start, 1)->toDateString());
    }

    public function test_direct_and_indirect_cycles_are_rejected(): void
    {
        [$a, $b, $c] = Task::factory()->count(3)->create(['project_id' => $this->project->id])->all();
        $service = app(PlanningService::class);
        $service->addDependency($b, $a, $this->architect);
        $service->addDependency($c, $b, $this->architect);
        try {
            $service->addDependency($a, $a, $this->architect);
            $this->fail('A self dependency was accepted.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('predecessor', $error->errors());
        }
        $this->expectException(ValidationException::class);
        $service->addDependency($a, $c, $this->architect);
    }

    public function test_cascade_requires_confirmation_and_rejects_stale_preview(): void
    {
        $a = Task::factory()->create(['project_id' => $this->project->id, 'start_date' => '2026-11-02', 'due_date' => '2026-11-03']);
        $b = Task::factory()->create(['project_id' => $this->project->id, 'start_date' => '2026-11-04', 'due_date' => '2026-11-05']);
        $c = Task::factory()->create(['project_id' => $this->project->id, 'start_date' => '2026-11-06', 'due_date' => '2026-11-09']);
        $service = app(PlanningService::class);
        $service->addDependency($b, $a, $this->architect);
        $service->addDependency($c, $b, $this->architect);
        $preview = $service->previewMove($a, $this->architect, '2026-11-03', '2026-11-05');
        $this->assertSame('2026-11-06', $preview['changes'][$b->id]['start_date']);
        $this->assertSame('2026-11-09', $preview['changes'][$b->id]['due_date']);
        $this->assertSame('2026-11-10', $preview['changes'][$c->id]['start_date']);
        $this->assertSame('2026-11-02', $a->fresh()->start_date->toDateString());
        DB::table('holidays')->insert(['date' => '2026-11-09', 'name_fr' => 'Test', 'name_ar' => 'اختبار', 'is_recurring_yearly' => false]);
        try {
            $service->confirmMove($a, $this->architect, '2026-11-03', '2026-11-05', $preview['token']);
            $this->fail('Stale preview was accepted.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('token', $error->errors());
        }
        $this->assertSame('2026-11-02', $a->fresh()->start_date->toDateString());
        $preview = $service->previewMove($a, $this->architect, '2026-11-03', '2026-11-05');
        $service->confirmMove($a, $this->architect, '2026-11-03', '2026-11-05', $preview['token']);
        $this->assertSame('2026-11-03', $a->fresh()->start_date->toDateString());
        $this->assertSame('2026-11-10', $b->fresh()->due_date->toDateString());
    }

    public function test_applying_template_respects_calendar_and_dependencies(): void
    {
        $this->seed(PlanningTemplateSeeder::class);
        $this->seed(PlanningTemplateSeeder::class);
        $template = PlanningTemplate::where('key', 'demo-villa')->firstOrFail();
        $this->assertCount(5, $template->items);
        DB::table('holidays')->insert(['date' => '2026-11-02', 'name_fr' => 'Test', 'name_ar' => 'اختبار', 'is_recurring_yearly' => false]);
        $service = app(PlanningTemplateService::class);
        $preview = $service->preview($this->project, $template, $this->architect, '2026-10-30', 'fr');
        $this->assertSame(0, $this->project->tasks()->count());
        $rows = array_values($preview['items']);
        $this->assertSame('2026-10-30', $rows[0]['start_date']);
        $this->assertSame('2026-11-06', $rows[0]['due_date']);
        $this->assertSame('2026-11-09', $rows[1]['start_date']);
        $service->apply($this->project, $template, $this->architect, '2026-10-30', 'fr', $preview['token']);
        $this->assertSame(4, $this->project->tasks()->count());
        $this->assertSame(1, $this->project->milestones()->count());
        $this->assertSame(3, DB::table('task_dependencies')->where('project_id', $this->project->id)->count());
    }

    public function test_status_completion_and_assignment_membership(): void
    {
        $service = app(PlanningService::class);
        $task = $service->createTask($this->project, $this->architect, ['title' => 'Task', 'status' => 'todo', 'priority' => 'normal'], [$this->architect->id]);
        $service->updateTask($task, $this->architect, ['status' => 'done'], null);
        $this->assertNotNull($task->fresh()->completed_at);
        $this->assertSame(TaskStatus::Done, $task->fresh()->status);
        $service->updateTask($task, $this->architect, ['status' => 'in_progress'], null);
        $this->assertNull($task->fresh()->completed_at);
        $outsider = User::factory()->create();
        $this->expectException(ValidationException::class);
        $service->updateTask($task, $this->architect, [], [$outsider->id]);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\SiteReportStatus;
use App\Enums\SnagStatus;
use App\Enums\TaskStatus;
use App\Models\Client;
use App\Models\Project;
use App\Models\SitePhoto;
use App\Models\SiteReport;
use App\Models\Snag;
use App\Models\Task;
use App\Models\User;
use App\Services\SiteProgressService;
use App\Services\SiteSupervisionService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhaseEightTest extends TestCase
{
    use RefreshDatabase;

    private string $disk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->disk = (string) config('site.disk');
        Storage::fake($this->disk);
        $this->seed(RolePermissionSeeder::class);
    }

    private function project(): Project
    {
        return Project::factory()->create(['client_id' => Client::factory()->create()->id]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function member(Project $project, string $role = 'collaborator'): User
    {
        $user = $this->userWithRole($role);
        $project->members()->attach($user->id, ['project_role' => 'collaborator']);

        return $user;
    }

    private function draftReport(Project $project, User $actor, array $data = []): SiteReport
    {
        $this->actingAs($actor)->post(route('site.reports.store', $project), $data + [
            'meeting_date' => today()->toDateString(),
            'agenda' => "1. Présents\n2. Constats",
            'decisions' => 'Reprise des travaux le lundi.',
        ])->assertSessionHasNoErrors();

        return SiteReport::query()->latest('id')->firstOrFail();
    }

    public function test_a_report_is_numbered_then_frozen_when_finalised(): void
    {
        $this->withoutVite();
        $project = $this->project();
        $architect = $this->member($project, 'architect');

        $first = $this->draftReport($project, $architect);
        $this->assertSame('CR-'.now()->format('Y').'-0001', $first->number);
        $this->assertSame(SiteReportStatus::Draft, $first->status);

        $second = $this->draftReport($project, $architect, ['meeting_date' => today()->subWeek()->toDateString()]);
        $this->assertSame('CR-'.now()->format('Y').'-0002', $second->number);

        $this->actingAs($architect)->put(route('site.reports.update', [$project, $first]), [
            'meeting_date' => today()->toDateString(),
            'agenda' => 'Ordre du jour corrigé',
            'decisions' => 'Décision corrigée',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Décision corrigée', $first->fresh()?->decisions);

        $this->actingAs($architect)->post(route('site.reports.finalize', [$project, $first]))
            ->assertSessionHasNoErrors();
        $final = $first->fresh();
        $this->assertSame(SiteReportStatus::Final, $final?->status);
        $this->assertNotNull($final?->finalized_at);

        $this->actingAs($architect)->put(route('site.reports.update', [$project, $first]), [
            'meeting_date' => today()->toDateString(),
            'decisions' => 'Réécriture après finalisation',
        ])->assertSessionHasErrors('report');
        $this->assertSame('Décision corrigée', $first->fresh()?->decisions);

        $this->actingAs($architect)->delete(route('site.reports.destroy', [$project, $first]))->assertForbidden();
    }

    public function test_clients_only_read_finalised_reports(): void
    {
        $this->withoutVite();
        $project = $this->project();
        $architect = $this->member($project, 'architect');
        $client = $this->userWithRole('client');
        $project->client->update(['user_id' => $client->id]);

        $draft = $this->draftReport($project, $architect);

        $this->actingAs($client)->get(route('site.index', $project))->assertOk()
            ->assertDontSee($draft->number);
        $this->actingAs($client)->get(route('site.reports.show', [$project, $draft]))->assertForbidden();
        $this->actingAs($client)->post(route('site.reports.store', $project), [
            'meeting_date' => today()->toDateString(),
            'decisions' => 'Tentative',
        ])->assertForbidden();

        $this->actingAs($architect)->post(route('site.reports.finalize', [$project, $draft]))->assertSessionHasNoErrors();
        $this->actingAs($client)->get(route('site.reports.show', [$project, $draft]))->assertOk()
            ->assertSee($draft->number)
            ->assertSee(__('phase8.decisions'));
        $this->actingAs($client)->get(route('site.index', $project))->assertOk()->assertSee($draft->number);
    }

    public function test_snags_follow_open_fixed_validated_and_lock_themselves(): void
    {
        $this->withoutVite();
        $project = $this->project();
        $architect = $this->member($project, 'architect');
        $collaborator = $this->member($project);
        $report = $this->draftReport($project, $architect);

        $this->actingAs($collaborator)->post(route('site.snags.store', $project), [
            'title' => 'Fissure en façade sud',
            'severity' => 'major',
            'site_report_id' => $report->id,
            'responsible' => 'Entreprise Gros Œuvre',
            'due_on' => today()->addDays(7)->toDateString(),
        ])->assertSessionHasNoErrors();

        $snag = Snag::query()->firstOrFail();
        $this->assertSame('R-'.now()->format('Y').'-0001', $snag->reference);
        $this->assertSame(SnagStatus::Open, $snag->status);
        $this->assertSame($report->id, $snag->site_report_id);

        $this->actingAs($collaborator)->post(route('site.snags.status', [$project, $snag]), ['status' => 'fixed'])
            ->assertSessionHasNoErrors();
        $this->assertSame(SnagStatus::Fixed, $snag->fresh()?->status);
        $this->assertNotNull($snag->fresh()?->fixed_at);

        // Validation is reserved to the site lead.
        $this->actingAs($collaborator)->post(route('site.snags.status', [$project, $snag]), ['status' => 'validated'])
            ->assertForbidden();
        $this->actingAs($architect)->post(route('site.snags.status', [$project, $snag]), ['status' => 'validated'])
            ->assertSessionHasNoErrors();
        $validated = $snag->fresh();
        $this->assertSame(SnagStatus::Validated, $validated?->status);
        $this->assertSame($architect->id, $validated?->validated_by);

        // A validated snag is frozen and cannot be deleted.
        $this->actingAs($architect)->put(route('site.snags.update', [$project, $snag]), [
            'title' => 'Réécriture', 'severity' => 'minor',
        ])->assertForbidden();
        $this->actingAs($architect)->delete(route('site.snags.destroy', [$project, $snag]))->assertForbidden();

        // The policy refuses any change on a validated snag, and the service
        // refuses an impossible transition even if it were called directly.
        $this->actingAs($architect)->post(route('site.snags.status', [$project, $snag]), ['status' => 'fixed'])
            ->assertForbidden();
        $this->expectException(ValidationException::class);
        app(SiteSupervisionService::class)->transition($snag->fresh() ?? $snag, $architect, SnagStatus::Fixed);
    }

    public function test_progress_is_declared_and_compared_with_the_planning(): void
    {
        $this->withoutVite();
        $project = $this->project();
        $architect = $this->member($project, 'architect');
        $collaborator = $this->member($project);

        Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Done, 'estimated_hours' => 8]);
        Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Todo, 'estimated_hours' => 8]);
        Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Cancelled, 'estimated_hours' => 100]);

        $this->assertSame('50.00', app(SiteProgressService::class)->computed($project));

        $this->actingAs($collaborator)->post(route('site.progress.store', $project), [
            'recorded_on' => today()->toDateString(),
            'percent' => '60',
        ])->assertForbidden();

        $this->actingAs($architect)->post(route('site.progress.store', $project), [
            'recorded_on' => today()->toDateString(),
            'percent' => '60',
            'note' => 'Gros œuvre en cours',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('site_progress_updates', [
            'project_id' => $project->id,
            'percent' => '60.00',
            'computed_percent' => '50.00',
        ]);

        $this->actingAs($architect)->get(route('site.index', $project))
            ->assertOk()
            ->assertSee(__('phase8.progress'))
            ->assertSee('50.00');
    }

    public function test_photos_are_reencoded_and_visibility_follows_publication(): void
    {
        $this->withoutVite();
        $project = $this->project();
        $architect = $this->member($project, 'architect');
        $client = $this->userWithRole('client');
        $project->client->update(['user_id' => $client->id]);
        $report = $this->draftReport($project, $architect);

        $this->actingAs($architect)->post(route('site.photos.store', $project), [
            'photo' => UploadedFile::fake()->image('chantier.jpg', 2000, 1200),
            'caption' => 'Ferraillage dalle R+1',
            'taken_on' => today()->toDateString(),
            'site_report_id' => $report->id,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $photo = SitePhoto::query()->firstOrFail();
        $this->assertSame('image/jpeg', $photo->mime_type);
        $this->assertLessThanOrEqual(1600, $photo->width);
        $this->assertTrue(Storage::disk($this->disk)->exists($photo->path));
        $this->assertTrue(Storage::disk($this->disk)->exists((string) $photo->thumbnail_path));
        $this->assertStringStartsWith("\xFF\xD8", (string) Storage::disk($this->disk)->get($photo->path));

        // Draft working photos stay internal.
        $this->actingAs($client)->get(route('site.index', $project))->assertOk()
            ->assertDontSee(__('phase8.upload_photo'), false);
        $this->actingAs($client)->get(route('site.photos.show', [$project, $photo]))->assertForbidden();
        $this->actingAs($client)->post(route('site.photos.store', $project), [
            'photo' => UploadedFile::fake()->image('client.jpg', 100, 100),
        ])->assertForbidden();

        $this->actingAs($architect)->post(route('site.reports.finalize', [$project, $report]))->assertSessionHasNoErrors();

        $this->actingAs($client)->get(route('site.photos.thumbnail', [$project, $photo]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($client)->get(route('site.photos.show', [$project, $photo]))->assertOk();

        // A photo published with a finalised report belongs to the archive.
        $this->actingAs($architect)->delete(route('site.photos.destroy', [$project, $photo]))->assertForbidden();
    }

    public function test_site_report_pdf_uses_the_private_renderer(): void
    {
        $this->withoutVite();
        $project = $this->project();
        $architect = $this->member($project, 'architect');
        $report = $this->draftReport($project, $architect);
        config(['billing.pdf_token' => str_repeat('c', 32), 'billing.pdf_url' => 'http://pdf:3000']);
        Http::fake(['http://pdf:3000/render' => Http::response('%PDF-1.7 compte rendu', 200, ['Content-Type' => 'application/pdf'])]);

        $this->actingAs($architect)->get(route('site.reports.pdf', [$project, $report]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        Http::assertSent(fn ($request) => str_contains($request->body(), $report->number));
    }

    public function test_contractors_and_unrelated_users_have_no_access(): void
    {
        $this->withoutVite();
        $project = $this->project();
        $contractor = $this->userWithRole('contractor');
        $outsider = $this->member($this->project());

        // Checked first: actingAs() persists on the test instance for later calls.
        $this->get(route('site.index', $project))->assertRedirect(route('login'));
        $this->actingAs($contractor)->get(route('site.index', $project))->assertForbidden();
        $this->actingAs($outsider)->get(route('site.index', $project))->assertForbidden();
    }

    public function test_site_pages_render_in_both_languages(): void
    {
        $this->withoutVite();
        $project = $this->project();
        $architect = $this->member($project, 'architect');
        $report = $this->draftReport($project, $architect);

        foreach (['fr' => 'ltr', 'ar' => 'rtl'] as $locale => $direction) {
            foreach ([route('site.index', $project), route('site.reports.show', [$project, $report])] as $url) {
                $this->actingAs($architect)->withSession(['locale' => $locale])->get($url)
                    ->assertOk()
                    ->assertSee('lang="'.$locale.'" dir="'.$direction.'"', false);
            }
        }
    }

    /** The snapshot stored with a progress statement is not recomputed later. */
    public function test_the_computed_snapshot_is_kept_when_the_planning_changes(): void
    {
        $project = $this->project();
        $architect = $this->member($project, 'architect');
        $task = Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Todo, 'estimated_hours' => 10]);

        app(SiteProgressService::class)->record($project, $architect, [
            'recorded_on' => today()->toDateString(),
            'percent' => '20',
        ]);
        $this->assertDatabaseHas('site_progress_updates', ['computed_percent' => '0.00']);

        $task->update(['status' => TaskStatus::Done]);
        $this->assertSame('100.00', app(SiteProgressService::class)->computed($project));
        $this->assertDatabaseHas('site_progress_updates', ['computed_percent' => '0.00']);

        $latest = app(SiteProgressService::class)->latest($project);
        $this->assertSame('20.00', $latest?->percent);
        $this->assertSame('20.00', app(SiteProgressService::class)->gap($latest));
    }
}

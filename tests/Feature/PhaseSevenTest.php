<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

class PhaseSevenTest extends TestCase
{
    use RefreshDatabase;

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

    /** An issued, overdue invoice: 1 000.000 HT, 1 191.000 TTC, 400.000 collected. */
    private function issuedInvoice(Project $project): int
    {
        return DB::table('invoices')->insertGetId([
            'project_id' => $project->id,
            'client_id' => $project->client_id,
            'number' => 'FAC-TEST-'.uniqid(),
            'status' => 'sent',
            'document_date' => today()->toDateString(),
            'due_date' => today()->subDay()->toDateString(),
            'discount_percent' => '0',
            'total_ht' => '1000.000',
            'total_discount' => '0',
            'total_tax' => '190.000',
            'stamp_amount' => '1.000',
            'withholding_amount' => '0.000',
            'total_ttc' => '1191.000',
            'net_due' => '1191.000',
            'issued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_financial_indicators_are_restricted_to_authorised_roles(): void
    {
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
        $project = $this->project();
        $architect = $this->member($project, 'architect');
        $collaborator = $this->member($project);
        $invoice = $this->issuedInvoice($project);
        DB::table('payments')->insert([
            'invoice_id' => $invoice,
            'amount' => '400.000',
            'payment_date' => today()->toDateString(),
            'method' => 'cash',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($architect)->get('/dashboard')
            ->assertOk()
            ->assertSee(__('phase7.outstanding'))
            ->assertSee('791.000');
        $this->actingAs($architect)->get('/reports')
            ->assertOk()
            ->assertSee('1000.000');

        $this->actingAs($collaborator)->get('/dashboard')
            ->assertOk()
            ->assertDontSee(__('phase7.outstanding'))
            ->assertDontSee('791.000');
        $this->actingAs($collaborator)->get('/reports')
            ->assertOk()
            ->assertSee(__('phase7.no_financial_access'))
            ->assertSee(__('phase7.hours'))
            ->assertDontSee(__('phase7.billed'))
            ->assertDontSee(__('phase7.margin_rate'));

        $client = $this->userWithRole('client');
        $project->client->update(['user_id' => $client->id]);
        $this->actingAs($client)->get('/dashboard')
            ->assertOk()
            // The phase 2 client welcome must survive the phase 7 rewrite.
            ->assertSee(__('common.welcome_client'))
            ->assertDontSee(__('phase7.outstanding'));
        $this->actingAs($client)->get('/reports')->assertForbidden();
        $this->actingAs($client)->get('/time')->assertForbidden();
        $this->actingAs($client)->get('/reports/export.xlsx')->assertForbidden();
        $this->actingAs($collaborator)->get('/reports/export.xlsx')->assertForbidden();
    }

    public function test_time_entries_belong_to_their_author_and_respect_the_daily_limit(): void
    {
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
        $project = $this->project();
        $author = $this->member($project);
        $other = $this->member($project);

        $this->actingAs($author)->post('/time', [
            'project_id' => $project->id,
            'worked_on' => today()->toDateString(),
            'minutes' => 1200,
            'note' => 'Étude',
            'billable' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('time_entries', ['project_id' => $project->id, 'minutes' => 1200, 'user_id' => $author->id]);

        $this->actingAs($author)->post('/time', [
            'project_id' => $project->id,
            'worked_on' => today()->toDateString(),
            'minutes' => 300,
        ])->assertSessionHasErrors('minutes');
        $this->assertSame(1, TimeEntry::query()->count());

        $entry = TimeEntry::query()->firstOrFail();
        $this->actingAs($other)->put('/time/'.$entry->id, [
            'worked_on' => today()->toDateString(),
            'minutes' => 60,
        ])->assertForbidden();
        $this->assertSame(1200, $entry->fresh()?->minutes);

        // The edit form sends only the date, the duration, the task and the note.
        $this->actingAs($author)->put('/time/'.$entry->id, [
            'worked_on' => today()->toDateString(),
            'minutes' => 60,
            'billable' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame(60, $entry->fresh()?->minutes);

        $this->actingAs($author)->delete('/time/'.$entry->id)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('time_entries', 0);
    }

    public function test_server_timer_runs_one_entry_per_user_at_a_time(): void
    {
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
        $project = $this->project();
        $user = $this->member($project);

        $this->actingAs($user)->post('/time/start', ['project_id' => $project->id])->assertSessionHasNoErrors();
        $entry = TimeEntry::query()->firstOrFail();
        $this->assertNotNull($entry->started_at);
        $this->assertNull($entry->stopped_at);

        $this->actingAs($user)->post('/time/start', ['project_id' => $project->id])->assertSessionHasErrors('timer');
        $this->assertSame(1, TimeEntry::query()->count());

        $this->actingAs($user)->post('/time/'.$entry->id.'/stop')->assertSessionHasNoErrors();
        $stopped = $entry->fresh();
        $this->assertNotNull($stopped?->stopped_at);
        $this->assertGreaterThanOrEqual(1, $stopped?->minutes);
        $this->actingAs($user)->post('/time/'.$entry->id.'/stop')->assertSessionHasErrors('timer');
    }

    public function test_exports_produce_a_valid_workbook_and_a_pdf(): void
    {
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
        $project = $this->project();
        $architect = $this->member($project, 'architect');
        $this->issuedInvoice($project);
        $this->actingAs($architect)->post('/time', [
            'project_id' => $project->id,
            'worked_on' => today()->toDateString(),
            'minutes' => 120,
        ])->assertSessionHasNoErrors();

        $response = $this->actingAs($architect)->get('/reports/export.xlsx');
        $response->assertOk();
        $file = $response->baseResponse->getFile();
        $this->assertNotNull($file);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($file->getPathname()) === true);
        $this->assertNotFalse($zip->locateName('[Content_Types].xml'));
        $this->assertNotFalse($zip->locateName('xl/workbook.xml'));
        $this->assertNotFalse($zip->locateName('xl/worksheets/sheet1.xml'));
        $this->assertNotFalse($zip->locateName('xl/worksheets/sheet2.xml'));
        $this->assertStringContainsString(__('phase7.report_sheet'), (string) $zip->getFromName('xl/workbook.xml'));
        $this->assertStringContainsString('2.000', (string) $zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();

        config(['billing.pdf_token' => str_repeat('b', 32), 'billing.pdf_url' => 'http://pdf:3000']);
        Http::fake(['http://pdf:3000/render' => Http::response('%PDF-1.7 rapport', 200, ['Content-Type' => 'application/pdf'])]);
        $this->actingAs($architect)->get('/reports/export.pdf')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        Http::assertSent(fn ($request) => str_contains($request->body(), 'dir=\\"ltr\\"')
            // json_encode escapes non-ASCII characters, so check the unaccented stem.
            && str_contains($request->body(), 'Rentabilit'));
    }

    public function test_dashboard_time_and_report_pages_render_in_both_languages(): void
    {
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
        $project = $this->project();
        $architect = $this->member($project, 'architect');
        $admin = $this->userWithRole('admin');

        foreach (['fr' => 'ltr', 'ar' => 'rtl'] as $locale => $direction) {
            foreach (['/dashboard', '/time', '/reports'] as $url) {
                $this->actingAs($architect)->withSession(['locale' => $locale])->get($url)
                    ->assertOk()
                    ->assertSee('lang="'.$locale.'" dir="'.$direction.'"', false);
            }
            $this->actingAs($admin)->withSession(['locale' => $locale])->get('/reports/settings')
                ->assertOk()
                ->assertSee('lang="'.$locale.'" dir="'.$direction.'"', false);
        }

        $this->actingAs($architect)->get('/reports/settings')->assertForbidden();
    }
}

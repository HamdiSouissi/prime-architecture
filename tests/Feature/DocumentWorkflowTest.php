<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectAdministration;
use Database\Seeders\DocumentCategorySeeder;
use Database\Seeders\DocumentDemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;
use ZipArchive;

class DocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $architect;

    private User $admin;

    private Project $project;

    private DocumentCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('documents');
        $this->seed([RolePermissionSeeder::class, DocumentCategorySeeder::class]);
        $this->architect = $this->user('architect');
        $this->admin = $this->user('admin');
        $this->project = app(ProjectAdministration::class)->create(['name' => 'Project', 'project_type' => 'villa', 'client_id' => Client::factory()->create()->id, 'lead_architect_id' => $this->architect->id], $this->architect);
        $this->category = DocumentCategory::firstOrFail();
        $this->actingAs($this->architect);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function pdf(string $body = 'one', string $name = 'plan.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n$body\n%%EOF\n");
    }

    private function createDocument(bool $visible = false): Document
    {
        $this->post(route('documents.store', $this->project), ['title' => 'Plan', 'category_id' => $this->category->id, 'visible_to_client' => $visible, 'file' => $this->pdf()])->assertSessionHasNoErrors()->assertRedirect();

        return Document::where('title', 'Plan')->firstOrFail();
    }

    public function test_version_history_streaming_copy_and_identical_detection(): void
    {
        $document = $this->createDocument();
        $first = $document->currentVersion;
        Storage::disk('documents')->assertExists($first->path);
        $this->assertStringStartsWith('blobs/', $first->path);
        $this->post(route('documents.versions.store', [$this->project, $document]), ['file' => $this->pdf('two'), 'change_note' => 'V2'])->assertSessionHasNoErrors();
        $this->post(route('documents.versions.store', [$this->project, $document]), ['file' => $this->pdf('three')])->assertSessionHasNoErrors();
        $this->assertSame([3, 2, 1], $document->versions()->pluck('version_number')->all());
        $this->assertSame(3, $document->fresh()->currentVersion->version_number);
        $this->get(route('documents.versions.download', [$this->project, $document, $first]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('documents.versions.preview', [$this->project, $document, $first]))->assertOk()->assertHeader('Content-Security-Policy', "sandbox; default-src 'none'");
        $this->post(route('documents.versions.store', [$this->project, $document]), ['file' => $this->pdf('three')])->assertSessionHasErrors('file');
        $this->assertSame(3, $document->versions()->count());
        $this->post(route('documents.versions.copy', [$this->project, $document, $first]))->assertSessionHasNoErrors();
        $this->assertSame(4, $document->fresh()->currentVersion->version_number);
        $this->assertSame($first->checksum_sha256, $document->fresh()->currentVersion->checksum_sha256);
        $this->assertDatabaseCount('document_upload_intents', 0);
    }

    public function test_rejects_masquerading_double_extension_and_size(): void
    {
        $url = route('documents.store', $this->project);
        $base = ['title' => 'Invalid', 'category_id' => $this->category->id];
        $this->post($url, $base + ['file' => UploadedFile::fake()->createWithContent('plan.pdf', '<?php echo 1;')])->assertSessionHasErrors('file');
        $this->post($url, $base + ['file' => $this->pdf('one', 'plan.php.pdf')])->assertSessionHasErrors('file');
        $this->post($url, $base + ['file' => UploadedFile::fake()->createWithContent('plan.exe', 'not allowed')])->assertSessionHasErrors('file');
        config(['documents.max_file_kb' => 1]);
        $this->post($url, $base + ['file' => $this->pdf(str_repeat('x', 2048))])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_project_document_version_and_comments_are_authorized(): void
    {
        $clientUser = $this->user('client');
        $this->project->client->update(['user_id' => $clientUser->id]);
        $visible = $this->createDocument(true);
        $this->post(route('documents.store', $this->project), ['title' => 'Private', 'category_id' => $this->category->id, 'file' => $this->pdf('private')])->assertSessionHasNoErrors();
        $private = Document::where('title', 'Private')->firstOrFail();
        $version = $visible->currentVersion;
        $this->post(route('documents.comments.store', [$this->project, $visible]), ['body' => 'SECRET INTERNAL', 'is_internal' => 1])->assertSessionHasNoErrors();
        $this->post(route('documents.comments.store', [$this->project, $visible]), ['body' => 'Public comment', 'is_internal' => 0])->assertSessionHasNoErrors();
        $this->actingAs($clientUser)->get(route('documents.index', $this->project))->assertOk()->assertSee('Plan')->assertDontSee('Private');
        $this->get(route('documents.show', [$this->project, $visible]))->assertOk()->assertSee('Public comment')->assertDontSee('SECRET INTERNAL');
        $this->get(route('documents.show', [$this->project, $private]))->assertForbidden();
        $this->get(route('documents.versions.download', [$this->project, $private, $private->currentVersion]))->assertForbidden();
        $this->get(route('documents.versions.preview', [$this->project, $private, $private->currentVersion]))->assertForbidden();
        $this->get(route('documents.versions.download', [$this->project, $visible, $version]))->assertOk();
        $this->post(route('documents.comments.store', [$this->project, $visible]), ['body' => 'Client comment', 'is_internal' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('document_comments', ['body' => 'Client comment', 'is_internal' => false]);
        $otherClient = $this->user('client');
        $this->actingAs($otherClient)->get(route('documents.show', [$this->project, $visible]))->assertForbidden();
        $this->get(route('documents.versions.download', [$this->project, $visible, $version]))->assertForbidden();
        $this->get(route('documents.versions.preview', [$this->project, $visible, $version]))->assertForbidden();
        $outsider = $this->user('collaborator');
        $this->actingAs($outsider)->get(route('documents.show', [$this->project, $visible]))->assertForbidden();
        $this->get(route('documents.versions.download', [$this->project, $visible, $version]))->assertForbidden();
        $other = Project::factory()->create();
        $this->actingAs($this->architect)->get(route('documents.versions.download', [$other, $visible, $version]))->assertForbidden();
        $this->actingAs($this->user('contractor'))->get(route('documents.index', $this->project))->assertForbidden();
    }

    public function test_soft_delete_restore_zip_and_limits(): void
    {
        $document = $this->createDocument();
        $version = $document->currentVersion;
        $response = $this->post(route('documents.zip', $this->project), ['documents' => [$document->id]])->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $this->assertStringContainsString('%PDF', $zip->getFromIndex(0));
        $zip->close();
        unlink($path);
        config(['documents.max_zip_bytes' => 1]);
        $this->post(route('documents.zip', $this->project), ['documents' => [$document->id]])->assertSessionHasErrors('documents');
        $this->delete(route('documents.versions.destroy', [$this->project, $document, $version]))->assertRedirect();
        $this->assertSoftDeleted('document_versions', ['id' => $version->id]);
        $this->assertNull($document->fresh()->current_version_id);
        $this->post(route('documents.versions.restore', [$this->project, $document, $version]))->assertForbidden();
        $this->actingAs($this->admin)->post(route('documents.versions.restore', [$this->project, $document, $version]))->assertRedirect();
        $this->assertNotSoftDeleted('document_versions', ['id' => $version->id]);
        $this->actingAs($this->architect)->delete(route('documents.destroy', [$this->project, $document]))->assertRedirect();
        $this->assertSoftDeleted('documents', ['id' => $document->id]);
        Storage::disk('documents')->assertExists($version->path);
        $this->post(route('documents.restore', [$this->project, $document]))->assertForbidden();
        $this->actingAs($this->admin)->post(route('documents.restore', [$this->project, $document]))->assertRedirect();
        $this->assertNotSoftDeleted('documents', ['id' => $document->id]);
    }

    public function test_failed_storage_leaves_no_document_or_version(): void
    {
        $disk = Mockery::mock();
        $disk->shouldReceive('writeStream')->once()->andThrow(new \RuntimeException('storage failed'));
        $disk->shouldReceive('exists')->once()->andReturn(false);
        Storage::shouldReceive('disk')->andReturn($disk);
        $this->post(route('documents.store', $this->project), ['title' => 'Fail', 'category_id' => $this->category->id, 'file' => $this->pdf()])->assertStatus(500);
        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('document_versions', 0);
        $this->assertDatabaseCount('document_upload_intents', 0);
    }

    public function test_assigned_collaborator_can_upload_but_viewer_cannot_modify_or_comment(): void
    {
        $document = $this->createDocument();
        $collaborator = $this->user('collaborator');
        $this->project->members()->attach($collaborator->id, ['project_role' => 'collaborator']);
        $this->actingAs($collaborator)->post(route('documents.versions.store', [$this->project, $document]), ['file' => $this->pdf('collaborator')])->assertSessionHasNoErrors();
        $this->delete(route('documents.destroy', [$this->project, $document]))->assertForbidden();
        $viewer = $this->user('collaborator');
        $this->project->members()->attach($viewer->id, ['project_role' => 'viewer']);
        $this->actingAs($viewer)->get(route('documents.show', [$this->project, $document]))->assertOk();
        $this->post(route('documents.versions.store', [$this->project, $document]), ['file' => $this->pdf('viewer')])->assertForbidden();
        $this->post(route('documents.comments.store', [$this->project, $document]), ['body' => 'viewer'])->assertForbidden();
    }

    public function test_dxf_download_is_forced_and_not_previewed(): void
    {
        $this->post(route('documents.store', $this->project), ['title' => 'DXF', 'category_id' => $this->category->id, 'file' => UploadedFile::fake()->createWithContent('plan.dxf', "0\nSECTION\n2\nHEADER\n0\nENDSEC\n0\nEOF\n")])->assertSessionHasNoErrors();
        $document = Document::where('title', 'DXF')->firstOrFail();
        $response = $this->get(route('documents.versions.download', [$this->project, $document, $document->currentVersion]))->assertOk();
        $this->assertStringStartsWith('attachment;', $response->headers->get('Content-Disposition'));
        $this->get(route('documents.versions.preview', [$this->project, $document, $document->currentVersion]))->assertNotFound();
    }

    public function test_document_list_query_count_does_not_grow_with_rows(): void
    {
        Document::factory()->create(['project_id' => $this->project->id, 'category_id' => $this->category->id, 'created_by' => $this->architect->id]);
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });
        $this->get(route('documents.index', $this->project))->assertOk();
        $queries = [];
        $this->get(route('documents.index', $this->project))->assertOk();
        $single = count($queries);
        Document::factory()->count(14)->create(['project_id' => $this->project->id, 'category_id' => $this->category->id, 'created_by' => $this->architect->id]);
        $queries = [];
        $this->get(route('documents.index', $this->project))->assertOk();
        $this->assertLessThanOrEqual($single + 1, count($queries));
    }

    public function test_demo_seeder_is_repeatable_and_creates_a_real_image(): void
    {
        $this->seed(DocumentDemoSeeder::class);
        $this->seed(DocumentDemoSeeder::class);
        $documents = Document::where('project_id', $this->project->id)->where('title', 'DEMO — Plan de façade')->get();
        $this->assertCount(1, $documents);
        $version = $documents->first()->currentVersion;
        $this->assertSame('image/png', $version->mime_type);
        Storage::disk('documents')->assertExists($version->path);
    }

    public function test_views_are_translated_and_category_in_use_is_not_deleted(): void
    {
        $document = $this->createDocument();
        foreach (['fr' => 'ltr', 'ar' => 'rtl'] as $locale => $dir) {
            foreach ([route('documents.index', $this->project), route('documents.create', $this->project), route('documents.show', [$this->project, $document]), route('documents.edit', [$this->project, $document])] as $url) {
                $this->withSession(['locale' => $locale])->get($url)->assertOk()->assertSee('lang="'.$locale.'" dir="'.$dir.'"', false)->assertDontSee('phase4.');
            }
        }
        $this->actingAs($this->admin)->delete(route('document-categories.destroy', $this->category))->assertSessionHasErrors('category');
        $this->assertDatabaseHas('document_categories', ['id' => $this->category->id]);
    }
}

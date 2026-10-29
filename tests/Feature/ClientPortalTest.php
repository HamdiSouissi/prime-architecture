<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quote;
use App\Models\User;
use App\Services\PdfGenerator;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientPortalTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_access_is_explicit_scoped_and_revocable_including_legacy_access(): void
    {
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
        $admin = $this->user('admin');
        $architect = $this->user('architect');
        $clientUser = $this->user('client');
        $otherUser = $this->user('client');
        $client = Client::factory()->create(['user_id' => $clientUser->id]);
        $project = Project::factory()->create(['client_id' => $client->id]);
        $otherProject = Project::factory()->create();
        $quote = Quote::create(['project_id' => $project->id, 'client_id' => $client->id, 'status' => 'sent', 'document_date' => today(), 'number' => 'DEV-ISSUED', 'issued_at' => now(), 'total_ttc' => '12.000']);
        $draft = Quote::create(['project_id' => $project->id, 'client_id' => $client->id, 'status' => 'draft', 'document_date' => today(), 'number' => 'DEV-DRAFT', 'total_ttc' => '13.000']);
        $invoice = Invoice::create(['project_id' => $project->id, 'client_id' => $client->id, 'status' => 'sent', 'document_date' => today(), 'due_date' => today(), 'number' => 'FAC-ISSUED', 'issued_at' => now(), 'total_ttc' => '24.000']);
        DB::table('payments')->insert(['invoice_id' => $invoice->id, 'amount' => '4.000', 'payment_date' => today(), 'method' => 'cash', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($clientUser)->get(route('portal.index'))->assertOk()->assertSee($project->name)->assertDontSee($otherProject->name);
        $this->get(route('portal.finance', $project))->assertForbidden();
        $this->actingAs($architect)->post(route('portal.access.store', $client), ['user_id' => $clientUser->id, 'financial_access' => 1])->assertForbidden();
        $this->actingAs($admin)->post(route('portal.access.store', $client), ['user_id' => $clientUser->id, 'financial_access' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('portal.access.store', $client), ['user_id' => $otherUser->id, 'financial_access' => 0])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($clientUser)->get(route('portal.finance', $project))->assertOk()->assertSee($quote->number)->assertSee('FAC-ISSUED')->assertSee('4.000')->assertDontSee('DEV-DRAFT');
        $this->mock(PdfGenerator::class, function ($mock): void {
            $mock->shouldReceive('generate')->times(3)->andReturn('%PDF-test');
        });
        $this->get(route('portal.quotes.pdf', [$project, $quote, 'fr']))->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('portal.quotes.pdf', [$project, $quote, 'fr', 'download' => 1]))->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="document-fr.pdf"');
        $this->get(route('portal.quotes.pdf', [$project, $draft, 'fr']))->assertNotFound();
        $this->get(route('portal.quotes.pdf', [$otherProject, $quote, 'fr']))->assertForbidden();
        $this->actingAs($admin)->get(route('billing.quotes.pdf', [$quote, 'fr', 'download' => 1]))->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="document-fr.pdf"');
        $this->get(route('billing.quotes.pdf', [$draft, 'fr', 'download' => 1]))->assertNotFound();
        $this->actingAs($otherUser)->get(route('projects.show', $project))->assertOk();
        $this->get(route('portal.finance', $project))->assertForbidden();
        $this->get(route('portal.finance', $otherProject))->assertForbidden();
        $this->actingAs($admin)->delete(route('portal.access.destroy', [$client, $clientUser]))->assertRedirect();
        $this->actingAs($clientUser)->get(route('portal.finance', $project))->assertForbidden();
        $this->get(route('projects.show', $project))->assertForbidden();
    }

    public function test_client_upload_and_comments_are_scoped_and_private(): void
    {
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('documents');
        $user = $this->user('client');
        $client = Client::factory()->create(['user_id' => $user->id]);
        $project = Project::factory()->create(['client_id' => $client->id]);
        $other = Project::factory()->create();
        $category = DocumentCategory::factory()->create(['is_active' => true]);
        $file = fn () => UploadedFile::fake()->createWithContent('plan.pdf', "%PDF-1.4\nfixture\n%%EOF\n");
        $this->actingAs($user)->post(route('portal.upload.store', $other), ['title' => 'Interdit', 'category_id' => $category->id, 'file' => $file()])->assertForbidden();
        $this->post(route('portal.upload.store', $project), ['title' => 'Plan client', 'category_id' => $category->id, 'file' => $file(), 'visible_to_client' => 0])->assertRedirect()->assertSessionHasNoErrors();
        $document = Document::query()->firstOrFail();
        $this->assertTrue($document->visible_to_client);
        Storage::disk('documents')->assertExists($document->currentVersion->path);
        $this->post(route('documents.comments.store', [$project, $document]), ['body' => 'Commentaire client', 'is_internal' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('document_comments', ['document_id' => $document->id, 'body' => 'Commentaire client', 'is_internal' => false]);
        $this->get(route('documents.show', [$other, $document]))->assertForbidden();
    }
}

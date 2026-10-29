<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\QuoteStatus;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quote;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_is_scoped_to_staff_permissions_and_project_membership(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $architect = User::factory()->create();
        $architect->assignRole('architect');
        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();
        $quote = Quote::create(['project_id' => $project->id, 'client_id' => $project->client_id, 'created_by' => $architect->id, 'status' => QuoteStatus::Draft, 'document_date' => '2026-11-01']);
        $otherQuote = Quote::create(['project_id' => $otherProject->id, 'client_id' => $otherProject->client_id, 'created_by' => $architect->id, 'status' => QuoteStatus::Draft, 'document_date' => '2026-11-01']);
        $invoice = Invoice::create(['project_id' => $project->id, 'client_id' => $project->client_id, 'created_by' => $architect->id, 'status' => InvoiceStatus::Draft, 'document_date' => '2026-11-01', 'due_date' => '2026-11-30']);
        $member = User::factory()->create();
        $member->assignRole('collaborator');
        $project->members()->attach($member->id, ['project_role' => 'collaborator']);
        $this->assertFalse($member->can('view', $quote));
        $member->givePermissionTo(['billing.view', 'billing.quotes.create', 'billing.quotes.update', 'billing.quotes.issue', 'billing.invoices.create', 'billing.invoices.update', 'billing.invoices.issue']);
        $this->assertTrue($member->can('view', $quote));
        $this->assertTrue($member->can('update', $quote));
        $this->assertTrue($member->can('update', $invoice));
        $this->assertFalse($member->can('view', $otherQuote));
        $this->assertSame([$quote->id], Quote::query()->visibleTo($member)->pluck('id')->all());
        $this->assertSame([$invoice->id], Invoice::query()->visibleTo($member)->pluck('id')->all());
        $viewer = User::factory()->create();
        $viewer->assignRole('collaborator');
        $viewer->givePermissionTo(['billing.view', 'billing.quotes.update', 'billing.invoices.update']);
        $project->members()->attach($viewer->id, ['project_role' => 'viewer']);
        $this->assertTrue($viewer->can('view', $quote));
        $this->assertFalse($viewer->can('update', $quote));
        $this->assertFalse($viewer->can('update', $invoice));
        foreach (['client', 'contractor'] as $role) {
            $external = User::factory()->create();
            $external->assignRole($role);
            $external->givePermissionTo('billing.view');
            $this->assertFalse($external->can('viewAny', Quote::class));
            $this->assertFalse($external->can('view', $quote));
            $this->assertSame(0, Quote::query()->visibleTo($external)->count());
            $this->assertSame(0, Invoice::query()->visibleTo($external)->count());
        }
        $quote->update(['status' => QuoteStatus::Sent, 'issued_at' => now()]);
        $invoice->update(['status' => InvoiceStatus::Sent, 'issued_at' => now()]);
        $this->assertFalse($architect->can('update', $quote));
        $this->assertFalse($architect->can('update', $invoice));
    }
}

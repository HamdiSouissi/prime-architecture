<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'architect', 'collaborator']) && $user->can('billing.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->viewAny($user) && Invoice::query()->visibleTo($user)->whereKey($invoice->id)->exists();
    }

    public function create(User $user, Project $project): bool
    {
        return $this->viewAny($user) && $user->can('billing.invoices.create') && ! $project->trashed() && $user->can('update', $project);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice) && $user->can('billing.invoices.update') && $user->can('update', $invoice->project) && $invoice->status === InvoiceStatus::Draft && $invoice->issued_at === null;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->update($user, $invoice);
    }

    public function issue(User $user, Invoice $invoice): bool
    {
        return $this->update($user, $invoice) && $user->can('billing.invoices.issue');
    }

    public function pay(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice) && $user->can('billing.payments.manage') && $user->can('update', $invoice->project) && $invoice->issued_at !== null && ! in_array($invoice->status, [InvoiceStatus::Cancelled, InvoiceStatus::Paid], true);
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice) && $user->can('billing.invoices.cancel') && $user->can('update', $invoice->project) && $invoice->issued_at !== null && ! in_array($invoice->status, [InvoiceStatus::Cancelled, InvoiceStatus::Paid], true)
            && ! DB::table('payments')->where('invoice_id', $invoice->id)->exists()
            && ! DB::table('payment_withholdings')->where('invoice_id', $invoice->id)->exists();
    }
}

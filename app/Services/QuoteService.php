<?php

namespace App\Services;

use App\Enums\QuoteStatus;
use App\Models\Project;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class QuoteService
{
    /** @param array<string, mixed> $data */
    public function create(Project $project, User $actor, array $data): Quote
    {
        $data = app(BillingDraft::class)->validate($data, 'quote');

        return DB::transaction(function () use ($project, $actor, $data) {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            Gate::forUser($actor)->authorize('create', [Quote::class, $project]);
            $quote = Quote::create(['project_id' => $project->id, 'client_id' => $project->client_id, 'created_by' => $actor->id, 'status' => QuoteStatus::Draft, 'document_date' => $data['document_date'] ?? now()->toDateString()]);
            app(BillingDraft::class)->apply($quote, $project, $data, 'quote');
            activity('billing')->causedBy($actor)->performedOn($quote)->event('quote.created')->log('quote.created');

            return $quote->fresh();
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function update(Quote $quote, User $actor, array $data): Quote
    {
        return DB::transaction(function () use ($quote, $actor, $data) {
            $quote = Quote::query()->lockForUpdate()->findOrFail($quote->id);
            Gate::forUser($actor)->authorize('update', $quote);
            app(BillingDraft::class)->apply($quote, $quote->project, $data, 'quote');
            activity('billing')->causedBy($actor)->performedOn($quote)->event('quote.updated')->log('quote.updated');

            return $quote->fresh();
        }, 3);
    }

    public function issue(Quote $quote, User $actor): Quote
    {
        return DB::transaction(function () use ($quote, $actor) {
            $quote = Quote::query()->lockForUpdate()->findOrFail($quote->id);
            Gate::forUser($actor)->authorize('issue', $quote);
            app(BillingIssue::class)->issue($quote, 'quote');
            activity('billing')->causedBy($actor)->performedOn($quote)->event('quote.issued')->log('quote.issued');

            return $quote->fresh();
        }, 3);
    }

    public function decide(Quote $quote, User $actor, QuoteStatus $status): Quote
    {
        return DB::transaction(function () use ($quote, $actor, $status) {
            $quote = Quote::query()->lockForUpdate()->findOrFail($quote->id);
            Gate::forUser($actor)->authorize('decide', $quote);
            if (! in_array($status, [QuoteStatus::Accepted, QuoteStatus::Rejected], true) || ($quote->valid_until && $quote->valid_until->isBefore(today()))) {
                throw ValidationException::withMessages(['status' => __('phase6.invalid_transition')]);
            }
            $quote->update(['status' => $status]);
            activity('billing')->causedBy($actor)->performedOn($quote)->withProperties(['status' => $status->value])->event('quote.status_changed')->log('quote.status_changed');

            return $quote->fresh();
        }, 3);
    }

    public function delete(Quote $quote, User $actor): void
    {
        DB::transaction(function () use ($quote, $actor) {
            $quote = Quote::query()->lockForUpdate()->findOrFail($quote->id);
            Gate::forUser($actor)->authorize('update', $quote);
            $quote->delete();
            activity('billing')->causedBy($actor)->performedOn($quote)->event('quote.archived')->log('quote.archived');
        }, 3);
    }
}

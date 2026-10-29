<?php

namespace App\Policies;

use App\Enums\QuoteStatus;
use App\Models\Project;
use App\Models\Quote;
use App\Models\User;

class QuotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'architect', 'collaborator']) && $user->can('billing.view');
    }

    public function view(User $user, Quote $quote): bool
    {
        return $this->viewAny($user) && ! $quote->trashed() && Quote::query()->visibleTo($user)->whereKey($quote->id)->exists();
    }

    public function create(User $user, Project $project): bool
    {
        return $this->viewAny($user) && $user->can('billing.quotes.create') && ! $project->trashed() && $user->can('update', $project);
    }

    public function update(User $user, Quote $quote): bool
    {
        return $this->view($user, $quote) && $user->can('billing.quotes.update') && $user->can('update', $quote->project) && $quote->status === QuoteStatus::Draft && $quote->issued_at === null;
    }

    public function issue(User $user, Quote $quote): bool
    {
        return $this->update($user, $quote) && $user->can('billing.quotes.issue');
    }

    public function decide(User $user, Quote $quote): bool
    {
        return $this->view($user, $quote) && $user->can('billing.quotes.issue') && $user->can('update', $quote->project) && $quote->status === QuoteStatus::Sent;
    }
}

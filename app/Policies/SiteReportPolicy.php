<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\SiteReport;
use App\Models\User;

class SiteReportPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return ($user->can('site.view') || $user->can('site.client.view'))
            && ! $project->trashed()
            && $user->can('view', $project);
    }

    /** Clients only read finalised records; the site team also sees drafts. */
    public function view(User $user, SiteReport $report): bool
    {
        if (! $this->viewAny($user, $report->project)) {
            return false;
        }

        return $report->isFinal() || $user->can('site.reports.manage');
    }

    public function create(User $user, Project $project): bool
    {
        return $user->can('site.reports.manage') && $this->viewAny($user, $project) && $user->can('update', $project);
    }

    public function update(User $user, SiteReport $report): bool
    {
        return ! $report->isFinal()
            && $user->can('site.reports.manage')
            && $user->can('update', $report->project);
    }

    public function finalize(User $user, SiteReport $report): bool
    {
        return ! $report->isFinal()
            && $user->can('site.reports.finalize')
            && $user->can('update', $report->project);
    }

    public function delete(User $user, SiteReport $report): bool
    {
        return $this->update($user, $report);
    }
}

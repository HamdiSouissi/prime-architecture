<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ClientPortalAccess
{
    public function canViewFinance(User $user, Project $project): bool
    {
        return $user->hasRole('client') && $user->can('portal.view') && ! $project->trashed()
            && $user->can('view', $project)
            && DB::table('client_portal_access')->where('client_id', $project->client_id)
                ->where('user_id', $user->id)->where('financial_access', true)->exists();
    }

    public function grant(Client $client, User $actor, User $recipient, bool $financial): void
    {
        DB::transaction(function () use ($client, $actor, $recipient, $financial): void {
            $client = Client::query()->lockForUpdate()->findOrFail($client->id);
            Gate::forUser($actor)->authorize('update', $client);
            abort_unless($actor->can('portal.manage'), 403);
            if (! $recipient->is_active || $recipient->trashed() || ! $recipient->hasRole('client') || $recipient->hasAnyRole(['admin', 'architect', 'collaborator', 'contractor'])) {
                throw ValidationException::withMessages(['user_id' => __('phase11.invalid_account')]);
            }
            DB::table('client_portal_access')->updateOrInsert(['client_id' => $client->id, 'user_id' => $recipient->id], ['financial_access' => $financial, 'updated_at' => now(), 'created_at' => now()]);
            activity('portal')->causedBy($actor)->performedOn($client)->withProperties(['user_id' => $recipient->id, 'financial_access' => $financial])->event('portal.access_granted')->log('portal.access_granted');
        }, 3);
    }

    public function revoke(Client $client, User $actor, User $recipient): void
    {
        DB::transaction(function () use ($client, $actor, $recipient): void {
            $client = Client::query()->lockForUpdate()->findOrFail($client->id);
            Gate::forUser($actor)->authorize('update', $client);
            abort_unless($actor->can('portal.manage'), 403);
            DB::table('client_portal_access')->where('client_id', $client->id)->where('user_id', $recipient->id)->delete();
            // Legacy direct access is independent from the new grant; revoke both.
            if ($client->user_id === $recipient->id) {
                $client->update(['user_id' => null]);
            }
            activity('portal')->causedBy($actor)->performedOn($client)->withProperties(['user_id' => $recipient->id])->event('portal.access_revoked')->log('portal.access_revoked');
        }, 3);
    }
}

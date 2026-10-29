<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool { return $user->hasAnyRole(['admin', 'architect']) && $user->can('clients.view'); }
    public function view(User $user, Client $client): bool { return $user->hasAnyRole(['admin', 'architect']) && $user->can('clients.view'); }
    public function create(User $user): bool { return $user->hasAnyRole(['admin', 'architect']) && $user->can('clients.create'); }
    public function update(User $user, Client $client): bool { return $user->hasAnyRole(['admin', 'architect']) && $user->can('clients.update') && ! $client->trashed(); }
    public function delete(User $user, Client $client): bool { return $user->hasAnyRole(['admin', 'architect']) && $user->can('clients.archive') && ! $client->trashed(); }
    public function restore(User $user, Client $client): bool { return $user->hasAnyRole(['admin', 'architect']) && $user->can('clients.restore') && $client->trashed(); }
}

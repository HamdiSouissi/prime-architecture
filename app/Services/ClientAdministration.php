<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ClientAdministration
{
    public function archive(Client $client, User $actor): void
    {
        DB::transaction(function () use ($client, $actor) {
            $client = Client::query()->lockForUpdate()->findOrFail($client->id);
            Gate::forUser($actor)->authorize('delete', $client);
            foreach ($client->projects()->lockForUpdate()->get() as $project) {
                $project->delete();
                activity('projects')->causedBy($actor)->performedOn($project)->event('project.archived')->log('project.archived');
            }
            $client->delete();
            activity('clients')->causedBy($actor)->performedOn($client)->event('client.archived')->log('client.archived');
        }, 3);
    }

    public function restore(Client $client, User $actor): void
    {
        DB::transaction(function () use ($client, $actor) {
            $client = Client::withTrashed()->lockForUpdate()->findOrFail($client->id);
            Gate::forUser($actor)->authorize('restore', $client);
            $client->restore();
            activity('clients')->causedBy($actor)->performedOn($client)->event('client.restored')->log('client.restored');
        }, 3);
    }

    public function saveContact(Client $client, ?ClientContact $contact, array $data): ClientContact
    {
        return DB::transaction(function () use ($client, $contact, $data) {
            $client = Client::query()->lockForUpdate()->findOrFail($client->id);
            if (! empty($data['is_primary'])) {
                $client->contacts()->where('is_primary', true)->update(['is_primary' => false]);
            }
            if ($contact) {
                $contact = $client->contacts()->findOrFail($contact->id);
                $contact->update($data);
            } else {
                $contact = $client->contacts()->create($data);
            }

            return $contact;
        }, 3);
    }
}

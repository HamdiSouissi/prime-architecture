<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Models\Client;
use App\Models\ClientContact;
use App\Services\ClientAdministration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;

class ClientContactController extends Controller
{
    public function store(ContactRequest $request, Client $client, ClientAdministration $service): RedirectResponse
    {
        $service->saveContact($client, null, $request->validated());

        return redirect()->route('clients.show', $client)->with('status', __('common.saved'));
    }

    public function update(ContactRequest $request, Client $client, ClientContact $contact, ClientAdministration $service): RedirectResponse
    {
        abort_unless($contact->client_id === $client->id, 404);
        $service->saveContact($client, $contact, $request->validated());

        return redirect()->route('clients.show', $client)->with('status', __('common.saved'));
    }

    public function restore(Client $client, ClientContact $contact): RedirectResponse
    {
        Gate::authorize('update', $client);
        abort_unless($contact->client_id === $client->id && $contact->trashed(), 404);
        DB::transaction(function () use ($client, $contact) {
            $client = Client::query()->lockForUpdate()->findOrFail($client->id);
            Gate::authorize('update', $client);
            $contact = $client->contacts()->withTrashed()->lockForUpdate()->findOrFail($contact->id);
            if ($contact->is_primary && $client->contacts()->where('is_primary', true)->exists()) {
                $contact->is_primary = false;
            }
            $contact->restore();
        }, 3);

        return redirect()->route('clients.show', $client)->with('status', __('phase3.restored'));
    }

    public function destroy(Client $client, ClientContact $contact): RedirectResponse
    {
        Gate::authorize('update', $client);
        abort_unless($contact->client_id === $client->id, 404);
        $contact->delete();

        return redirect()->route('clients.show', $client)->with('status', __('phase3.archived'));
    }
}

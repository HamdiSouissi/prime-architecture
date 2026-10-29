<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientIndexRequest;
use App\Http\Requests\ClientRequest;
use App\Models\Client;
use App\Models\Governorate;
use App\Models\User;
use App\Services\ClientAdministration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(ClientIndexRequest $request): View
    {
        Gate::authorize('viewAny', Client::class);
        $data = $request->validated();
        $query = Client::query()->with('governorate');
        if (! empty($data['archived'])) {
            $query->onlyTrashed();
        }
        if (! empty($data['search'])) {
            $search = '%'.$data['search'].'%';
            $query->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('email', 'like', $search)->orWhere('phone', 'like', $search)->orWhere('tax_id', 'like', $search)->orWhere('national_id', 'like', $search));
        }
        if (! empty($data['type'])) {
            $query->where('type', $data['type']);
        }
        if (! empty($data['governorate_id'])) {
            $query->where('governorate_id', $data['governorate_id']);
        }

        return view('clients.index', ['clients' => $query->orderBy('name')->paginate(20)->withQueryString(), 'governorates' => Governorate::orderBy('name_fr')->get()]);
    }

    public function create(): View
    {
        Gate::authorize('create', Client::class);

        return view('clients.form', $this->formData(new Client));
    }

    public function store(ClientRequest $request): RedirectResponse
    {
        $client = DB::transaction(function () use ($request) {
            $client = Client::create($request->validated());
            activity('clients')->performedOn($client)->event('client.created')->log('client.created');

            return $client;
        });

        return redirect()->route('clients.show', $client)->with('status', __('common.saved'));
    }

    public function show(Client $client): View
    {
        Gate::authorize('view', $client);
        $client->load(['governorate', 'delegation', 'user', 'contacts' => fn ($query) => $query->withTrashed(), 'projects.leadArchitect']);

        return view('clients.show', compact('client'));
    }

    public function edit(Client $client): View
    {
        Gate::authorize('update', $client);

        return view('clients.form', $this->formData($client));
    }

    public function update(ClientRequest $request, Client $client): RedirectResponse
    {
        DB::transaction(function () use ($request, $client) {
            $client = Client::query()->lockForUpdate()->findOrFail($client->id);
            Gate::authorize('update', $client);
            $client->update($request->validated());
            activity('clients')->performedOn($client)->event('client.updated')->log('client.updated');
        });

        return redirect()->route('clients.show', $client)->with('status', __('common.saved'));
    }

    public function destroy(Request $request, Client $client, ClientAdministration $service): RedirectResponse
    {
        $service->archive($client, $request->user());

        return redirect()->route('clients.index')->with('status', __('phase3.archived'));
    }

    public function restore(Request $request, Client $client, ClientAdministration $service): RedirectResponse
    {
        $service->restore($client, $request->user());

        return redirect()->route('clients.show', $client)->with('status', __('phase3.restored'));
    }

    private function formData(Client $client): array
    {
        return ['client' => $client, 'governorates' => Governorate::with('delegations')->orderBy('name_fr')->get(), 'users' => User::role('client')->where('is_active', true)->orderBy('name')->get()];
    }
}

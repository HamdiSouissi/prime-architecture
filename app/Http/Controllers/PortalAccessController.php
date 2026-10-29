<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\User;
use App\Services\ClientPortalAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PortalAccessController extends Controller
{
    public function index(Client $client): View
    {
        Gate::authorize('update', $client);
        abort_unless(request()->user()->can('portal.manage'), 403);
        $users = User::role('client')->where('is_active', true)->orderBy('name')->get();
        $grants = DB::table('client_portal_access')->where('client_id', $client->id)->get()->keyBy('user_id');

        return view('portal.access', compact('client', 'users', 'grants'));
    }

    public function store(Request $request, Client $client, ClientPortalAccess $access): RedirectResponse
    {
        abort_unless($request->user()->can('portal.manage'), 403);
        $data = $request->validate(['user_id' => ['required', 'integer', 'min:1'], 'financial_access' => ['required', 'boolean']]);
        $access->grant($client, $request->user(), User::query()->findOrFail($data['user_id']), (bool) $data['financial_access']);

        return back()->with('status', __('phase11.saved'));
    }

    public function destroy(Request $request, Client $client, User $user, ClientPortalAccess $access): RedirectResponse
    {
        abort_unless($request->user()->can('portal.manage'), 403);
        $access->revoke($client, $request->user(), $user);

        return back()->with('status', __('phase11.saved'));
    }
}

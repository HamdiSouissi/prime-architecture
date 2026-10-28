<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserAdministration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'role' => ['nullable', Rule::in(array_keys(config('access.roles')))], 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $query = User::query()->with('roles')->orderBy('name');
        if (! empty($filters['search'])) {
            $search = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['search']);
            $query->where(fn ($q) => $q->where('name', 'like', "%$search%")->orWhere('email', 'like', "%$search%"));
        }
        if (! empty($filters['role'])) {
            $query->role($filters['role']);
        }
        if (! empty($filters['status'])) {
            $query->where('is_active', $filters['status'] === 'active');
        }

        return view('users.index', ['users' => $query->paginate(20)->withQueryString(), 'roles' => Role::where('guard_name', 'web')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('users.create', ['roles' => Role::where('guard_name', 'web')->orderBy('name')->get()]);
    }

    public function store(Request $request, UserAdministration $service): RedirectResponse
    {
        Gate::authorize('create', User::class);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'role' => ['required', Rule::in(array_keys(config('access.roles')))]]);
        $service->invite($data);

        return redirect()->route('users.index')->with('status', __('common.invitation_sent'));
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('users.edit', ['user' => $user, 'roles' => Role::where('guard_name', 'web')->orderBy('name')->get()]);
    }

    public function update(Request $request, User $user, UserAdministration $service): RedirectResponse
    {
        Gate::authorize('update', $user);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)], 'role' => ['required', Rule::in(array_keys(config('access.roles')))], 'is_active' => ['required', 'boolean']]);
        $ownEmailChanged = $request->user()->id === $user->id && isset($data['email']) && $data['email'] !== $user->email;
        try {
            $service->update($request->user(), $user, $data);
        } finally {
            // The database update may succeed even if sending the email fails.
            if ($ownEmailChanged && $user->fresh()?->email === $data['email']) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }
        if ($ownEmailChanged) {
            return redirect()->route('login');
        }

        return redirect()->route('users.index')->with('status', __('common.saved'));
    }

    public function resendInvitation(User $user, UserAdministration $service): RedirectResponse
    {
        Gate::authorize('update', $user);
        $service->resendInvitation($user);

        return redirect()->route('users.index')->with('status', __('common.invitation_sent'));
    }

    public function destroy(Request $request, User $user, UserAdministration $service): RedirectResponse
    {
        Gate::authorize('delete', $user);
        $service->update($request->user(), $user, [], true);

        return redirect()->route('users.index')->with('status', __('common.saved'));
    }
}

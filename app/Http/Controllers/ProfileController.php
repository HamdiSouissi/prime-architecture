<?php

namespace App\Http\Controllers;

use App\Services\SessionRevoker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $sessions = DB::connection(config('session.connection'))->table(config('session.table'))
            ->where('user_id', $request->user()->id)
            ->where('last_activity', '>=', now()->subMinutes((int) config('session.lifetime'))->timestamp)
            ->orderByDesc('last_activity')->get();

        return view('profile.edit', ['sessions' => $sessions]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:32'], 'locale' => ['required', Rule::in(config('architecture.locales'))]]);
        $request->user()->update($data);
        $request->session()->put('locale', $data['locale']);
        activity('users')->performedOn($request->user())->event('user.updated')->log('user.updated');

        return redirect()->route('profile.edit')->with('status', __('common.saved'));
    }

    public function password(Request $request, SessionRevoker $sessions): RedirectResponse
    {
        $data = $request->validate(['current_password' => ['required', 'current_password'], 'password' => ['required', 'confirmed', Password::defaults()]]);
        $user = $request->user();
        DB::transaction(function () use ($user, $data, $sessions, $request) {
            $user->forceFill(['password' => $data['password']])->save();
            $sessions->revokeAll($user, $request->session()->getId());
            activity('security')->causedBy($user)->event('password.changed')->log('password.changed');
        });

        return redirect()->route('profile.edit')->with('status', __('common.saved'));
    }

    public function logoutOthers(Request $request, SessionRevoker $sessions): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        DB::transaction(fn () => $sessions->revokeAll($request->user(), $request->session()->getId()));

        return redirect()->route('profile.edit')->with('status', __('common.sessions_revoked'));
    }
}

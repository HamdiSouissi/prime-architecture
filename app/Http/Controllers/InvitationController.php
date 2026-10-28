<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function show(string $token): View
    {
        $invitation = $this->validInvitation($token);

        return view('auth.invitation', ['invitation' => $invitation]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $validated = $request->validate(['password' => ['required', 'confirmed', Password::defaults()]]);
        DB::transaction(function () use ($token, $validated) {
            $invitation = $this->validInvitation($token, true);
            $user = $invitation->user;
            abort_unless($user && $user->is_active, 403);
            $user->forceFill(['password' => $validated['password'], 'email_verified_at' => now()])->save();
            $invitation->forceFill(['used_at' => now()])->save();
            activity('security')->causedBy($user)->event('password.changed')->log('password.changed');
        });

        return redirect()->route('login')->with('status', __('common.invitation_complete'));
    }

    private function validInvitation(string $token, bool $lock = false): Invitation
    {
        $query = Invitation::where('token_hash', hash('sha256', $token));
        if ($lock) {
            $query->lockForUpdate();
        }
        $invitation = $query->firstOrFail();
        abort_if($invitation->used_at !== null || $invitation->expires_at->isPast(), 410);

        return $invitation;
    }
}

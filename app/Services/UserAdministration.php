<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserAdministration
{
    public function invite(array $data): User
    {
        $token = Str::random(64);
        $expires = now()->addMinutes((int) config('security.invitation_expiry_minutes'));
        $user = DB::transaction(function () use ($data, $token, $expires) {
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => Str::random(64)]);
            $user->assignRole($data['role']);
            Invitation::create(['user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'expires_at' => $expires]);
            activity('users')->performedOn($user)->event('user.created')->log('user.created');
            activity('users')->performedOn($user)->withProperties(['role' => $data['role']])->event('role.changed')->log('role.changed');

            return $user;
        });
        $url = URL::temporarySignedRoute('invitation.show', $expires, ['token' => $token]);
        $user->notify((new UserInvitation($url))->locale($user->locale));

        return $user;
    }

    public function resendInvitation(User $target): void
    {
        $token = Str::random(64);
        $expires = now()->addMinutes((int) config('security.invitation_expiry_minutes'));
        $user = DB::transaction(function () use ($target, $token, $expires) {
            $user = User::query()->lockForUpdate()->findOrFail($target->id);
            abort_if(! $user->is_active || $user->hasVerifiedEmail(), 403);
            Invitation::where('user_id', $user->id)->whereNull('used_at')->update(['used_at' => now()]);
            Invitation::create(['user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'expires_at' => $expires]);

            return $user;
        });
        $url = URL::temporarySignedRoute('invitation.show', $expires, ['token' => $token]);
        $user->notify((new UserInvitation($url))->locale($user->locale));
    }

    public function update(User $actor, User $target, array $data, bool $delete = false): void
    {
        $result = DB::transaction(function () use ($actor, $target, $data, $delete) {
            // Serialize every admin mutation, including concurrent demotion/deactivation.
            Role::query()->where('name', 'admin')->where('guard_name', 'web')->lockForUpdate()->firstOrFail();
            $user = User::query()->lockForUpdate()->findOrFail($target->id);
            $role = $data['role'] ?? $user->getRoleNames()->first();
            $active = $delete ? false : (bool) $data['is_active'];
            if ($actor->id === $user->id && ($delete || ! $active || $role !== 'admin')) {
                throw ValidationException::withMessages(['role' => __('common.cannot_change_self')]);
            }
            if ($user->is_active && $user->hasRole('admin') && (! $active || $role !== 'admin')) {
                $activeAdmins = User::role('admin')->where('is_active', true)->count();
                if ($activeAdmins <= 1) {
                    throw ValidationException::withMessages(['role' => __('common.last_admin')]);
                }
            }
            $oldRole = $user->getRoleNames()->first();
            $wasActive = $user->is_active;
            $wasVerified = $user->hasVerifiedEmail();
            $oldEmail = $user->email;
            $newEmail = $delete ? $oldEmail : ($data['email'] ?? $oldEmail);
            $emailChanged = $oldEmail !== $newEmail;
            $user->forceFill(['name' => $data['name'] ?? $user->name, 'email' => $newEmail, 'is_active' => $active]);
            if ($emailChanged) {
                $user->email_verified_at = null;
                Invitation::where('user_id', $user->id)->whereNull('used_at')->update(['used_at' => now()]);
                DB::table(config('auth.passwords.users.table'))->whereIn('email', [$oldEmail, $newEmail])->delete();
            }
            $user->save();
            if ($emailChanged) {
                app(SessionRevoker::class)->revokeAll($user);
            }
            if (! $delete) {
                $user->syncRoles([$role]);
            }
            if ($wasActive && ! $active) {
                if (! $emailChanged) {
                    app(SessionRevoker::class)->revokeAll($user);
                }
                activity('users')->performedOn($user)->event('user.deactivated')->log('user.deactivated');
            }
            if ($delete) {
                $user->delete();
            }
            if (! $delete && $role !== $oldRole) {
                activity('users')->performedOn($user)->withProperties(['from' => $oldRole, 'to' => $role])->event('role.changed')->log('role.changed');
            }
            activity('users')->performedOn($user)->event($delete ? 'user.deleted' : 'user.updated')->log($delete ? 'user.deleted' : 'user.updated');

            return ['user' => $user, 'email_changed' => $emailChanged, 'was_verified' => $wasVerified];
        });
        if ($result['email_changed'] && $result['user']->is_active) {
            if ($result['was_verified']) {
                $result['user']->sendEmailVerificationNotification();
            } else {
                $this->resendInvitation($result['user']);
            }
        }
    }
}

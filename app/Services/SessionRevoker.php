<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SessionRevoker
{
    public function revokeAll(User $user, ?string $exceptId = null): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->save();
        $sessions = DB::connection(config('session.connection'))->table(config('session.table'))->where('user_id', $user->id);
        if ($exceptId !== null) {
            $sessions->where('id', '!=', $exceptId);
        }
        $sessions->delete();
    }
}

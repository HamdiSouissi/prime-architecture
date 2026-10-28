<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class InitialAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (User::role('admin')->where('is_active', true)->exists()) {
            return;
        }
        $data = Validator::make(['email' => config('security.admin_email'), 'password' => config('security.admin_password')], [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', Password::defaults()],
        ])->validate();
        if (User::withTrashed()->where('email', $data['email'])->exists()) {
            throw new RuntimeException('Initial admin email is already in use.');
        }
        $user = User::create(['name' => config('security.admin_name'), 'email' => $data['email'], 'password' => $data['password']]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole('admin');
    }
}

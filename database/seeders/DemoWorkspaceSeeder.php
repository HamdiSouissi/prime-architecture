<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Services\ClientPortalAccess;
use Illuminate\Database\Seeder;
use LogicException;

/** Explicitly opt-in, for a fresh local database only. */
class DemoWorkspaceSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Demo accounts are allowed only in local or testing environments.');
        }
        $password = config('demo.password');
        if (! is_string($password) || strlen($password) < 12) {
            throw new LogicException('Set DEMO_PASSWORD to a unique password of at least 12 characters.');
        }
        if (User::withTrashed()->exists() || Client::withTrashed()->exists() || Project::withTrashed()->exists()) {
            throw new LogicException('Demo seeding requires an empty workspace; existing accounts and data are never overwritten.');
        }
        $this->call(RolePermissionSeeder::class);
        $users = [];
        foreach (['admin', 'architect', 'collaborator', 'client', 'contractor'] as $role) {
            $user = User::create(['name' => 'Démo '.ucfirst($role), 'email' => 'demo-'.$role.'@example.test', 'password' => $password]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole($role);
            $users[$role] = $user;
        }
        $this->call(PhaseThreeDemoSeeder::class);
        foreach (Project::query()->get() as $project) {
            $project->members()->syncWithoutDetaching([
                $users['architect']->id => ['project_role' => 'architect'],
                $users['collaborator']->id => ['project_role' => 'collaborator'],
            ]);
        }
        $client = Client::where('email', 'demo-client-1@example.test')->firstOrFail();
        app(ClientPortalAccess::class)->grant($client, $users['admin'], $users['client'], true);
    }
}

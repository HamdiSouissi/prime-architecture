<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_and_permissions_can_be_seeded_repeatedly(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->assertSame(5, Role::query()->count());

        foreach (config('access.roles') as $roleName => $permissions) {
            $user = User::factory()->create();
            $user->assignRole($roleName);

            foreach (['dashboard.internal.view', 'dashboard.client.view', 'users.manage', 'activity.view'] as $permission) {
                $this->assertSame(in_array($permission, $permissions, true), $user->can($permission), "$roleName: $permission");
            }
        }
    }
}

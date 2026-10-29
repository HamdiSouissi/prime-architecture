<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserSecurityFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_defaults_and_password_hashing(): void
    {
        $user = User::factory()->create(['password' => 'test-password']);
        $user->refresh();

        $this->assertTrue($user->is_active);
        $this->assertSame('fr', $user->locale);
        $this->assertNull($user->phone);
        $this->assertNull($user->last_login_at);
        $this->assertTrue(Hash::check('test-password', $user->password));
        $this->assertNotSame('test-password', $user->password);
    }

    public function test_deleting_a_user_is_soft(): void
    {
        $user = User::factory()->create();
        $user->delete();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertNull(User::find($user->id));
        $this->assertNotNull(User::withTrashed()->find($user->id));
    }
}

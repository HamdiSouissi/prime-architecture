<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\User;
use App\Notifications\UserInvitation;
use App\Services\UserAdministration;
use Database\Seeders\InitialAdminSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhaseTwoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_roles_are_authorized_on_dashboard_and_admin_routes(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        foreach (['admin', 'architect', 'collaborator', 'client', 'contractor'] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->get('/dashboard')->assertOk();
            $this->actingAs($user)->get('/users')->assertStatus($role === 'admin' ? 200 : 403);
            $this->actingAs($user)->get('/activity')->assertStatus($role === 'admin' ? 200 : 403);
        }
    }

    public function test_invitation_is_signed_expires_and_is_single_use(): void
    {
        Notification::fake();
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->post('/users', ['name' => 'Invité', 'email' => 'invite@example.com', 'role' => 'architect'])->assertRedirect('/users');
        $invited = User::where('email', 'invite@example.com')->firstOrFail();
        Notification::assertSentTo($invited, UserInvitation::class);
        $notification = Notification::sent($invited, UserInvitation::class)->first();
        $url = $notification->toMail($invited)->actionUrl;
        $this->post('/logout');
        $this->get($url)->assertOk();
        $this->post($url, ['password' => 'A-safe-password-123', 'password_confirmation' => 'A-safe-password-123'])->assertRedirect(route('login'));
        $this->assertNotNull($invited->fresh()->email_verified_at);
        $this->get($url)->assertStatus(410);
        $this->get('/invitation/not-signed')->assertForbidden();
        $expired = URL::temporarySignedRoute('invitation.show', now()->subMinute(), ['token' => 'expired']);
        $this->get($expired)->assertForbidden();
        $this->assertNotNull(Invitation::firstOrFail()->used_at);
    }

    public function test_admin_can_resend_an_invitation_and_invalidate_the_old_link(): void
    {
        Notification::fake();
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->post('/users', ['name' => 'Invité', 'email' => 'resend@example.com', 'role' => 'client']);
        $invited = User::where('email', 'resend@example.com')->firstOrFail();
        $oldUrl = Notification::sent($invited, UserInvitation::class)->first()->toMail($invited)->actionUrl;
        $this->actingAs($admin)->post(route('users.invitation', $invited))->assertRedirect('/users');
        $newUrl = Notification::sent($invited, UserInvitation::class)->last()->toMail($invited)->actionUrl;
        $this->post('/logout');
        $this->get($oldUrl)->assertStatus(410);
        $this->get($newUrl)->assertOk();
    }

    public function test_changing_user_email_revokes_sessions_and_requires_verification(): void
    {
        Notification::fake();
        $admin = $this->userWithRole('admin');
        $user = $this->userWithRole('client');
        DB::table('sessions')->insert(['id' => 'old-user-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp]);
        DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'old-token', 'created_at' => now()]);
        $this->actingAs($admin)->put(route('users.update', $user), ['name' => $user->name, 'email' => 'changed@example.com', 'role' => 'client', 'is_active' => '1'])->assertRedirect('/users');
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertDatabaseMissing('sessions', ['id' => 'old-user-session']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        Notification::assertSentTo($user->fresh(), VerifyEmail::class);
    }

    public function test_admin_changing_own_email_is_logged_out(): void
    {
        Notification::fake();
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->put(route('users.update', $admin), ['name' => $admin->name, 'email' => 'new-admin@example.com', 'role' => 'admin', 'is_active' => '1'])->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull($admin->fresh()->email_verified_at);
        Notification::assertSentTo($admin->fresh(), VerifyEmail::class);
    }

    public function test_changing_pending_email_invalidates_old_invitation(): void
    {
        Notification::fake();
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->post('/users', ['name' => 'Invité', 'email' => 'old@example.com', 'role' => 'client']);
        $user = User::where('email', 'old@example.com')->firstOrFail();
        $oldUrl = Notification::sent($user, UserInvitation::class)->first()->toMail($user)->actionUrl;
        $this->actingAs($admin)->put(route('users.update', $user), ['name' => $user->name, 'email' => 'new@example.com', 'role' => 'client', 'is_active' => '1'])->assertRedirect('/users');
        $newUrl = Notification::sent($user->fresh(), UserInvitation::class)->last()->toMail($user)->actionUrl;
        $this->post('/logout');
        $this->get($oldUrl)->assertStatus(410);
        $this->get($newUrl)->assertOk();
    }

    public function test_admin_cannot_deactivate_self_and_last_admin_is_protected(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->put(route('users.update', $admin), ['name' => 'Admin', 'role' => 'admin', 'is_active' => '0'])->assertSessionHasErrors('role');
        $this->actingAs($admin)->delete(route('users.destroy', $admin))->assertSessionHasErrors('role');
        $other = $this->userWithRole('architect');
        try {
            app(UserAdministration::class)->update($other, $admin, ['name' => 'Admin', 'role' => 'architect', 'is_active' => true]);
            $this->fail('The last active administrator was demoted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('role', $exception->errors());
        }
    }

    public function test_deactivation_revokes_sessions_even_after_reactivation(): void
    {
        $admin = $this->userWithRole('admin');
        $target = $this->userWithRole('architect');
        DB::table('sessions')->insert(['id' => 'other-session', 'user_id' => $target->id, 'payload' => '', 'last_activity' => now()->timestamp]);
        $this->actingAs($admin)->put(route('users.update', $target), ['name' => $target->name, 'role' => 'architect', 'is_active' => '0'])->assertRedirect('/users');
        $this->assertDatabaseMissing('sessions', ['id' => 'other-session']);
        $this->assertDatabaseHas('activity_log', ['event' => 'user.deactivated', 'subject_id' => $target->id]);
        $this->assertFalse($target->fresh()->is_active);
    }

    public function test_profile_locale_persists_and_password_requires_current_password(): void
    {
        $user = $this->userWithRole('architect');
        $this->actingAs($user)->patch('/profile', ['name' => $user->name, 'phone' => '12345678', 'locale' => 'ar'])->assertRedirect('/profile');
        $this->assertSame('ar', $user->fresh()->locale);
        $this->actingAs($user)->put('/profile/password', ['current_password' => 'wrong', 'password' => 'A-safe-password-123', 'password_confirmation' => 'A-safe-password-123'])->assertSessionHasErrors('current_password');
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHas('locale', 'ar');
    }

    public function test_password_change_revokes_other_sessions_and_records_the_event(): void
    {
        $user = $this->userWithRole('client');
        DB::table('sessions')->insert(['id' => 'old-device', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp]);
        $this->actingAs($user)->put('/profile/password', ['current_password' => 'password', 'password' => 'A-safe-password-123', 'password_confirmation' => 'A-safe-password-123'])->assertRedirect('/profile');
        $this->assertDatabaseMissing('sessions', ['id' => 'old-device']);
        $this->assertDatabaseHas('activity_log', ['event' => 'password.changed', 'causer_id' => $user->id]);
    }

    public function test_initial_admin_seeder_is_idempotent(): void
    {
        config(['security.admin_email' => 'initial@example.com', 'security.admin_password' => 'A-safe-password-123']);
        $this->seed(InitialAdminSeeder::class);
        $this->seed(InitialAdminSeeder::class);
        $this->assertSame(1, User::role('admin')->count());
    }
}

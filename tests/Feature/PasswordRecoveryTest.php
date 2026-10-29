<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_password_reset_uses_breeze_broker_and_records_change(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);
        $token = Notification::sent($user, ResetPassword::class)->first()->token;
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'A-long-new-password', 'password_confirmation' => 'A-long-new-password'])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('A-long-new-password', $user->fresh()->password));
        $this->assertDatabaseHas('activity_log', ['event' => 'password.changed', 'causer_id' => $user->id]);
    }

    public function test_reset_token_is_valid_before_the_configured_expiration(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email]);
        $token = Notification::sent($user, ResetPassword::class)->first()->token;

        $this->travel(59)->minutes();
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'A-long-new-password', 'password_confirmation' => 'A-long-new-password'])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('A-long-new-password', $user->fresh()->password));
    }

    public function test_reset_token_expires_after_the_configured_duration(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email]);
        $token = Notification::sent($user, ResetPassword::class)->first()->token;

        $this->travel(61)->minutes();
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'A-long-new-password', 'password_confirmation' => 'A-long-new-password'])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_email_verification_requires_signed_link_and_unlocks_dashboard(): void
    {
        $user = User::factory()->unverified()->create();
        $user->assignRole('client');
        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('verification.notice'));
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->actingAs($user)->get($url)->assertRedirect();
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->actingAs($user->fresh())->get('/dashboard')->assertOk();
    }

    public function test_password_minimum_length_is_enforced(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');
        $this->actingAs($user)->put('/profile/password', ['current_password' => 'password', 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
    }
}

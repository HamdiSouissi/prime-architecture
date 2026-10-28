<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_login_succeeds_and_applies_preferred_locale(): void
    {
        $user = User::factory()->create(['locale' => 'ar']);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/')
            ->assertSessionHas('locale', 'ar');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('activity_log', ['event' => 'login.succeeded', 'causer_id' => $user->id]);
        $this->get('/')->assertSee('lang="ar" dir="rtl"', false);
    }

    public function test_bad_password_and_inactive_account_cannot_log_in(): void
    {
        $user = User::factory()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseHas('activity_log', ['event' => 'login.failed']);

        $user->forceFill(['is_active' => false])->save();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_user_is_logged_out_on_next_request(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->forceFill(['is_active' => false])->save();

        $this->get('/')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_guest_cannot_log_out_and_public_registration_does_not_exist(): void
    {
        $this->post('/logout')->assertRedirect(route('login'));
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertStatus(405);
    }
}

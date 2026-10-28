<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class BilingualPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_and_admin_pages_render_in_both_languages(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = 'test-invitation-token';
        Invitation::create(['user_id' => $admin->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHour()]);
        $invite = URL::temporarySignedRoute('invitation.show', now()->addHour(), ['token' => $token]);
        foreach (['fr' => 'ltr', 'ar' => 'rtl'] as $locale => $direction) {
            foreach (['/login', '/forgot-password', '/reset-password/example?email=test@example.com', $invite] as $url) {
                $this->withSession(['locale' => $locale])->get($url)->assertOk()->assertSee('lang="'.$locale.'" dir="'.$direction.'"', false);
            }
            foreach (['/dashboard', '/profile', '/users', '/users/create', route('users.edit', $admin), '/activity'] as $url) {
                $this->actingAs($admin)->withSession(['locale' => $locale])->get($url)->assertOk()->assertSee('lang="'.$locale.'" dir="'.$direction.'"', false);
            }
            $this->post('/logout');
        }
    }

    public function test_email_verification_page_renders_in_both_languages(): void
    {
        $user = User::factory()->unverified()->create();
        foreach (['fr' => 'ltr', 'ar' => 'rtl'] as $locale => $direction) {
            $this->actingAs($user)->withSession(['locale' => $locale])->get('/verify-email')->assertOk()->assertSee('lang="'.$locale.'" dir="'.$direction.'"', false);
        }
    }
}

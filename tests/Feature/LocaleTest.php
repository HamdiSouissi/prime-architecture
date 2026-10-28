<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocaleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_home_page_is_french_by_default(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('lang="fr" dir="ltr"', false)
            ->assertSee(__('common.welcome'));
    }

    public function test_language_choice_is_kept_in_session_and_renders_rtl(): void
    {
        $this->post('/locale', ['locale' => 'ar'])
            ->assertRedirect(route('home'))
            ->assertSessionHas('locale', 'ar');

        $this->get('/')->assertOk()
            ->assertSee('lang="ar" dir="rtl"', false)
            ->assertSee('مرحبًا بكم في أرشي تونس');

        $this->post('/locale', ['locale' => 'fr'])->assertSessionHas('locale', 'fr');
        $this->get('/')->assertSee('lang="fr" dir="ltr"', false);
    }

    public function test_invalid_language_is_rejected_without_changing_the_session(): void
    {
        $this->withSession(['locale' => 'ar'])
            ->from('/')
            ->post('/locale', ['locale' => 'en'])
            ->assertRedirect('/')
            ->assertSessionHasErrors('locale')
            ->assertSessionHas('locale', 'ar');
    }

    public function test_not_found_page_uses_the_session_language(): void
    {
        $this->withSession(['locale' => 'ar'])->get('/missing-page')
            ->assertNotFound()
            ->assertSee('lang="ar" dir="rtl"', false)
            ->assertSee('الصفحة غير موجودة');
    }
}

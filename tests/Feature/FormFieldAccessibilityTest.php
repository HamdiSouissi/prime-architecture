<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class FormFieldAccessibilityTest extends TestCase
{
    public function test_help_and_error_are_both_associated_with_the_control(): void
    {
        $errors = (new ViewErrorBag)->put('default', new MessageBag(['email' => ['Please check this email.']]));
        View::share('errors', $errors);
        $html = Blade::render('<x-form-field name="email" label="Email" help="Help text" />');
        $this->assertStringContainsString('for="email"', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('aria-describedby="email-help email-error"', $html);
        $this->assertStringContainsString('id="email-help"', $html);
        $this->assertStringContainsString('id="email-error"', $html);
    }
}

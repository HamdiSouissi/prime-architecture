<?php

namespace Tests\Unit;

use Tests\TestCase;

class ConfigurationTest extends TestCase
{
    public function test_supported_locales_are_explicit(): void
    {
        $this->assertSame(['fr', 'ar'], config('architecture.locales'));
    }
}

<?php

namespace Tests\Feature;

use App\Services\StrictUncompromisedVerifier;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CompromisedPasswordTest extends TestCase
{
    public function test_compromised_verifier_fails_closed_and_uses_hash_prefix(): void
    {
        $hash = strtoupper(sha1('known-password'));
        Http::fake(['*' => Http::response(substr($hash, 5).':42', 200)]);
        $verifier = new StrictUncompromisedVerifier;
        $this->assertFalse($verifier->verify(['value' => 'known-password', 'threshold' => 0]));
        Http::assertSent(fn ($request) => str_ends_with($request->url(), substr($hash, 0, 5)) && ! str_contains($request->url(), 'known-password'));
        Http::fake(['*' => Http::response('unavailable', 503)]);
        $this->assertFalse($verifier->verify(['value' => 'another-password', 'threshold' => 0]));
    }
}

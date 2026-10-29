<?php

namespace App\Services;

use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Support\Facades\Http;
use Throwable;

class StrictUncompromisedVerifier implements UncompromisedVerifier
{
    public function verify($data): bool
    {
        $password = (string) ($data['value'] ?? '');
        if ($password === '') {
            return false;
        }
        $hash = strtoupper(sha1($password));
        try {
            $response = Http::withHeaders(['Add-Padding' => 'true'])->timeout(5)
                ->get('https://api.pwnedpasswords.com/range/'.substr($hash, 0, 5))->throw();
            $validLines = 0;
            foreach (preg_split('/\r?\n/', $response->body()) ?: [] as $line) {
                if (! preg_match('/^([A-F0-9]{35}):([0-9]+)$/i', trim($line), $match)) {
                    continue;
                }
                $validLines++;
                if (strtoupper($match[1]) === substr($hash, 5) && (int) $match[2] > (int) ($data['threshold'] ?? 0)) {
                    return false;
                }
            }

            return $validLines > 0;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}

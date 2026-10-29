<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Quote;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class PdfGenerator
{
    public function generate(Quote|Invoice $document, string $language): string
    {
        if (! in_array($language, ['fr', 'ar'], true) || $document->issued_at === null || ! is_array($document->snapshot)) {
            throw new RuntimeException('A PDF requires an issued document and a supported language.');
        }
        $token = (string) config('billing.pdf_token');
        $url = rtrim((string) config('billing.pdf_url'), '/');
        if (strlen($token) < 32 || ! preg_match('#^https?://[a-zA-Z0-9.-]+(?::[0-9]+)?$#D', $url)) {
            throw new RuntimeException('The PDF renderer is not configured.');
        }
        $fonts = [];
        foreach (['inter-latin-400', 'inter-latin-700', 'inter-latin-ext-400', 'inter-latin-ext-700', 'cairo-latin-400', 'cairo-latin-700', 'cairo-arabic-400', 'cairo-arabic-700'] as $font) {
            $path = glob(public_path('build/assets/'.$font.'-normal-*.woff2'))[0] ?? null;
            if (! $path || ! is_file($path)) {
                throw new RuntimeException('Local PDF fonts are unavailable.');
            }
            $fonts[$font] = base64_encode((string) file_get_contents($path));
        }
        $previous = App::getLocale();
        try {
            App::setLocale($language);
            $html = view('billing.pdf', ['document' => $document, 'snapshot' => $document->snapshot, 'language' => $language, 'fonts' => $fonts])->render();
        } finally {
            App::setLocale($previous);
        }
        if (strlen($html) > 1500000) {
            throw new RuntimeException('The PDF document is too large.');
        }
        try {
            $response = Http::timeout(25)->withToken($token)->withBody(json_encode(['html' => $html], JSON_THROW_ON_ERROR), 'application/json')->post($url.'/render');
        } catch (Throwable $exception) {
            throw new RuntimeException('The PDF renderer is unavailable.', previous: $exception);
        }
        $pdf = $response->body();
        if (! $response->successful() || ! str_starts_with($pdf, '%PDF-') || strlen($pdf) > 10485760) {
            throw new RuntimeException('The PDF renderer returned an invalid document.');
        }

        return $pdf;
    }
}

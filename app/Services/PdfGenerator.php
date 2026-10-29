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

        return $this->render('billing.pdf', ['document' => $document, 'snapshot' => $document->snapshot, 'credit' => null], $language);
    }

    public function credit(Invoice $invoice, object $credit, string $language): string
    {
        if ($invoice->issued_at === null || (int) $credit->invoice_id !== (int) $invoice->id) {
            throw new RuntimeException('Invalid credit note.');
        }
        try {
            $data = json_decode((string) $credit->snapshot, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('Invalid credit snapshot.', previous: $exception);
        }
        if (! is_array($data) || ! is_array($data['invoice'] ?? null)) {
            throw new RuntimeException('Invalid credit snapshot.');
        }

        return $this->render('billing.pdf', ['document' => $invoice, 'snapshot' => $data['invoice'], 'credit' => $credit], $language);
    }

    public function receipt(Invoice $invoice, object $payment, string $language): string
    {
        if ($invoice->issued_at === null || ! is_array($invoice->snapshot) || (int) $payment->invoice_id !== (int) $invoice->id) {
            throw new RuntimeException('Invalid payment receipt.');
        }

        return $this->render('billing.receipt-pdf', ['document' => $invoice, 'snapshot' => $invoice->snapshot, 'payment' => $payment], $language);
    }

    /**
     * Generic report rendering, reusing the isolated Chromium renderer.
     *
     * @param  array<string, mixed>  $data
     */
    public function report(array $data, string $language): string
    {
        return $this->render('reports.pdf', $data, $language);
    }

    /** @param array<string, mixed> $data */
    private function render(string $template, array $data, string $language): string
    {
        if (! in_array($language, ['fr', 'ar'], true)) {
            throw new RuntimeException('Unsupported PDF language.');
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
            $html = view($template, $data + ['language' => $language, 'fonts' => $fonts])->render();
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
            throw new RuntimeException('The PDF renderer returned an invalid document (HTTP '.$response->status().').');
        }

        return $pdf;
    }
}

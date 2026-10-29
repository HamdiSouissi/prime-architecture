<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Quote;
use App\Services\PdfGenerator;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class BillingPdfController extends Controller
{
    public function quote(Quote $quote, string $language): Response
    {
        return $this->render($quote, $language);
    }

    public function invoice(Invoice $invoice, string $language): Response
    {
        return $this->render($invoice, $language);
    }

    private function render(Quote|Invoice $document, string $language): Response
    {
        Gate::authorize('view', $document);
        abort_unless($document->issued_at !== null, 404);
        try {
            $pdf = app(PdfGenerator::class)->generate($document, $language);
        } catch (RuntimeException) {
            abort(503, __('phase6.pdf_unavailable'));
        }

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="document-'.$language.'.pdf"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}

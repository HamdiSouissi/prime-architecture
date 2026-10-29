<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Quote;
use App\Services\PdfGenerator;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
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

    public function credit(Invoice $invoice, string $language): Response
    {
        Gate::authorize('view', $invoice);
        $credit = DB::table('credit_notes')->where('invoice_id', $invoice->id)->first();
        abort_unless($credit !== null, 404);
        try {
            $pdf = app(PdfGenerator::class)->credit($invoice, $credit, $language);
        } catch (RuntimeException) {
            abort(503, __('phase6.pdf_unavailable'));
        }

        return $this->pdfResponse($pdf, $language);
    }

    public function receipt(Invoice $invoice, int $payment, string $language): Response
    {
        Gate::authorize('view', $invoice);
        $record = DB::table('payments')->where('invoice_id', $invoice->id)->where('id', $payment)->first();
        abort_unless($record !== null, 404);
        try {
            $pdf = app(PdfGenerator::class)->receipt($invoice, $record, $language);
        } catch (RuntimeException) {
            abort(503, __('phase6.pdf_unavailable'));
        }

        return $this->pdfResponse($pdf, $language);
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

        return $this->pdfResponse($pdf, $language);
    }

    private function pdfResponse(string $pdf, string $language): Response
    {
        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="document-'.$language.'.pdf"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}

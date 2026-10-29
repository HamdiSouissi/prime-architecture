<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quote;
use App\Services\ClientPortalAccess;
use App\Services\PdfGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

class PortalPdfController extends Controller
{
    public function quote(Request $request, Project $project, Quote $quote, string $language, ClientPortalAccess $access, PdfGenerator $pdf): Response
    {
        return $this->render($request, $project, $quote, $language, $access, $pdf);
    }

    public function invoice(Request $request, Project $project, Invoice $invoice, string $language, ClientPortalAccess $access, PdfGenerator $pdf): Response
    {
        return $this->render($request, $project, $invoice, $language, $access, $pdf);
    }

    private function render(Request $request, Project $project, Quote|Invoice $document, string $language, ClientPortalAccess $access, PdfGenerator $pdf): Response
    {
        abort_unless($access->canViewFinance($request->user(), $project), 403);
        abort_unless($document->project_id === $project->id && $document->client_id === $project->client_id && $document->issued_at !== null, 404);
        try {
            $bytes = $pdf->generate($document, $language);
        } catch (RuntimeException) {
            abort(503, __('phase6.pdf_unavailable'));
        }
        activity('portal')->causedBy($request->user())->performedOn($document)->event('portal.pdf_viewed')->log('portal.pdf_viewed');

        return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="document-'.$language.'.pdf"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}

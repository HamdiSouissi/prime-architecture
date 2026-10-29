<?php

namespace App\Http\Controllers;

use App\Enums\QuoteStatus;
use App\Http\Requests\BillingActionRequest;
use App\Http\Requests\BillingDocumentRequest;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quote;
use App\Services\BillingCalculation;
use App\Services\InvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use App\Services\QuoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Quote::class);
        $search = $request->validate(['search' => ['nullable', 'string', 'max:100']])['search'] ?? null;
        $quotes = Quote::query()->visibleTo($request->user())->with('project')->when($search, fn ($q) => $q->where(fn ($q) => $q->where('number', 'like', '%'.$search.'%')->orWhereHas('project', fn ($p) => $p->where('name', 'like', '%'.$search.'%'))))->orderByDesc('id')->paginate(15, ['*'], 'quotes_page')->withQueryString();
        $invoices = Invoice::query()->visibleTo($request->user())->with('project')->when($search, fn ($q) => $q->where(fn ($q) => $q->where('number', 'like', '%'.$search.'%')->orWhereHas('project', fn ($p) => $p->where('name', 'like', '%'.$search.'%'))))->orderByDesc('id')->paginate(15, ['*'], 'invoices_page')->withQueryString();

        return view('billing.index', compact('quotes', 'invoices', 'search'));
    }

    public function createQuote(Project $project): View
    {
        Gate::authorize('create', [Quote::class, $project]);

        return $this->form($project, new Quote, 'quote');
    }

    public function createInvoice(Project $project): View
    {
        Gate::authorize('create', [Invoice::class, $project]);

        return $this->form($project, new Invoice, 'invoice');
    }

    public function editQuote(Quote $quote): View
    {
        Gate::authorize('update', $quote);

        return $this->form($quote->project, $quote, 'quote');
    }

    public function editInvoice(Invoice $invoice): View
    {
        Gate::authorize('update', $invoice);

        return $this->form($invoice->project, $invoice, 'invoice');
    }

    private function form(Project $project, Quote|Invoice $document, string $type): View
    {
        $date = $document->exists ? $document->document_date->toDateString() : today()->toDateString();
        $settings = DB::table('tax_settings')->whereDate('effective_from', '<=', $date)->orderByDesc('effective_from')->orderByDesc('id')->first();
        $rates = $settings ? DB::table('tax_rates')->where('tax_setting_id', $settings->id)->get() : collect();
        $withholdings = $settings ? DB::table('withholding_rates')->where('tax_setting_id', $settings->id)->get() : collect();
        $lines = $document->exists ? $document->lines->map(fn ($line) => ['description' => $line->description, 'unit' => $line->unit, 'quantity' => $line->quantity, 'unit_price' => $line->unit_price, 'discount_percent' => $line->discount_percent, 'tax_rate' => $line->tax_rate])->all() : [];

        return view('billing.form', compact('project', 'document', 'type', 'settings', 'rates', 'withholdings', 'lines'));
    }

    public function preview(BillingDocumentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $document = $request->route('quote') ?? $request->route('invoice');
        $project = $request->route('project') ?? $document->project;
        $type = $request->routeIs('billing.quotes.*') ? 'quote' : 'invoice';
        $result = app(BillingCalculation::class)->calculate($project, CarbonImmutable::parse($data['document_date']), array_values($data['lines']), $data['discount_percent'], isset($data['withholding_rate_id']) ? (int) $data['withholding_rate_id'] : null, $type, false);

        return response()->json($result['totals']);
    }

    public function storeQuote(BillingDocumentRequest $request, Project $project): RedirectResponse
    {
        $quote = app(QuoteService::class)->create($project, $request->user(), $request->validated());

        return redirect()->route('billing.quotes.show', $quote)->with('status', __('phase6.saved'));
    }

    public function storeInvoice(BillingDocumentRequest $request, Project $project): RedirectResponse
    {
        $invoice = app(InvoiceService::class)->create($project, $request->user(), $request->validated());

        return redirect()->route('billing.invoices.show', $invoice)->with('status', __('phase6.saved'));
    }

    public function updateQuote(BillingDocumentRequest $request, Quote $quote): RedirectResponse
    {
        app(QuoteService::class)->update($quote, $request->user(), $request->validated());

        return redirect()->route('billing.quotes.show', $quote)->with('status', __('phase6.saved'));
    }

    public function updateInvoice(BillingDocumentRequest $request, Invoice $invoice): RedirectResponse
    {
        app(InvoiceService::class)->update($invoice, $request->user(), $request->validated());

        return redirect()->route('billing.invoices.show', $invoice)->with('status', __('phase6.saved'));
    }

    public function showQuote(Quote $quote): View
    {
        Gate::authorize('view', $quote);
        $quote->load('lines', 'project');

        return view('billing.show', ['document' => $quote, 'type' => 'quote', 'payments' => collect(), 'withholdings' => collect(), 'balance' => null, 'credit' => null]);
    }

    public function showInvoice(Invoice $invoice): View
    {
        Gate::authorize('view', $invoice);
        $invoice->load('lines', 'project');

        return view('billing.show', ['document' => $invoice, 'type' => 'invoice', 'payments' => DB::table('payments')->where('invoice_id', $invoice->id)->orderByDesc('id')->get(), 'withholdings' => DB::table('payment_withholdings')->where('invoice_id', $invoice->id)->orderByDesc('id')->get(), 'balance' => app(InvoiceService::class)->balance($invoice), 'credit' => DB::table('credit_notes')->where('invoice_id', $invoice->id)->first()]);
    }

    public function issueQuote(BillingActionRequest $request, Quote $quote): RedirectResponse
    {
        app(QuoteService::class)->issue($quote, $request->user());

        return back()->with('status', __('phase6.issued'));
    }

    public function decideQuote(BillingActionRequest $request, Quote $quote): RedirectResponse
    {
        app(QuoteService::class)->decide($quote, $request->user(), QuoteStatus::from($request->validated('status')));

        return back()->with('status', __('phase6.saved'));
    }

    public function destroyQuote(BillingActionRequest $request, Quote $quote): RedirectResponse
    {
        app(QuoteService::class)->delete($quote, $request->user());

        return redirect()->route('billing.index')->with('status', __('phase6.saved'));
    }

    public function convertQuote(BillingActionRequest $request, Quote $quote): RedirectResponse
    {
        $invoice = app(InvoiceService::class)->fromQuote($quote, $request->user(), $request->validated('due_date'));

        return redirect()->route('billing.invoices.show', $invoice)->with('status', __('phase6.saved'));
    }

    public function issueInvoice(BillingActionRequest $request, Invoice $invoice): RedirectResponse
    {
        app(InvoiceService::class)->issue($invoice, $request->user());

        return back()->with('status', __('phase6.issued'));
    }

    public function pay(BillingActionRequest $request, Invoice $invoice): RedirectResponse
    {
        app(InvoiceService::class)->pay($invoice, $request->user(), $request->validated());

        return back()->with('status', __('phase6.saved'));
    }

    public function withholding(BillingActionRequest $request, Invoice $invoice): RedirectResponse
    {
        app(InvoiceService::class)->recordWithholding($invoice, $request->user(), $request->validated());

        return back()->with('status', __('phase6.saved'));
    }

    public function cancel(BillingActionRequest $request, Invoice $invoice): RedirectResponse
    {
        app(InvoiceService::class)->cancel($invoice, $request->user(), $request->validated('reason'));

        return back()->with('status', __('phase6.saved'));
    }
}

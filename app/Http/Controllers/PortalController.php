<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quote;
use App\Services\ClientPortalAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PortalController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('portal.view') && $request->user()->hasRole('client'), 403);
        $projects = Project::query()->visibleTo($request->user())->orderBy('name')->paginate(20);

        return view('portal.index', compact('projects'));
    }

    public function finance(Request $request, Project $project, ClientPortalAccess $access): View
    {
        Gate::authorize('view', $project);
        abort_unless($access->canViewFinance($request->user(), $project), 403);
        $quotes = Quote::query()->where('project_id', $project->id)->where('client_id', $project->client_id)->whereNotNull('issued_at')->latest('id')->get();
        $invoices = Invoice::query()->where('project_id', $project->id)->where('client_id', $project->client_id)->whereNotNull('issued_at')->latest('id')->get();
        $payments = DB::table('payments')->whereIn('invoice_id', $invoices->pluck('id'))->orderByDesc('payment_date')->get();

        return view('portal.finance', compact('project', 'quotes', 'invoices', 'payments'));
    }
}

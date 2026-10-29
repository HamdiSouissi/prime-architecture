<?php

namespace App\Http\Controllers;

use App\Models\MeasurementLine;
use App\Models\PriceItem;
use App\Models\Project;
use App\Services\MeasurementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MeasurementController extends Controller
{
    public function index(Project $project, MeasurementService $service): View
    {
        Gate::authorize('viewProject', [PriceItem::class, $project]);
        $lines = MeasurementLine::query()->where('project_id', $project->id)->with('priceItem')->orderBy('id')->get();
        $totals = [];
        $total = '0.000';
        foreach ($lines as $line) {
            $totals[$line->id] = $service->total($line);
            $total = bcadd($total, $totals[$line->id], 3);
        }

        return view('prices.measurement', ['project' => $project, 'lines' => $lines, 'totals' => $totals, 'total' => $total, 'items' => PriceItem::query()->orderBy('category')->orderBy('name')->get()]);
    }

    public function store(Request $request, Project $project, MeasurementService $service): RedirectResponse
    {
        Gate::authorize('manageProject', [PriceItem::class, $project]);
        $data = $request->validate([
            'price_item_id' => ['required', 'integer', 'exists:price_items,id'],
            'quantity' => ['required', 'string', 'regex:/^(?:0|[1-9][0-9]{0,8})(?:\.[0-9]{1,6})?$/D'],
        ]);
        $service->add($project, PriceItem::query()->findOrFail($data['price_item_id']), $request->user(), $data['quantity']);

        return back()->with('status', __('phase9.saved'));
    }

    public function destroy(Request $request, Project $project, MeasurementLine $line, MeasurementService $service): RedirectResponse
    {
        Gate::authorize('manageProject', [PriceItem::class, $project]);
        abort_unless($line->project_id === $project->id, 404);
        $service->remove($line, $request->user());

        return back()->with('status', __('phase9.saved'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeeScheduleRequest;
use App\Models\FeeSchedule;
use App\Models\Project;
use App\Services\FeeCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class FeeScheduleController extends Controller
{
    public function index(Project $project): View
    {
        Gate::authorize('viewAny', [FeeSchedule::class, $project]);
        $fees = FeeSchedule::query()->where('project_id', $project->id)->orderBy('sort_order')->orderBy('id')->get();
        $totals = [];
        foreach ($fees as $fee) {
            // DECIMAL(15,6) stores trailing zeroes; percentage accepts four places and fixed amounts three.
            $scale = $fee->mode === 'fixed' ? 3 : 4;
            $value = bcadd((string) $fee->value, '0', $scale);
            $totals[$fee->id] = app(FeeCalculator::class)->calculate($fee->mode, $value, $fee->works_amount);
        }

        return view('billing.fees', compact('project', 'fees', 'totals'));
    }

    public function store(FeeScheduleRequest $request, Project $project): RedirectResponse
    {
        $data = $request->validated();
        $this->validateCalculation($data);
        DB::transaction(function () use ($project, $request, $data) {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            Gate::forUser($request->user())->authorize('manage', [FeeSchedule::class, $project]);
            $fee = FeeSchedule::create(['project_id' => $project->id, 'phase' => $data['phase'], 'mode' => $data['mode'], 'value' => $data['value'], 'works_amount' => $data['mode'] === 'percentage' ? $data['works_amount'] : null]);
            activity('billing')->causedBy($request->user())->performedOn($fee)->event('fee.created')->log('fee.created');
        }, 3);

        return back()->with('status', __('phase6.saved'));
    }

    public function update(FeeScheduleRequest $request, Project $project, int $fee): RedirectResponse
    {
        $data = $request->validated();
        $this->validateCalculation($data);
        DB::transaction(function () use ($request, $project, $fee, $data) {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            Gate::forUser($request->user())->authorize('manage', [FeeSchedule::class, $project]);
            $record = FeeSchedule::query()->where('project_id', $project->id)->lockForUpdate()->findOrFail($fee);
            $record->update(['phase' => $data['phase'], 'mode' => $data['mode'], 'value' => $data['value'], 'works_amount' => $data['mode'] === 'percentage' ? $data['works_amount'] : null]);
            activity('billing')->causedBy($request->user())->performedOn($record)->event('fee.updated')->log('fee.updated');
        }, 3);

        return back()->with('status', __('phase6.saved'));
    }

    public function destroy(Request $request, Project $project, int $fee): RedirectResponse
    {
        DB::transaction(function () use ($request, $project, $fee) {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            Gate::forUser($request->user())->authorize('manage', [FeeSchedule::class, $project]);
            $record = FeeSchedule::query()->where('project_id', $project->id)->lockForUpdate()->findOrFail($fee);
            activity('billing')->causedBy($request->user())->performedOn($record)->event('fee.deleted')->log('fee.deleted');
            $record->delete();
        }, 3);

        return back()->with('status', __('phase6.saved'));
    }

    /** @param array<string, mixed> $data */
    private function validateCalculation(array $data): void
    {
        try {
            app(FeeCalculator::class)->calculate($data['mode'], $data['value'], $data['works_amount'] ?? null);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['value' => __('phase6.invalid_amount')]);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlanningTemplateApplyRequest;
use App\Http\Requests\PlanningTemplateItemRequest;
use App\Http\Requests\PlanningTemplateRequest;
use App\Models\PlanningTemplate;
use App\Models\PlanningTemplateItem;
use App\Models\Project;
use App\Services\PlanningTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlanningTemplateController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manage', PlanningTemplate::class);
        $templates = PlanningTemplate::query()->with('items.predecessors')->orderBy('name_fr')->paginate(20);

        return view('planning.templates', compact('templates'));
    }

    public function store(PlanningTemplateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $template = PlanningTemplate::create($data);
        activity('planning')->performedOn($template)->event('template.created')->log('template.created');

        return redirect()->route('planning.templates.index')->with('status', __('common.saved'));
    }

    public function update(PlanningTemplateRequest $request, PlanningTemplate $template): RedirectResponse
    {
        DB::transaction(function () use ($request, $template) {
            $template = PlanningTemplate::query()->lockForUpdate()->findOrFail($template->id);
            $data = $request->validated();
            $data['is_active'] = (bool) ($data['is_active'] ?? false);
            $template->update($data);
            activity('planning')->performedOn($template)->event('template.updated')->log('template.updated');
        }, 3);

        return redirect()->route('planning.templates.index')->with('status', __('common.saved'));
    }

    public function destroy(PlanningTemplate $template): RedirectResponse
    {
        Gate::authorize('manage', PlanningTemplate::class);
        DB::transaction(function () use ($template) {
            $template = PlanningTemplate::query()->lockForUpdate()->findOrFail($template->id);
            $template->delete();
            activity('planning')->performedOn($template)->event('template.archived')->log('template.archived');
        }, 3);

        return redirect()->route('planning.templates.index')->with('status', __('common.saved'));
    }

    public function storeItem(PlanningTemplateItemRequest $request, PlanningTemplate $template): RedirectResponse
    {
        $this->saveItem($request, $template, null);

        return redirect()->route('planning.templates.index')->with('status', __('common.saved'));
    }

    public function updateItem(PlanningTemplateItemRequest $request, PlanningTemplate $template, PlanningTemplateItem $item): RedirectResponse
    {
        $this->saveItem($request, $template, $item);

        return redirect()->route('planning.templates.index')->with('status', __('common.saved'));
    }

    private function saveItem(PlanningTemplateItemRequest $request, PlanningTemplate $template, ?PlanningTemplateItem $item): void
    {
        DB::transaction(function () use ($request, $template, $item) {
            $template = PlanningTemplate::query()->lockForUpdate()->findOrFail($template->id);
            if ($template->items()->count() >= (int) config('planning.gantt_max_items') && ! $item) {
                throw ValidationException::withMessages(['template' => __('phase5.template_invalid')]);
            }
            $data = $request->safe()->except('predecessors');
            $data['visible_to_client'] = (bool) ($data['visible_to_client'] ?? false);
            if (($data['type'] === 'task' && (int) $data['duration_workdays'] < 1) || ($data['type'] === 'milestone' && (int) $data['duration_workdays'] !== 0)) {
                throw ValidationException::withMessages(['duration_workdays' => __('phase5.template_invalid')]);
            }
            if ($item) {
                $item = $template->items()->findOrFail($item->id);
                $item->update($data);
            } else {
                $item = $template->items()->create($data);
            }
            $item->predecessors()->syncWithPivotValues($request->validated('predecessors') ?? [], ['planning_template_id' => $template->id]);
            $items = $template->items()->with('predecessors')->get()->keyBy('id');
            foreach ($items as $current) {
                foreach ($current->predecessors as $predecessor) {
                    if ($current->type !== 'task' || $predecessor->type !== 'task' || $current->offset_workdays < $predecessor->offset_workdays + $predecessor->duration_workdays) {
                        throw ValidationException::withMessages(['predecessors' => __('phase5.template_invalid')]);
                    }
                }
            }
            activity('planning')->performedOn($template)->event('template.item_saved')->withProperties(['item_id' => $item->id])->log('template.item_saved');
        }, 3);
    }

    public function destroyItem(PlanningTemplate $template, PlanningTemplateItem $item): RedirectResponse
    {
        Gate::authorize('manage', PlanningTemplate::class);
        abort_unless($item->planning_template_id === $template->id, 403);
        DB::transaction(function () use ($template, $item) {
            PlanningTemplate::query()->lockForUpdate()->findOrFail($template->id);
            DB::table('planning_template_dependencies')->where('planning_template_id', $template->id)->where(fn ($q) => $q->where('item_id', $item->id)->orWhere('depends_on_item_id', $item->id))->delete();
            $item->delete();
            activity('planning')->performedOn($template)->event('template.item_deleted')->withProperties(['item_id' => $item->id])->log('template.item_deleted');
        }, 3);

        return redirect()->route('planning.templates.index')->with('status', __('common.saved'));
    }

    public function preview(PlanningTemplateApplyRequest $request, Project $project, PlanningTemplate $template, PlanningTemplateService $service): View
    {
        $data = $request->validated();
        $preview = $service->preview($project, $template, $request->user(), $data['start'], $data['locale']);

        return view('planning.template-preview', compact('project', 'template', 'data', 'preview'));
    }

    public function apply(PlanningTemplateApplyRequest $request, Project $project, PlanningTemplate $template, PlanningTemplateService $service): RedirectResponse
    {
        $data = $request->validated();
        $service->apply($project, $template, $request->user(), $data['start'], $data['locale'], $data['token']);

        return redirect()->route('planning.index', $project)->with('status', __('common.saved'));
    }
}

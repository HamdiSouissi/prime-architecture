<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $internal = Gate::allows('dashboard.internal.view');
        abort_unless($internal || Gate::allows('dashboard.client.view'), 403);
        $counts = collect();
        $recent = collect();
        if ($request->user()->can('projects.view')) {
            $counts = Project::query()->visibleTo($request->user())->whereIn('status', config('projects.active_statuses'))->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
            $recent = Project::query()->visibleTo($request->user())->orderByDesc('updated_at')->orderByDesc('id')->limit(5)->get();
        }

        return view('dashboard', ['internal' => $internal, 'counts' => $counts, 'recent' => $recent]);
    }
}

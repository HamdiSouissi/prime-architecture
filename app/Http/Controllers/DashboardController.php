<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\DashboardService;
use App\Services\TimeEntryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboardService): View
    {
        $user = $request->user();
        $internal = Gate::allows('dashboard.internal.view');
        abort_unless($internal || Gate::allows('dashboard.client.view'), 403);

        if (! $internal) {
            // Clients and contractors get a read-only summary without financial data.
            $projects = $user->can('projects.view')
                ? Project::query()->visibleTo($user)->orderByDesc('updated_at')->orderByDesc('id')->limit(5)->get()
                : collect();

            return view('dashboard', ['internal' => false, 'clientProjects' => $projects]);
        }

        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();

        return view('dashboard', [
            'internal' => true,
            'overview' => $dashboardService->overview($user, $today),
            'running' => $user->can('time.log') ? app(TimeEntryService::class)->runningFor($user) : null,
            'today' => $today,
        ]);
    }
}

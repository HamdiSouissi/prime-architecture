<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlanningPreferenceRequest;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanningNotificationController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('tasks.view'), 403);
        $notifications = $request->user()->notifications()->latest()->paginate(30);
        $ids = $notifications->getCollection()->map(fn ($notification) => $notification->data['task_id'] ?? null)->filter()->unique()->values()->all();
        $tasks = Task::query()->visibleTo($request->user())->whereIn('id', $ids)->get(['id', 'project_id', 'title'])->keyBy('id');

        return view('planning.notifications', compact('notifications', 'tasks'));
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        abort_unless($request->user()->can('tasks.view'), 403);
        $request->user()->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        return redirect()->route('planning.notifications');
    }

    public function preference(PlanningPreferenceRequest $request): RedirectResponse
    {
        $request->user()->forceFill($request->validated())->save();

        return redirect()->route('planning.notifications')->with('status', __('common.saved'));
    }
}

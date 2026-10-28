<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('activity.view');
        $data = $request->validate(['user_id' => ['nullable', 'integer', 'min:1'], 'action' => ['nullable', 'string', 'max:100'], 'date_from' => ['nullable', 'date_format:Y-m-d'], 'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from']]);
        $query = Activity::with('causer')->orderByDesc('id');
        if (! empty($data['user_id'])) {
            $query->where('causer_type', User::class)->where('causer_id', $data['user_id']);
        }
        if (! empty($data['action'])) {
            $query->where('event', $data['action']);
        }
        if (! empty($data['date_from'])) {
            $query->whereDate('created_at', '>=', $data['date_from']);
        }
        if (! empty($data['date_to'])) {
            $query->whereDate('created_at', '<=', $data['date_to']);
        }

        return view('activity.index', ['activities' => $query->paginate(20)->withQueryString(), 'users' => User::withTrashed()->orderBy('name')->get()]);
    }
}

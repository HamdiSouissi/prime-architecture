<?php

namespace App\Http\Controllers;

use App\Enums\SnagSeverity;
use App\Models\Project;
use App\Models\Snag;
use App\Services\OfflineSnagSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OfflineSnagController extends Controller
{
    public function store(Request $request, Project $project, OfflineSnagSync $sync): JsonResponse
    {
        Gate::authorize('create', [Snag::class, $project]);
        $data = $request->validate([
            'operation_id' => ['required', 'uuid'],
            'owner_id' => ['required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:200'],
            'severity' => ['required', Rule::enum(SnagSeverity::class)],
            'description' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:150'],
            'responsible' => ['nullable', 'string', 'max:150'],
            'due_on' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return response()->json(['id' => $sync->create($project, $request->user(), $data)])->header('Cache-Control', 'private, no-store');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\SitePhoto;
use App\Services\OfflinePhotoSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OfflinePhotoController extends Controller
{
    public function store(Request $request, Project $project, OfflinePhotoSync $sync): JsonResponse
    {
        Gate::authorize('create', [SitePhoto::class, $project]);
        $data = $request->validate([
            'operation_id' => ['required', 'uuid'],
            'owner_id' => ['required', 'integer', 'min:1'],
            'photo' => ['required', 'file', 'max:'.(int) config('site.photo_max_kb'), 'mimes:jpg,jpeg,png,webp'],
            'caption' => ['nullable', 'string', 'max:200'],
            'taken_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);
        $file = $request->file('photo');
        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages(['photo' => __('phase8.invalid_photo')]);
        }
        unset($data['photo']);

        return response()->json(['id' => $sync->create($project, $request->user(), $file, $data)])->header('Cache-Control', 'private, no-store');
    }
}

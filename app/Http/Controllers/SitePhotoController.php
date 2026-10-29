<?php

namespace App\Http\Controllers;

use App\Http\Requests\SitePhotoRequest;
use App\Models\Project;
use App\Models\SitePhoto;
use App\Services\SitePhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SitePhotoController extends Controller
{
    public function store(SitePhotoRequest $request, Project $project, SitePhotoService $service): RedirectResponse
    {
        Gate::authorize('create', [SitePhoto::class, $project]);
        $service->store($project, $request->user(), $request->file('photo'), $request->safe()->except('photo'));

        return back()->with('status', __('phase8.photo_uploaded'));
    }

    public function show(Request $request, Project $project, SitePhoto $photo): StreamedResponse
    {
        $this->ensureBelongs($project, $photo);
        Gate::authorize('view', $photo);

        return $this->stream($photo, $photo->path, 'photo-'.$photo->id.'.jpg');
    }

    public function thumbnail(Request $request, Project $project, SitePhoto $photo): StreamedResponse
    {
        $this->ensureBelongs($project, $photo);
        Gate::authorize('view', $photo);

        return $this->stream($photo, $photo->thumbnail_path ?? $photo->path, 'photo-'.$photo->id.'-thumb.jpg');
    }

    public function destroy(Request $request, Project $project, SitePhoto $photo, SitePhotoService $service): RedirectResponse
    {
        $this->ensureBelongs($project, $photo);
        Gate::authorize('delete', $photo);
        $service->delete($photo, $request->user());

        return back()->with('status', __('phase8.photo_deleted'));
    }

    private function stream(SitePhoto $photo, string $path, string $downloadName): StreamedResponse
    {
        abort_unless(Storage::disk($photo->disk)->exists($path), 404);

        return Storage::disk($photo->disk)->response($path, $downloadName, [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    private function ensureBelongs(Project $project, SitePhoto $photo): void
    {
        abort_unless($photo->project_id === $project->id, 404);
    }
}

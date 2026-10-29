<?php

namespace App\Services;

use App\Models\Project;
use App\Models\SitePhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Throwable;

class OfflinePhotoSync
{
    /** @param array<string, mixed> $data */
    public function create(Project $project, User $actor, UploadedFile $file, array $data): int
    {
        $photo = null;
        try {
            // Do not auto-retry a transaction that performs filesystem writes.
            return DB::transaction(function () use ($project, $actor, $file, $data, &$photo): int {
                User::query()->lockForUpdate()->findOrFail($actor->id);
                $project = Project::query()->lockForUpdate()->findOrFail($project->id);
                Gate::forUser($actor)->authorize('create', [SitePhoto::class, $project]);
                abort_unless((int) $data['owner_id'] === $actor->id, 403);
                $checksum = hash_file('sha256', $file->getRealPath());
                abort_unless(is_string($checksum), 422);
                $attributes = ['caption' => $data['caption'] ?? null, 'taken_on' => $data['taken_on'] ?? null];
                $hash = hash_hmac('sha256', json_encode([$project->id, $actor->id, $checksum, $file->getClientOriginalName(), $attributes], JSON_THROW_ON_ERROR), (string) config('app.key'));
                $existing = DB::table('offline_photo_operations')->where('user_id', $actor->id)->where('operation_id', $data['operation_id'])->first();
                if ($existing) {
                    if (! hash_equals($existing->payload_hash, $hash)) {
                        throw new ConflictHttpException('Operation ID reused with different content.');
                    }
                    if ($existing->site_photo_id === null) {
                        throw new GoneHttpException('This photo has already been removed.');
                    }

                    return (int) $existing->site_photo_id;
                }
                $photo = app(SitePhotoService::class)->store($project, $actor, $file, $attributes);
                DB::table('offline_photo_operations')->insert([
                    'user_id' => $actor->id, 'operation_id' => $data['operation_id'], 'payload_hash' => $hash,
                    'project_id' => $project->id, 'site_photo_id' => $photo->id, 'created_at' => now(), 'updated_at' => now(),
                ]);

                return $photo->id;
            });
        } catch (Throwable $error) {
            if ($photo !== null) {
                Storage::disk($photo->disk)->delete(array_filter([$photo->path, $photo->thumbnail_path]));
            }
            throw $error;
        }
    }
}

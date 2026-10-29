<?php

namespace App\Services;

use App\Models\Project;
use App\Models\SiteProgressUpdate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class OfflineProgressSync
{
    /** @param array<string, mixed> $data */
    public function apply(Project $project, User $actor, array $data): int
    {
        return DB::transaction(function () use ($project, $actor, $data): int {
            // Serialize retry keys across projects as well as writers to the same project.
            User::query()->lockForUpdate()->findOrFail($actor->id);
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            Gate::forUser($actor)->authorize('update', $project);
            abort_unless($actor->can('site.progress.record') && (int) $data['owner_id'] === $actor->id, 403);
            // Bind the retry key to the exact authenticated payload, without retaining its contents.
            $hash = hash_hmac('sha256', json_encode([
                $project->id, $actor->id, $data['base_revision'] ?? null,
                $data['recorded_on'], $data['percent'], $data['note'] ?? null,
            ], JSON_THROW_ON_ERROR), (string) config('app.key'));
            $existing = DB::table('offline_operations')->where('user_id', $actor->id)->where('operation_id', $data['operation_id'])->first();
            if ($existing) {
                if (! hash_equals($existing->payload_hash, $hash)) {
                    throw new ConflictHttpException('Operation ID reused with different content.');
                }

                return (int) $existing->site_progress_update_id;
            }
            $revision = SiteProgressUpdate::query()->where('project_id', $project->id)->max('id');
            $base = $data['base_revision'] ?? null;
            if (($revision === null ? null : (int) $revision) !== ($base === null ? null : (int) $base)) {
                throw new ConflictHttpException('The server has newer progress.');
            }
            $update = app(SiteProgressService::class)->record($project, $actor, $data);
            DB::table('offline_operations')->insert([
                'user_id' => $actor->id, 'operation_id' => $data['operation_id'], 'payload_hash' => $hash,
                'project_id' => $project->id, 'site_progress_update_id' => $update->id, 'created_at' => now(), 'updated_at' => now(),
            ]);

            return $update->id;
        }, 3);
    }
}

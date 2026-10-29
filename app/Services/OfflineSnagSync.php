<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Snag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class OfflineSnagSync
{
    /** @param array<string, mixed> $data */
    public function create(Project $project, User $actor, array $data): int
    {
        return DB::transaction(function () use ($project, $actor, $data): int {
            User::query()->lockForUpdate()->findOrFail($actor->id);
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            Gate::forUser($actor)->authorize('create', [Snag::class, $project]);
            abort_unless((int) $data['owner_id'] === $actor->id, 403);
            $attributes = [
                'title' => $data['title'], 'severity' => $data['severity'],
                'description' => $data['description'] ?? null, 'location' => $data['location'] ?? null,
                'responsible' => $data['responsible'] ?? null, 'due_on' => $data['due_on'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
            ];
            $hash = hash_hmac('sha256', json_encode([$project->id, $actor->id, $attributes], JSON_THROW_ON_ERROR), (string) config('app.key'));
            $existing = DB::table('offline_snag_operations')->where('user_id', $actor->id)->where('operation_id', $data['operation_id'])->first();
            if ($existing) {
                if (! hash_equals($existing->payload_hash, $hash)) {
                    throw new ConflictHttpException('Operation ID reused with different content.');
                }

                return (int) $existing->snag_id;
            }
            $snag = app(SiteSupervisionService::class)->createSnag($project, $actor, $attributes);
            DB::table('offline_snag_operations')->insert([
                'user_id' => $actor->id, 'operation_id' => $data['operation_id'], 'payload_hash' => $hash,
                'project_id' => $project->id, 'snag_id' => $snag->id, 'created_at' => now(), 'updated_at' => now(),
            ]);

            return $snag->id;
        }, 3);
    }
}

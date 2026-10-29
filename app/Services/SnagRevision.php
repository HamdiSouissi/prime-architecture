<?php

namespace App\Services;

use App\Models\Snag;

/** Opaque optimistic revision including all editable fields and lifecycle state. */
class SnagRevision
{
    public function for(Snag $snag): string
    {
        return hash_hmac('sha256', json_encode([
            $snag->id, $snag->project_id, $snag->site_report_id, $snag->title, $snag->description,
            $snag->internal_notes, $snag->location, $snag->responsible, $snag->severity->value,
            $snag->status->value, $snag->due_on?->toDateString(), $snag->updated_at?->toISOString(),
        ], JSON_THROW_ON_ERROR), (string) config('app.key'));
    }
}

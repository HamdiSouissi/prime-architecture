<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermitStep extends Model
{
    protected $fillable = ['permit_dossier_id', 'label', 'due_on', 'completed_on', 'notes'];

    protected function casts(): array
    {
        return ['due_on' => 'immutable_date', 'completed_on' => 'immutable_date'];
    }

    /** @return BelongsTo<PermitDossier, $this> */
    public function dossier(): BelongsTo
    {
        return $this->belongsTo(PermitDossier::class, 'permit_dossier_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectStatusHistory extends Model
{
    protected $table = 'project_status_history';

    public $timestamps = false;

    protected $fillable = ['from_status', 'to_status', 'changed_by', 'comment'];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by')->withTrashed();
    }
}

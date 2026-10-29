<?php

namespace App\Models;

use Database\Factories\DocumentCommentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentComment extends Model
{
    /** @use HasFactory<DocumentCommentFactory> */
    use HasFactory;

    use SoftDeletes;

    public $timestamps = false;

    protected $fillable = ['document_id', 'document_version_id', 'user_id', 'body', 'is_internal'];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean', 'created_at' => 'datetime'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}

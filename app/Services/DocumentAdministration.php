<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class DocumentAdministration
{
    public function __construct(private readonly DocumentFileInspector $inspector) {}

    public function create(Project $project, User $actor, array $data, UploadedFile $file): Document
    {
        $meta = $this->inspector->inspect($file);
        $stream = fopen($file->getRealPath(), 'rb');
        if ($stream === false) {
            throw new RuntimeException('Unable to read uploaded document.');
        }
        try {
            return $this->stage($stream, $meta, function (array $stored) use ($project, $actor, $data) {
                $project = Project::query()->lockForUpdate()->findOrFail($project->id);
                Gate::forUser($actor)->authorize('create', [Document::class, $project]);
                $document = Document::create(['project_id' => $project->id, 'created_by' => $actor->id] + $data);
                $version = $this->append($document, $actor, $stored, null);
                activity('documents')->causedBy($actor)->performedOn($document)->event('document.created')->withProperties(['version' => $version->version_number])->log('document.created');

                return $document;
            });
        } finally {
            fclose($stream);
        }
    }

    public function addVersion(Document $document, User $actor, UploadedFile $file, ?string $note): DocumentVersion
    {
        $meta = $this->inspector->inspect($file);
        $stream = fopen($file->getRealPath(), 'rb');
        if ($stream === false) {
            throw new RuntimeException('Unable to read uploaded document.');
        }
        try {
            return $this->stage($stream, $meta, function (array $stored) use ($document, $actor, $note) {
                $locked = Document::query()->lockForUpdate()->findOrFail($document->id);
                Gate::forUser($actor)->authorize('update', $locked);
                $version = $this->append($locked, $actor, $stored, $note);
                activity('documents')->causedBy($actor)->performedOn($locked)->event('document.version_created')->withProperties(['version' => $version->version_number])->log('document.version_created');

                return $version;
            });
        } finally {
            fclose($stream);
        }
    }

    public function copyVersion(Document $document, DocumentVersion $source, User $actor): DocumentVersion
    {
        abort_unless($source->document_id === $document->id && ! $source->trashed(), 404);
        Gate::forUser($actor)->authorize('update', $document);
        $reader = Storage::disk($source->disk)->readStream($source->path);
        if (! is_resource($reader)) {
            throw new RuntimeException('Unable to read source version.');
        }
        // Verify existing bytes before copying, without loading the file in memory.
        $hash = hash_init('sha256');
        hash_update_stream($hash, $reader);
        fclose($reader);
        if (! hash_equals($source->checksum_sha256, hash_final($hash))) {
            throw ValidationException::withMessages(['file' => __('phase4.integrity_error')]);
        }
        $reader = Storage::disk($source->disk)->readStream($source->path);
        if (! is_resource($reader)) {
            throw new RuntimeException('Unable to read source version.');
        }
        try {
            $meta = $source->only(['original_name', 'extension', 'mime_type', 'size_bytes', 'checksum_sha256']);

            return $this->stage($reader, $meta, function (array $stored) use ($document, $source, $actor) {
                $locked = Document::query()->lockForUpdate()->findOrFail($document->id);
                Gate::forUser($actor)->authorize('update', $locked);
                $source = DocumentVersion::query()->where('document_id', $locked->id)->findOrFail($source->id);
                $version = $this->append($locked, $actor, $stored, __('phase4.restored_from', ['number' => $source->version_number]));
                activity('documents')->causedBy($actor)->performedOn($locked)->event('document.version_copied')->withProperties(['from' => $source->version_number, 'version' => $version->version_number])->log('document.version_copied');

                return $version;
            });
        } finally {
            fclose($reader);
        }
    }

    /** @param resource $stream */
    private function stage($stream, array $meta, callable $commit): mixed
    {
        $id = (string) Str::uuid();
        $disk = (string) config('documents.disk');
        $path = 'blobs/'.$id;
        DB::table('document_upload_intents')->insert(['id' => $id, 'disk' => $disk, 'path' => $path, 'created_at' => now(), 'updated_at' => now()]);
        try {
            if (! Storage::disk($disk)->writeStream($path, $stream, ['visibility' => 'private'])) {
                throw new RuntimeException('Unable to write document.');
            }
            $read = Storage::disk($disk)->readStream($path);
            if (! is_resource($read)) {
                throw new RuntimeException('Unable to verify document.');
            }
            $context = hash_init('sha256');
            $size = hash_update_stream($context, $read);
            fclose($read);
            if ($size !== $meta['size_bytes'] || ! hash_equals($meta['checksum_sha256'], hash_final($context))) {
                throw new RuntimeException('Document integrity verification failed.');
            }

            return DB::transaction(function () use ($id, $disk, $path, $meta, $commit) {
                DB::table('document_upload_intents')->where('id', $id)->lockForUpdate()->firstOrFail();
                $result = $commit($meta + ['disk' => $disk, 'path' => $path]);
                DB::table('document_upload_intents')->where('id', $id)->delete();

                return $result;
            });
        } catch (Throwable $exception) {
            // If a commit outcome is uncertain, the intent lock and a fresh DB
            // check ensure that a committed version is never deleted here.
            try {
                $this->reconcile($id);
            } catch (Throwable $cleanupError) {
                report($cleanupError); // Keep the durable intent for an operator.
            }
            throw $exception;
        }
    }

    public function reconcile(string $id): void
    {
        DB::transaction(function () use ($id) {
            $intent = DB::table('document_upload_intents')->where('id', $id)->lockForUpdate()->first();
            if (! $intent) {
                return;
            }
            if (! DocumentVersion::withTrashed()->where('disk', $intent->disk)->where('path', $intent->path)->exists()) {
                if (Storage::disk($intent->disk)->exists($intent->path) && ! Storage::disk($intent->disk)->delete($intent->path)) {
                    throw new RuntimeException('Unable to remove unreferenced document blob.');
                }
            }
            DB::table('document_upload_intents')->where('id', $id)->delete();
        });
    }

    private function append(Document $document, User $actor, array $stored, ?string $note): DocumentVersion
    {
        $last = $document->versions()->withTrashed()->orderByDesc('version_number')->first();
        if ($last && hash_equals($last->checksum_sha256, $stored['checksum_sha256'])) {
            throw ValidationException::withMessages(['file' => __('phase4.duplicate_file')]);
        }
        $version = $document->versions()->create($stored + ['version_number' => ($last->version_number ?? 0) + 1, 'change_note' => $note, 'uploaded_by' => $actor->id]);
        $document->forceFill(['current_version_id' => $version->id])->save();

        return $version;
    }

    public function archive(Document $document, User $actor): void
    {
        DB::transaction(function () use ($document, $actor) {
            $document = Document::query()->lockForUpdate()->findOrFail($document->id);
            Gate::forUser($actor)->authorize('delete', $document);
            $document->delete();
            activity('documents')->causedBy($actor)->performedOn($document)->event('document.archived')->log('document.archived');
        });
    }

    public function restore(Document $document, User $actor): void
    {
        DB::transaction(function () use ($document, $actor) {
            $document = Document::withTrashed()->lockForUpdate()->findOrFail($document->id);
            Gate::forUser($actor)->authorize('restore', $document);
            $document->restore();
            activity('documents')->causedBy($actor)->performedOn($document)->event('document.restored')->log('document.restored');
        });
    }

    public function archiveVersion(Document $document, DocumentVersion $version, User $actor): void
    {
        DB::transaction(function () use ($document, $version, $actor) {
            $document = Document::query()->lockForUpdate()->findOrFail($document->id);
            Gate::forUser($actor)->authorize('delete', $document);
            $version = $document->versions()->findOrFail($version->id);
            $version->delete();
            if ($document->current_version_id === $version->id) {
                $document->forceFill(['current_version_id' => $document->versions()->first()?->id])->save();
            }
            activity('documents')->causedBy($actor)->performedOn($document)->event('document.version_archived')->withProperties(['version' => $version->version_number])->log('document.version_archived');
        });
    }

    public function restoreVersion(Document $document, DocumentVersion $version, User $actor): void
    {
        DB::transaction(function () use ($document, $version, $actor) {
            $document = Document::query()->lockForUpdate()->findOrFail($document->id);
            Gate::forUser($actor)->authorize('restoreVersion', $document);
            $version = $document->versions()->withTrashed()->findOrFail($version->id);
            $version->restore();
            activity('documents')->causedBy($actor)->performedOn($document)->event('document.version_restored')->withProperties(['version' => $version->version_number])->log('document.version_restored');
        });
    }
}

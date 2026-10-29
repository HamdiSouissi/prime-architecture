<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;
use ZipArchive;

class DocumentZip
{
    /** @param list<int> $ids */
    public function create(Project $project, User $actor, array $ids): string
    {
        $documents = $project->documents()->visibleTo($actor)->whereIn('id', $ids)->with('currentVersion')->get();
        if ($documents->count() !== count($ids)) {
            abort(403);
        }
        $total = 0;
        foreach ($documents as $document) {
            Gate::forUser($actor)->authorize('download', $document);
            if (! $document->currentVersion) {
                throw ValidationException::withMessages(['documents' => __('phase4.no_current_version')]);
            }
            $total += $document->currentVersion->size_bytes;
        }
        if ($total > (int) config('documents.max_zip_bytes')) {
            throw ValidationException::withMessages(['documents' => __('phase4.zip_too_large')]);
        }
        $zipPath = tempnam(sys_get_temp_dir(), 'archi-zip-');
        if ($zipPath === false) {
            throw new RuntimeException('Cannot allocate ZIP.');
        }
        $zip = new ZipArchive;
        $temporaryFiles = [];
        try {
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Cannot create ZIP.');
            }
            foreach ($documents as $document) {
                $version = $document->currentVersion;
                $source = Storage::disk($version->disk)->readStream($version->path);
                $tmp = tempnam(sys_get_temp_dir(), 'archi-file-');
                if (! is_resource($source) || $tmp === false) {
                    throw new RuntimeException('Cannot read document for ZIP.');
                }
                $temporaryFiles[] = $tmp;
                $dest = fopen($tmp, 'wb');
                if ($dest === false) {
                    fclose($source);
                    throw new RuntimeException('Cannot create temporary file.');
                }
                $hash = hash_init('sha256');
                $size = 0;
                try {
                    while (! feof($source)) {
                        $chunk = fread($source, 65536);
                        if ($chunk === false || ($chunk !== '' && fwrite($dest, $chunk) !== strlen($chunk))) {
                            throw new RuntimeException('Cannot copy document for ZIP.');
                        }
                        $size += strlen($chunk);
                        if ($size > $version->size_bytes) {
                            throw new RuntimeException('Document size mismatch.');
                        }
                        hash_update($hash, $chunk);
                    }
                } finally {
                    fclose($source);
                    fclose($dest);
                }
                if ($size !== $version->size_bytes || ! hash_equals($version->checksum_sha256, hash_final($hash))) {
                    throw ValidationException::withMessages(['documents' => __('phase4.integrity_error')]);
                }
                if (! $zip->addFile($tmp, $document->id.'-v'.$version->version_number.'-'.$version->original_name)) {
                    throw new RuntimeException('Cannot add document to ZIP.');
                }
                activity('documents')->causedBy($actor)->performedOn($document)->event('document.downloaded')->withProperties(['version' => $version->version_number, 'bulk' => true])->log('document.downloaded');
            }
            if (! $zip->close()) {
                throw new RuntimeException('Cannot finish ZIP.');
            }
        } catch (Throwable $exception) {
            $zip->close();
            @unlink($zipPath);
            throw $exception;
        } finally {
            foreach ($temporaryFiles as $tmp) {
                @unlink($tmp);
            }
        }

        return $zipPath;
    }
}

<?php

namespace App\Services;

use App\Contracts\DocumentScanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class DocumentFileInspector
{
    public function __construct(private readonly DocumentScanner $scanner) {}

    /** @return array{original_name: string, extension: string, mime_type: string, size_bytes: int, checksum_sha256: string} */
    public function inspect(UploadedFile $file): array
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages(['file' => __('phase4.invalid_file')]);
        }
        $name = str_replace('\\', '/', $file->getClientOriginalName());
        $name = basename($name);
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $name));
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $stem = pathinfo($name, PATHINFO_FILENAME);
        if ($stem === '' || str_contains($stem, '.') || mb_strlen($name) > 200 || ! preg_match('/^[a-z0-9]+$/', $extension)) {
            throw ValidationException::withMessages(['file' => __('phase4.invalid_filename')]);
        }
        $allowed = config('documents.formats', []);
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $size = $file->getSize();
        if (! isset($allowed[$extension]) || ! in_array($mime, $allowed[$extension], true) || $size === false || $size < 1 || $size > (int) config('documents.max_file_kb') * 1024) {
            throw ValidationException::withMessages(['file' => __('phase4.invalid_file')]);
        }
        $path = $file->getRealPath();
        if ($path === false) {
            throw ValidationException::withMessages(['file' => __('phase4.invalid_file')]);
        }
        $head = file_get_contents($path, false, null, 0, 8192);
        if ($head === false || ! $this->matchesContent($extension, $path, $head)) {
            throw ValidationException::withMessages(['file' => __('phase4.invalid_file')]);
        }
        $this->scanner->inspect($path);
        $checksum = hash_file('sha256', $path);
        if ($checksum === false) {
            throw ValidationException::withMessages(['file' => __('phase4.invalid_file')]);
        }

        return ['original_name' => $name, 'extension' => $extension, 'mime_type' => $mime, 'size_bytes' => $size, 'checksum_sha256' => $checksum];
    }

    private function matchesContent(string $extension, string $path, string $head): bool
    {
        if ($extension === 'pdf') {
            return str_starts_with($head, '%PDF-');
        }
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $image = @getimagesize($path);
            $expected = ['jpg' => IMAGETYPE_JPEG, 'jpeg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG, 'webp' => IMAGETYPE_WEBP];

            return $image !== false && $image[2] === $expected[$extension];
        }
        if ($extension === 'dwg') {
            return preg_match('/^AC10[0-9]{2}/', $head) === 1;
        }
        if ($extension === 'dxf') {
            return str_starts_with($head, "AutoCAD Binary DXF\r\n\x1a\0") || preg_match('/(?:^|\R)\s*SECTION\s*(?:\R|$)/', $head) === 1;
        }
        if (in_array($extension, ['docx', 'xlsx', 'pptx', 'zip'], true)) {
            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::RDONLY) !== true) {
                return false;
            }
            $required = ['docx' => 'word/document.xml', 'xlsx' => 'xl/workbook.xml', 'pptx' => 'ppt/presentation.xml'];
            $valid = $extension === 'zip' || ($zip->locateName('[Content_Types].xml') !== false && $zip->locateName($required[$extension]) !== false);
            $zip->close();

            return $valid;
        }

        return true;
    }
}

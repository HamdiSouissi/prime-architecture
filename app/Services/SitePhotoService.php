<?php

namespace App\Services;

use App\Models\Project;
use App\Models\SitePhoto;
use App\Models\SiteReport;
use App\Models\Snag;
use App\Models\User;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Site photos are re-encoded with GD before storage. Re-encoding strips EXIF
 * metadata (notably GPS coordinates), normalises the format to JPEG and
 * guarantees the stored bytes are a freshly produced image rather than the
 * uploaded payload. It is not a malware scan.
 */
class SitePhotoService
{
    private const ALLOWED = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    /** @param array<string, mixed> $data */
    public function store(Project $project, User $actor, UploadedFile $file, array $data): SitePhoto
    {
        if (! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('The GD extension is required to process site photos.');
        }
        if (! $file->isValid()) {
            throw ValidationException::withMessages(['photo' => __('phase8.invalid_photo')]);
        }
        $bytes = (int) $file->getSize();
        if ($bytes < 1 || $bytes > (int) config('site.photo_max_kb') * 1024) {
            throw ValidationException::withMessages(['photo' => __('phase8.photo_too_large', ['limit' => (int) config('site.photo_max_kb')])]);
        }
        $info = @getimagesize($file->getRealPath());
        if ($info === false || ! in_array($info[2], self::ALLOWED, true) || $info[0] < 1 || $info[1] < 1) {
            throw ValidationException::withMessages(['photo' => __('phase8.invalid_photo')]);
        }
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $source instanceof GdImage) {
            throw ValidationException::withMessages(['photo' => __('phase8.invalid_photo')]);
        }

        $disk = (string) config('site.disk');
        $directory = 'site/'.$project->id.'/'.now()->format('Y/m');
        $name = (string) Str::uuid();
        $path = $directory.'/'.$name.'.jpg';
        $thumbPath = $directory.'/'.$name.'-thumb.jpg';
        try {
            $main = $this->resize($source, (int) config('site.photo_max_dimension'));
            $thumb = $this->resize($source, (int) config('site.thumbnail_dimension'));
            $width = imagesx($main);
            $height = imagesy($main);
            $mainBytes = $this->encode($main, (int) config('site.photo_quality'));
            $thumbBytes = $this->encode($thumb, (int) config('site.thumbnail_quality'));
            imagedestroy($main);
            imagedestroy($thumb);
        } finally {
            imagedestroy($source);
        }

        if (! Storage::disk($disk)->put($path, $mainBytes, ['visibility' => 'private'])
            || ! Storage::disk($disk)->put($thumbPath, $thumbBytes, ['visibility' => 'private'])) {
            Storage::disk($disk)->delete([$path, $thumbPath]);
            throw new RuntimeException('Unable to store the site photo.');
        }
        $checksum = hash('sha256', $mainBytes);

        try {
            $photo = SitePhoto::create([
                'project_id' => $project->id,
                'site_report_id' => $this->reportId($project, $data['site_report_id'] ?? null),
                'snag_id' => $this->snagId($project, $data['snag_id'] ?? null),
                'disk' => $disk,
                'path' => $path,
                'thumbnail_path' => $thumbPath,
                'original_name' => $this->originalName($file->getClientOriginalName()),
                'mime_type' => 'image/jpeg',
                'size_bytes' => strlen($mainBytes),
                'width' => $width,
                'height' => $height,
                'checksum_sha256' => $checksum,
                'caption' => $this->caption($data['caption'] ?? null),
                'taken_on' => $this->takenOn($data['taken_on'] ?? null),
                'uploaded_by' => $actor->id,
            ]);
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete([$path, $thumbPath]);
            throw $exception;
        }
        activity('site')->causedBy($actor)->performedOn($photo)->withProperties([
            'project_id' => $project->id,
            'site_report_id' => $photo->site_report_id,
            'snag_id' => $photo->snag_id,
        ])->event('site.photo_uploaded')->log('site.photo_uploaded');

        return $photo;
    }

    public function delete(SitePhoto $photo, User $actor): void
    {
        $paths = array_values(array_filter([$photo->path, $photo->thumbnail_path]));
        $photo->delete();
        Storage::disk($photo->disk)->delete($paths);
        activity('site')->causedBy($actor)->withProperties([
            'project_id' => $photo->project_id,
            'photo_id' => $photo->id,
        ])->event('site.photo_deleted')->log('site.photo_deleted');
    }

    private function resize(GdImage $source, int $max): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $ratio = min(1.0, $max / max(1, max($width, $height)));
        $targetWidth = max(1, (int) floor($width * $ratio));
        $targetHeight = max(1, (int) floor($height * $ratio));
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        if (! $canvas instanceof GdImage) {
            throw new RuntimeException('Unable to allocate the photo canvas.');
        }
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, (int) $white);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $canvas;
    }

    private function encode(GdImage $image, int $quality): string
    {
        ob_start();
        imagejpeg($image, null, max(1, min(100, $quality)));
        $contents = (string) ob_get_clean();
        if ($contents === '') {
            throw new RuntimeException('Unable to encode the site photo.');
        }

        return $contents;
    }

    private function reportId(Project $project, mixed $id): ?int
    {
        if ($id === null || $id === '') {
            return null;
        }
        $report = SiteReport::query()->where('project_id', $project->id)->whereKey((int) $id)->first();
        if ($report === null) {
            throw ValidationException::withMessages(['site_report_id' => __('phase8.invalid_reference')]);
        }

        return $report->id;
    }

    private function snagId(Project $project, mixed $id): ?int
    {
        if ($id === null || $id === '') {
            return null;
        }
        $snag = Snag::query()->where('project_id', $project->id)->whereKey((int) $id)->first();
        if ($snag === null) {
            throw ValidationException::withMessages(['snag_id' => __('phase8.invalid_reference')]);
        }

        return $snag->id;
    }

    /** Stored for display only; control and bidi formatting characters are removed. */
    private function originalName(string $name): string
    {
        $base = basename(str_replace('\\', '/', $name));
        $clean = trim((string) preg_replace('/\p{Cc}|\p{Cf}/u', '', $base));

        return mb_substr($clean === '' ? 'photo.jpg' : $clean, 0, 200);
    }

    private function caption(mixed $caption): ?string
    {
        $value = trim((string) $caption);

        return $value === '' ? null : mb_substr($value, 0, 200);
    }

    private function takenOn(mixed $date): ?string
    {
        $value = trim((string) $date);

        return $value === '' ? null : $value;
    }
}

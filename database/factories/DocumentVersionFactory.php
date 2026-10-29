<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<DocumentVersion> */
class DocumentVersionFactory extends Factory
{
    public function definition(): array
    {
        return ['document_id' => Document::factory(), 'version_number' => 1, 'disk' => 'documents', 'path' => 'blobs/'.Str::uuid(), 'original_name' => 'test.txt', 'extension' => 'txt', 'mime_type' => 'text/plain', 'size_bytes' => 4, 'checksum_sha256' => hash('sha256', 'test'), 'uploaded_by' => User::factory()];
    }
}

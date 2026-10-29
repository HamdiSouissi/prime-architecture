<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentZipRequest;
use App\Models\Project;
use App\Services\DocumentZip;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentZipController extends Controller
{
    public function __invoke(DocumentZipRequest $request, Project $project, DocumentZip $service): BinaryFileResponse
    {
        $path = $service->create($project, $request->user(), $request->validated('documents'));

        return response()->download($path, 'documents.zip', ['Content-Type' => 'application/zip', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'])->deleteFileAfterSend(true);
    }
}

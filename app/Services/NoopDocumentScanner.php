<?php

namespace App\Services;

use App\Contracts\DocumentScanner;

class NoopDocumentScanner implements DocumentScanner
{
    public function inspect(string $localPath): void
    {
        // Extension point only: no malware detection is performed.
    }
}

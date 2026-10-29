<?php

namespace App\Contracts;

interface DocumentScanner
{
    /** A deployment can bind a real scanner; the default does not scan. */
    public function inspect(string $localPath): void;
}

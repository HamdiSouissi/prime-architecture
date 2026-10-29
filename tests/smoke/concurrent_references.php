<?php

use App\Services\ProjectReferenceGenerator;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (($argv[1] ?? '') === 'worker') {
    echo DB::transaction(fn () => app(ProjectReferenceGenerator::class)->next(), 5);
    exit;
}

$workers = [];
for ($i = 0; $i < 12; $i++) {
    $worker = new Process([PHP_BINARY, __FILE__, 'worker']);
    $worker->start();
    $workers[] = $worker;
}
$references = [];
foreach ($workers as $worker) {
    $worker->wait();
    if (! $worker->isSuccessful()) {
        throw new RuntimeException('Reference worker failed: '.$worker->getErrorOutput());
    }
    $references[] = trim($worker->getOutput());
}
if (count(array_unique($references)) !== count($workers)) {
    throw new RuntimeException('Duplicate references generated concurrently.');
}
echo 'Concurrent references: '.count($references)." distinct values\n";

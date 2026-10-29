<?php

use App\Services\DocumentNumberGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! app()->environment('testing') || DB::connection()->getDriverName() !== 'mysql') {
    throw new RuntimeException('Only run this test against a disposable MySQL test database.');
}

if (($argv[1] ?? '') === 'worker') {
    echo DB::transaction(fn () => app(DocumentNumberGenerator::class)->next('quote', CarbonImmutable::parse('2100-01-01')), 5);
    exit;
}

DB::table('billing_number_formats')->insert(['document_type' => 'quote', 'effective_from' => '2100-01-01', 'format' => 'CI-{year}-{sequence}', 'padding' => 4]);
try {
    $workers = [];
    for ($i = 0; $i < 12; $i++) {
        $worker = new Process([PHP_BINARY, __FILE__, 'worker']);
        $worker->setTimeout(90);
        $worker->start();
        $workers[] = $worker;
    }
    $numbers = [];
    foreach ($workers as $worker) {
        $worker->wait();
        if (! $worker->isSuccessful()) {
            throw new RuntimeException('Billing number worker failed: '.$worker->getErrorOutput());
        }
        $numbers[] = trim($worker->getOutput());
    }
    sort($numbers);
    $expected = array_map(fn (int $n) => 'CI-2100-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT), range(1, 12));
    if ($numbers !== $expected) {
        throw new RuntimeException('Concurrent billing number allocation is not unique and consecutive.');
    }
    echo "Concurrent billing number allocation: 12 distinct consecutive values\n";
} finally {
    DB::table('billing_number_counters')->where('document_type', 'quote')->where('year', 2100)->delete();
    DB::table('billing_number_formats')->where('document_type', 'quote')->where('effective_from', '2100-01-01')->delete();
}

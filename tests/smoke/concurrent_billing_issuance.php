<?php

use App\Models\Project;
use App\Models\User;
use App\Services\QuoteService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TaxSettingsSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || DB::connection()->getDriverName() !== 'mysql') {
    throw new RuntimeException('Only use a disposable MySQL testing database.');
}
if (($argv[1] ?? '') === 'worker') {
    $project = Project::findOrFail((int) $argv[2]);
    $actor = User::findOrFail((int) $argv[3]);
    $data = ['document_date' => today()->toDateString(), 'valid_until' => null, 'discount_percent' => '0', 'lines' => [['description' => 'Concurrent', 'unit' => null, 'quantity' => '1', 'unit_price' => '1.000', 'discount_percent' => '0', 'tax_rate' => '19.0000']]];
    $quote = app(QuoteService::class)->create($project, $actor, $data);
    echo app(QuoteService::class)->issue($quote, $actor)->number;
    exit;
}
app(RolePermissionSeeder::class)->run();
app(TaxSettingsSeeder::class)->run();
DB::table('tax_settings')->where('seed_key', 'indicative-initial')->update(['is_verified' => true]);
DB::table('billing_profiles')->insert(['effective_from' => today()->toDateString(), 'name' => 'CI cabinet']);
$actor = User::factory()->create();
$actor->assignRole('architect');
$project = Project::factory()->create();
$workers = [];
for ($i = 0; $i < 8; $i++) {
    $worker = new Process([PHP_BINARY, __FILE__, 'worker', (string) $project->id, (string) $actor->id]);
    $worker->setTimeout(90);
    $worker->start();
    $workers[] = $worker;
}
$numbers = [];
foreach ($workers as $worker) {
    $worker->wait();
    if (! $worker->isSuccessful()) {
        throw new RuntimeException('Concurrent issuance worker failed: '.$worker->getErrorOutput());
    }
    $numbers[] = trim($worker->getOutput());
}
sort($numbers);
$year = today()->format('Y');
$expected = array_map(fn (int $n) => 'DEV-'.$year.'-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT), range(1, 8));
if ($numbers !== $expected) {
    throw new RuntimeException('Concurrent issuance was not unique and consecutive.');
}
echo "Concurrent quote issuance: 8 distinct consecutive persisted documents\n";

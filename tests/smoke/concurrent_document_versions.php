<?php

use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\User;
use App\Services\DocumentAdministration;
use App\Services\ProjectAdministration;
use Database\Seeders\DocumentCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Symfony\Component\Process\Process;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function upload(string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'archi-race-');
    if ($path === false) {
        throw new RuntimeException('Cannot create test file');
    }
    file_put_contents($path, "%PDF-1.4\n$content\n%%EOF\n");

    return new UploadedFile($path, 'race.pdf', 'application/pdf', null, true);
}

if (($argv[1] ?? '') === 'worker') {
    $actor = User::findOrFail((int) $argv[2]);
    $document = Document::findOrFail((int) $argv[3]);
    $file = upload('worker-'.$argv[4]);
    try {
        echo app(DocumentAdministration::class)->addVersion($document, $actor, $file, null)->version_number;
    } finally {
        @unlink($file->getPathname());
    }
    exit;
}

app(RolePermissionSeeder::class)->run();
app(DocumentCategorySeeder::class)->run();
$actor = User::factory()->create();
$actor->assignRole('architect');
$project = app(ProjectAdministration::class)->create(['name' => 'Concurrency test', 'client_id' => Client::factory()->create()->id, 'lead_architect_id' => $actor->id, 'project_type' => 'villa'], $actor);
$file = upload('initial');
try {
    $document = app(DocumentAdministration::class)->create($project, $actor, ['title' => 'Concurrency', 'category_id' => DocumentCategory::firstOrFail()->id, 'visible_to_client' => false], $file);
} finally {
    @unlink($file->getPathname());
}
$workers = [];
for ($i = 0; $i < 8; $i++) {
    $worker = new Process([PHP_BINARY, __FILE__, 'worker', (string) $actor->id, (string) $document->id, (string) $i]);
    $worker->setTimeout(90);
    $worker->start();
    $workers[] = $worker;
}
$numbers = [];
foreach ($workers as $worker) {
    $worker->wait();
    if (! $worker->isSuccessful()) {
        throw new RuntimeException('Version worker failed: '.$worker->getErrorOutput());
    }
    $numbers[] = (int) trim($worker->getOutput());
}
sort($numbers);
if ($numbers !== range(2, 9) || $document->fresh()->currentVersion->version_number !== 9) {
    throw new RuntimeException('Concurrent document version numbers are incorrect.');
}
echo "Concurrent document versions: 8 distinct values\n";

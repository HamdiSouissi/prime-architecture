<?php

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Services\PdfGenerator;
use App\Services\QuoteService;
use Database\Seeders\TaxSettingsSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local')) {
    throw new RuntimeException('Only use this on a disposable local CI database.');
}
$actor = User::where('email', env('ADMIN_EMAIL'))->firstOrFail();
app(TaxSettingsSeeder::class)->run();
DB::table('tax_settings')->where('seed_key', 'indicative-initial')->update(['is_verified' => true]);
DB::table('billing_profiles')->insert(['effective_from' => today()->toDateString(), 'name' => 'Cabinet Exemple', 'address' => 'Tunis', 'tax_id' => 'TEST']);
$client = Client::factory()->create(['name' => 'شركة المثال للتصميم', 'address' => 'تونس']);
$project = Project::factory()->create(['client_id' => $client->id, 'name' => 'مشروع سكني نموذجي']);
$data = ['document_date' => today()->toDateString(), 'valid_until' => today()->addDays(30)->toDateString(), 'discount_percent' => '0', 'lines' => [['description' => 'دراسة معمارية أولية — Étude architecturale', 'unit' => 'phase', 'quantity' => '1.000000', 'unit_price' => '1200.000', 'discount_percent' => '0', 'tax_rate' => '19.0000']]];
$quote = app(QuoteService::class)->issue(app(QuoteService::class)->create($project, $actor, $data), $actor);
foreach (['fr', 'ar'] as $language) {
    $pdf = app(PdfGenerator::class)->generate($quote, $language);
    file_put_contents(storage_path('app/private/ci-quote-'.$language.'.pdf'), $pdf);
    echo $language.' PDF: '.strlen($pdf)." bytes\n";
}

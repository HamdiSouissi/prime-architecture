<?php

use Database\Seeders\TaxSettingsSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local')) {
    throw new RuntimeException('Use only on a disposable CI database.');
}
app(TaxSettingsSeeder::class)->run();
DB::table('tax_settings')->where('seed_key', 'indicative-initial')->update(['is_verified' => true]);
DB::table('billing_profiles')->insert(['effective_from' => today()->toDateString(), 'name' => 'Cabinet exemple']);

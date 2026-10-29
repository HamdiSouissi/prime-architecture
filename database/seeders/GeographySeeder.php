<?php

namespace Database\Seeders;

use App\Models\Delegation;
use App\Models\Governorate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GeographySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            foreach (require database_path('data/governorates.php') as [$key, $fr, $ar]) {
                Governorate::updateOrCreate(['key' => $key], ['name_fr' => $fr, 'name_ar' => $ar]);
            }
            foreach (require database_path('data/delegations.php') as [$key, $governorateKey, $fr, $ar]) {
                $governorate = Governorate::where('key', $governorateKey)->firstOrFail();
                Delegation::updateOrCreate(['key' => $key], ['governorate_id' => $governorate->id, 'name_fr' => $fr, 'name_ar' => $ar]);
            }
        });
    }
}

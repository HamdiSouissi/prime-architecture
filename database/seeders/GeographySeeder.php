<?php

namespace Database\Seeders;

use App\Models\Delegation;
use App\Models\Governorate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class GeographySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            foreach (require database_path('data/governorates.php') as [$key, $fr, $ar]) {
                Governorate::updateOrCreate(['key' => $key], ['name_fr' => $fr, 'name_ar' => $ar]);
            }
            $governorates = Governorate::query()->pluck('id', 'key');
            foreach (require database_path('data/delegations.php') as [$key, $governorateKey, $fr, $ar]) {
                $governorateId = $governorates->get($governorateKey);
                if ($governorateId === null) {
                    throw new LogicException("Unknown governorate key: {$governorateKey}");
                }
                $delegation = Delegation::firstOrCreate(['key' => $key], ['governorate_id' => $governorateId, 'name_fr' => $fr, 'name_ar' => $ar]);
                if ((int) $delegation->governorate_id !== (int) $governorateId) {
                    // Never silently move a delegation already referenced by clients or projects.
                    throw new LogicException("Delegation key {$key} already belongs to another governorate.");
                }
                $delegation->update(['name_fr' => $fr, 'name_ar' => $ar]);
            }
        });
    }
}

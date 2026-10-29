<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RolePermissionSeeder::class, GeographySeeder::class, DocumentCategorySeeder::class, WorkingCalendarSeeder::class, PlanningTemplateSeeder::class, TaxSettingsSeeder::class]);
    }
}

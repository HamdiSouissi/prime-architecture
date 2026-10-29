<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkingCalendarSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1, 7) as $day) {
            DB::table('working_calendar_days')->insertOrIgnore(['iso_weekday' => $day, 'is_working' => $day <= 5, 'created_at' => now(), 'updated_at' => now()]);
        }
        $holidays = array_merge(require database_path('data/planning_holidays.php'), require database_path('data/planning_holidays_extra.php'));
        foreach ($holidays as [$key, $date, $fr, $ar, $recurring]) {
            DB::table('holidays')->insertOrIgnore(['key' => $key, 'date' => $date, 'name_fr' => $fr, 'name_ar' => $ar, 'is_recurring_yearly' => $recurring, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}

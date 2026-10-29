<?php

namespace Database\Seeders;

use App\Models\PlanningTemplate;
use Illuminate\Database\Seeder;

class PlanningTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $template = PlanningTemplate::withTrashed()->firstOrCreate(['key' => 'demo-villa'], ['name_fr' => 'Villa — démonstration', 'name_ar' => 'فيلا — نموذج تجريبي', 'is_active' => true]);
        if (! $template->wasRecentlyCreated) {
            return;
        }
        $items = [
            ['Esquisse', 'تصميم أولي', 'task', 0, 5, 'sketch'],
            ['APS', 'الدراسة الأولية', 'task', 5, 5, 'preliminary_design'],
            ['APD', 'الدراسة المفصلة', 'task', 10, 5, 'detailed_design'],
            ['Dossier de permis', 'ملف الرخصة', 'task', 15, 5, 'permit'],
            ['Dépôt du permis', 'إيداع ملف الرخصة', 'milestone', 20, 0, 'permit'],
        ];
        $previous = null;
        foreach ($items as $order => [$fr, $ar, $type, $offset, $duration, $phase]) {
            $item = $template->items()->create(['title_fr' => $fr, 'title_ar' => $ar, 'type' => $type, 'offset_workdays' => $offset, 'duration_workdays' => $duration, 'sort_order' => $order, 'project_phase' => $phase, 'visible_to_client' => false]);
            if ($previous && $type === 'task') {
                $item->predecessors()->attach($previous->id, ['planning_template_id' => $template->id]);
            }
            if ($type === 'task') {
                $previous = $item;
            }
        }
    }
}

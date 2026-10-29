<?php

namespace Database\Seeders;

use App\Models\DocumentCategory;
use Illuminate\Database\Seeder;

class DocumentCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['architectural-plans', 'Plans architecturaux', 'المخططات المعمارية'],
            ['execution-plans', 'Plans d’exécution', 'مخططات التنفيذ'],
            ['administrative', 'Pièces administratives', 'الوثائق الإدارية'],
            ['permits', 'Permis et autorisations', 'الرخص والتراخيص'],
            ['contracts', 'Contrats et devis', 'العقود وعروض الأسعار'],
            ['technical-studies', 'Études techniques', 'الدراسات الفنية'],
            ['photos', 'Photos', 'الصور'],
            ['reports', 'Comptes rendus', 'المحاضر'],
            ['miscellaneous', 'Divers', 'متفرقات'],
        ];
        foreach ($categories as $order => [$slug, $fr, $ar]) {
            DocumentCategory::firstOrCreate(['slug' => $slug], ['name_fr' => $fr, 'name_ar' => $ar, 'sort_order' => $order, 'is_active' => true]);
        }
    }
}

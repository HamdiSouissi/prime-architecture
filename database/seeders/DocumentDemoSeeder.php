<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Project;
use App\Models\User;
use App\Services\DocumentAdministration;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use LogicException;
use RuntimeException;

class DocumentDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new LogicException('Demo data must not be seeded in production.');
        }
        $this->call(DocumentCategorySeeder::class);
        $actor = User::role(['admin', 'architect'])->where('is_active', true)->first();
        $category = DocumentCategory::where('slug', 'architectural-plans')->firstOrFail();
        if (! $actor) {
            return;
        }
        $service = app(DocumentAdministration::class);
        foreach (Project::query()->limit(3)->get() as $project) {
            $title = 'DEMO — Plan de façade';
            if (Document::withTrashed()->where('project_id', $project->id)->where('title', $title)->exists()) {
                continue;
            }
            $path = tempnam(sys_get_temp_dir(), 'archi-demo-');
            if ($path === false) {
                throw new RuntimeException('Cannot create demo file.');
            }
            try {
                file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC', true));
                $file = new UploadedFile($path, 'demonstration.png', 'image/png', null, true);
                $service->create($project, $actor, ['title' => $title, 'category_id' => $category->id, 'description' => null, 'visible_to_client' => false], $file);
            } finally {
                @unlink($path);
            }
        }
    }
}

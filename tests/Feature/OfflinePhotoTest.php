<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\SitePhoto;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfflinePhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_photo_sync_is_private_idempotent_and_does_not_resurrect_deleted_photos(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('documents');
        $user = User::factory()->create();
        $user->assignRole('collaborator');
        $project = Project::factory()->create();
        $project->members()->attach($user->id, ['project_role' => 'collaborator']);
        $image = UploadedFile::fake()->image('site.jpg', 30, 20);
        $contents = (string) file_get_contents($image->getRealPath());
        $file = fn () => UploadedFile::fake()->createWithContent('site.jpg', $contents);
        $input = ['operation_id' => (string) Str::uuid(), 'owner_id' => $user->id, 'caption' => 'Façade'];
        $id = $this->actingAs($user)->post(route('offline.photos.store', $project), $input + ['photo' => $file()], ['Accept' => 'application/json'])->assertOk()->json('id');
        $this->post(route('offline.photos.store', $project), $input + ['photo' => $file()], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('id', $id);
        $this->assertDatabaseCount('site_photos', 1);
        $photo = SitePhoto::query()->findOrFail($id);
        Storage::disk($photo->disk)->assertExists($photo->path);
        $this->delete(route('site.photos.destroy', [$project, $photo]))->assertRedirect();
        $this->post(route('offline.photos.store', $project), $input + ['photo' => $file()], ['Accept' => 'application/json'])->assertStatus(410);
        $this->assertDatabaseCount('site_photos', 0);
    }
}

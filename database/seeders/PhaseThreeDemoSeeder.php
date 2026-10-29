<?php

namespace Database\Seeders;

use App\Enums\ProjectStatus;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectAdministration;
use Illuminate\Database\Seeder;
use LogicException;

class PhaseThreeDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new LogicException('Demo data must not be seeded in production.');
        }
        $architect = User::role(['admin', 'architect'])->where('is_active', true)->first();
        if (! $architect) {
            throw new LogicException('Create an active administrator or architect before seeding demo projects.');
        }
        $names = ['Amel Ben Salah', 'Mohamed Trabelsi', 'Sarra Gharbi', 'Ahmed Mansour', 'Leila Hamdi', 'Sofiene Jlassi', 'Meriem Chaabane', 'Hatem Baccouche', 'Rim Khelifi', 'Youssef Mejri', 'Nadia Ben Amor', 'Slim Ayari', 'Ines Bouzid', 'Walid Guesmi', 'Salma Saidi'];
        $statuses = ProjectStatus::cases();
        $service = app(ProjectAdministration::class);
        foreach ($names as $index => $name) {
            $client = Client::firstOrCreate(['email' => 'demo-client-'.($index + 1).'@example.test'], ['type' => 'individual', 'name' => $name, 'phone' => '+216'.str_pad((string) (20000001 + $index), 8, '0', STR_PAD_LEFT)]);
            foreach ([1, 2] as $number) {
                $projectName = 'Démo — Projet '.($index + 1).'-'.$number;
                if (Project::withTrashed()->where('client_id', $client->id)->where('name', $projectName)->exists()) { continue; }
                if ($client->trashed()) { continue; }
                $project = $service->create(['client_id' => $client->id, 'name' => $projectName, 'project_type' => $number === 1 ? 'villa' : 'renovation', 'lead_architect_id' => $architect->id], $architect);
                $status = $statuses[($index * 2 + $number) % count($statuses)];
                if ($status !== ProjectStatus::Sketch) { $service->changeStatus($project, $architect, $status, null); }
            }
        }
    }
}

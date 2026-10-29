<?php

namespace Database\Seeders;

use App\Enums\ProjectStatus;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Services\ClientPortalAccess;
use App\Services\InvoiceService;
use App\Services\ProjectAdministration;
use App\Services\QuoteService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;
use Spatie\Permission\Models\Role;

/** Explicitly opt-in; demo administrator and architect retain their normal global permissions. */
class DemoWorkspaceSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Demo accounts are allowed only in local or testing environments.');
        }
        $password = config('demo.password');
        if (! is_string($password) || strlen($password) < 12) {
            throw new LogicException('Set DEMO_PASSWORD to a unique password of at least 12 characters.');
        }
        if (! config('demo.allow_existing_data') && (User::withTrashed()->exists() || Client::withTrashed()->exists() || Project::withTrashed()->exists())) {
            throw new LogicException('Existing data detected. Demo admin and architect have global access. Only on an isolated local installation, set DEMO_ALLOW_EXISTING_DATA=1 to acknowledge this risk.');
        }
        $roles = ['admin', 'architect', 'collaborator', 'client', 'contractor'];
        foreach ($roles as $role) {
            if (! Role::where('name', $role)->where('guard_name', 'web')->exists()) {
                throw new LogicException('Missing roles; run the standard database seed before creating demo data.');
            }
        }
        $userEmails = array_map(fn (string $role): string => 'demo-'.$role.'@example.test', $roles);
        $clientEmails = array_map(fn (int $index): string => 'demo-client-'.$index.'@example.test', range(1, 15));
        if (User::withTrashed()->whereIn('email', $userEmails)->exists() || Client::withTrashed()->whereIn('email', $clientEmails)->exists()) {
            throw new LogicException('A reserved demo account or client email already exists, including archived records. No data was changed; resolve the collision before retrying.');
        }

        DB::transaction(function () use ($password, $roles, $clientEmails): void {
            $users = [];
            foreach ($roles as $role) {
                $user = User::create(['name' => 'Démo '.ucfirst($role), 'email' => 'demo-'.$role.'@example.test', 'password' => $password]);
                $user->forceFill(['email_verified_at' => now()])->save();
                $user->assignRole($role);
                $users[$role] = $user;
            }
            $names = ['Amel Ben Salah', 'Mohamed Trabelsi', 'Sarra Gharbi', 'Ahmed Mansour', 'Leila Hamdi', 'Sofiene Jlassi', 'Meriem Chaabane', 'Hatem Baccouche', 'Rim Khelifi', 'Youssef Mejri', 'Nadia Ben Amor', 'Slim Ayari', 'Ines Bouzid', 'Walid Guesmi', 'Salma Saidi'];
            $statuses = ProjectStatus::cases();
            $service = app(ProjectAdministration::class);
            $firstClient = null;
            $firstProject = null;
            foreach ($names as $index => $name) {
                $client = Client::create(['email' => $clientEmails[$index], 'type' => 'individual', 'name' => 'Démo — '.$name, 'phone' => '+216'.str_pad((string) (20000001 + $index), 8, '0', STR_PAD_LEFT)]);
                $firstClient ??= $client;
                foreach ([1, 2] as $number) {
                    $project = $service->create(['client_id' => $client->id, 'name' => 'Démo — Projet '.($index + 1).'-'.$number, 'project_type' => $number === 1 ? 'villa' : 'renovation', 'lead_architect_id' => $users['admin']->id], $users['admin']);
                    $project->members()->syncWithoutDetaching([
                        $users['architect']->id => ['project_role' => 'architect'],
                        $users['collaborator']->id => ['project_role' => 'collaborator'],
                    ]);
                    $status = $statuses[($index * 2 + $number) % count($statuses)];
                    if ($status !== $project->status) {
                        $service->changeStatus($project, $users['admin'], $status, null);
                    }
                    $firstProject ??= $project;
                }
            }
            app(ClientPortalAccess::class)->grant($firstClient, $users['admin'], $users['client'], true);
            $taxSetting = DB::table('tax_settings')->whereDate('effective_from', '<=', today())->orderByDesc('effective_from')->orderByDesc('id')->first();
            $rate = $taxSetting ? DB::table('tax_rates')->where('tax_setting_id', $taxSetting->id)->orderByDesc('is_default')->orderBy('id')->value('rate') : null;
            if ($rate === null) {
                $this->command?->warn('No applicable tax rate: demo billing drafts were skipped.');

                return;
            }
            $draft = [
                'document_date' => today()->toDateString(), 'discount_percent' => '0',
                'lines' => [['description' => 'DÉMONSTRATION — Étude architecturale', 'quantity' => '1', 'unit_price' => '1000.000', 'discount_percent' => '0', 'tax_rate' => (string) $rate]],
            ];
            app(QuoteService::class)->create($firstProject, $users['admin'], $draft);
            app(InvoiceService::class)->create($firstProject, $users['admin'], $draft + ['due_date' => today()->toDateString()]);
        }, 3);
    }
}

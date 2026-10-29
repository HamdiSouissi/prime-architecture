<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Services\ClientPortalAccess;
use App\Services\InvoiceService;
use App\Services\QuoteService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Explicitly opt-in, for a fresh local database only. */
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
        if (User::withTrashed()->exists() || Client::withTrashed()->exists() || Project::withTrashed()->exists()) {
            throw new LogicException('Demo seeding requires an empty workspace; existing accounts and data are never overwritten.');
        }
        $this->call([RolePermissionSeeder::class, TaxSettingsSeeder::class]);
        $users = [];
        foreach (['admin', 'architect', 'collaborator', 'client', 'contractor'] as $role) {
            $user = User::create(['name' => 'Démo '.ucfirst($role), 'email' => 'demo-'.$role.'@example.test', 'password' => $password]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole($role);
            $users[$role] = $user;
        }
        $this->call(PhaseThreeDemoSeeder::class);
        foreach (Project::query()->get() as $project) {
            $project->members()->syncWithoutDetaching([
                $users['architect']->id => ['project_role' => 'architect'],
                $users['collaborator']->id => ['project_role' => 'collaborator'],
            ]);
        }
        $client = Client::where('email', 'demo-client-1@example.test')->firstOrFail();
        app(ClientPortalAccess::class)->grant($client, $users['admin'], $users['client'], true);
        $project = $client->projects()->firstOrFail();
        $taxSetting = DB::table('tax_settings')->whereDate('effective_from', '<=', today())->orderByDesc('effective_from')->orderByDesc('id')->first();
        $rate = DB::table('tax_rates')->where('tax_setting_id', $taxSetting->id)->orderByDesc('is_default')->orderBy('id')->value('rate');
        if ($rate === null) {
            throw new LogicException('No tax rate configured for the demo drafts.');
        }
        $draft = [
            'document_date' => today()->toDateString(), 'discount_percent' => '0',
            'lines' => [['description' => 'DÉMONSTRATION — Étude architecturale', 'quantity' => '1', 'unit_price' => '1000.000', 'discount_percent' => '0', 'tax_rate' => (string) $rate]],
        ];
        app(QuoteService::class)->create($project, $users['admin'], $draft);
        app(InvoiceService::class)->create($project, $users['admin'], $draft + ['due_date' => today()->toDateString()]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use App\Services\BillingCalculation;
use App\Services\InvoiceService;
use App\Services\QuoteService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Synthetic issued documents must only ever be created in an isolated, empty business database. */
class RealisticDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Realistic demo data is restricted to local and testing environments.');
        }
        if (Client::withTrashed()->exists() || Project::withTrashed()->exists() || Quote::withTrashed()->exists() || Invoice::query()->exists()) {
            throw new LogicException('Realistic demo requires an isolated database without clients, projects, quotes or invoices. Never use a real workspace.');
        }
        $tax = DB::table('tax_settings')->whereDate('effective_from', '<=', today())->orderByDesc('effective_from')->orderByDesc('id')->first();
        $rate = $tax ? DB::table('tax_rates')->where('tax_setting_id', $tax->id)->orderByDesc('is_default')->orderBy('id')->value('rate') : null;
        if ($rate === null) {
            throw new LogicException('Seed the standard roles and indicative tax settings before the realistic demo.');
        }

        DB::transaction(function () use ($rate): void {
            $this->call(DemoWorkspaceSeeder::class);
            $admin = User::where('email', 'demo-admin@example.test')->firstOrFail();
            $collaborator = User::where('email', 'demo-collaborator@example.test')->firstOrFail();
            $geography = DB::table('governorates')->orderBy('id')->get();
            $projects = Project::query()->orderBy('id')->get();
            foreach ($projects as $index => $project) {
                $governorate = $geography->isEmpty() ? null : $geography[$index % $geography->count()];
                $delegation = $governorate ? DB::table('delegations')->where('governorate_id', $governorate->id)->orderBy('id')->first() : null;
                $project->client->update([
                    'address' => 'Adresse fictive '.($index + 1).' — Tunisie', 'governorate_id' => $governorate?->id,
                    'delegation_id' => $delegation?->id, 'notes' => 'Fiche de démonstration — coordonnées fictives.',
                ]);
                $project->update([
                    'description' => 'Projet fictif : conception, coordination et suivi de réalisation. Données destinées uniquement à la démonstration.',
                    'address' => 'Site fictif '.($index + 1).' — Tunisie', 'governorate_id' => $governorate?->id, 'delegation_id' => $delegation?->id,
                    'land_title_number' => 'DEMO-'.($index + 1), 'land_area_m2' => 450 + 10 * $index,
                    'built_area_m2' => 220 + 5 * $index, 'floors_count' => 2, 'estimated_budget' => 180000 + 5000 * $index,
                    'start_date' => today()->subDays(50 + $index)->toDateString(), 'expected_end_date' => today()->addDays(180 + $index)->toDateString(),
                ]);
                foreach ([
                    ['Relevé et programme', 'done', -35, -25, 'normal'],
                    ['Esquisse et variantes', 'done', -24, -10, 'normal'],
                    ['Plans et coordination', 'in_progress', -6, 7, 'high'],
                    ['Dossier administratif', 'todo', 8, 18, 'normal'],
                    ['Réunion de suivi chantier', 'todo', 20, 21, 'high'],
                ] as $order => [$title, $status, $start, $end, $priority]) {
                    $task = Task::create([
                        'project_id' => $project->id, 'title' => 'Démo — '.$title, 'description' => 'Action fictive liée au projet de démonstration.',
                        'status' => $status, 'priority' => $priority, 'start_date' => today()->addDays($start + $index % 5),
                        'due_date' => today()->addDays($end + $index % 5), 'estimated_hours' => '8.00',
                        'spent_hours' => $status === 'done' ? '8.00' : '0.00', 'completed_at' => $status === 'done' ? now()->subDays(3) : null,
                        'sort_order' => $order, 'visible_to_client' => true, 'created_by' => $admin->id,
                    ]);
                    $task->assignees()->attach($collaborator->id, ['project_id' => $project->id]);
                }
                Milestone::create(['project_id' => $project->id, 'title' => 'Démo — Présentation du dossier', 'due_date' => today()->addDays(25 + $index % 5), 'is_reached' => false, 'notes' => 'Jalon fictif.']);
                $lines = [
                    ['description' => 'DÉMONSTRATION — Étude architecturale', 'unit' => 'forfait', 'quantity' => '1', 'unit_price' => '2400.000', 'discount_percent' => '0', 'tax_rate' => (string) $rate],
                    ['description' => 'DÉMONSTRATION — Suivi et coordination', 'unit' => 'forfait', 'quantity' => '1', 'unit_price' => '900.000', 'discount_percent' => '0', 'tax_rate' => (string) $rate],
                ];
                $draft = ['document_date' => today()->toDateString(), 'discount_percent' => '0', 'lines' => $lines];
                $quote = app(QuoteService::class)->create($project, $admin, $draft + ['valid_until' => today()->addDays(30)->toDateString()]);
                $invoice = app(InvoiceService::class)->create($project, $admin, $draft + ['due_date' => today()->addDays(30)->toDateString()]);
                foreach ([['quote', $quote], ['invoice', $invoice]] as [$type, $document]) {
                    $result = app(BillingCalculation::class)->calculate($project, CarbonImmutable::today(), $lines, '0', null, $type, false);
                    $snapshot = $result['snapshot'];
                    $snapshot['profile'] = ['name' => 'CABINET FICTIF — DÉMONSTRATION', 'address' => 'Adresse fictive — Tunisie', 'phone' => '', 'email' => 'cabinet@example.test', 'tax_id' => '', 'bank_details' => '', 'legal_terms_fr' => 'DOCUMENT FICTIF — SANS VALEUR COMMERCIALE OU FISCALE', 'legal_terms_ar' => 'وثيقة تجريبية بلا قيمة تجارية أو جبائية', 'logo' => null];
                    $snapshot['demo_only'] = true;
                    $snapshot['lines'] = $lines;
                    $snapshot['totals'] = $result['totals'];
                    // Fixtures only: bypass real issuance and numbering; real fiscal settings remain unverified.
                    $document->update(['number' => 'DEMO-'.strtoupper($type).'-'.str_pad((string) $document->id, 5, '0', STR_PAD_LEFT), 'status' => 'sent', 'issued_at' => now(), 'snapshot' => $snapshot]);
                }
                if ($index % 3 !== 0) {
                    app(InvoiceService::class)->pay($invoice, $admin, [
                        'amount' => $index % 3 === 1 ? '500.000' : $invoice->total_ttc,
                        'payment_date' => today()->toDateString(), 'method' => 'bank_transfer', 'reference' => 'DEMO-PAIEMENT',
                    ]);
                }
            }
        }, 3);
    }
}

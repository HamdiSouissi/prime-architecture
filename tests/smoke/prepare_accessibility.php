<?php

use App\Models\Project;
use App\Models\User;
use App\Services\ClientPortalAccess;
use App\Services\InvoiceService;
use App\Services\PermitAdministration;
use App\Services\QuoteService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local')) {
    throw new RuntimeException('Only use this on a disposable local CI database.');
}
$actor = User::where('email', env('ADMIN_EMAIL'))->firstOrFail();
$project = Project::where('name', 'Projet UI Delegation')->firstOrFail();
$data = [
    'document_date' => today()->toDateString(),
    'discount_percent' => '0',
    'lines' => [['description' => 'Brouillon UI', 'quantity' => '1', 'unit_price' => '100.000', 'discount_percent' => '0', 'tax_rate' => '19']],
];
app(QuoteService::class)->create($project, $actor, $data);
app(InvoiceService::class)->create($project, $actor, $data + ['due_date' => today()->addDays(30)->toDateString()]);

$permit = app(PermitAdministration::class)->create($project, $actor, ['title' => 'Permis UI test', 'permit_type' => 'Test', 'status' => 'En cours']);
app(PermitAdministration::class)->addStep($permit, $actor, ['label' => 'Pièce UI test', 'due_on' => today()->addDays(7)->toDateString()]);

$portalUser = User::firstOrCreate(['email' => 'portal-ui@example.test'], ['name' => 'Client UI', 'password' => Hash::make('portal-ci-password-123')]);
$portalUser->forceFill(['email_verified_at' => now(), 'is_active' => true])->save();
$portalUser->assignRole('client');
app(ClientPortalAccess::class)->grant($project->client, $actor, $portalUser, true);

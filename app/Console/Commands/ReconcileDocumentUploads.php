<?php

namespace App\Console\Commands;

use App\Services\DocumentAdministration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileDocumentUploads extends Command
{
    protected $signature = 'documents:reconcile {--delete : Remove unreferenced blobs after ensuring uploads have stopped} {--hours=24 : Minimum intent age in hours}';

    protected $description = 'List or reconcile stale document upload intents';

    public function handle(DocumentAdministration $service): int
    {
        $hours = max(24, (int) $this->option('hours'));
        $intents = DB::table('document_upload_intents')->where('created_at', '<', now()->subHours($hours))->orderBy('created_at')->get();
        foreach ($intents as $intent) {
            $this->line($intent->id.' '.$intent->disk.' '.$intent->path);
            if ($this->option('delete')) {
                $service->reconcile($intent->id);
            }
        }
        $this->info($intents->count().' stale intents.');

        return self::SUCCESS;
    }
}

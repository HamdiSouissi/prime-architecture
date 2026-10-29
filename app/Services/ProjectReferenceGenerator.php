<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use LogicException;

class ProjectReferenceGenerator
{
    /** Must be called inside the transaction creating the project. */
    public function next(): string
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('A transaction is required to allocate a project reference.');
        }
        $format = (string) config('projects.reference_format');
        $padding = (int) config('projects.reference_padding');
        if (substr_count($format, '{year}') !== 1 || substr_count($format, '{sequence}') !== 1 || $padding < 1 || $padding > 12) {
            throw new LogicException('Invalid project reference configuration.');
        }
        $year = (int) now((string) config('projects.reference_timezone'))->format('Y');
        DB::table('project_reference_counters')->insertOrIgnore(['year' => $year, 'last_number' => 0]);
        $counter = DB::table('project_reference_counters')->where('year', $year)->lockForUpdate()->first();
        $number = $counter->last_number + 1;
        DB::table('project_reference_counters')->where('year', $year)->update(['last_number' => $number]);

        return str_replace(['{year}', '{sequence}'], [(string) $year, str_pad((string) $number, $padding, '0', STR_PAD_LEFT)], $format);
    }
}

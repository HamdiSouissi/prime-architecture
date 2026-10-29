<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

class DocumentNumberGenerator
{
    /** Must run inside the same transaction that issues and persists the document. */
    public function next(string $type, CarbonImmutable $date): string
    {
        if (DB::transactionLevel() < 1 || ! in_array($type, ['quote', 'invoice', 'credit_note'], true)) {
            throw new LogicException('Document numbering requires an issuing transaction and a valid type.');
        }
        $year = (int) $date->format('Y');
        DB::table('billing_number_counters')->insertOrIgnore(['document_type' => $type, 'year' => $year, 'last_number' => 0]);
        $counter = DB::table('billing_number_counters')->where('document_type', $type)->where('year', $year)->lockForUpdate()->firstOrFail();
        $format = DB::table('billing_number_formats')->where('document_type', $type)->whereDate('effective_from', '<=', $date->toDateString())->orderByDesc('effective_from')->first();
        if (! $format || substr_count($format->format, '{year}') !== 1 || substr_count($format->format, '{sequence}') !== 1 || preg_match('/[{}]/', str_replace(['{year}', '{sequence}'], '', $format->format)) || $format->padding < 1 || $format->padding > 12) {
            throw new LogicException('Invalid or missing document numbering configuration.');
        }
        if ($counter->last_number >= PHP_INT_MAX) {
            throw new LogicException('Document counter exhausted.');
        }
        $next = $counter->last_number + 1;
        $number = str_replace(['{year}', '{sequence}'], [(string) $year, str_pad((string) $next, (int) $format->padding, '0', STR_PAD_LEFT)], $format->format);
        if (mb_strlen($number) > 100) {
            throw new LogicException('Document number is too long.');
        }
        DB::table('billing_number_counters')->where('document_type', $type)->where('year', $year)->update(['last_number' => $next]);

        return $number;
    }
}

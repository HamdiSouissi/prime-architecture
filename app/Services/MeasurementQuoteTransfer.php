<?php

namespace App\Services;

use App\Models\MeasurementLine;
use App\Models\PriceItem;
use App\Models\Project;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MeasurementQuoteTransfer
{
    public function transfer(Project $project, User $actor, ?int $quoteId, string $taxRate): Quote
    {
        return DB::transaction(function () use ($project, $actor, $quoteId, $taxRate): Quote {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            Gate::forUser($actor)->authorize('manageProject', [PriceItem::class, $project]);
            $quote = $quoteId === null ? null : Quote::query()->where('project_id', $project->id)->lockForUpdate()->findOrFail($quoteId);
            if ($quote) {
                Gate::forUser($actor)->authorize('update', $quote);
            } else {
                Gate::forUser($actor)->authorize('create', [Quote::class, $project]);
            }
            $measurements = MeasurementLine::query()->where('project_id', $project->id)->orderBy('id')->lockForUpdate()->get();
            if ($measurements->isEmpty()) {
                throw ValidationException::withMessages(['measurement' => __('phase9.empty_measurement')]);
            }
            $items = PriceItem::query()->whereIn('id', $measurements->pluck('price_item_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $lines = $quote ? $quote->lines->map(fn ($line) => [
                'description' => $line->description, 'unit' => $line->unit, 'quantity' => $line->quantity,
                'unit_price' => $line->unit_price, 'discount_percent' => $line->discount_percent, 'tax_rate' => $line->tax_rate,
            ])->all() : [];
            foreach ($measurements as $measurement) {
                $item = $items->get($measurement->price_item_id);
                $lines[] = ['description' => $item->name, 'unit' => $item->unit, 'quantity' => $measurement->quantity,
                    'unit_price' => $item->unit_price, 'discount_percent' => '0', 'tax_rate' => $taxRate];
            }
            $data = [
                'document_date' => $quote ? $quote->document_date->toDateString() : today()->toDateString(),
                'valid_until' => $quote?->valid_until?->toDateString(),
                'discount_percent' => $quote ? $quote->discount_percent : '0',
                'withholding_rate_id' => $quote?->withholding_rate_id,
                'lines' => $lines,
            ];
            $result = $quote ? app(QuoteService::class)->update($quote, $actor, $data) : app(QuoteService::class)->create($project, $actor, $data);
            activity('prices')->causedBy($actor)->performedOn($result)->withProperties(['project_id' => $project->id])->event('measurement.transferred')->log('measurement.transferred');

            return $result;
        }, 3);
    }
}

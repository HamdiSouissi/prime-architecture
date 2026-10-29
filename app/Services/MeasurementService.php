<?php

namespace App\Services;

use App\Models\MeasurementLine;
use App\Models\PriceItem;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MeasurementService
{
    public function add(Project $project, PriceItem $item, User $actor, string $quantity): MeasurementLine
    {
        $quantity = BillingDecimal::validate($quantity, 6, '999999999.999999');

        return DB::transaction(function () use ($project, $item, $actor, $quantity): MeasurementLine {
            $line = MeasurementLine::create(['project_id' => $project->id, 'price_item_id' => $item->id, 'quantity' => $quantity]);
            activity('prices')->causedBy($actor)->performedOn($line)->event('measurement.created')->log('measurement.created');

            return $line;
        }, 3);
    }

    public function remove(MeasurementLine $line, User $actor): void
    {
        DB::transaction(function () use ($line, $actor): void {
            activity('prices')->causedBy($actor)->performedOn($line)->event('measurement.deleted')->log('measurement.deleted');
            $line->delete();
        }, 3);
    }

    public function total(MeasurementLine $line): string
    {
        return bcadd(bcmul((string) $line->quantity, (string) $line->priceItem->unit_price, 12), '0.0005', 3);
    }
}

<?php

namespace App\Services;

use App\Models\PriceItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Price history is appended in the same transaction as each catalog change. */
class PriceCatalog
{
    /** @param array{category: string, name: string, unit: string, unit_price: string} $data */
    public function create(User $actor, array $data): PriceItem
    {
        $data['unit_price'] = BillingDecimal::validate($data['unit_price'], 3, BillingDecimal::MAX_AMOUNT);

        return DB::transaction(function () use ($actor, $data): PriceItem {
            $item = PriceItem::create($data);
            $item->prices()->create(['recorded_by' => $actor->id, 'unit_price' => $item->unit_price, 'created_at' => now()]);
            activity('prices')->causedBy($actor)->performedOn($item)->event('price.created')->log('price.created');

            return $item;
        }, 3);
    }

    /** @param array{category: string, name: string, unit: string, unit_price: string} $data */
    public function update(User $actor, PriceItem $item, array $data): PriceItem
    {
        $data['unit_price'] = BillingDecimal::validate($data['unit_price'], 3, BillingDecimal::MAX_AMOUNT);

        return DB::transaction(function () use ($actor, $item, $data): PriceItem {
            $locked = PriceItem::query()->lockForUpdate()->findOrFail($item->id);
            $changed = bccomp((string) $locked->unit_price, $data['unit_price'], 3) !== 0;
            $locked->update($data);
            if ($changed) {
                $locked->prices()->create(['recorded_by' => $actor->id, 'unit_price' => $locked->unit_price, 'created_at' => now()]);
            }
            activity('prices')->causedBy($actor)->performedOn($locked)->event('price.updated')->log('price.updated');

            return $locked;
        }, 3);
    }
}

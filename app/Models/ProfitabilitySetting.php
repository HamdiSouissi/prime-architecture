<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property numeric-string $default_hourly_cost
 * @property CarbonImmutable $effective_from
 * @property string $currency
 * @property int $currency_decimals
 */
class ProfitabilitySetting extends Model
{
    protected $fillable = ['effective_from', 'currency', 'currency_decimals', 'default_hourly_cost', 'label'];

    protected function casts(): array
    {
        return ['effective_from' => 'immutable_date', 'default_hourly_cost' => 'decimal:3', 'currency_decimals' => 'integer'];
    }

    /** Latest version whose effective date is not after the given day. */
    public static function currentFor(CarbonImmutable $date): ?self
    {
        return static::query()
            ->whereDate('effective_from', '<=', $date->toDateString())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }
}

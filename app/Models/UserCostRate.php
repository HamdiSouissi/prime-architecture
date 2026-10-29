<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $user_id
 * @property numeric-string $hourly_cost_rate
 * @property CarbonImmutable $effective_from
 */
class UserCostRate extends Model
{
    protected $fillable = ['user_id', 'effective_from', 'hourly_cost_rate'];

    protected function casts(): array
    {
        return ['effective_from' => 'immutable_date', 'hourly_cost_rate' => 'decimal:3'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Latest rate for this user at the given day, or null when the global default applies. */
    public static function rateFor(User $user, CarbonImmutable $date): ?string
    {
        $rate = static::query()
            ->where('user_id', $user->id)
            ->whereDate('effective_from', '<=', $date->toDateString())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->value('hourly_cost_rate');

        return is_string($rate) ? $rate : null;
    }
}

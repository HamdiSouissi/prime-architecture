<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkingCalendar
{
    /** @var list<int> */
    private array $weekdays;

    /** @var array<string, bool> */
    private array $dates = [];

    /** @var array<string, bool> */
    private array $recurring = [];

    public function __construct()
    {
        $this->weekdays = DB::table('working_calendar_days')->where('is_working', true)->orderBy('iso_weekday')->pluck('iso_weekday')->map(fn ($day) => (int) $day)->all();
        if ($this->weekdays === []) {
            throw ValidationException::withMessages(['calendar' => __('phase5.no_workdays')]);
        }
        foreach (DB::table('holidays')->get(['date', 'is_recurring_yearly']) as $holiday) {
            if ($holiday->is_recurring_yearly) {
                $this->recurring[substr($holiday->date, 5, 5)] = true;
            } else {
                $this->dates[$holiday->date] = true;
            }
        }
    }

    public function isWorking(CarbonImmutable $date): bool
    {
        return in_array($date->isoWeekday(), $this->weekdays, true) && ! isset($this->dates[$date->toDateString()]) && ! isset($this->recurring[$date->format('m-d')]);
    }

    public function nextOrSame(CarbonImmutable $date): CarbonImmutable
    {
        for ($attempts = 0; $attempts < 370; $attempts++) {
            if ($this->isWorking($date)) {
                return $date;
            }
            $date = $date->addDay();
        }
        throw ValidationException::withMessages(['calendar' => __('phase5.no_workdays')]);
    }

    /** Zero returns the first working day on or after start. */
    public function addWorkingDays(CarbonImmutable $start, int $offset): CarbonImmutable
    {
        if ($offset < 0 || $offset > 10000) {
            throw ValidationException::withMessages(['calendar' => __('phase5.invalid_duration')]);
        }
        $date = $this->nextOrSame($start);
        for ($i = 0; $i < $offset; $i++) {
            $date = $this->nextOrSame($date->addDay());
        }

        return $date;
    }

    public function workingDaysInclusive(CarbonImmutable $start, CarbonImmutable $end): int
    {
        if ($start->gt($end) || $start->diffInDays($end) > 15000) {
            throw ValidationException::withMessages(['due_date' => __('phase5.invalid_duration')]);
        }
        $count = 0;
        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            if ($this->isWorking($day)) {
                $count++;
            }
        }

        return $count;
    }
}

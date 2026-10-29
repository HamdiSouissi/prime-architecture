<?php

namespace App\Http\Controllers;

use App\Http\Requests\HolidayRequest;
use App\Http\Requests\WorkingDaysRequest;
use App\Models\Holiday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WorkingCalendarController extends Controller
{
    public function index(): View
    {
        abort_unless(request()->user()->can('manage', Holiday::class), 403);
        $workingDays = DB::table('working_calendar_days')->where('is_working', true)->pluck('iso_weekday')->all();
        $holidays = Holiday::query()->orderBy('date')->paginate(50);

        return view('planning.calendar-admin', compact('workingDays', 'holidays'));
    }

    public function updateDays(WorkingDaysRequest $request): RedirectResponse
    {
        $days = array_map('intval', $request->validated('working_days'));
        DB::transaction(function () use ($days) {
            DB::table('planning_calendar_state')->where('id', 1)->lockForUpdate()->firstOrFail();
            foreach (range(1, 7) as $day) {
                DB::table('working_calendar_days')->updateOrInsert(['iso_weekday' => $day], ['is_working' => in_array($day, $days, true), 'updated_at' => now()]);
            }
            DB::table('planning_calendar_state')->where('id', 1)->increment('revision');
            activity('planning')->event('calendar.working_days_changed')->withProperties(['days' => $days])->log('calendar.working_days_changed');
        }, 3);

        return redirect()->route('planning.calendar-admin')->with('status', __('common.saved'));
    }

    public function store(HolidayRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            DB::table('planning_calendar_state')->where('id', 1)->lockForUpdate()->firstOrFail();
            $data = $request->validated();
            $data['is_recurring_yearly'] = (bool) ($data['is_recurring_yearly'] ?? false);
            $holiday = Holiday::create($data);
            DB::table('planning_calendar_state')->where('id', 1)->increment('revision');
            activity('planning')->performedOn($holiday)->event('calendar.holiday_created')->log('calendar.holiday_created');
        }, 3);

        return redirect()->route('planning.calendar-admin')->with('status', __('common.saved'));
    }

    public function update(HolidayRequest $request, Holiday $holiday): RedirectResponse
    {
        DB::transaction(function () use ($request, $holiday) {
            DB::table('planning_calendar_state')->where('id', 1)->lockForUpdate()->firstOrFail();
            $data = $request->validated();
            $data['is_recurring_yearly'] = (bool) ($data['is_recurring_yearly'] ?? false);
            $holiday->update($data);
            DB::table('planning_calendar_state')->where('id', 1)->increment('revision');
            activity('planning')->performedOn($holiday)->event('calendar.holiday_updated')->log('calendar.holiday_updated');
        }, 3);

        return redirect()->route('planning.calendar-admin')->with('status', __('common.saved'));
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        abort_unless(request()->user()->can('manage', Holiday::class), 403);
        DB::transaction(function () use ($holiday) {
            DB::table('planning_calendar_state')->where('id', 1)->lockForUpdate()->firstOrFail();
            activity('planning')->performedOn($holiday)->event('calendar.holiday_deleted')->log('calendar.holiday_deleted');
            $holiday->delete();
            DB::table('planning_calendar_state')->where('id', 1)->increment('revision');
        }, 3);

        return redirect()->route('planning.calendar-admin')->with('status', __('common.saved'));
    }
}

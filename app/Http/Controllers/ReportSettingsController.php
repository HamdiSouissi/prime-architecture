<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfitabilitySettingRequest;
use App\Http\Requests\UserCostRateRequest;
use App\Models\ProfitabilitySetting;
use App\Models\User;
use App\Models\UserCostRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportSettingsController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('time.rates.manage'), 403);

        return view('reports.settings', [
            'settings' => ProfitabilitySetting::query()->orderByDesc('effective_from')->orderByDesc('id')->limit(20)->get(),
            'rates' => UserCostRate::query()->with('user:id,name')->orderByDesc('effective_from')->orderByDesc('id')->limit(60)->get(),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(ProfitabilitySettingRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $setting = ProfitabilitySetting::create([
            'effective_from' => $data['effective_from'],
            'currency' => 'TND',
            'currency_decimals' => 3,
            'default_hourly_cost' => $data['default_hourly_cost'],
            'label' => $data['label'] ?? null,
        ]);
        activity('reports')->causedBy($request->user())->performedOn($setting)->withProperties(['effective_from' => $data['effective_from']])->event('reports.cost_version_created')->log('reports.cost_version_created');

        return back()->with('status', __('phase7.settings_saved'));
    }

    public function storeRate(UserCostRateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $rate = UserCostRate::updateOrCreate(
            ['user_id' => (int) $data['user_id'], 'effective_from' => $data['effective_from']],
            ['hourly_cost_rate' => $data['hourly_cost_rate']],
        );
        activity('reports')->causedBy($request->user())->performedOn($rate)->withProperties(['user_id' => $rate->user_id, 'effective_from' => $data['effective_from']])->event('reports.user_cost_rate_saved')->log('reports.user_cost_rate_saved');

        return back()->with('status', __('phase7.settings_saved'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\BillingSettingsRequest;
use App\Services\BillingDecimal;
use App\Services\DocumentFileInspector;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class BillingSettingsController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasRole('admin') && $request->user()->can('billing.configure'), 403);
        $settings = DB::table('tax_settings')->orderByDesc('effective_from')->orderByDesc('id')->limit(20)->get();
        $profiles = DB::table('billing_profiles')->orderByDesc('effective_from')->orderByDesc('id')->limit(20)->get();
        $formats = DB::table('billing_number_formats')->orderByDesc('effective_from')->orderByDesc('id')->limit(60)->get();

        return view('billing.settings', compact('settings', 'profiles', 'formats'));
    }

    public function tax(BillingSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        try {
            BillingDecimal::validate($data['stamp_amount'], 3, BillingDecimal::MAX_AMOUNT);
            $seen = [];
            foreach ($data['rates'] as $rate) {
                $key = bcadd(BillingDecimal::validate($rate['rate'], 4, '100'), '0', 4);
                if (isset($seen[$key])) {
                    throw new InvalidArgumentException('Duplicate tax rate');
                }
                $seen[$key] = true;
            }
            foreach ($data['withholdings'] ?? [] as $withholding) {
                BillingDecimal::validate($withholding['rate'], 4, '100');
            }
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['rates' => __('phase6.invalid_amount')]);
        }
        DB::transaction(function () use ($data, $request) {
            $this->lockSettings();
            $id = DB::table('tax_settings')->insertGetId(['effective_from' => $data['effective_from'], 'currency' => 'TND', 'currency_decimals' => 3, 'stamp_amount' => $data['stamp_amount'], 'stamp_on_quote' => (bool) ($data['stamp_on_quote'] ?? false), 'stamp_on_invoice' => (bool) ($data['stamp_on_invoice'] ?? false), 'is_verified' => true, 'created_at' => now(), 'updated_at' => now()]);
            foreach (array_values($data['rates']) as $index => $rate) {
                DB::table('tax_rates')->insert(['tax_setting_id' => $id, 'label_fr' => $rate['label_fr'], 'label_ar' => $rate['label_ar'], 'rate' => $rate['rate'], 'is_default' => $index === 0, 'created_at' => now(), 'updated_at' => now()]);
            }
            foreach ($data['withholdings'] ?? [] as $withholding) {
                DB::table('withholding_rates')->insert(['tax_setting_id' => $id, 'label_fr' => $withholding['label_fr'], 'label_ar' => $withholding['label_ar'], 'rate' => $withholding['rate'], 'base' => $withholding['base'], 'includes_stamp' => (bool) ($withholding['includes_stamp'] ?? false), 'created_at' => now(), 'updated_at' => now()]);
            }
            activity('billing')->causedBy($request->user())->withProperties(['tax_setting_id' => $id])->event('billing.tax_version_created')->log('billing.tax_version_created');
        }, 3);

        return back()->with('status', __('phase6.saved'));
    }

    public function profile(BillingSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset($data['logo']);
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $info = app(DocumentFileInspector::class)->inspect($file);
            if (! in_array($info['extension'], ['jpg', 'jpeg', 'png'], true)) {
                throw ValidationException::withMessages(['logo' => __('phase4.invalid_file')]);
            }
            $path = Storage::disk('documents')->putFileAs('billing-logos', $file, Str::uuid().'.'.$info['extension']);
            if (! is_string($path)) {
                throw ValidationException::withMessages(['logo' => __('phase4.invalid_file')]);
            }
            $data['logo_path'] = $path;
        }
        DB::transaction(function () use ($data, $request) {
            $this->lockSettings();
            $id = DB::table('billing_profiles')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);
            activity('billing')->causedBy($request->user())->withProperties(['profile_id' => $id])->event('billing.profile_created')->log('billing.profile_created');
        }, 3);

        return back()->with('status', __('phase6.saved'));
    }

    public function format(BillingSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $format = $data['format'];
        if (substr_count($format, '{year}') !== 1 || substr_count($format, '{sequence}') !== 1 || preg_match('/[{}]/', str_replace(['{year}', '{sequence}'], '', $format))) {
            throw ValidationException::withMessages(['format' => __('phase6.invalid_format')]);
        }
        DB::transaction(function () use ($data, $request) {
            // Serialize format creation and counter allocation for this type and year.
            $year = (int) substr($data['effective_from'], 0, 4);
            DB::table('billing_number_counters')->insertOrIgnore(['document_type' => $data['document_type'], 'year' => $year, 'last_number' => 0]);
            DB::table('billing_number_counters')->where('document_type', $data['document_type'])->where('year', $year)->lockForUpdate()->firstOrFail();
            if (DB::table('billing_number_formats')->where('document_type', $data['document_type'])->where('effective_from', $data['effective_from'])->exists()) {
                throw ValidationException::withMessages(['format' => __('phase6.format_exists')]);
            }
            DB::table('billing_number_formats')->insert($data + ['created_at' => now(), 'updated_at' => now()]);
            activity('billing')->causedBy($request->user())->withProperties(['type' => $data['document_type'], 'effective_from' => $data['effective_from']])->event('billing.number_format_created')->log('billing.number_format_created');
        }, 3);

        return back()->with('status', __('phase6.saved'));
    }

    private function lockSettings(): void
    {
        DB::table('billing_number_counters')->insertOrIgnore(['document_type' => 'tax_config', 'year' => 0, 'last_number' => 0]);
        DB::table('billing_number_counters')->where('document_type', 'tax_config')->where('year', 0)->lockForUpdate()->firstOrFail();
    }
}

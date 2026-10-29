<?php

namespace App\Services;

use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/** Builds a calculation from the effective fiscal version, never from browser totals. */
class BillingCalculation
{
    /** @param list<array<string, mixed>> $lines
     * @return array<string, mixed>
     */
    public function calculate(Project $project, CarbonImmutable $date, array $lines, string $discount, ?int $withholdingId, string $type, bool $requireVerified): array
    {
        $settings = DB::table('tax_settings')->whereDate('effective_from', '<=', $date->toDateString())->orderByDesc('effective_from')->orderByDesc('id')->first();
        if (! $settings || ($requireVerified && ! $settings->is_verified) || $settings->currency !== 'TND' || (int) $settings->currency_decimals !== 3) {
            throw ValidationException::withMessages(['tax_settings' => __('phase6.fiscal_not_ready')]);
        }
        $profile = DB::table('billing_profiles')->whereDate('effective_from', '<=', $date->toDateString())->orderByDesc('effective_from')->orderByDesc('id')->first();
        if ($requireVerified && ! $profile) {
            throw ValidationException::withMessages(['tax_settings' => __('phase6.profile_missing')]);
        }
        $rates = DB::table('tax_rates')->where('tax_setting_id', $settings->id)->get();
        foreach ($lines as $line) {
            if (! is_string($line['tax_rate'] ?? null) || ! $rates->contains(fn ($rate) => bccomp((string) $rate->rate, $line['tax_rate'], 4) === 0)) {
                throw ValidationException::withMessages(['lines' => __('phase6.invalid_tax_rate')]);
            }
        }
        $withholding = $withholdingId === null ? null : DB::table('withholding_rates')->where('tax_setting_id', $settings->id)->where('id', $withholdingId)->first();
        if ($withholdingId !== null && ! $withholding) {
            throw ValidationException::withMessages(['withholding_rate_id' => __('phase6.invalid_withholding')]);
        }
        $stamp = ($type === 'quote' ? $settings->stamp_on_quote : $settings->stamp_on_invoice) ? (string) $settings->stamp_amount : '0.000';
        try {
            $calculation = app(TaxCalculator::class)->calculate($lines, $discount, $stamp, (string) ($withholding->rate ?? '0'), (string) ($withholding->base ?? 'ht'), (bool) ($withholding->includes_stamp ?? false));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['lines' => __('phase6.invalid_amount')]);
        }
        $logo = null;
        if ($requireVerified && $profile->logo_path) {
            if (! preg_match('/^billing-logos\/[0-9a-f-]{36}\.(png|jpg|jpeg)$/D', $profile->logo_path, $match)) {
                throw ValidationException::withMessages(['tax_settings' => __('phase6.logo_unavailable')]);
            }
            try {
                $contents = Storage::disk('documents')->get($profile->logo_path);
                if (! is_string($contents) || strlen($contents) < 1 || strlen($contents) > 262144) {
                    throw new InvalidArgumentException('Invalid stored logo');
                }
                $mime = $match[1] === 'png' ? 'image/png' : 'image/jpeg';
                $logo = 'data:'.$mime.';base64,'.base64_encode($contents);
            } catch (Throwable) {
                throw ValidationException::withMessages(['tax_settings' => __('phase6.logo_unavailable')]);
            }
        }
        $client = $project->client;
        if (! $client || $client->trashed()) {
            throw ValidationException::withMessages(['project' => __('phase6.client_unavailable')]);
        }

        return [
            'totals' => $calculation,
            'snapshot' => [
                'currency' => 'TND',
                'tax_setting_id' => $settings->id,
                'tax_effective_from' => $settings->effective_from,
                'stamp' => $stamp,
                'withholding' => $withholding ? ['label_fr' => $withholding->label_fr, 'label_ar' => $withholding->label_ar, 'rate' => (string) $withholding->rate, 'base' => $withholding->base, 'includes_stamp' => (bool) $withholding->includes_stamp] : null,
                'tax_groups' => $calculation['taxes'],
                'project' => ['reference' => $project->reference, 'name' => $project->name],
                'client' => ['name' => $client->name, 'legal_name' => $client->legal_name, 'tax_id' => $client->tax_id, 'address' => $client->address],
                'profile' => $profile ? ['name' => $profile->name, 'tax_id' => $profile->tax_id, 'address' => $profile->address, 'phone' => $profile->phone, 'email' => $profile->email, 'bank_details' => $profile->bank_details, 'legal_terms_fr' => $profile->legal_terms_fr, 'legal_terms_ar' => $profile->legal_terms_ar, 'logo' => $logo] : null,
            ],
        ];
    }
}

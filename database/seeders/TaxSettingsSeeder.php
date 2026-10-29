<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Illustrative values only; an administrator must validate them with a professional. */
class TaxSettingsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $inserted = DB::table('tax_settings')->insertOrIgnore([
                'seed_key' => 'indicative-initial', 'effective_from' => now()->toDateString(),
                'currency' => 'TND', 'currency_decimals' => 3, 'stamp_amount' => '1.000',
                'is_verified' => false, 'stamp_on_quote' => false, 'stamp_on_invoice' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($inserted) {
                $id = DB::table('tax_settings')->where('seed_key', 'indicative-initial')->value('id');
                foreach ([['19.0000', true], ['13.0000', false], ['7.0000', false]] as [$rate, $default]) {
                    DB::table('tax_rates')->insert(['tax_setting_id' => $id, 'label_fr' => 'TVA '.$rate.' %', 'label_ar' => 'الأداء على القيمة المضافة '.$rate.' ٪', 'rate' => $rate, 'is_default' => $default, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            foreach (['quote' => 'DEV-{year}-{sequence}', 'invoice' => 'FAC-{year}-{sequence}', 'credit_note' => 'AVR-{year}-{sequence}'] as $type => $format) {
                if (! DB::table('billing_number_formats')->where('document_type', $type)->exists()) {
                    DB::table('billing_number_formats')->insertOrIgnore(['document_type' => $type, 'effective_from' => now()->toDateString(), 'format' => $format, 'padding' => 4, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }, 3);
    }
}

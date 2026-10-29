<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quote;
use Illuminate\Support\Facades\Validator;

/** Validation is deliberately repeated in the service, not entrusted to the browser. */
class BillingDraft
{
    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    public function validate(array $data, string $type): array
    {
        $rules = [
            'document_date' => ['required', 'date_format:Y-m-d'],
            'discount_percent' => ['required', 'string', 'regex:/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,4})?$/D', 'max:8'],
            'withholding_rate_id' => ['nullable', 'integer', 'min:1'],
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.unit' => ['nullable', 'string', 'max:50'],
            'lines.*.quantity' => ['required', 'string', 'regex:/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,6})?$/D', 'max:16'],
            'lines.*.unit_price' => ['required', 'string', 'regex:/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,3})?$/D', 'max:16'],
            'lines.*.discount_percent' => ['required', 'string', 'regex:/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,4})?$/D', 'max:8'],
            'lines.*.tax_rate' => ['required', 'string', 'regex:/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,4})?$/D', 'max:8'],
        ];
        $rules[$type === 'quote' ? 'valid_until' : 'due_date'] = [$type === 'quote' ? 'nullable' : 'required', 'date_format:Y-m-d', 'after_or_equal:document_date'];

        return Validator::make($data, $rules)->validate();
    }

    /** @param array<string, mixed> $data */
    public function apply(Quote|Invoice $document, Project $project, array $data, string $type): void
    {
        $data = $this->validate($data, $type);
        $lines = array_values($data['lines']);
        $withholdingId = isset($data['withholding_rate_id']) ? (int) $data['withholding_rate_id'] : null;
        $result = app(BillingCalculation::class)->calculate($project, \Carbon\CarbonImmutable::parse($data['document_date']), $lines, $data['discount_percent'], $withholdingId, $type, false);
        $totals = $result['totals'];
        $document->fill([
            'document_date' => $data['document_date'],
            $type === 'quote' ? 'valid_until' : 'due_date' => $data[$type === 'quote' ? 'valid_until' : 'due_date'] ?? null,
            'discount_percent' => $data['discount_percent'],
            'withholding_rate_id' => $withholdingId,
            'total_ht' => $totals['total_ht'], 'total_discount' => $totals['discount'], 'total_tax' => $totals['total_tax'],
            'stamp_amount' => $totals['stamp'], 'withholding_amount' => $totals['withholding'], 'total_ttc' => $totals['total_ttc'], 'net_due' => $totals['net_due'],
        ]);
        $document->save();
        $document->lines()->delete();
        foreach ($lines as $index => $line) {
            $document->lines()->create([
                'description' => $line['description'], 'unit' => $line['unit'] ?? null,
                'quantity' => $line['quantity'], 'unit_price' => $line['unit_price'], 'discount_percent' => $line['discount_percent'],
                'tax_rate' => $line['tax_rate'], 'total_ht' => $totals['lines'][$index]['total_ht'], 'sort_order' => $index,
            ]);
        }
    }
}

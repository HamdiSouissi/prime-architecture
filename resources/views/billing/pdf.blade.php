<!DOCTYPE html>
<html lang="{{ $language }}" dir="{{ $language === 'ar' ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta http-equiv="Content-Security-Policy" content="default-src 'none'; style-src 'unsafe-inline'; font-src data:; img-src data:; base-uri 'none'; form-action 'none'">
<style>
@include('billing.pdf-fonts')
* { box-sizing: border-box; } body { color: #172033; font-family: {{ $language === 'ar' ? 'Cairo, Inter' : 'Inter, Cairo' }}, sans-serif; font-size: 10pt; line-height: 1.6; }
header { border-bottom: 3px solid #4338ca; padding-bottom: 14px; margin-bottom: 23px; display: flex; justify-content: space-between; gap: 20px; }
h1 { font-size: 22pt; line-height: 1.25; margin: 0; color: #3730a3; } h2 { font-size: 11pt; margin: 0 0 8px; }
p { margin: 2px 0; white-space: pre-wrap; overflow-wrap: anywhere; } .small { font-size: 9pt; color: #475569; }
.meta { text-align: end; } .parties { display: flex; gap: 20px; margin-bottom: 24px; } .party { width: 50%; background: #f6f8fc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 8px; }
table { width: 100%; border-collapse: collapse; } thead { display: table-header-group; } tr { break-inside: avoid; } th { text-align: start; background: #eef2ff; color: #3730a3; } th, td { padding: 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; overflow-wrap: anywhere; }
.number { direction: ltr; unicode-bidi: isolate; text-align: end; white-space: nowrap; } .totals { margin-top: 18px; margin-inline-start: auto; width: 48%; } .totals td { padding: 5px; } .grand { font-weight: bold; color: #3730a3; }
footer { margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 12px; } .note { font-size: 8pt; color: #475569; }
</style>
</head>
<body>
<header><div>@if(!empty($snapshot['profile']['logo']))<img src="{{ $snapshot['profile']['logo'] }}" alt="" style="max-width:150px;max-height:65px;object-fit:contain">@endif<h2>{{ $snapshot['profile']['name'] ?? '' }}</h2><p class="small">{{ $snapshot['profile']['address'] ?? '' }}</p><p class="small">{{ $snapshot['profile']['tax_id'] ?? '' }}</p><p class="small">{{ $snapshot['profile']['phone'] ?? '' }} {{ $snapshot['profile']['email'] ?? '' }}</p></div><div class="meta"><h1>{{ __($credit ? 'phase6.credit_note' : ($document instanceof \App\Models\Quote ? 'phase6.quote' : 'phase6.invoice')) }}</h1><p dir="ltr">{{ $credit ? $credit->number : $document->number }}</p><p>{{ __('phase6.document_date') }} : <span dir="ltr">{{ $credit ? $credit->document_date : $document->document_date->format('Y-m-d') }}</span></p></div></header>
<div class="parties"><div class="party"><h2>{{ __('phase6.client') }}</h2><p>{{ $snapshot['client']['legal_name'] ?: $snapshot['client']['name'] }}</p><p>{{ $snapshot['client']['address'] ?? '' }}</p><p dir="auto">{{ $snapshot['client']['tax_id'] ?? '' }}</p></div><div class="party"><h2>{{ __('phase6.project') }}</h2><p>{{ $snapshot['project']['name'] }}</p><p dir="auto">{{ $snapshot['project']['reference'] }}</p>@if($credit)<p>{{ __('phase6.invoice') }} : <span dir="ltr">{{ $document->number }}</span></p>@else<p>{{ __($document instanceof \App\Models\Quote ? 'phase6.valid_until' : 'phase6.due_date') }} : <span dir="ltr">{{ ($document instanceof \App\Models\Quote ? $document->valid_until : $document->due_date)?->format('Y-m-d') ?? '—' }}</span></p>@endif</div></div>
<table><thead><tr><th>{{ __('phase6.description') }}</th><th>{{ __('phase6.quantity') }}</th><th>{{ __('phase6.unit_price') }}</th><th>{{ __('phase6.line_discount') }}</th><th>{{ __('phase6.tax_rate') }}</th><th>{{ __('phase6.total_ht') }}</th></tr></thead><tbody>
@foreach($snapshot['lines'] as $index => $line)<tr><td>{{ $line['description'] }} @if($line['unit']) ({{ $line['unit'] }}) @endif</td><td class="number">{{ $line['quantity'] }}</td><td class="number">{{ $line['unit_price'] }}</td><td class="number">{{ $line['discount_percent'] }} %</td><td class="number">{{ $line['tax_rate'] }} %</td><td class="number">{{ $snapshot['totals']['lines'][$index]['total_ht'] }}</td></tr>@endforeach
</tbody></table>
<table class="totals"><tbody><tr><td>{{ __('phase6.total_discount') }}</td><td class="number">{{ $snapshot['totals']['discount'] }} TND</td></tr>@foreach($snapshot['tax_groups'] as $group)<tr><td>{{ __('phase6.tax_rate') }} <span dir="ltr">{{ $group['rate'] }} %</span></td><td class="number">{{ $group['amount'] }} TND</td></tr>@endforeach @foreach(['total_ht'=>'total_ht','total_tax'=>'total_tax','stamp'=>'stamp','total_ttc'=>'total_ttc','withholding'=>'withholding','net_due'=>'net_due'] as $label => $key)<tr class="{{ $key === 'total_ttc' ? 'grand' : '' }}"><td>{{ __('phase6.'.$label) }}</td><td class="number">{{ $snapshot['totals'][$key] }} TND</td></tr>@endforeach</tbody></table>
<footer>@if($credit)<p class="small">{{ __('phase6.cancellation_reason') }} : {{ $credit->reason }}</p>@endif<p class="note">{{ __('phase6.net_note') }}</p><p class="small">{{ $snapshot['profile']['bank_details'] ?? '' }}</p><p class="small">{{ $snapshot['profile'][$language === 'ar' ? 'legal_terms_ar' : 'legal_terms_fr'] ?? '' }}</p></footer>
</body></html>

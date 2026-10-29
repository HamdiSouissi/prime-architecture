<!DOCTYPE html>
<html lang="{{ $language }}" dir="{{ $language === 'ar' ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<title>{{ __('phase7.reports') }} — {{ $from->format('Y-m-d') }} / {{ $to->format('Y-m-d') }}</title>
<meta http-equiv="Content-Security-Policy" content="default-src 'none'; style-src 'unsafe-inline'; font-src data:; img-src data:; base-uri 'none'; form-action 'none'">
<style>
@include('billing.pdf-fonts')
* { box-sizing: border-box; } body { color: #172033; font-family: {{ $language === 'ar' ? 'Cairo, Inter' : 'Inter, Cairo' }}, sans-serif; font-size: 9.5pt; line-height: 1.55; margin: 0; }
header { border-bottom: 3px solid #4338ca; padding-bottom: 12px; margin-bottom: 20px; display: flex; justify-content: space-between; gap: 16px; }
h1 { font-size: 19pt; margin: 0; color: #3730a3; } h2 { font-size: 11pt; margin: 0 0 6px; }
p { margin: 2px 0; } .small { font-size: 8.5pt; color: #475569; }
table { width: 100%; border-collapse: collapse; } thead { display: table-header-group; } tr { break-inside: avoid; }
th { text-align: start; background: #eef2ff; color: #3730a3; font-size: 8.5pt; }
th, td { padding: 6px; border-bottom: 1px solid #e2e8f0; vertical-align: top; overflow-wrap: anywhere; }
.number { direction: ltr; unicode-bidi: isolate; text-align: end; white-space: nowrap; }
tfoot td, tfoot th { font-weight: bold; background: #f6f8fc; color: #3730a3; }
footer { margin-top: 18px; border-top: 1px solid #e2e8f0; padding-top: 10px; }
</style>
</head>
<body>
<header>
    <div><h1>{{ __('phase7.reports') }}</h1><p class="small">{{ __('phase7.report_sheet') }}</p></div>
    <div class="small"><p>{{ __('phase7.from') }} <span dir="ltr">{{ $from->format('Y-m-d') }}</span></p><p>{{ __('phase7.to') }} <span dir="ltr">{{ $to->format('Y-m-d') }}</span></p></div>
</header>
@if(! $financial)<p class="small">{{ __('phase7.no_financial_access') }}</p>@endif
<table>
<thead><tr>
    <th>{{ __('phase7.project') }}</th><th>{{ __('phase7.reference') }}</th><th>{{ __('phase7.hours') }}</th>
    @if($financial)<th>{{ __('phase7.cost') }}</th><th>{{ __('phase7.billed') }}</th><th>{{ __('phase7.collected') }}</th><th>{{ __('phase7.margin') }}</th><th>{{ __('phase7.effective_rate') }}</th>@endif
</tr></thead>
<tbody>
@foreach($rows as $row)
    <tr>
        <td>{{ $row['name'] }}</td><td>{{ $row['reference'] }}</td><td class="number">{{ $row['hours'] }}</td>
        @if($financial)<td class="number">{{ $row['cost'] }}</td><td class="number">{{ $row['billed'] }}</td><td class="number">{{ $row['collected'] }}</td><td class="number">{{ $row['margin'] }}</td><td class="number">{{ $row['effective_rate'] }}</td>@endif
    </tr>
@endforeach
</tbody>
<tfoot><tr>
    <th colspan="2">{{ __('phase7.totals') }}</th><td class="number">{{ $totals['hours'] }}</td>
    @if($financial)<td class="number">{{ $totals['cost'] }}</td><td class="number">{{ $totals['billed'] }}</td><td class="number">{{ $totals['collected'] }}</td><td class="number">{{ $totals['margin'] }}</td><td class="number">{{ $totals['effective_rate'] }}</td>@endif
</tr></tfoot>
</table>
<footer><p class="small">{{ __('phase7.cost_note') }}</p></footer>
</body></html>

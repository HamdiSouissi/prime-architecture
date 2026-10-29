<!DOCTYPE html>
<html lang="{{ $language }}" dir="{{ $language === 'ar' ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<title>{{ $report->number }} — {{ $project->name }}</title>
<meta http-equiv="Content-Security-Policy" content="default-src 'none'; style-src 'unsafe-inline'; font-src data:; img-src data:; base-uri 'none'; form-action 'none'">
<style>
@include('billing.pdf-fonts')
* { box-sizing: border-box; } body { color: #172033; font-family: {{ $language === 'ar' ? 'Cairo, Inter' : 'Inter, Cairo' }}, sans-serif; font-size: 9.5pt; line-height: 1.55; margin: 0; }
header { border-bottom: 3px solid #4338ca; padding-bottom: 12px; margin-bottom: 20px; display: flex; justify-content: space-between; gap: 16px; }
h1 { font-size: 18pt; margin: 0; color: #3730a3; } h2 { font-size: 11pt; margin: 18px 0 6px; color: #3730a3; }
p { margin: 2px 0; } .small { font-size: 8.5pt; color: #475569; }
.pre { white-space: pre-line; }
table { width: 100%; border-collapse: collapse; } thead { display: table-header-group; } tr { break-inside: avoid; }
th { text-align: start; background: #eef2ff; color: #3730a3; font-size: 8.5pt; }
th, td { padding: 6px; border-bottom: 1px solid #e2e8f0; vertical-align: top; overflow-wrap: anywhere; }
.date { direction: ltr; unicode-bidi: isolate; white-space: nowrap; }
footer { margin-top: 18px; border-top: 1px solid #e2e8f0; padding-top: 10px; }
</style>
</head>
<body>
<header>
    <div>
        <h1>{{ __('phase8.site_report_pdf') }}</h1>
        <p class="small">{{ $project->reference }} — {{ $project->name }}</p>
        @if($project->client)<p class="small">{{ $project->client->name }}</p>@endif
    </div>
    <div class="small">
        <p>{{ __('phase8.number') }} <strong class="date">{{ $report->number }}</strong></p>
        <p>{{ __('phase8.meeting_date') }} <strong class="date">{{ $report->meeting_date->format('Y-m-d') }}</strong></p>
        @if($report->next_meeting_on)<p>{{ __('phase8.next_meeting') }} <strong class="date">{{ $report->next_meeting_on->format('Y-m-d') }}</strong></p>@endif
    </div>
</header>

<h2>{{ __('phase8.agenda') }}</h2>
<p class="pre">{{ $report->agenda ?: __('phase8.empty_section') }}</p>

<h2>{{ __('phase8.decisions') }}</h2>
<p class="pre">{{ $report->decisions ?: __('phase8.empty_section') }}</p>

<h2>{{ __('phase8.snags') }}</h2>
@if($snags->isEmpty())
    <p class="small">{{ __('phase8.no_snags_hint') }}</p>
@else
    <table>
        <thead><tr>
            <th>{{ __('phase8.reference') }}</th><th>{{ __('phase8.snag_title') }}</th><th>{{ __('phase8.severity') }}</th>
            <th>{{ __('phase8.status') }}</th><th>{{ __('phase8.due_on') }}</th><th>{{ __('phase8.responsible') }}</th>
        </tr></thead>
        <tbody>
        @foreach($snags as $snag)
            <tr>
                <td class="date">{{ $snag->reference }}</td>
                <td>{{ $snag->title }}@if($snag->location)<br><span class="small">{{ $snag->location }}</span>@endif</td>
                <td>{{ __('phase8.severity_'.$snag->severity->value) }}</td>
                <td>{{ __('phase8.snag_status.'.$snag->status->value) }}</td>
                <td class="date">{{ $snag->due_on?->format('Y-m-d') ?? '—' }}</td>
                <td>{{ $snag->responsible ?? '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

<footer>
    <p class="small">{{ __('phase8.pdf_notice') }}</p>
    <p class="small">{{ __('phase8.number') }} {{ $report->number }} — {{ __('phase8.generated_on') }} <span class="date">{{ now()->format('Y-m-d H:i') }}</span></p>
</footer>
</body>
</html>

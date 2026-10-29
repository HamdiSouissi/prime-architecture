@extends('layouts.app')
@section('title', __('phase7.reports'))
@section('content')
<x-page-header :title="__('phase7.reports')" :description="__('phase7.reports_intro')">
    @can('reports.export')
        <a class="action-link" href="{{ route('reports.export.xlsx', request()->query()) }}">{{ __('phase7.export_xlsx') }}</a>
        <a class="action-link" href="{{ route('reports.export.pdf', request()->query()) }}" target="_blank" rel="noopener">{{ __('phase7.export_pdf') }}</a>
    @endcan
    @can('time.rates.manage')<a class="action-link" href="{{ route('reports.settings.index') }}"><x-icon name="cog-6-tooth" />{{ __('phase7.reports_settings') }}</a>@endcan
</x-page-header>

<form method="GET" action="{{ route('reports.index') }}" class="mb-6 grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-soft sm:grid-cols-2 lg:grid-cols-5">
    <x-form-field type="date" name="from" :label="__('phase7.from')" :value="$from->toDateString()" />
    <x-form-field type="date" name="to" :label="__('phase7.to')" :value="$to->toDateString()" />
    <div class="min-w-0">
        <label for="report-client" class="billing-label">{{ __('phase7.client') }}</label>
        <select id="report-client" name="client_id">
            <option value="">{{ __('phase7.all_clients') }}</option>
            @foreach($clients as $client)
                <option value="{{ $client->id }}" @selected((string) request('client_id') === (string) $client->id)>{{ $client->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="min-w-0">
        <label for="report-project" class="billing-label">{{ __('phase7.project') }}</label>
        <select id="report-project" name="project_id">
            <option value="">{{ __('phase7.all_projects') }}</option>
            @foreach($projects as $project)
                <option value="{{ $project->id }}" @selected((string) request('project_id') === (string) $project->id)>{{ $project->reference }} — {{ $project->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="flex items-end"><x-button type="submit">{{ __('phase7.filter') }}</x-button></div>
</form>

@unless($financial)
    <x-alert variant="info" class="mb-6">{{ __('phase7.no_financial_access') }}</x-alert>
@endunless

<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-soft sm:p-7">
    <div class="flex flex-wrap items-baseline justify-between gap-3">
        <h2 class="section-title">{{ __('phase7.report_sheet') }}</h2>
        <p class="text-sm text-slate-600"><span dir="ltr">{{ $from->format('d/m/Y') }}</span> — <span dir="ltr">{{ $to->format('d/m/Y') }}</span></p>
    </div>
    @if($rows === [])
        <div class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
            <p class="font-semibold">{{ __('phase7.no_rows') }}</p>
            <p class="mt-1 text-sm text-slate-600">{{ __('phase7.no_rows_hint') }}</p>
        </div>
    @else
        <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[820px] text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                    <tr>
                        <th scope="col">{{ __('phase7.project') }}</th>
                        <th scope="col">{{ __('phase7.reference') }}</th>
                        <th scope="col" class="text-end">{{ __('phase7.hours') }}</th>
                        @if($financial)
                            <th scope="col" class="text-end">{{ __('phase7.cost') }}</th>
                            <th scope="col" class="text-end">{{ __('phase7.billed') }}</th>
                            <th scope="col" class="text-end">{{ __('phase7.collected') }}</th>
                            <th scope="col" class="text-end">{{ __('phase7.margin') }}</th>
                            <th scope="col" class="text-end">{{ __('phase7.effective_rate') }}</th>
                            <th scope="col" class="text-end">{{ __('phase7.margin_rate') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                @foreach($rows as $row)
                    <tr class="border-t border-slate-100">
                        <td><a class="font-semibold text-brand-700 hover:underline" href="{{ route('projects.show', $row['id']) }}">{{ $row['name'] }}</a></td>
                        <td dir="auto">{{ $row['reference'] }}</td>
                        <td class="text-end" dir="ltr">{{ $row['hours'] }}</td>
                        @if($financial)
                            <td class="text-end" dir="ltr">{{ $row['cost'] }}</td>
                            <td class="text-end" dir="ltr">{{ $row['billed'] }}</td>
                            <td class="text-end" dir="ltr">{{ $row['collected'] }}</td>
                            <td class="text-end" dir="ltr">{{ $row['margin'] }}</td>
                            <td class="text-end" dir="ltr">{{ $row['effective_rate'] }}</td>
                            <td class="text-end" dir="ltr">{{ $row['margin_rate'] ?? '—' }}@if($row['margin_rate'] !== null) %@endif</td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
                <tfoot class="bg-brand-50 text-brand-700">
                    <tr class="font-semibold">
                        <th scope="row" colspan="2" class="text-start">{{ __('phase7.totals') }}</th>
                        <td class="text-end" dir="ltr">{{ $totals['hours'] }}</td>
                        @if($financial)
                            <td class="text-end" dir="ltr">{{ $totals['cost'] }}</td>
                            <td class="text-end" dir="ltr">{{ $totals['billed'] }}</td>
                            <td class="text-end" dir="ltr">{{ $totals['collected'] }}</td>
                            <td class="text-end" dir="ltr">{{ $totals['margin'] }}</td>
                            <td class="text-end" dir="ltr">{{ $totals['effective_rate'] }}</td>
                            <td class="text-end" dir="ltr">{{ $totals['margin_rate'] ?? '—' }}@if($totals['margin_rate'] !== null) %@endif</td>
                        @endif
                    </tr>
                </tfoot>
            </table>
        </div>
        <p class="mt-4 text-xs leading-5 text-slate-500">{{ __('phase7.cost_note') }}</p>
    @endif
</section>
@endsection

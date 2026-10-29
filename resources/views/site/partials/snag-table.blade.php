<div class="mt-4 overflow-x-auto" role="region" aria-label="{{ __('phase8.snag_register') }}" tabindex="0">
    <table class="w-full min-w-[900px] text-sm">
        <thead>
            <tr>
                <th scope="col">{{ __('phase8.reference') }}</th>
                <th scope="col">{{ __('phase8.snag_title') }}</th>
                <th scope="col">{{ __('phase8.severity') }}</th>
                <th scope="col">{{ __('phase8.status') }}</th>
                <th scope="col">{{ __('phase8.due_on') }}</th>
                <th scope="col">{{ __('phase8.responsible') }}</th>
                <th scope="col">{{ __('phase8.report') }}</th>
                <th scope="col">{{ __('common.actions') }}</th>
            </tr>
        </thead>
        <tbody>
        @foreach($snags as $snag)
            @php($overdue = $snag->isOverdue($today))
            <tr>
                <td class="font-semibold" dir="ltr">{{ $snag->reference }}</td>
                <td>
                    <span class="block font-semibold">{{ $snag->title }}</span>
                    @if($snag->location)<span class="block text-xs text-slate-500">{{ $snag->location }}</span>@endif
                </td>
                <td>
                    <x-site-badge :tone="match($snag->severity->value) { 'critical' => 'danger', 'major' => 'warning', default => 'neutral' }">{{ __('phase8.severity_'.$snag->severity->value) }}</x-site-badge>
                </td>
                <td>
                    <x-site-badge :tone="match($snag->status->value) { 'validated' => 'success', 'fixed' => 'info', default => 'warning' }">{{ __('phase8.snag_status.'.$snag->status->value) }}</x-site-badge>
                </td>
                <td dir="ltr">
                    {{ $snag->due_on?->format('Y-m-d') ?? '—' }}
                    @if($overdue)<span class="ms-1 text-xs font-semibold text-red-700">{{ __('phase8.overdue') }}</span>@endif
                </td>
                <td>{{ $snag->responsible ?? '—' }}</td>
                <td>{{ $snag->siteReport?->number ?? '—' }}</td>
                <td>
                    <div class="flex flex-wrap items-center gap-2">
                        @if($snag->status === \App\Enums\SnagStatus::Open)
                            @can('update', $snag)
                                <form method="POST" action="{{ route('site.snags.status', [$project, $snag]) }}">@csrf<input type="hidden" name="status" value="fixed"><button type="submit" class="text-xs font-semibold text-brand-700 hover:underline">{{ __('phase8.mark_fixed') }}</button></form>
                            @endcan
                        @elseif($snag->status === \App\Enums\SnagStatus::Fixed)
                            @can('validate', $snag)
                                <form method="POST" action="{{ route('site.snags.status', [$project, $snag]) }}">@csrf<input type="hidden" name="status" value="validated"><button type="submit" class="text-xs font-semibold text-emerald-800 hover:underline">{{ __('phase8.validate_snag') }}</button></form>
                            @endcan
                        @endif
                        @can('delete', $snag)
                            <form method="POST" action="{{ route('site.snags.destroy', [$project, $snag]) }}" data-confirm="{{ __('phase8.confirm_snag_delete') }}" onsubmit="return confirm(this.dataset.confirm)">@csrf @method('DELETE')<button type="submit" class="text-xs font-semibold text-red-700 hover:underline">{{ __('common.delete') }}</button></form>
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

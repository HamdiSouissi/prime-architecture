@props(['governorates', 'model'])
@php
    $oldGovernorate = old('governorate_id', $model->governorate_id);
    $oldDelegation = old('delegation_id', $model->delegation_id);
    $selectedGovernorate = is_scalar($oldGovernorate) ? (string) $oldGovernorate : '';
    $selectedDelegation = is_scalar($oldDelegation) ? (string) $oldDelegation : '';
    $counts = $governorates->mapWithKeys(fn ($g) => [$g->id => $g->delegations->count()]);
    $nameField = app()->getLocale() === 'ar' ? 'name_ar' : 'name_fr';
@endphp
<div x-data="{ governorate: @js($selectedGovernorate), counts: @js($counts) }" class="space-y-3">
    <div>
        <label for="governorate_id">{{ __('phase3.governorate') }}</label>
        <select id="governorate_id" name="governorate_id" class="w-full rounded border-slate-300" x-model="governorate" @change="$refs.delegation.value = ''" @error('governorate_id') aria-describedby="governorate-error" @enderror>
            <option value="">{{ __('phase3.none') }}</option>
            @foreach ($governorates as $governorate)
                <option value="{{ $governorate->id }}" @selected($selectedGovernorate === (string) $governorate->id)>{{ $governorate->$nameField }}</option>
            @endforeach
        </select>
        @error('governorate_id')<p id="governorate-error" class="text-red-700" role="alert">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="delegation_id">{{ __('phase3.delegation') }}</label>
        <select id="delegation_id" name="delegation_id" class="w-full rounded border-slate-300" x-ref="delegation" aria-describedby="delegation-hint{{ $errors->has('delegation_id') ? ' delegation-error' : '' }}">
            <option value="">{{ __('phase3.none') }}</option>
            @foreach ($governorates as $governorate)
                @foreach ($governorate->delegations->sortBy($nameField) as $delegation)
                    <option value="{{ $delegation->id }}" data-governorate-id="{{ $governorate->id }}"
                        :hidden="governorate !== @js((string) $governorate->id)"
                        :disabled="governorate !== @js((string) $governorate->id)"
                        @selected($selectedDelegation === (string) $delegation->id)>{{ $delegation->$nameField }}</option>
                @endforeach
            @endforeach
        </select>
        <p id="delegation-hint" x-cloak class="text-sm text-slate-600">
            <span x-show="!governorate">{{ __('phase3.choose_governorate_first') }}</span>
            <span x-show="governorate && !counts[governorate]">{{ __('phase3.no_delegations') }}</span>
        </p>
        <noscript><p class="text-sm text-slate-600">{{ __('phase3.delegation_without_javascript') }}</p></noscript>
        @error('delegation_id')<p id="delegation-error" class="text-red-700" role="alert">{{ $message }}</p>@enderror
    </div>
</div>

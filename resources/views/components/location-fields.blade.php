@props(['governorates', 'model'])
@php
    $items = $governorates->mapWithKeys(fn ($g) => [$g->id => $g->delegations->map(fn ($d) => ['id' => $d->id, 'name' => app()->getLocale() === 'ar' ? $d->name_ar : $d->name_fr])->values()]);
@endphp
<div x-data="{ governorate: @js((string) old('governorate_id', $model->governorate_id)), delegation: @js((string) old('delegation_id', $model->delegation_id)), items: @js($items) }" class="space-y-3">
    <div><label for="governorate_id">{{ __('phase3.governorate') }}</label><select id="governorate_id" name="governorate_id" class="w-full rounded border-slate-300" x-model="governorate" @change="delegation = ''"><option value="">{{ __('phase3.none') }}</option>@foreach ($governorates as $governorate)<option value="{{ $governorate->id }}">{{ app()->getLocale() === 'ar' ? $governorate->name_ar : $governorate->name_fr }}</option>@endforeach</select>@error('governorate_id')<p class="text-red-700" role="alert">{{ $message }}</p>@enderror</div>
    <div><label for="delegation_id">{{ __('phase3.delegation') }}</label><select id="delegation_id" name="delegation_id" class="w-full rounded border-slate-300" x-model="delegation"><option value="">{{ __('phase3.none') }}</option><template x-for="item in (items[governorate] || [])" :key="item.id"><option :value="item.id" x-text="item.name"></option></template></select><p x-show="governorate && !(items[governorate] || []).length" class="text-sm text-slate-600">{{ __('phase3.no_delegations') }}</p>@error('delegation_id')<p class="text-red-700" role="alert">{{ $message }}</p>@enderror</div>
</div>

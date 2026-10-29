@extends('layouts.app')
@section('title', __('phase9.catalog'))
@section('content')
<x-page-header :title="__('phase9.catalog')" :description="__('phase9.intro')" />
@if(session('status'))<x-alert variant="success" class="mb-5">{{ session('status') }}</x-alert>@endif
@if($errors->any())<x-alert class="mb-5"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-alert>@endif
@can('manage', \App\Models\PriceItem::class)
<x-card class="mb-6"><h2 class="section-title">{{ __('phase9.add_item') }}</h2><form method="POST" action="{{ route('prices.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">@csrf
<x-form-field name="category" :label="__('phase9.category')" required maxlength="120" />
<x-form-field name="name" :label="__('phase9.name')" required maxlength="200" />
<x-form-field name="unit" :label="__('phase9.unit')" required maxlength="40" />
<x-form-field name="unit_price" :label="__('phase9.unit_price')" required inputmode="decimal" />
<div><x-button type="submit">{{ __('phase9.add_item') }}</x-button></div></form></x-card>
@endcan
<div class="space-y-4">@forelse($items as $item)<x-card><div class="flex flex-wrap items-center justify-between gap-3"><div><p class="text-sm text-slate-600">{{ $item->category }}</p><h2 class="font-bold">{{ $item->name }}</h2></div><p dir="ltr">{{ $item->unit_price }} TND / {{ $item->unit }}</p></div>
<details class="mt-3"><summary class="cursor-pointer font-semibold text-brand-700">{{ __('phase9.history') }}</summary><ul class="mt-2">@foreach($item->prices->sortByDesc('id') as $price)<li><span dir="ltr">{{ $price->created_at->format('Y-m-d H:i') }} — {{ $price->unit_price }} TND</span></li>@endforeach</ul></details>
@can('manage', \App\Models\PriceItem::class)<details class="mt-3"><summary class="cursor-pointer font-semibold text-brand-700">{{ __('common.edit') }}</summary><form method="POST" action="{{ route('prices.update', $item) }}" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">@csrf @method('PUT')
<x-form-field name="category" :id="'category-'.$item->id" :label="__('phase9.category')" :value="$item->category" required maxlength="120" />
<x-form-field name="name" :id="'name-'.$item->id" :label="__('phase9.name')" :value="$item->name" required maxlength="200" />
<x-form-field name="unit" :id="'unit-'.$item->id" :label="__('phase9.unit')" :value="$item->unit" required maxlength="40" />
<x-form-field name="unit_price" :id="'price-'.$item->id" :label="__('phase9.unit_price')" :value="$item->unit_price" required inputmode="decimal" />
<div><x-button type="submit">{{ __('common.save') }}</x-button></div></form></details>@endcan
</x-card>@empty<x-card><p>{{ __('phase9.empty_catalog') }}</p></x-card>@endforelse</div><div class="mt-4">{{ $items->links() }}</div>
@endsection

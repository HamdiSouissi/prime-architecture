@props(['name', 'label', 'type' => 'text', 'help' => null, 'id' => null])
@php($fieldId = $id ?? $name)
<div>
    <label for="{{ $fieldId }}" class="mb-1.5 block text-sm font-semibold text-ink">{{ $label }}</label>
    <input id="{{ $fieldId }}" name="{{ $name }}" type="{{ $type }}" @if($errors->has($name)) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @elseif($help) aria-describedby="{{ $fieldId }}-help" @endif {{ $attributes->class(['w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-ink']) }}>
    @if($help)<p id="{{ $fieldId }}-help" class="mt-1 text-sm text-slate-600">{{ $help }}</p>@endif
    @error($name)<p id="{{ $fieldId }}-error" class="mt-1 text-sm font-medium text-danger" role="alert">{{ $message }}</p>@enderror
</div>

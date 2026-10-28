@props(['name', 'label', 'type' => 'text'])
<div>
    <label for="{{ $name }}" class="mb-1 block text-sm font-medium">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" {{ $attributes->class(['w-full rounded border border-slate-300 px-3 py-2']) }}>
    @error($name)<p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
</div>

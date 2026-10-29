@props(['name', 'label', 'type' => 'text', 'help' => null])
<div>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-semibold text-ink">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" @if($errors->has($name)) aria-invalid="true" aria-describedby="{{ $name }}-error" @elseif($help) aria-describedby="{{ $name }}-help" @endif {{ $attributes->class(['w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-ink']) }}>
    @if($help)<p id="{{ $name }}-help" class="mt-1 text-sm text-slate-600">{{ $help }}</p>@endif
    @error($name)<p id="{{ $name }}-error" class="mt-1 text-sm font-medium text-danger" role="alert">{{ $message }}</p>@enderror
</div>

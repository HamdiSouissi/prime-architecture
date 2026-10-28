@props(['type' => 'button'])
<button type="{{ $type }}" {{ $attributes->class(['rounded bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-500']) }}>{{ $slot }}</button>

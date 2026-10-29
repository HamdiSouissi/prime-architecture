@props(['tone' => 'neutral'])
@php($styles = [
    'neutral' => 'border-slate-300 bg-slate-50 text-slate-700',
    'success' => 'border-emerald-300 bg-emerald-50 text-emerald-900',
    'warning' => 'border-amber-300 bg-amber-50 text-amber-900',
    'danger' => 'border-red-300 bg-red-50 text-red-900',
    'info' => 'border-blue-300 bg-blue-50 text-blue-900',
])
<span {{ $attributes->class(['inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-semibold', $styles[$tone] ?? $styles['neutral']]) }}>{{ $slot }}</span>

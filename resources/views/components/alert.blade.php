@props(['variant' => 'warning'])
@php($styles = ['warning' => 'border-amber-200 bg-amber-50 text-amber-900', 'success' => 'border-emerald-200 bg-emerald-50 text-emerald-900', 'error' => 'border-red-200 bg-red-50 text-red-900', 'info' => 'border-blue-200 bg-blue-50 text-blue-900'])
<div role="{{ $attributes->get('role', $variant === 'success' ? 'status' : 'alert') }}" {{ $attributes->except('role')->class(['rounded-xl border px-4 py-3 text-sm leading-6', $styles[$variant] ?? $styles['warning']]) }}>{{ $slot }}</div>

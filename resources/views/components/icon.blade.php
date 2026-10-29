@props(['name'])
@php($available = ['home', 'squares-2x2', 'users', 'rectangle-stack', 'folder-open', 'calendar-days', 'user-circle', 'bell', 'clock', 'cog-6-tooth', 'bars-3', 'arrow-right-on-rectangle'])
@if(in_array($name, $available, true))<span {{ $attributes->class(['inline-flex h-5 w-5 shrink-0']) }} aria-hidden="true">@include('components.icons.'.$name)</span>@endif

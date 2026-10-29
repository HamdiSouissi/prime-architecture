@extends('layouts.app')
@section('title', __('common.home').' — '.__('common.app_name'))
@section('content')
<section class="hero-panel grid min-h-[360px] lg:grid-cols-2">
    <div class="relative z-[1] flex flex-col items-start justify-center p-7 sm:p-12 lg:p-14">
        <span class="eyebrow">{{ __('common.welcome_eyebrow') }}</span><p class="mt-3 text-sm font-semibold text-slate-600">{{ __('common.welcome') }}</p>
        <h1 class="mt-4 max-w-lg text-4xl font-bold leading-tight tracking-tight text-ink sm:text-5xl">{{ __('common.welcome_title') }}</h1>
        <p class="mt-5 max-w-md text-base leading-7 text-slate-600">{{ __('common.welcome_detail') }}</p>
        @guest<a href="{{ route('login') }}" class="mt-8 inline-flex min-h-12 items-center justify-center gap-3 rounded-xl bg-brand-600 px-5 py-3 font-semibold text-white shadow-soft hover:bg-brand-700">{{ __('common.get_started') }} <span aria-hidden="true" class="rtl:rotate-180">→</span></a>
        @else<a href="{{ route('dashboard') }}" class="mt-8 inline-flex min-h-12 items-center justify-center gap-3 rounded-xl bg-brand-600 px-5 py-3 font-semibold text-white shadow-soft hover:bg-brand-700">{{ __('common.get_started') }} <span aria-hidden="true" class="rtl:rotate-180">→</span></a>@endguest
        <p class="mt-8 text-sm text-slate-500">{{ __('common.workspace_note') }}</p>
    </div>
    <div class="hero-art relative hidden min-h-[300px] overflow-hidden lg:block" aria-hidden="true">
        <div class="absolute inset-10 rounded-[3rem] border border-white/80"></div>
        <div class="hero-line absolute start-[13%] top-[18%] h-44 w-56 rotate-[-8deg] bg-white/70"></div>
        <div class="hero-line absolute end-[13%] top-[28%] h-56 w-48 rotate-[9deg] bg-indigo-100/80"></div>
        <div class="hero-line absolute bottom-[12%] start-[26%] h-28 w-56 bg-white/90 p-5"><div class="h-2 w-16 rounded-full bg-brand-600"></div><div class="mt-4 h-2 w-32 rounded-full bg-slate-200"></div><div class="mt-3 h-2 w-24 rounded-full bg-slate-100"></div></div>
        <div class="absolute end-[19%] top-[15%] h-14 w-14 rounded-2xl bg-teal-700/90"></div>
    </div>
</section>
@endsection

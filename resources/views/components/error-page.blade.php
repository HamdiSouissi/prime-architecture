@props(['code', 'title', 'message'])
<div class="mx-auto max-w-2xl py-8 sm:py-14">
    <div class="hero-panel p-6 shadow-soft sm:p-10">
        <div class="mb-6 inline-flex rounded-2xl bg-brand-50 px-4 py-2 text-sm font-bold tracking-wider text-brand-700" aria-hidden="true">{{ $code }}</div>
        <h1 class="text-3xl font-bold tracking-tight text-ink">{{ $code }} — {{ $title }}</h1>
        <p class="mt-4 max-w-xl leading-relaxed text-slate-600">{{ $message }}</p>
        <a href="{{ route('home') }}" class="action-link mt-7">{{ __('common.back_home') }}</a>
    </div>
</div>

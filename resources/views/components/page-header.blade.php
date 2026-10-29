@props(['title', 'eyebrow' => null, 'description' => null])
<header {{ $attributes->class(['mb-6 flex flex-wrap items-end justify-between gap-4']) }}>
    <div class="min-w-0">
        @if($eyebrow)
            <p class="eyebrow">{{ $eyebrow }}</p>
        @endif
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-ink sm:text-3xl">{{ $title }}</h1>
        @if($description)
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{{ $description }}</p>
        @endif
    </div>
    @if(! $slot->isEmpty())
        <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</header>

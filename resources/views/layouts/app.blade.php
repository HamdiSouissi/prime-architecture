<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('common.app_name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="{{ app()->getLocale() === 'ar' ? 'font-arabic' : 'font-sans' }} min-h-screen bg-slate-50 text-slate-900">
<div x-data="{ menuOpen: false }" class="min-h-screen flex flex-col">
    <header class="bg-slate-900 text-white">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-4 px-4 py-4 sm:px-6">
            <a href="{{ route('home') }}" class="font-bold text-lg">{{ __('common.app_name') }}</a>
            <button type="button" class="ms-auto rounded border border-slate-500 px-3 py-1 md:hidden" @click="menuOpen = !menuOpen" :aria-expanded="menuOpen.toString()" aria-controls="site-navigation">{{ __('common.menu') }}</button>
            @guest
                <a href="{{ route('login') }}" class="rounded border border-slate-500 px-3 py-1 text-sm">{{ __('common.login') }}</a>
            @else
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="rounded border border-slate-500 px-3 py-1 text-sm">{{ __('common.logout') }}</button>
                </form>
            @endguest
            <form action="{{ route('locale.update') }}" method="POST" class="flex items-center gap-2 md:ms-auto">
                @csrf
                <label for="locale" class="text-sm">{{ __('common.language') }}</label>
                <select id="locale" name="locale" class="rounded border-slate-300 bg-white text-slate-900 text-sm">
                    <option value="fr" @selected(app()->getLocale() === 'fr')>{{ __('common.french') }}</option>
                    <option value="ar" @selected(app()->getLocale() === 'ar')>{{ __('common.arabic') }}</option>
                </select>
                <button type="submit" class="rounded border border-slate-500 px-2 py-1 text-sm">{{ __('common.change_language') }}</button>
            </form>
        </div>
    </header>
    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col md:flex-row">
        <nav id="site-navigation" :class="menuOpen ? 'block' : 'hidden'" class="hidden w-full border-slate-200 bg-white p-4 md:block md:w-56 md:border-e">
            <a href="{{ route('home') }}" class="block rounded bg-slate-100 px-3 py-2 font-medium">{{ __('common.home') }}</a>
            @auth
                @canany(['dashboard.internal.view', 'dashboard.client.view'])<a href="{{ route('dashboard') }}" class="block px-3 py-2">{{ __('common.dashboard') }}</a>@endcanany
                @can('clients.view')<a href="{{ route('clients.index') }}" class="block px-3 py-2">{{ __('phase3.clients') }}</a>@endcan
                @can('projects.view')<a href="{{ route('projects.index') }}" class="block px-3 py-2">{{ __('phase3.projects') }}</a>@endcan
                <a href="{{ route('profile.edit') }}" class="block px-3 py-2">{{ __('common.profile') }}</a>
                @can('users.manage')<a href="{{ route('users.index') }}" class="block px-3 py-2">{{ __('common.users') }}</a>@endcan
                @role('admin')<a href="{{ route('document-categories.index') }}" class="block px-3 py-2">{{ __('phase4.manage_categories') }}</a>@endrole
                @can('activity.view')<a href="{{ route('activity.index') }}" class="block px-3 py-2">{{ __('common.activity') }}</a>@endcan
            @endauth
        </nav>
        <main class="min-w-0 flex-1 p-4 sm:p-6">@yield('content')</main>
    </div>
    <footer class="border-t border-slate-200 px-4 py-4 text-center text-sm text-slate-600">{{ __('common.footer') }}</footer>
</div>
</body>
</html>

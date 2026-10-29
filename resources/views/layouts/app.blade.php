<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $__env->yieldContent('title', __('common.app_name')) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="{{ app()->getLocale() === 'ar' ? 'font-arabic' : 'font-sans' }} min-h-screen">
<a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-50 focus:rounded-xl focus:bg-white focus:p-3">{{ __('common.skip_content') }}</a>
<div x-data="{ menuOpen: false }" class="flex min-h-screen flex-col">
    <header class="app-header">
        <div class="mx-auto flex max-w-screen-2xl flex-wrap items-center gap-3 px-4 py-3 sm:px-7">
            <a href="{{ route('home') }}" class="group flex items-center gap-3 text-ink"><span aria-hidden="true" class="brand-mark">A</span><span class="leading-tight"><span class="block text-base font-bold tracking-tight">{{ __('common.app_name') }}</span><span class="hidden text-xs font-medium text-slate-500 sm:block">{{ __('common.brand_subtitle') }}</span></span></a>
            <button type="button" class="ms-auto inline-flex min-h-11 items-center gap-2 rounded-xl border border-slate-200 px-3 text-sm font-semibold md:hidden" @click="menuOpen = !menuOpen" :aria-expanded="menuOpen.toString()" aria-controls="site-navigation"><x-icon name="bars-3" />{{ __('common.menu') }}</button>
            <form action="{{ route('locale.update') }}" method="POST" class="flex items-center gap-2 md:ms-auto">
                @csrf
                <label for="locale" class="text-sm font-medium text-slate-700">{{ __('common.language') }}</label>
                <select id="locale" name="locale" class="text-sm">
                    <option value="fr" @selected(app()->getLocale() === 'fr')>{{ __('common.french') }}</option>
                    <option value="ar" @selected(app()->getLocale() === 'ar')>{{ __('common.arabic') }}</option>
                </select>
                <button type="submit" class="min-h-11 rounded-xl px-3 text-sm font-semibold text-brand-700 hover:bg-brand-50">{{ __('common.change_language') }}</button>
            </form>
            @auth
                <a href="{{ route('profile.edit') }}" class="hidden max-w-40 items-center gap-2 rounded-full bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700 lg:inline-flex"><span class="avatar-mark" aria-hidden="true">{{ mb_substr(auth()->user()->name, 0, 1) }}</span><span class="truncate">{{ auth()->user()->name }}</span></a>
            @endauth
            @guest
                <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center rounded-xl bg-brand-600 px-4 text-sm font-semibold text-white hover:bg-brand-700">{{ __('common.login') }}</a>
            @else
                <form action="{{ route('logout') }}" method="POST">@csrf<button type="submit" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-slate-200 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"><x-icon name="arrow-right-on-rectangle" class="rtl:rotate-180" />{{ __('common.logout') }}</button></form>
            @endguest
        </div>
    </header>
    <div class="mx-auto flex w-full max-w-screen-2xl flex-1 flex-col md:flex-row">
        <nav id="site-navigation" aria-label="{{ __('common.navigation') }}" :class="menuOpen ? 'block' : 'hidden md:block'" class="app-sidebar w-full p-4 md:block md:w-64 md:shrink-0 md:border-e">
            <p class="nav-caption">{{ __('common.workspace_section') }}</p>
            <div class="space-y-1">
                <a href="{{ route('home') }}" @class(['app-nav-link', 'app-nav-link-active' => request()->routeIs('home')])><x-icon name="home" />{{ __('common.home') }}</a>
                @auth
                    @canany(['dashboard.internal.view', 'dashboard.client.view'])<a href="{{ route('dashboard') }}" @class(['app-nav-link', 'app-nav-link-active' => request()->routeIs('dashboard')])><x-icon name="squares-2x2" />{{ __('common.dashboard') }}</a>@endcanany
                    @can('clients.view')<a href="{{ route('clients.index') }}" @class(['app-nav-link', 'app-nav-link-active' => request()->routeIs('clients.*')])><x-icon name="users" />{{ __('phase3.clients') }}</a>@endcan
                    @can('projects.view')<a href="{{ route('projects.index') }}" @class(['app-nav-link', 'app-nav-link-active' => request()->routeIs('projects.*')])><x-icon name="folder-open" />{{ __('phase3.projects') }}</a>@endcan
                    @can('viewAny', \App\Models\Quote::class)<a href="{{ route('billing.index') }}" @class(['app-nav-link', 'app-nav-link-active' => request()->routeIs('billing.*')])><x-icon name="rectangle-stack" />{{ __('phase6.billing') }}</a>@endcan
                    <div class="nav-divider"></div><p class="nav-caption">{{ __('common.manage_section') }}</p>
                    <a href="{{ route('profile.edit') }}" @class(['app-nav-link', 'app-nav-link-active' => request()->routeIs('profile.*')])><x-icon name="user-circle" />{{ __('common.profile') }}</a>
                    @can('users.manage')<a href="{{ route('users.index') }}" @class(['app-nav-link', 'app-nav-link-active' => request()->routeIs('users.*')])><x-icon name="users" />{{ __('common.users') }}</a>@endcan
                    @role('admin')<a href="{{ route('document-categories.index') }}" @class(['app-nav-link', 'app-nav-link-active' => request()->routeIs('document-categories.*')])><x-icon name="rectangle-stack" />{{ __('phase4.manage_categories') }}</a>@endrole
                    @can('manage', \App\Models\PlanningTemplate::class)<a href="{{ route('planning.templates.index') }}" @class(['app-nav-link', 'app-nav-link-active' => request()->routeIs('planning.templates.*')])><x-icon name="rectangle-stack" />{{ __('phase5.templates') }}</a>@endcan
                    @can('manage', \App\Models\Holiday::class)<a href="{{ route('planning.calendar-admin') }}" @class(['app-nav-link', 'app-nav-link-active' => request()->routeIs('planning.calendar*')])><x-icon name="calendar-days" />{{ __('phase5.calendar_admin') }}</a>@endcan
                    @can('tasks.view')<a href="{{ route('planning.notifications') }}" @class(['app-nav-link', 'app-nav-link-active' => request()->routeIs('planning.notifications*')])><x-icon name="bell" />{{ __('phase5.notifications') }}</a>@endcan
                    @hasanyrole('admin|architect|collaborator')@can('tasks.view')<a href="{{ route('planning.my-tasks') }}" @class(['app-nav-link', 'app-nav-link-active' => request()->routeIs('planning.my-tasks')])><x-icon name="calendar-days" />{{ __('phase5.my_tasks') }}</a>@endcan @endhasanyrole
                    @can('activity.view')<a href="{{ route('activity.index') }}" @class(['app-nav-link', 'app-nav-link-active' => request()->routeIs('activity.*')])><x-icon name="clock" />{{ __('common.activity') }}</a>@endcan
                @endauth
            </div>
        </nav>
        <main id="main-content" class="min-w-0 flex-1 px-4 py-5 sm:px-7 sm:py-7">
            <nav aria-label="{{ __('common.breadcrumb') }}" class="mb-6 text-sm text-slate-600"><a href="{{ route('home') }}" class="hover:text-brand-700">{{ __('common.home') }}</a><span aria-hidden="true" class="mx-2">/</span><span aria-current="page">{{ $__env->yieldContent('title', __('common.app_name')) }}</span></nav>
            @yield('content')
        </main>
    </div>
    <footer class="border-t border-slate-200 bg-white px-4 py-4 text-center text-sm text-slate-500">{{ __('common.footer') }}</footer>
</div>
</body>
</html>

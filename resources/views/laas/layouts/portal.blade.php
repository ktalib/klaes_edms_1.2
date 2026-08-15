@extends('laas.layouts.app')

@section('body')
@php $applicant = auth('laas')->user(); @endphp

<div class="min-h-screen flex flex-col">

    <header class="sticky top-0 z-30 border-b border-slate-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6">

            <a href="{{ route('laas.dashboard') }}" class="flex items-center gap-3">
                <img src="{{ asset('assets/logo/laas-light-logo.jpeg') }}" alt="LAAS Portal"
                     class="h-10 w-auto object-contain dark:hidden">
                <img src="{{ asset('assets/logo/laas-dark-logo.jpeg') }}" alt="LAAS Portal"
                     class="hidden h-10 w-auto object-contain dark:block">
            </a>

            <nav class="hidden items-center gap-1 md:flex">
                @php
                    $links = [
                        ['laas.dashboard', 'Dashboard', 'layout-dashboard'],
                        ['laas.apply.form', 'New Application', 'file-plus-2'],
                        ['laas.notifications', 'Updates', 'bell'],
                    ];
                @endphp
                @foreach($links as [$route, $label, $icon])
                    <a href="{{ route($route) }}"
                       class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition
                              {{ request()->routeIs($route) ? 'bg-green-50 text-[#1a6b3c] dark:bg-green-900/30 dark:text-green-300' : 'text-slate-600 hover:bg-slate-100 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                        <i data-lucide="{{ $icon }}" class="h-4 w-4"></i>
                        {{ $label }}
                        @if($route === 'laas.notifications' && ($unreadUpdates ?? 0) > 0)
                            <span class="ml-1 rounded-full bg-[#f0a500] px-1.5 py-0.5 text-[10px] font-bold text-white">{{ $unreadUpdates }}</span>
                        @endif
                    </a>
                @endforeach
            </nav>

            <div class="flex items-center gap-2">
                <button onclick="laasToggleTheme()" title="Toggle dark mode"
                        class="rounded-md p-2 text-slate-500 transition-colors hover:bg-slate-100 dark:text-gray-400 dark:hover:bg-gray-700">
                    <i data-lucide="sun" class="h-4 w-4 dark:hidden"></i>
                    <i data-lucide="moon" class="hidden h-4 w-4 dark:block"></i>
                </button>

                <div class="hidden text-right sm:block">
                    <p class="text-sm font-semibold leading-tight">{{ $applicant->name ?? '' }}</p>
                    <p class="text-xs text-slate-500 dark:text-gray-400">{{ $applicant->phone ?? '' }}</p>
                </div>

                <form method="POST" action="{{ route('laas.logout') }}">
                    @csrf
                    <button type="submit" title="Sign out"
                            class="rounded-md p-2 text-slate-500 transition-colors hover:bg-red-50 hover:text-red-600 dark:text-gray-400 dark:hover:bg-red-900/30">
                        <i data-lucide="log-out" class="h-4 w-4"></i>
                    </button>
                </form>
            </div>
        </div>

        <!-- Mobile nav -->
        <nav class="flex items-center gap-1 overflow-x-auto border-t border-slate-200 px-4 py-2 md:hidden dark:border-gray-700">
            @foreach($links as [$route, $label, $icon])
                <a href="{{ route($route) }}"
                   class="inline-flex flex-shrink-0 items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium
                          {{ request()->routeIs($route) ? 'bg-green-50 text-[#1a6b3c] dark:bg-green-900/30 dark:text-green-300' : 'text-slate-600 dark:text-gray-300' }}">
                    <i data-lucide="{{ $icon }}" class="h-3.5 w-3.5"></i>{{ $label }}
                </a>
            @endforeach
        </nav>
    </header>

    <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6 sm:py-8">
        @if(session('status'))
            <div class="mb-6 flex items-start gap-3 rounded-xl border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/30">
                <i data-lucide="check-circle-2" class="mt-0.5 h-5 w-5 flex-shrink-0 text-[#1a6b3c] dark:text-green-400"></i>
                <p class="text-sm text-green-900 dark:text-green-200">{{ session('status') }}</p>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/30">
                <i data-lucide="triangle-alert" class="mt-0.5 h-5 w-5 flex-shrink-0 text-red-600 dark:text-red-400"></i>
                <p class="text-sm text-red-900 dark:text-red-200">{{ session('error') }}</p>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="border-t border-slate-200 bg-white py-5 dark:border-gray-700 dark:bg-gray-800">
        <p class="text-center text-xs text-slate-500 dark:text-gray-400">
            &copy; {{ date('Y') }} Kano State Ministry of Land &amp; Physical Planning — LAAS Portal
        </p>
    </footer>
</div>
@endsection

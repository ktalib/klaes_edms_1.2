@extends('laas.layouts.app')

@section('title', 'LAAS Portal — Land Allocation Application System')

@section('body')
<div class="min-h-screen bg-white dark:bg-gray-900">

    <header class="border-b border-slate-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6">
            <img src="{{ asset('assets/logo/laas-light-logo.jpeg') }}" alt="LAAS Portal" class="h-12 w-auto object-contain dark:hidden">
            <img src="{{ asset('assets/logo/laas-dark-logo.jpeg') }}" alt="LAAS Portal" class="hidden h-12 w-auto object-contain dark:block">

            <div class="flex items-center gap-2">
                <button onclick="laasToggleTheme()" title="Toggle dark mode"
                        class="rounded-md p-2 text-slate-500 hover:bg-slate-100 dark:text-gray-400 dark:hover:bg-gray-700">
                    <i data-lucide="sun" class="h-4 w-4 dark:hidden"></i>
                    <i data-lucide="moon" class="hidden h-4 w-4 dark:block"></i>
                </button>
                <a href="{{ route('laas.login') }}"
                   class="rounded-lg px-4 py-2 text-sm font-semibold text-[#1a6b3c] hover:bg-green-50 dark:text-green-300 dark:hover:bg-green-900/30">Sign in</a>
                <a href="{{ route('laas.register') }}"
                   class="laas-btn rounded-lg px-4 py-2 text-sm font-semibold text-white transition">Apply now</a>
            </div>
        </div>
    </header>

    <!-- Hero -->
    <section class="mx-auto max-w-6xl px-4 py-14 sm:px-6 sm:py-20">
        <div class="grid items-center gap-10 md:grid-cols-2">
            <div>
                <p class="mb-3 text-xs font-extrabold uppercase tracking-widest text-[#f0a500]">
                    Kano State Ministry of Land &amp; Physical Planning
                </p>
                <h1 class="text-3xl font-extrabold leading-tight text-slate-900 sm:text-4xl dark:text-white">
                    Apply for land allocation online, and follow it to the RoFO.
                </h1>
                <p class="mt-4 text-base leading-7 text-slate-600 dark:text-gray-300">
                    Fill and submit your application from anywhere. You are told by SMS the moment it is
                    received, when the Director approves it, when your file number is assigned, and at every
                    step afterwards — right through to your signed Right of Occupancy.
                </p>
                <div class="mt-7 flex flex-wrap gap-3">
                    <a href="{{ route('laas.register') }}"
                       class="laas-btn inline-flex items-center gap-2 rounded-lg px-5 py-3 text-sm font-semibold text-white transition">
                        <i data-lucide="file-plus-2" class="h-4 w-4"></i> Start an application
                    </a>
                    <a href="{{ route('laas.login') }}"
                       class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">
                        <i data-lucide="search" class="h-4 w-4"></i> Track an existing application
                    </a>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-6 dark:border-gray-700 dark:bg-gray-800">
                <p class="mb-5 text-xs font-extrabold uppercase tracking-widest text-slate-500 dark:text-gray-400">
                    How your application moves
                </p>
                <ol class="space-y-4">
                    @foreach([
                        ['send',           'You submit',              'Fill the form and submit. You are told at once that processing has started.'],
                        ['user-check',     'Director approves',       'The Director or assigned officer reviews and approves your application.'],
                        ['hash',           'File Number assigned',    'The Ministry generates your file number and sends it to you.'],
                        ['ruler',          'Survey &amp; Cadastral',  'A survey request is raised, goes to Cadastral, and returns completed.'],
                        ['clipboard-check','Recommendation',          'The Land Office prepares and approves your recommendation.'],
                        ['stamp',          'RoFO signed',             'The Director of Lands signs your Right of Occupancy, ready for collection.'],
                    ] as $i => [$icon, $title, $desc])
                        <li class="flex gap-4">
                            <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-[#1a6b3c] text-white">
                                <i data-lucide="{{ $icon }}" class="h-4 w-4"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $i + 1 }}. {!! $title !!}</p>
                                <p class="mt-0.5 text-[13px] leading-6 text-slate-600 dark:text-gray-400">{!! $desc !!}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    </section>

    <!-- What you need -->
    <section class="border-t border-slate-200 bg-slate-50 py-14 dark:border-gray-700 dark:bg-gray-800/50">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <h2 class="mb-8 text-center text-xl font-bold text-slate-900 dark:text-white">Before you begin</h2>
            <div class="grid gap-5 sm:grid-cols-3">
                @foreach([
                    ['smartphone',  'A working phone number', 'Every update is sent to it by SMS, so use a number you can receive messages on.'],
                    ['id-card',     'Means of identification', 'A National ID, driver&rsquo;s licence, voter&rsquo;s card or international passport.'],
                    ['map-pin',     'Details of the land',     'The LGA, district and location of the plot you are applying for.'],
                ] as [$icon, $title, $desc])
                    <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                        <i data-lucide="{{ $icon }}" class="mb-3 h-6 w-6 text-[#1a6b3c] dark:text-green-400"></i>
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $title }}</p>
                        <p class="mt-1 text-[13px] leading-6 text-slate-600 dark:text-gray-400">{!! $desc !!}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <footer class="border-t border-slate-200 py-6 dark:border-gray-700">
        <p class="text-center text-xs text-slate-500 dark:text-gray-400">
            &copy; {{ date('Y') }} Kano State Ministry of Land &amp; Physical Planning — LAAS Portal (KLAES)
        </p>
    </footer>
</div>
@endsection

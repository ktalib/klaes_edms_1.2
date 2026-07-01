{{--
    Online Legal Search — shared top navigation header.
    Header logo: http://app.klaes.ng/storage/upload/logo/logo.png
    Falls back to the bundled asset logo when the storage copy is unavailable (e.g. local dev).
    Fully public portal: no accounts, no sign-in.
--}}
<nav class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-sm sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center gap-2 h-16">
            <a href="{{ route('ols.landing') }}" class="flex items-center gap-2 sm:gap-3 min-w-0">
                <img src="{{ asset('storage/upload/logo/1.jpeg') }}" alt="KLAES"
                     class="h-8 sm:h-10 w-auto shrink-0"
                     onerror="this.onerror=null;this.src='{{ asset('assets/logo/1.jpeg') }}';">
                <div class="leading-tight min-w-0">
                    <span class="block text-base sm:text-lg font-bold text-gray-900 dark:text-white truncate">Online Legal Search</span>
                    <span class="hidden sm:block text-xs text-gray-500 dark:text-gray-400 truncate"> LAnd ADmin Enterprise System</span>
                </div>
            </a>

            <div class="flex items-center gap-2 sm:gap-4 shrink-0">
                <a id="new-search-nav" href="{{ route('ols.landing') }}"
                   class="inline-flex items-center justify-center rounded-lg bg-cyan-600 px-3 sm:px-4 py-1.5 sm:py-2 text-sm font-semibold text-white shadow hover:bg-cyan-700 whitespace-nowrap">
                    <i data-lucide="search" class="inline h-4 w-4 sm:mr-1.5"></i> <span class="hidden sm:inline">New Search</span>
                </a>
            </div>
        </div>
    </div>
</nav>

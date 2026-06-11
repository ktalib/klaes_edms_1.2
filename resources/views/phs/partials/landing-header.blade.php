{{-- Shared public/landing header for PHS marketing pages (landing, get-started). --}}
<nav class="sticky top-0 z-20 bg-white/80 backdrop-blur-md shadow-sm">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">
            <a href="{{ route('phs.landing') }}" class="flex items-center">
                <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES" class="h-11 w-auto object-contain">
            </a>

            <!-- Desktop Menu -->
            <div class="hidden items-center space-x-4 md:flex">
                <a href="{{ route('phs.login') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-transparent px-4 py-2 text-sm font-medium text-gray-700 transition-all hover:bg-gray-50">
                    Sign In
                </a>
                <a href="{{ route('phs.get_started') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-transparent px-4 py-2 text-sm font-medium text-gray-700 transition-all hover:bg-gray-50">
                    Get Started
                </a>
                <a href="{{ route('phs.request.form') }}" class="inline-flex items-center justify-center rounded-md border-0 bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-all hover:bg-blue-700">
                    Request Access
                </a>
            </div>

            <!-- Mobile Menu Button -->
            <button id="mobile-menu-btn" class="rounded-lg p-2 hover:bg-gray-100 md:hidden">
                <i data-lucide="menu" class="h-6 w-6 text-gray-700"></i>
            </button>
        </div>

        <!-- Mobile Menu -->
        <div id="mobile-menu" class="invisible max-h-0 overflow-hidden border-t border-gray-200 opacity-0 transition-all duration-300 ease-in-out md:hidden">
            <div class="space-y-3 py-4">
                <a href="{{ route('phs.login') }}" class="block rounded-md border border-gray-300 bg-transparent px-4 py-2 text-center text-sm font-medium text-gray-700 transition-all hover:bg-gray-50">
                    Sign In
                </a>
                <a href="{{ route('phs.get_started') }}" class="block rounded-md border border-gray-300 bg-transparent px-4 py-2 text-center text-sm font-medium text-gray-700 transition-all hover:bg-gray-50">
                    Get Started
                </a>
                <a href="{{ route('phs.request.form') }}" class="block rounded-md border-0 bg-blue-600 px-4 py-2 text-center text-sm font-medium text-white transition-all hover:bg-blue-700">
                    Request Access
                </a>
            </div>
        </div>
    </div>
</nav>

<script>
    document.getElementById('mobile-menu-btn')?.addEventListener('click', function () {
        const menu = document.getElementById('mobile-menu');
        if (!menu) return;
        menu.classList.toggle('invisible');
        menu.classList.toggle('max-h-0');
        menu.classList.toggle('opacity-0');
    });
</script>

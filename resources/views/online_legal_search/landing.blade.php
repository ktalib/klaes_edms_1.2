@extends('online_legal_search.layout')

@section('title', 'Online Legal Search Portal')

@section('body')
<div id="preloader" style="position:fixed;inset:0;background:#fff;display:flex;align-items:center;justify-content:center;z-index:9999;">
    <img src="{{ asset('storage/upload/logo/klas_logo.gif') }}" alt="Loading..." style="width:200px;height:auto;">
</div>

<div class="min-h-screen bg-gradient-to-br from-cyan-50 via-white to-blue-50 dark:from-gray-900 dark:via-gray-900 dark:to-gray-800">
    @include('online_legal_search.partials.header')

    <!-- Hero Section -->
    <div class="relative mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 md:py-24">
        <div class="text-center">
            <div class="mb-6 inline-flex items-center rounded-full bg-cyan-100 dark:bg-cyan-900/40 px-3 py-1 text-xs font-medium text-cyan-700 dark:text-cyan-300">
                <i data-lucide="shield-check" class="mr-2 h-4 w-4"></i> Official Government Platform
            </div>
            <h1 class="mb-6 text-3xl font-extrabold leading-tight text-gray-900 dark:text-gray-100 sm:text-5xl md:text-6xl">
                Kano State Ministry of Land
                <span class="bg-gradient-to-r from-cyan-600 to-blue-600 bg-clip-text text-transparent">Online Legal Search</span>
            </h1>
            <p class="mx-auto mb-10 max-w-3xl text-base text-gray-600 dark:text-gray-400 sm:text-lg md:text-xl">
                Access verified property records and conduct legal searches online. Secure, instant, and official.
            </p>
            <div class="flex flex-col justify-center gap-4 sm:flex-row">
                <a href="{{ route('ols.register') }}" class="inline-flex items-center justify-center rounded-lg bg-cyan-600 px-6 py-3 font-semibold text-white shadow-lg transition-all hover:bg-cyan-700 hover:shadow-xl sm:px-8 sm:py-4">
                    Create Account
                    <i data-lucide="arrow-right" class="ml-2 h-5 w-5"></i>
                </a>
                <a href="{{ route('ols.login') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-6 py-3 font-semibold text-gray-700 dark:text-gray-200 shadow transition-all hover:bg-gray-50 dark:hover:bg-gray-700 sm:px-8 sm:py-4">
                    Sign In
                </a>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <div class="bg-white dark:bg-gray-800 py-16 sm:py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-12 text-center">
                <h2 class="mb-4 text-2xl font-bold text-gray-900 dark:text-gray-100 sm:text-3xl md:text-4xl">
                    Why Use Online Legal Search?
                </h2>
            </div>
            <div class="grid gap-8 sm:grid-cols-2 md:grid-cols-3">
                <div class="text-center p-6">
                    <div class="mx-auto mb-5 flex h-20 w-20 items-center justify-center rounded-2xl bg-cyan-100 dark:bg-cyan-900/30">
                        <i data-lucide="zap" class="h-10 w-10 text-cyan-600 dark:text-cyan-400"></i>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold">Instant Results</h3>
                    <p class="text-gray-600 dark:text-gray-400">Get official search slips instantly with secure online payment.</p>
                </div>
                <div class="text-center p-6">
                    <div class="mx-auto mb-5 flex h-20 w-20 items-center justify-center rounded-2xl bg-blue-100 dark:bg-blue-900/30">
                        <i data-lucide="shield-check" class="h-10 w-10 text-blue-600 dark:text-blue-400"></i>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold">Official &amp; Verified</h3>
                    <p class="text-gray-600 dark:text-gray-400">Results come directly from the KLAES government database.</p>
                </div>
                <div class="text-center p-6">
                    <div class="mx-auto mb-5 flex h-20 w-20 items-center justify-center rounded-2xl bg-green-100 dark:bg-green-900/30">
                        <i data-lucide="clock" class="h-10 w-10 text-green-600 dark:text-green-400"></i>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold">24/7 Access</h3>
                    <p class="text-gray-600 dark:text-gray-400">Conduct searches anytime, anywhere without visiting the office.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Disclaimer -->
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="rounded-xl border border-amber-200 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 p-6">
            <div class="flex items-start gap-3">
                <i data-lucide="alert-triangle" class="h-6 w-6 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5"></i>
                <div class="text-sm text-amber-800 dark:text-amber-200">
                    <p class="font-semibold mb-1">Disclaimer</p>
                    <p>The Online Legal Search results provided by the Kano State Ministry of Land and Physical Planning are generated for informational and preliminary due diligence purposes. All genuine reports contain unique verification codes. It is the sole responsibility of the user to verify the authenticity of any document via the Ministry's official verification portal before making any financial or legal decisions.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 py-8">
        <div class="mx-auto max-w-7xl px-4 text-center text-sm text-gray-500 dark:text-gray-400">
            <p>&copy; {{ date('Y') }} Kano State Ministry of Land and Physical Planning — KLAES. All rights reserved.</p>
        </div>
    </footer>
</div>

<script>
    window.addEventListener('load', function () {
        const preloader = document.getElementById('preloader');
        if (preloader) preloader.style.display = 'none';
    });
</script>
@endsection

{{--
    Online Legal Search — centered brand block for auth screens.
    Header logo: http://app.klaes.ng/storage/upload/logo/logo.png (falls back to bundled asset).
    Optional: pass $subtitle to override the tagline.
--}}
<div class="mb-8 text-center">
    <a href="{{ route('ols.landing') }}" class="inline-block">
        <img src="{{ asset('storage/upload/logo/logo.png') }}" alt="KLAES"
             class="mx-auto mb-4 h-16 w-auto"
             onerror="this.onerror=null;this.src='{{ asset('assets/logo/logo.png') }}';">
    </a>
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Online Legal Search</h1>
    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $subtitle ?? ' LAnd ADmin Enterprise System' }}</p>
</div>

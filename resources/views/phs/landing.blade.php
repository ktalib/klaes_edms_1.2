@extends('phs.layouts.app')

@section('title', 'KLAES - Property History Search Portal')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-green-50">
    <!-- Responsive Navigation -->
    <nav class="sticky top-0 z-10 bg-white/80 backdrop-blur-md shadow-sm">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between">
                <div class="flex items-center">
                    <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES" class="h-11 w-auto object-contain">
                </div>
                
                <!-- Desktop Menu -->
                <div class="hidden items-center space-x-4 md:flex">
                    <a href="{{ route('phs.login') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-transparent px-4 py-2 text-sm font-medium text-gray-700 transition-all hover:bg-gray-50">
                        Sign In
                    </a>
                    <a href="{{ route('phs.register') }}" class="inline-flex items-center justify-center rounded-md border-0 bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-all hover:bg-blue-700">
                        Register
                    </a>
                </div>
                
                <!-- Mobile Menu Button -->
                <button id="mobile-menu-btn" class="rounded-lg p-2 hover:bg-gray-100 md:hidden">
                    <i data-lucide="menu" class="h-6 w-6 text-gray-700"></i>
                </button>
            </div>
            
            <!-- Mobile Menu -->
            <div id="mobile-menu" class="invisible max-h-0 overflow-hidden border-t border-gray-200 opacity-0 transition-all duration-300 ease-in-out md:hidden">
                <div class="space-y-3 border-t border-gray-200 py-4">
                    <a href="{{ route('phs.login') }}" class="block rounded-md border border-gray-300 bg-transparent px-4 py-2 text-center text-sm font-medium text-gray-700 transition-all hover:bg-gray-50">
                        Sign In
                    </a>
                    <a href="{{ route('phs.register') }}" class="block rounded-md border-0 bg-blue-600 px-4 py-2 text-center text-sm font-medium text-white transition-all hover:bg-blue-700">
                        Register
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Image Slider Banner -->
    <div class="relative mx-auto max-w-7xl px-4 pt-8 sm:px-6 lg:px-8">
        <div class="slider-container shadow-xl" id="hero-slider">
            <div class="slide active" style="background-image: url('http://app.klaes.ng/storage/upload/logo/5.jpeg')">
                <div class="slide-content">
                    <h3 class="text-2xl font-bold">Official Land Records</h3>
                    <p class="text-lg">Access verified property information directly from the government database</p>
                </div>
            </div>
            <div class="slide" style="background-image: url('http://app.klaes.ng/storage/upload/logo/4.jpeg')">
                <div class="slide-content">
                    <h3 class="text-2xl font-bold">Bank &amp; Legal Institution Search</h3>
                    <p class="text-lg">Trusted by leading banks and law firms across Nigeria</p>
                </div>
            </div>
            <div class="slide" style="background-image: url('http://app.klaes.ng/storage/upload/logo/2.jpeg')">
                <div class="slide-content">
                    <h3 class="text-2xl font-bold">Token-Based System</h3>
                    <p class="text-lg">Flexible packages starting from 2,000 tokens</p>
                </div>
            </div>
            <div class="slide" style="background-image: url('http://app.klaes.ng/storage/upload/logo/3.jpeg')">
                <div class="slide-content">
                    <h3 class="text-2xl font-bold">Instant Search Results</h3>
                    <p class="text-lg">Get official search slips instantly with just 1 token</p>
                </div>
            </div>
            <button class="slider-btn prev" id="prevSlide" aria-label="Previous slide">&#10094;</button>
            <button class="slider-btn next" id="nextSlide" aria-label="Next slide">&#10095;</button>
            <div class="slider-dots" id="sliderDots"></div>
        </div>
    </div>

    <!-- Hero Section -->
    <div class="relative mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8 md:py-16">
        <div class="text-center">
            <div class="mb-6 inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-700">
                <i data-lucide="crown" class="mr-2 h-4 w-4"></i> Official Government Platform
            </div>
            <h1 class="mb-6 text-3xl font-extrabold leading-tight text-gray-900 sm:text-5xl md:text-6xl lg:text-7xl">
                Kano State Ministry of Land
                <span class="bg-gradient-to-r from-blue-600 to-green-600 bg-clip-text text-transparent">and Physical Planning Property History Search (PHS) Portal</span>
            </h1>
            <p class="mx-auto mb-10 max-w-3xl text-base text-gray-600 sm:text-lg md:text-xl">
                Secure, token-based legal search for banks, law firms, and corporate institutions.
            </p>
            <div class="flex flex-col justify-center gap-4 sm:flex-row">
                <a href="{{ route('phs.login') }}" class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-6 py-3 font-semibold text-white shadow-lg transition-all hover:bg-blue-700 hover:shadow-xl sm:px-8 sm:py-4">
                    Get Started
                    <i data-lucide="arrow-right" class="ml-2 h-5 w-5"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <div class="bg-white py-12 sm:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-8 text-center sm:mb-12">
                <h2 class="mb-4 text-2xl font-bold text-gray-900 sm:text-3xl md:text-4xl">
                    Why Choose KLAES Enterprise?
                </h2>
            </div>
            <div class="grid gap-6 sm:grid-cols-2 md:grid-cols-3 sm:gap-8">
                <div class="p-4 text-center sm:p-6">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-100 sm:mb-5 sm:h-20 sm:w-20">
                        <i data-lucide="coins" class="h-8 w-8 text-blue-600 sm:h-10 sm:w-10"></i>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold sm:mb-3 sm:text-xl">Token-Based System</h3>
                    <p class="text-sm text-gray-600 sm:text-base">
                        Pay-as-you-go with flexible token packages. Each search consumes 1 token.
                    </p>
                </div>
                <div class="p-4 text-center sm:p-6">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-green-100 sm:mb-5 sm:h-20 sm:w-20">
                        <i data-lucide="shield" class="h-8 w-8 text-green-600 sm:h-10 sm:w-10"></i>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold sm:mb-3 sm:text-xl">Official & Secure</h3>
                    <p class="text-sm text-gray-600 sm:text-base">
                        Government-verified records with official search slips.
                    </p>
                </div>
                <div class="p-4 text-center sm:p-6">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-purple-100 sm:mb-5 sm:h-20 sm:w-20">
                        <i data-lucide="clock" class="h-8 w-8 text-purple-600 sm:h-10 sm:w-10"></i>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold sm:mb-3 sm:text-xl">Instant Results</h3>
                    <p class="text-sm text-gray-600 sm:text-base">
                        Real-time searches with downloadable official slips.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Pricing Section -->
    <div class="bg-gradient-to-br from-gray-50 to-blue-50 py-12 sm:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-8 text-center sm:mb-12">
                <h2 class="mb-4 text-2xl font-bold text-gray-900 sm:text-3xl md:text-4xl">
                    Flexible Token Packages
                </h2>
            </div>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 sm:gap-8">
                <!-- Starter Package -->
                <div class="rounded-2xl bg-white p-6 shadow-lg sm:p-8">
                    <h3 class="mb-2 text-xl font-bold text-green-600 sm:text-2xl">Starter</h3>
                    <div class="mb-2 text-3xl font-bold sm:text-4xl">₦50,000</div>
                    <p class="mb-4 text-gray-500">2,000 Tokens</p>
                    <button class="landing-package-btn w-full rounded-lg bg-green-600 py-3 text-white transition hover:bg-green-700" data-tokens="2000" data-price="50000" data-name="Starter">
                        Get Started
                    </button>
                </div>
                
                <!-- Professional Package -->
                <div class="relative rounded-2xl border-2 border-blue-500 bg-white p-6 shadow-lg sm:p-8">
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 transform whitespace-nowrap rounded-full bg-gradient-to-r from-blue-500 to-pink-500 px-4 py-1 text-xs font-semibold text-white">
                        POPULAR
                    </div>
                    <h3 class="mb-2 text-xl font-bold text-blue-600 sm:text-2xl">Professional</h3>
                    <div class="mb-2 text-3xl font-bold sm:text-4xl">₦100,000</div>
                    <p class="mb-4 text-gray-500">5,000 Tokens</p>
                    <button class="landing-package-btn w-full rounded-lg bg-blue-600 py-3 text-white transition hover:bg-blue-700" data-tokens="5000" data-price="100000" data-name="Professional">
                        Get Started
                    </button>
                </div>
                
                <!-- Enterprise Package -->
                <div class="rounded-2xl bg-white p-6 shadow-lg sm:col-span-2 lg:col-span-1 sm:p-8">
                    <h3 class="mb-2 text-xl font-bold text-purple-600 sm:text-2xl">Enterprise</h3>
                    <div class="mb-2 text-3xl font-bold sm:text-4xl">₦180,000</div>
                    <p class="mb-4 text-gray-500">10,000 Tokens</p>
                    <button class="landing-package-btn w-full rounded-lg bg-purple-600 py-3 text-white transition hover:bg-purple-700" data-tokens="10000" data-price="180000" data-name="Enterprise">
                        Get Started
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- CTA Section -->
    <div class="bg-gradient-to-r from-blue-600 to-purple-700 py-12 sm:py-16">
        <div class="mx-auto max-w-4xl px-4 text-center">
            <h2 class="mb-4 text-2xl font-bold text-white sm:text-3xl md:text-4xl">
                Ready to Transform Your Legal Search Process?
            </h2>
            <a href="{{ route('phs.register') }}" class="inline-flex items-center rounded-lg bg-white px-6 font-semibold text-blue-600 transition hover:shadow-xl sm:px-8 sm:py-4 py-3 text-sm sm:text-base">
                Start Your Journey
                <i data-lucide="arrow-right" class="ml-2 h-5 w-5"></i>
            </a>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-900 py-12 text-white sm:py-12 no-print">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4 sm:gap-10">
                <div>
                    <div class="flex items-center">
                        <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES" class="h-11 w-auto object-contain bg-white rounded-md p-1">
                    </div>
                    <p class="mt-4 text-sm leading-relaxed text-gray-400">
                        Official government platform for legal search services and land record verification in Kano State.
                    </p>
                </div>
                <div>
                    <h3 class="mb-4 text-base font-semibold uppercase tracking-wider text-gray-300">Services</h3>
                    <ul class="space-y-2 text-sm text-gray-400">
                        <li><a href="#" class="hover:text-white transition">Legal Search</a></li>
                        <li><a href="#" class="hover:text-white transition">Property Records</a></li>
                        <li><a href="#" class="hover:text-white transition">Certification</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="mb-4 text-base font-semibold uppercase tracking-wider text-gray-300">Company</h3>
                    <ul class="space-y-2 text-sm text-gray-400">
                        <li><a href="#" class="hover:text-white transition">About</a></li>
                        <li><a href="#" class="hover:text-white transition">Contact</a></li>
                        <li><a href="#" class="hover:text-white transition">Support</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="mb-4 text-base font-semibold uppercase tracking-wider text-gray-300">Legal</h3>
                    <ul class="space-y-2 text-sm text-gray-400">
                        <li><a href="#" class="hover:text-white transition">Privacy</a></li>
                        <li><a href="#" class="hover:text-white transition">Terms</a></li>
                        <li><a href="#" class="hover:text-white transition">Cookies</a></li>
                    </ul>
                </div>
            </div>
            <div class="mt-8 border-t border-gray-800 pt-8 text-center text-sm text-gray-400">
                <p>&copy; {{ date('Y') }} Kano State Ministry of Land and Physical Planning. All rights reserved.</p>
            </div>
        </div>
    </footer>
</div>

<script>
document.getElementById('mobile-menu-btn')?.addEventListener('click', function() {
    const menu = document.getElementById('mobile-menu');
    menu.classList.toggle('invisible');
    menu.classList.toggle('max-h-0');
    menu.classList.toggle('opacity-0');
});

// ---- Hero image slider ----
(function () {
    const slides = Array.from(document.querySelectorAll('#hero-slider .slide'));
    const dotsContainer = document.getElementById('sliderDots');
    if (!slides.length || !dotsContainer) return;

    let current = 0;
    let timer = null;

    slides.forEach((_, i) => {
        const dot = document.createElement('div');
        dot.className = 'dot' + (i === 0 ? ' active' : '');
        dot.addEventListener('click', () => goTo(i));
        dotsContainer.appendChild(dot);
    });
    const dots = Array.from(dotsContainer.children);

    function render() {
        slides.forEach((s, i) => s.classList.toggle('active', i === current));
        dots.forEach((d, i) => d.classList.toggle('active', i === current));
    }
    function goTo(i) { current = (i + slides.length) % slides.length; render(); restart(); }
    function next() { goTo(current + 1); }
    function prev() { goTo(current - 1); }
    function restart() { clearInterval(timer); timer = setInterval(next, 5000); }

    document.getElementById('nextSlide')?.addEventListener('click', next);
    document.getElementById('prevSlide')?.addEventListener('click', prev);
    restart();
})();

// Handle package button clicks to redirect to registration
document.querySelectorAll('.landing-package-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const name = this.dataset.name;
        window.location.href = "{{ route('phs.register') }}?package=" + encodeURIComponent(name);
    });
});
</script>
@endsection

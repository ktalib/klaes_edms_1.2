<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>KLAES Enterprise - Organizational Search History Platform</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>

  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: "#3b82f6",
            "primary-foreground": "#ffffff",
            muted: "#f3f4f6",
            "muted-foreground": "#6b7280",
            border: "#e5e7eb",
            destructive: "#ef4444",
            "destructive-foreground": "#ffffff",
            secondary: "#f1f5f9",
            "secondary-foreground": "#0f172a",
          },
        },
      },
    };
  </script>

  <style>
    .loading-spinner {
      width: 1rem;
      height: 1rem;
      border: 2px solid #e5e7eb;
      border-top: 2px solid #3b82f6;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }

    @keyframes spin {
      0% {
        transform: rotate(0deg);
      }

      100% {
        transform: rotate(360deg);
      }
    }

    .timeline-item {
      border-left: 2px solid #e5e7eb;
      position: relative;
      padding-left: 1.5rem;
      padding-bottom: 1.5rem;
    }

    .timeline-item:last-child {
      padding-bottom: 0;
    }

    .timeline-item::before {
      content: "";
      position: absolute;
      left: -0.5rem;
      top: 0;
      width: 1rem;
      height: 1rem;
      border-radius: 50%;
      background: #3b82f6;
      border: 2px solid white;
      box-shadow: 0 0 0 2px #e5e7eb;
    }

    .timeline-item.completed::before {
      background: #10b981;
    }

    .token-display {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    /* Source tags — mirrors the Legal Search Property Timeline card */
    .phs-source-tag {
      display: inline-flex;
      align-items: center;
      padding: 0.15rem 0.5rem;
      border-radius: 9999px;
      font-size: 0.6rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      white-space: nowrap;
      border: 1px solid transparent;
    }

    .phs-source-tag-file_history_staging { background: #dbeafe; color: #1e40af; border-color: #bfdbfe; }
    .phs-source-tag-CofO_staging         { background: #d1fae5; color: #065f46; border-color: #a7f3d0; }
    .phs-source-tag-pra                  { background: #fef3c7; color: #92400e; border-color: #fde68a; }
    .phs-source-tag-deed_registrations   { background: #ede9fe; color: #5b21b6; border-color: #ddd6fe; }

    .package-card {
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .package-card.selected {
      border: 2px solid #3b82f6 !important;
      background-color: #eff6ff;
    }

    .page {
      transition: opacity 0.3s ease;
    }

    .page-hidden {
      display: none;
    }

    .result-card {
      transition: all 0.3s ease;
    }

    .result-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 20px 25px -12px rgba(0, 0, 0, 0.15);
    }

    /* Slider Styles */
    .slider-container {
      position: relative;
      width: 100%;
      height: 500px;
      overflow: hidden;
      border-radius: 1rem;
    }

    .slide {
      position: absolute;
      width: 100%;
      height: 100%;
      opacity: 0;
      transition: opacity 0.5s ease-in-out;
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
    }

    .slide.active {
      opacity: 1;
    }

    .slide-content {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      background: linear-gradient(to top, rgba(0, 0, 0, 0.8), transparent);
      color: white;
      padding: 2rem;
      text-align: center;
    }

    .slider-btn {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      background: rgba(255, 255, 255, 0.3);
      backdrop-filter: blur(5px);
      color: white;
      border: none;
      padding: 1rem;
      cursor: pointer;
      border-radius: 50%;
      transition: all 0.3s ease;
      z-index: 10;
    }

    .slider-btn:hover {
      background: rgba(255, 255, 255, 0.5);
      transform: translateY(-50%) scale(1.1);
    }

    .slider-btn.prev {
      left: 1rem;
    }

    .slider-btn.next {
      right: 1rem;
    }

    .slider-dots {
      position: absolute;
      bottom: 1rem;
      left: 50%;
      transform: translateX(-50%);
      display: flex;
      gap: 0.5rem;
      z-index: 10;
    }

    .dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.5);
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .dot.active {
      background: white;
      width: 20px;
      border-radius: 10px;
    }

    /* Mobile menu styles */
    .mobile-menu-open {
      max-height: 300px;
      opacity: 1;
      visibility: visible;
    }

    .mobile-menu-closed {
      max-height: 0;
      opacity: 0;
      visibility: hidden;
    }

    /* Responsive slider */
    @media (max-width: 768px) {
      .slider-container {
        height: 300px;
      }
      
      .slide-content h3 {
        font-size: 1.25rem;
      }
      
      .slide-content p {
        font-size: 0.875rem;
      }
    }

    /* Print styles */
    @media print {
      .no-print {
        display: none !important;
      }

      body {
        background: white;
        padding: 0;
        margin: 0;
      }

      .print-container {
        padding: 20px;
      }

      .timeline-item {
        break-inside: avoid;
        page-break-inside: avoid;
      }
    }
  </style>
</head>

<body class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-green-50">
  <!-- ==================== LANDING PAGE ==================== -->
  <div id="landing-page" class="page page-hidden">
    <div class="relative overflow-hidden">
      <div class="absolute inset-0 bg-gradient-to-br from-blue-600/5 via-transparent to-green-600/5"></div>

      <!-- Responsive Navigation -->
      <nav class="relative z-10 bg-white/80 backdrop-blur-md shadow-sm sticky top-0">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="flex justify-between items-center h-16">
            <div class="flex items-center space-x-4">
              <div
                class="w-10 h-10 rounded-lg flex items-center justify-center overflow-hidden bg-gradient-to-r from-blue-600 to-purple-600">
                <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES Logo" class="w-full h-full object-cover"
                  onerror="this.src='https://placehold.co/40x40/3b82f6/white?text=K'" />
              </div>
              <div>
                <h1 class="text-xl font-bold text-gray-900">KLAES</h1>
              </div>
            </div>
            
            <!-- Desktop Menu -->
            <div class="hidden md:flex items-center space-x-4">
              <button id="landing-signin-btn"
                class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50">
                Sign In
              </button>
              <button id="landing-register-btn"
                class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer border-0 bg-blue-600 text-white hover:bg-blue-700">
                Register
              </button>
            </div>
            
            <!-- Mobile Menu Button -->
            <button id="mobile-menu-btn" class="md:hidden p-2 rounded-lg hover:bg-gray-100 transition">
              <i data-lucide="menu" class="w-6 h-6 text-gray-700"></i>
            </button>
          </div>
          
          <!-- Mobile Menu Dropdown -->
          <div id="mobile-menu" class="md:hidden overflow-hidden transition-all duration-300 ease-in-out max-h-0 opacity-0 invisible">
            <div class="py-4 border-t border-gray-200 space-y-3">
              <button id="mobile-landing-signin-btn"
                class="w-full inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50">
                Sign In
              </button>
              <button id="mobile-landing-register-btn"
                class="w-full inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer border-0 bg-blue-600 text-white hover:bg-blue-700">
                Register
              </button>
            </div>
          </div>
        </div>
      </nav>

      <!-- Image Slider Banner - Responsive -->
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="slider-container">
          <div class="slide active" style="background-image: url('imagebg1.jpeg')">
            <div class="slide-content">
              <h3 class="text-2xl font-bold">Official Land Records</h3>
              <p class="text-lg">
                Access verified property information directly from government
                database
              </p>
            </div>
          </div>
          <div class="slide" style="background-image: url('imagebg2.jpeg')">
            <div class="slide-content">
              <h3 class="text-2xl font-bold">
                Bank & Legal Institution Search
              </h3>
              <p class="text-lg">
                Trusted by leading banks and law firms across Nigeria
              </p>
            </div>
          </div>
          <div class="slide" style="background-image: url('imagebg3.jpeg')">
            <div class="slide-content">
              <h3 class="text-2xl font-bold">Token-Based System</h3>
              <p class="text-lg">
                Flexible packages starting from 2,000 tokens
              </p>
            </div>
          </div>
          <div class="slide" style="background-image: url('imagebg4.jpeg')">
            <div class="slide-content">
              <h3 class="text-2xl font-bold">Instant Search Results</h3>
              <p class="text-lg">
                Get official search slips instantly with just 1 token
              </p>
            </div>
          </div>
          <button class="slider-btn prev" id="prevSlide">â®</button>
          <button class="slider-btn next" id="nextSlide">â¯</button>
          <div class="slider-dots" id="sliderDots"></div>
        </div>
      </div>

      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-16">
        <div class="text-center">
          <div
            class="inline-flex items-center rounded-full text-xs font-medium px-3 py-1 bg-blue-100 text-blue-700 mb-6">
            <i data-lucide="crown" class="w-4 h-4 mr-2"></i> Official
            Government Platform
          </div>
          <h1 class="text-3xl sm:text-5xl md:text-6xl lg:text-7xl font-extrabold text-gray-900 mb-6 leading-tight">
            Kano State Ministry of Land
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-green-600">and Physical
              Planning Property History Search (PHS)
              Portal</span>
          </h1>
          <p class="text-base sm:text-lg md:text-xl text-gray-600 max-w-3xl mx-auto mb-10">
            Secure, token-based Search History for banks, law firms, and
            corporate institutions.
          </p>
          <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <button id="hero-signin-btn"
              class="inline-flex items-center justify-center px-6 sm:px-8 py-3 sm:py-4 text-base sm:text-lg font-semibold rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition-all shadow-lg hover:shadow-xl">
              Get Started
              <i data-lucide="arrow-right" class="ml-2 w-5 h-5"></i>
            </button>
          </div>
        </div>
      </div>

      <div class="bg-white py-12 sm:py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="text-center mb-8 sm:mb-12">
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-4">
              Why Choose KLAES Enterprise?
            </h2>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 sm:gap-8">
            <div class="text-center p-4 sm:p-6">
              <div class="w-16 h-16 sm:w-20 sm:h-20 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-4 sm:mb-5">
                <i data-lucide="coins" class="w-8 h-8 sm:w-10 sm:h-10 text-blue-600"></i>
              </div>
              <h3 class="text-lg sm:text-xl font-semibold mb-2 sm:mb-3">Token-Based System</h3>
              <p class="text-sm sm:text-base text-gray-600">
                Pay-as-you-go with flexible token packages. Each search
                consumes 1 token.
              </p>
            </div>
            <div class="text-center p-4 sm:p-6">
              <div class="w-16 h-16 sm:w-20 sm:h-20 bg-green-100 rounded-2xl flex items-center justify-center mx-auto mb-4 sm:mb-5">
                <i data-lucide="shield" class="w-8 h-8 sm:w-10 sm:h-10 text-green-600"></i>
              </div>
              <h3 class="text-lg sm:text-xl font-semibold mb-2 sm:mb-3">Official & Secure</h3>
              <p class="text-sm sm:text-base text-gray-600">
                Government-verified records with official search slips.
              </p>
            </div>
            <div class="text-center p-4 sm:p-6">
              <div class="w-16 h-16 sm:w-20 sm:h-20 bg-purple-100 rounded-2xl flex items-center justify-center mx-auto mb-4 sm:mb-5">
                <i data-lucide="clock" class="w-8 h-8 sm:w-10 sm:h-10 text-purple-600"></i>
              </div>
              <h3 class="text-lg sm:text-xl font-semibold mb-2 sm:mb-3">Instant Results</h3>
              <p class="text-sm sm:text-base text-gray-600">
                Real-time searches with downloadable official slips.
              </p>
            </div>
          </div>
        </div>
      </div>

      <div class="py-12 sm:py-20 bg-gradient-to-br from-gray-50 to-blue-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="text-center mb-8 sm:mb-12">
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-4">
              Flexible Token Packages
            </h2>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
            <div class="bg-white rounded-2xl shadow-lg p-6 sm:p-8 text-center">
              <h3 class="text-xl sm:text-2xl font-bold text-green-600 mb-2">Starter</h3>
              <div class="text-3xl sm:text-4xl font-bold mb-2">₦50,000</div>
              <p class="text-gray-500 mb-4">2,000 Tokens</p>
              <button
                class="landing-package-btn w-full py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition"
                data-tokens="2000" data-price="50000" data-name="Starter">
                Get Started
              </button>
            </div>
            <div class="bg-white rounded-2xl shadow-lg p-6 sm:p-8 text-center border-2 border-blue-500 relative">
              <div
                class="absolute -top-3 left-1/2 transform -translate-x-1/2 px-4 py-1 bg-gradient-to-r from-blue-500 to-pink-500 text-white text-xs font-semibold rounded-full whitespace-nowrap">
                POPULAR
              </div>
              <h3 class="text-xl sm:text-2xl font-bold text-blue-600 mb-2">
                Professional
              </h3>
              <div class="text-3xl sm:text-4xl font-bold mb-2">₦100,000</div>
              <p class="text-gray-500 mb-4">5,000 Tokens</p>
              <button
                class="landing-package-btn w-full py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                data-tokens="5000" data-price="100000" data-name="Professional">
                Get Started
              </button>
            </div>
            <div class="bg-white rounded-2xl shadow-lg p-6 sm:p-8 text-center sm:col-span-2 lg:col-span-1">
              <h3 class="text-xl sm:text-2xl font-bold text-purple-600 mb-2">
                Enterprise
              </h3>
              <div class="text-3xl sm:text-4xl font-bold mb-2">₦180,000</div>
              <p class="text-gray-500 mb-4">10,000 Tokens</p>
              <button
                class="landing-package-btn w-full py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition"
                data-tokens="10000" data-price="180000" data-name="Enterprise">
                Get Started
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="bg-gradient-to-r from-blue-600 to-purple-700 py-12 sm:py-16">
        <div class="max-w-4xl mx-auto text-center px-4">
          <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-white mb-4">
            Ready to Transform Your Search History Process?
          </h2>
          <button id="cta-signin-btn"
            class="inline-flex items-center px-6 sm:px-8 py-3 sm:py-4 bg-white text-blue-600 rounded-lg font-semibold hover:shadow-xl transition text-sm sm:text-base">
            Start Your Journey
            <i data-lucide="arrow-right" class="ml-2 w-5 h-5"></i>
          </button>
        </div>
      </div>

      <footer class="bg-gray-900 text-white py-12 sm:py-12 mt-16 no-print">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 sm:gap-10">
            <div>
              <div class="flex items-center space-x-4">
                <div
                  class="w-10 h-10 rounded-lg flex items-center justify-center overflow-hidden bg-gradient-to-r from-blue-600 to-purple-600">
                  <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES Logo" class="w-full h-full object-cover"
                    onerror="this.src='https://placehold.co/40x40/3b82f6/white?text=K'" />
                </div>
                <div>
                  <h1 class="text-xl font-bold text-white">KLAES</h1>
                </div>
              </div>
              <p class="text-gray-400 text-sm leading-relaxed mt-4">
                Official government platform for Search History services and
                land record verification in Kano State.
              </p>
            </div>
            <div>
              <h3 class="text-base font-semibold uppercase tracking-wider text-gray-300 mb-4">
                Services
              </h3>
              <ul class="space-y-2 text-sm">
                <li>
                  <a href="#" class="text-gray-400 hover:text-white transition-colors">Search History</a>
                </li>
                <li>
                  <a href="#" class="text-gray-400 hover:text-white transition-colors">Property Verification</a>
                </li>
                <li>
                  <a href="#" class="text-gray-400 hover:text-white transition-colors">Title Investigation</a>
                </li>
                <li>
                  <a href="#" class="text-gray-400 hover:text-white transition-colors">Due Diligence Reports</a>
                </li>
              </ul>
            </div>
            <div>
              <h3 class="text-base font-semibold uppercase tracking-wider text-gray-300 mb-4">
                Support
              </h3>
              <ul class="space-y-2 text-sm">
                <li>
                  <a href="#" class="text-gray-400 hover:text-white transition-colors">Help Center</a>
                </li>
                <li>
                  <a href="#" class="text-gray-400 hover:text-white transition-colors">Contact Support</a>
                </li>
                <li>
                  <a href="#" class="text-gray-400 hover:text-white transition-colors">API Documentation</a>
                </li>
                <li>
                  <a href="#" class="text-gray-400 hover:text-white transition-colors">System Status</a>
                </li>
              </ul>
            </div>
            <div>
              <h3 class="text-base font-semibold uppercase tracking-wider text-gray-300 mb-4">
                Contact Us
              </h3>
              <div class="space-y-3 text-sm">
                <div class="flex items-center space-x-2.5 text-gray-400">
                  <i data-lucide="mail" class="w-4 h-4 flex-shrink-0"></i><a href="mailto:support@klas.gov.ng"
                    class="hover:text-white transition-colors">support@klas.gov.ng</a>
                </div>
                <div class="flex items-center space-x-2.5 text-gray-400">
                  <i data-lucide="phone" class="w-4 h-4 flex-shrink-0"></i><a href="tel:+23491234567"
                    class="hover:text-white transition-colors">+234 (0) 9 123 4567</a>
                </div>
                <div class="flex items-start space-x-2.5 text-gray-400">
                  <i data-lucide="map-pin" class="w-4 h-4 flex-shrink-0 mt-0.5"></i><span>KLAES Headquarters, Kano,
                    Nigeria</span>
                </div>
              </div>
            </div>
          </div>
          <div class="border-t border-gray-800 mt-8 sm:mt-10 pt-8 text-center text-gray-500 text-xs sm:text-sm">
            <p>
              &copy; 2026 Kano State Land Administration System (KLAES). All
              rights reserved.
            </p>
            <p class="mt-1">
              Empowering Kano with transparent and efficient land
              administration.
            </p>
          </div>
        </div>
      </footer>
    </div>
  </div>

  <!-- ==================== SIGN IN PAGE ==================== -->
  <div id="signin-page" class="page page-hidden">
    <div
      class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 bg-gradient-to-br from-blue-50 via-white to-green-50">
      <div class="max-w-md w-full space-y-8">
        <div class="text-center">
          <div class="flex justify-center">
            <div class="w-16 h-16 bg-blue-600 rounded-2xl flex items-center justify-center">
              <i data-lucide="building" class="h-8 w-8 text-white"></i>
            </div>
          </div>
          <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
            Welcome Back
          </h2>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-6 sm:p-8">
          <div class="mb-6 p-3 bg-blue-50 rounded-lg border border-blue-200">
            <div class="flex items-start gap-2">
              <i data-lucide="info" class="w-5 h-5 text-blue-600 mt-0.5"></i>
              <div class="text-sm text-blue-800">
                <p class="font-medium">Demo Accounts:</p>
                <p class="text-xs mt-1">
                  Bank: Musa Trust Bank / Password: password
                </p>
                <p class="text-xs">
                  Law Firm: Musa Chambers / Password: password
                </p>
              </div>
            </div>
          </div>
          <form id="signin-form" class="space-y-6">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Institution Name</label>
              <div class="relative">
                <i data-lucide="building-2"
                  class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5"></i>
                <input type="text" id="institution-name" required
                  class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="Enter your institution name" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
              <div class="relative">
                <i data-lucide="lock"
                  class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5"></i>
                <input type="password" id="password" required
                  class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="Enter your password" />
              </div>
            </div>
            <button type="submit"
              class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 transition">
              Sign In
            </button>
          </form>
          <div class="mt-6 text-center">
            <p class="text-sm text-gray-600">
              Don't have an account?
              <button id="switch-to-register" class="font-medium text-blue-600 hover:text-blue-500">
                Register here
              </button>
            </p>
          </div>
          <div class="mt-4 pt-4 border-t border-gray-200">
            <button id="back-to-landing"
              class="w-full flex items-center justify-center gap-2 py-2 px-4 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
              <i data-lucide="arrow-left" class="w-4 h-4"></i>Back to Home
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ==================== REGISTER PAGE ==================== -->
  <div id="register-page" class="page page-hidden">
    <div
      class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 bg-gradient-to-br from-blue-50 via-white to-green-50">
      <div class="max-w-md w-full space-y-8">
        <div class="text-center">
          <div class="flex justify-center">
            <div class="w-16 h-16 bg-green-600 rounded-2xl flex items-center justify-center">
              <i data-lucide="users" class="h-8 w-8 text-white"></i>
            </div>
          </div>
          <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
            Register Your Institution
          </h2>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-6 sm:p-8">
          <form id="register-form" class="space-y-5">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Institution Type</label>
              <select id="institution-type" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                <option value="bank">Bank / Financial Institution</option>
                <option value="law_firm">Law Firm</option>
                <option value="corporate">Corporate Organization</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Institution Name *</label>
              <input type="text" id="reg-institution-name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
              <input type="email" id="reg-email" required class="w-full px-3 py-2 border border-gray-300 rounded-lg" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number *</label>
              <input type="tel" id="reg-phone" required class="w-full px-3 py-2 border border-gray-300 rounded-lg" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
              <input type="password" id="reg-password" required class="w-full px-3 py-2 border border-gray-300 rounded-lg" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password *</label>
              <input type="password" id="reg-confirm-password" required class="w-full px-3 py-2 border border-gray-300 rounded-lg" />
            </div>
            <label class="flex items-center">
              <input type="checkbox" required class="rounded border-gray-300 text-blue-600" />
              <span class="ml-2 text-sm text-gray-600">I agree to the Terms of Service and Privacy Policy</span>
            </label>
            <button type="submit"
              class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 transition">
              Register Institution
            </button>
          </form>
          <div class="mt-6 text-center">
            <p class="text-sm text-gray-600">
              Already have an account?
              <button id="switch-to-signin" class="font-medium text-blue-600 hover:text-blue-500">
                Sign In
              </button>
            </p>
          </div>
          <div class="mt-4 pt-4 border-t border-gray-200">
            <button id="back-to-landing-from-register"
              class="w-full flex items-center justify-center gap-2 py-2 px-4 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
              <i data-lucide="arrow-left" class="w-4 h-4"></i>Back to Home
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ==================== DASHBOARD with Dynamic Organization Branding ==================== -->
  <div id="dashboard-page" class="page lg:pl-64">
    <aside class="hidden lg:flex fixed inset-y-0 left-0 z-50 w-64 flex-col border-r border-gray-200 bg-white no-print">
      <div class="h-16 px-5 flex items-center gap-3 border-b border-gray-100">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-r from-blue-600 to-purple-600 flex items-center justify-center text-white shadow-sm">
          <i data-lucide="building-2" class="w-5 h-5"></i>
        </div>
        <div class="min-w-0">
          <p class="text-sm font-bold text-gray-900 truncate" id="sidebar-dashboard-org-name">Search History Portal</p>
          <p class="text-xs text-gray-500 truncate">PHS Portal</p>
        </div>
      </div>
      <nav class="flex-1 px-3 py-5 space-y-1">
        <a href="{{ route('phs.dashboard') }}" id="sidebar-dashboard-link" data-view="dashboard" class="phs-nav-link flex items-center gap-3 rounded-xl bg-blue-50 px-3 py-2.5 text-sm font-semibold text-blue-700">
          <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
          Dashboard
        </a>
        <a href="#search-query" id="sidebar-search-link" data-view="search" class="phs-nav-link flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900">
          <i data-lucide="search" class="w-4 h-4"></i>
          Search History
        </a>
        @if ($member->isSuperAdmin())
          <a href="{{ route('phs.org.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900">
            <i data-lucide="settings" class="w-4 h-4"></i>
            Organization
          </a>
        @endif
      </nav>
      <div class="border-t border-gray-100 p-4 space-y-3">
        <div class="rounded-xl bg-gray-50 px-3 py-3" style="display:none">
          <p class="text-xs text-gray-500">Available Tokens</p>
          <p class="text-2xl font-bold text-blue-600" id="sidebar-token-display">0</p>
        </div>
        <button id="sidebar-dashboard-logout-btn" type="button" class="w-full flex items-center justify-center gap-2 rounded-xl border border-gray-300 px-3 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
          <i data-lucide="log-out" class="w-4 h-4"></i>
          Logout
        </button>
      </div>
    </aside>
    <!-- Responsive Dashboard Header with Collapsible Mobile Menu -->
    <header class="bg-white shadow-sm border-b sticky top-0 z-40 no-print">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
          <div class="flex items-center space-x-4">
            <div id="dashboard-logo"
              class="w-10 h-10 rounded-lg flex items-center justify-center overflow-hidden bg-gradient-to-r from-blue-600 to-purple-600">
              <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="Organization Logo" class="w-full h-full object-cover"
                onerror="this.style.display='none';this.parentElement.innerHTML='<i data-lucide=\'building\' class=\'h-6 w-6 text-white\'></i>'" />
            </div>
            <div>
              <h1 id="dashboard-org-name" class="text-lg sm:text-xl font-bold text-gray-900">
                KLAES
              </h1>
              <p class="text-xs sm:text-sm text-gray-600">
                Organizational Search History Platform
              </p>
            </div>
          </div>
          
          <!-- Desktop Menu -->
          <div class="hidden md:flex items-center space-x-4">
            <div class="text-right mr-4" style="display:none">
              <p class="text-xs text-gray-500">Available Tokens</p>
              <p class="text-lg sm:text-xl font-bold text-blue-600" id="token-display-header">
                0
              </p>
            </div>
            {{-- <div class="flex items-center space-x-2">
              <div
                class="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white">
                <span id="institution-initial">B</span>
              </div>
              <span class="hidden lg:inline text-sm font-medium" id="institution-name">Bank Name</span>
            </div> --}}
            <button id="dashboard-logout-btn"
              class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50">
              Logout
            </button>
            @if ($member->isSuperAdmin())
            <button id="open-user-management-btn"
              class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 bg-purple-600 text-white hover:bg-purple-700 transition ml-2"
              onclick="window.location.href='{{ route('phs.org.index') }}'">
              <i data-lucide="settings" class="w-4 h-4 mr-2"></i>Manage
              Organization
            </button>
            @endif
          </div>
          
          <!-- Mobile Menu Button -->
          <button id="dashboard-mobile-menu-btn" class="md:hidden p-2 rounded-lg hover:bg-gray-100 transition">
            <i data-lucide="menu" class="w-6 h-6 text-gray-700"></i>
          </button>
        </div>
        
        <!-- Mobile Menu Dropdown -->
        <div id="dashboard-mobile-menu" class="md:hidden overflow-hidden transition-all duration-300 ease-in-out max-h-0 opacity-0 invisible">
          <div class="py-4 border-t border-gray-200 space-y-4">
            <div class="flex items-center justify-between">
              <div style="display:none">
                <p class="text-xs text-gray-500">Available Tokens</p>
                <p class="text-xl font-bold text-blue-600" id="mobile-token-display">0</p>
              </div>
              <div class="flex items-center space-x-2">
                <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white">
                  <span id="mobile-institution-initial">B</span>
                </div>
                <span class="text-sm font-medium" id="mobile-institution-name">Bank Name</span>
              </div>
            </div>
            <div class="space-y-2">
              <button id="mobile-dashboard-logout-btn"
                class="w-full inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50">
                Logout
              </button>
              @if ($member->isSuperAdmin())
              <button id="mobile-open-user-management-btn"
                class="w-full inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 bg-purple-600 text-white hover:bg-purple-700 transition"
                onclick="window.location.href='{{ route('phs.org.index') }}'">
                <i data-lucide="settings" class="w-4 h-4 mr-2"></i>Manage Organization
              </button>
              @endif
            </div>
          </div>
        </div>
      </div>
    </header>

    <!-- Dynamic Organization Banner - Responsive -->
    <div id="org-dashboard-banner"
      class="max-w-7xl mx-auto h-48 sm:h-64 md:h-80 bg-gradient-to-r from-blue-600 to-purple-600 flex items-center justify-center relative overflow-hidden">
      <div class="absolute inset-0 bg-black/20"></div>
      <div class="relative z-10 text-center px-4">
        <p id="banner-text-dashboard" class="text-white text-xl sm:text-2xl font-bold">
         Welcome to Your Kano State<br>
          Ministry of Land and Physical Planning <br>
          Property History Search Portal
        </p>
        <p id="banner-subtext" class="text-white/80 text-xs sm:text-sm mt-2">
          Secure, fast, and reliable property records
        </p>
      </div>
    </div>

    <main class="flex-1">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">

        <!-- ==================== DASHBOARD OVERVIEW ==================== -->
        <div id="dashboard-view">
          @if ($stats['token_balance'] < 100)
          <div class="mb-5 flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3">
            <i data-lucide="alert-triangle" class="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0"></i>
            <div class="text-sm text-amber-800">
              <p class="font-semibold">Low token balance</p>
              <p>You have <strong>{{ number_format($stats['token_balance']) }}</strong> tokens left.
                @if ($member->isSuperAdmin())
                  <a href="{{ route('phs.org.index') }}?tab=subscription" class="font-semibold underline hover:text-amber-900">Top up now</a> to avoid interruptions.
                @else
                  Please contact your organization administrator to top up.
                @endif
              </p>
            </div>
          </div>
          @endif

          <!-- Stat Cards -->
          <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5 pb-6 sm:pb-8">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 flex items-center gap-4">
              <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0">
                <i data-lucide="coins" class="w-6 h-6 text-blue-600"></i>
              </div>
              <div class="min-w-0">
                <p class="text-xs text-gray-500">Available Tokens</p>
                <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['token_balance']) }}</p>
              </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 flex items-center gap-4">
              <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center flex-shrink-0">
                <i data-lucide="search" class="w-6 h-6 text-green-600"></i>
              </div>
              <div class="min-w-0">
                <p class="text-xs text-gray-500">Total Searches</p>
                <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['total_searches']) }}</p>
              </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 flex items-center gap-4">
              <div class="w-12 h-12 rounded-xl bg-purple-50 flex items-center justify-center flex-shrink-0">
                <i data-lucide="calendar" class="w-6 h-6 text-purple-600"></i>
              </div>
              <div class="min-w-0">
                <p class="text-xs text-gray-500">Searches This Month</p>
                <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['searches_this_month']) }}</p>
              </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 flex items-center gap-4">
              <div class="w-12 h-12 rounded-xl bg-orange-50 flex items-center justify-center flex-shrink-0">
                <i data-lucide="users" class="w-6 h-6 text-orange-600"></i>
              </div>
              <div class="min-w-0">
                <p class="text-xs text-gray-500">Team Members</p>
                <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['member_count']) }}</p>
              </div>
            </div>
          </section>

          <!-- Quick action + Recent searches -->
          <section class="pb-8">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
              <div class="flex items-center justify-between p-4 sm:p-6 border-b border-gray-100">
                <h2 class="text-lg font-semibold text-gray-900">Recent Searches</h2>
                <button id="dashboard-new-search-btn"
                  class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition">
                  <i data-lucide="search" class="w-4 h-4"></i> Search Now
                </button>
              </div>
              @if ($recentSearches->isEmpty())
                <div class="p-10 text-center">
                  <i data-lucide="file-search" class="w-12 h-12 text-gray-300 mx-auto mb-4"></i>
                  <p class="text-gray-500">No searches yet. Run your first Property History Search.</p>
                </div>
              @else
                <div class="divide-y divide-gray-100">
                  @foreach ($recentSearches as $log)
                    <div class="flex items-center justify-between gap-4 px-4 sm:px-6 py-3.5">
                      <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">{{ $log->file_number ?: $log->query }}</p>
                        <p class="text-xs text-gray-500 truncate">
                          {{ $log->member->name ?? 'Member' }} ·
                          {{ $log->result_count }} {{ \Illuminate\Support\Str::plural('result', $log->result_count) }}
                        </p>
                      </div>
                      <div class="text-right flex-shrink-0">
                        <p class="text-xs text-gray-500">{{ $log->created_at?->diffForHumans() }}</p>
                        <p class="text-[11px] text-gray-400 font-mono">{{ $log->reference_no }}</p>
                      </div>
                    </div>
                  @endforeach
                </div>
              @endif
            </div>
          </section>
        </div>

        <!-- ==================== SEARCH HISTORY VIEW ==================== -->
        <div id="search-view" class="hidden">
        <!-- Search Section - Responsive -->
        <section class="pb-6 sm:pb-8 no-print">
          <div class="bg-white rounded-xl shadow-lg border border-gray-200">
            <div class="p-4 sm:p-6 md:p-8">
              <h2 class="text-lg sm:text-xl font-semibold mb-4">
                Property History Search
              </h2>
              <div class="flex flex-col sm:flex-row items-center gap-3">
                <div class="flex-1 relative w-full">
                  <i data-lucide="search"
                    class="absolute left-3.5 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4 sm:w-5 sm:h-5"></i>
                  <input type="text" id="search-query" placeholder="File number, KANGIS No., Owner, Plot No..."
                    class="w-full pl-10 sm:pl-11 pr-4 py-2 sm:py-3 text-sm sm:text-base h-10 sm:h-12 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20" />
                </div>
                <button id="search-btn"
                  class="inline-flex items-center justify-center rounded-lg font-medium px-5 sm:px-7 py-2 sm:py-3 h-10 sm:h-12 bg-blue-600 text-white hover:bg-blue-700 transition text-sm sm:text-base w-full sm:w-auto">
                  <i data-lucide="search" class="w-4 h-4 sm:w-5 sm:h-5 mr-2"></i>1 Token per Search
                </button>
              </div>
              <p class="text-xs text-gray-500 mt-2.5">
                Examples: "COM-RES-2021-78", "KN12345", "John Doe"
              </p>
            </div>
          </div>
        </section>

        <!-- Loading -->
        <section id="loading-section" class="hidden pb-12">
          <div class="flex justify-center items-center py-20">
            <div class="loading-spinner"></div>
          </div>
        </section>

        <!-- Search Results -->
        <section id="results-section" class="hidden pb-12">
          <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
            <h2 class="text-xl sm:text-2xl font-semibold text-gray-800">
              Search Results
              <span id="results-count" class="text-gray-500 font-normal">(0 found)</span>
            </h2>
          </div>
          <div id="no-results" class="bg-white rounded-xl shadow-lg hidden">
            <div class="p-10 text-center">
              <i data-lucide="file-search" class="w-16 h-16 text-gray-300 mx-auto mb-6"></i>
              <h3 class="text-2xl font-semibold text-gray-700 mb-3">
                No Results Found
              </h3>
              <button id="try-new-search" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg">
                Try a New Search
              </button>
            </div>
          </div>
          <div id="cards-results" class="grid grid-cols-1 md:grid-cols-2 gap-5"></div>
        </section>

        <!-- File Details Section -->
        <section id="file-details-section" class="hidden pb-12">
          <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
            <div class="p-4 sm:p-6 border-b border-gray-200">
              <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between flex-wrap gap-4">
                <div>
                  <h2 class="text-xl sm:text-2xl font-bold">Search History Slip</h2>
                  <p class="text-sm sm:text-base text-gray-500">
                    Official search result for file
                    <span id="file-reference" class="font-medium text-gray-700">--</span>
                  </p>
                </div>
                <div class="flex items-center gap-2 no-print w-full sm:w-auto">
                  <button id="back-to-dashboard-btn"
                    class="flex-1 sm:flex-initial inline-flex items-center justify-center px-3 sm:px-4 py-2 border border-gray-300 rounded-lg text-xs sm:text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i>Back
                  </button>
                  <button id="print-slip-btn"
                    class="flex-1 sm:flex-initial inline-flex items-center justify-center px-3 sm:px-4 py-2 bg-black text-white rounded-lg text-xs sm:text-sm font-medium hover:bg-black/90">
                    <i data-lucide="printer" class="w-4 h-4 mr-2"></i>Print
                  </button>
                </div>
              </div>
            </div>
            <div class="p-4 sm:p-6">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 mb-6 sm:mb-8">
                <div class="bg-gray-50 rounded-lg p-3 sm:p-4">
                  <h3 class="font-semibold text-gray-700 mb-3 flex items-center text-sm sm:text-base">
                    <i data-lucide="home" class="w-4 h-4 mr-2"></i>Property Information
                  </h3>
                  <div class="space-y-2">
                    <div class="flex justify-between py-1"><span class="text-gray-500 text-xs sm:text-sm">File Number:</span><span class="font-medium text-xs sm:text-sm" id="file-number-value">--</span></div>
                    <div class="flex justify-between py-1"><span class="text-gray-500 text-xs sm:text-sm">File Title:</span><span class="font-semibold text-xs sm:text-sm" id="file-title-value">--</span></div>
                    <div class="flex justify-between py-1"><span class="text-gray-500 text-xs sm:text-sm">Plot No:</span><span class="font-medium text-xs sm:text-sm" id="plot-number-value">--</span></div>
                    <div class="flex justify-between py-1"><span class="text-gray-500 text-xs sm:text-sm">Size:</span><span class="font-medium text-xs sm:text-sm" id="size-value">--</span></div>
                    <div class="flex justify-between py-1"><span class="text-gray-500 text-xs sm:text-sm">TP No:</span><span class="font-medium text-xs sm:text-sm" id="tpno-value">--</span></div>
                    <div class="flex justify-between py-1"><span class="text-gray-500 text-xs sm:text-sm">District:</span><span class="font-medium text-xs sm:text-sm" id="district-value">--</span></div>
                    <div class="flex justify-between py-1"><span class="text-gray-500 text-xs sm:text-sm">LGA:</span><span class="font-medium text-xs sm:text-sm" id="lga-value">--</span></div>
                    <div class="flex justify-between py-1"><span class="text-gray-500 text-xs sm:text-sm">Land Use:</span><span class="font-medium text-xs sm:text-sm" id="property-type-value">--</span></div>
                    <div class="flex justify-between py-1"><span class="text-gray-500 text-xs sm:text-sm">Last Transaction:</span><span class="font-medium text-xs sm:text-sm" id="last-transaction-value">--</span></div>
                    <div class="flex justify-between py-1"><span class="text-gray-500 text-xs sm:text-sm">Status:</span><span class="font-medium text-green-600 text-xs sm:text-sm" id="status-value">Active</span></div>
                  </div>
                </div>
                <div class="bg-gray-50 rounded-lg p-3 sm:p-4">
                  <h3 class="font-semibold text-gray-700 mb-3 flex items-center text-sm sm:text-base">
                    <i data-lucide="building-2" class="w-4 h-4 mr-2"></i>Search Details
                  </h3>
                  <div class="space-y-2">
                    <div class="flex justify-between py-1"><span class="text-gray-500 text-xs sm:text-sm">Institution:</span><span class="font-medium text-xs sm:text-sm" id="requesting-institution">--</span></div>
                    <div class="flex justify-between py-1"><span class="text-gray-500 text-xs sm:text-sm">Search Date:</span><span class="font-medium text-xs sm:text-sm" id="search-date">--</span></div>
                    <div class="flex justify-between py-1"><span class="text-gray-500 text-xs sm:text-sm">Reference No.:</span><span class="font-medium text-xs sm:text-sm" id="reference-no">--</span></div>
                    <div class="flex justify-between py-1"><span class="text-gray-500 text-xs sm:text-sm">Tokens Used:</span><span class="font-medium text-xs sm:text-sm">1</span></div>
                  </div>
                </div>
              </div>
              <div class="flex flex-col gap-3 mb-4">
                <h3 class="font-semibold text-gray-700 flex items-center text-sm sm:text-base">
                  <i data-lucide="calendar" class="w-4 h-4 mr-2"></i>Property Timeline
                  <span id="timeline-total-count" class="ml-2 inline-flex items-center justify-center min-w-[1.5rem] h-[1.5rem] rounded-full bg-blue-100 text-blue-800 text-xs font-bold px-2">0</span>
                </h3>
                <div id="timeline-source-badges" class="flex flex-wrap gap-2"></div>
              </div>
              <div id="timeline-container" class="space-y-0">
                <div class="text-center py-8 text-gray-500 text-sm sm:text-base">
                  Select a file to view transaction history
                </div>
              </div>
            </div>
          </div>
        </section>

        <iframe id="print-frame" style="
              position: absolute;
              width: 0;
              height: 0;
              border: none;
              visibility: hidden;
            "></iframe>
        </div><!-- /#search-view -->
      </div>
    </main>

    <footer class="bg-gray-900 text-white py-8 sm:py-12 mt-8 sm:mt-16 no-print">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 sm:gap-10">
          <div>
            <div class="flex items-center space-x-4">
              <div class="w-10 h-10 rounded-lg flex items-center justify-center overflow-hidden bg-gradient-to-r from-blue-600 to-purple-600">
                <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES Logo" class="w-full h-full object-cover"
                  onerror="this.src='https://placehold.co/40x40/3b82f6/white?text=K'" />
              </div>
              <div>
                <h1 class="text-xl font-bold text-white">KLAES</h1>
              </div>
            </div>
            <p class="text-gray-400 text-sm leading-relaxed mt-4">
              Official government platform for Search History services and land
              record verification in Kano State.
            </p>
          </div>
          <div>
            <h3 class="text-base font-semibold uppercase tracking-wider text-gray-300 mb-4">
              Services
            </h3>
            <ul class="space-y-2 text-sm">
              <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Search History</a></li>
              <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Property Verification</a></li>
              <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Title Investigation</a></li>
              <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Due Diligence Reports</a></li>
            </ul>
          </div>
          <div>
            <h3 class="text-base font-semibold uppercase tracking-wider text-gray-300 mb-4">
              Support
            </h3>
            <ul class="space-y-2 text-sm">
              <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Help Center</a></li>
              <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Contact Support</a></li>
              <li><a href="#" class="text-gray-400 hover:text-white transition-colors">API Documentation</a></li>
              <li><a href="#" class="text-gray-400 hover:text-white transition-colors">System Status</a></li>
            </ul>
          </div>
          <div>
            <h3 class="text-base font-semibold uppercase tracking-wider text-gray-300 mb-4">
              Contact Us
            </h3>
            <div class="space-y-3 text-sm">
              <div class="flex items-center space-x-2.5 text-gray-400">
                <i data-lucide="mail" class="w-4 h-4 flex-shrink-0"></i><a href="mailto:support@klas.gov.ng"
                  class="hover:text-white transition-colors">support@klas.gov.ng</a>
              </div>
              <div class="flex items-center space-x-2.5 text-gray-400">
                <i data-lucide="phone" class="w-4 h-4 flex-shrink-0"></i><a href="tel:+23491234567"
                  class="hover:text-white transition-colors">+234 (0) 9 123 4567</a>
              </div>
              <div class="flex items-start space-x-2.5 text-gray-400">
                <i data-lucide="map-pin" class="w-4 h-4 flex-shrink-0 mt-0.5"></i><span>KLAES Headquarters, Kano, Nigeria</span>
              </div>
            </div>
          </div>
        </div>
        <div class="border-t border-gray-800 mt-8 sm:mt-10 pt-8 text-center text-gray-500 text-xs sm:text-sm">
          <p>&copy; 2026 Kano State Land Administration System (KLAES). All rights reserved.</p>
          <p class="mt-1">Empowering Kano with transparent and efficient land administration.</p>
        </div>
      </div>
    </footer>
  </div>

  <script>
    window.PHS_PORTAL = {
      institution: @json($institution),
      member: @json($member),
      packages: @json($packages),
      routes: {
        search: "{{ route('phs.search') }}",
        logout: "{{ route('phs.logout') }}",
        organization: "{{ route('phs.org.index') }}",
        payOnline: "{{ route('phs.tokens.payOnline') }}",
        requestInvoice: "{{ route('phs.tokens.requestInvoice') }}",
        transactions: "{{ route('phs.tokens.transactions') }}",
        print: "{{ route('phs.slip.print') }}"
      },
      assets: {
        logo: "{{ asset('assets/logo/logo.png') }}",
        fallbackLogo: "{{ asset('assets/logo/las.jpg') }}",
        organizationLogo: "{{ $institution->logo_path ? asset('storage/' . $institution->logo_path) : '' }}",
        organizationBanner: "{{ $institution->banner_path ? asset('storage/' . $institution->banner_path) : '' }}"
      }
    };
  </script>
  <script src="{{ asset('js/phs/portal.js') }}"></script>
</body>
</html>

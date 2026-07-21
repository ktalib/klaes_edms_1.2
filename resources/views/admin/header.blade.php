<link rel="stylesheet" href="{{ asset('css/admin-header.css') }}">

<div class="p-6 bg-white border-b border-gray-200" data-header-root
  data-auto-logout-enabled="{{ config('session.auto_logout_enabled', false) ? 'true' : 'false' }}"
  data-auto-logout-timeout="{{ (int) config('session.auto_logout_timeout', 180) }}"
  data-auto-logout-warning="{{ (int) config('session.auto_logout_warning', 30) }}" data-home-url="{{ url('/') }}">
  <div class="flex justify-between items-center">
    <div>
      <h1 class="text-2xl font-bold">{{ $PageTitle ?? '' }}</h1>
      <p class="text-gray-500">{{ $PageDescription ?? '' }}</p>
    </div>
    <div class="flex items-center space-x-4">
      <!-- Back Button -->
      <button type="button" onclick="window.history.back()"
        class="flex items-center px-3 py-2 border border-gray-300 rounded-md bg-gray-50 hover:bg-gray-100 text-gray-700"
        title="Go Back">
        <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i>
        Back
      </button>
      <div class="relative">
        <i data-lucide="search" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4"></i>
        <input type="text" placeholder="Search applications..."
          class="pl-10 pr-4 py-2 border border-gray-200 rounded-md w-64 focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
      <div class="relative" id="file-tracker-header-notifications" data-sound-url="{{ asset('sound/sound.wav') }}"
        data-endpoint="{{ route('file-tracker-dashboard.notifications') }}"
        data-fallback-endpoint="{{ url('api/file-tracker-dashboard/notifications') }}"
        data-icon-url="{{ asset('assets/logo/logo.png') }}" data-poll-interval="20000">
        <button type="button"
          class="relative flex items-center justify-center w-10 h-10 rounded-full border border-gray-200 bg-white hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500/40 transition"
          data-notification-toggle aria-haspopup="true" aria-expanded="false"
          aria-controls="file-tracker-notification-panel">
          <i data-lucide="bell" class="w-5 h-5 text-gray-600"></i>
          <span id="header-notification-badge"
            class="absolute -top-1 -right-1 bg-orange-500 text-white font-semibold rounded-full w-5 h-5 flex items-center justify-center text-[10px]"
            style="display: none;">0</span>
        </button>

        <div id="file-tracker-notification-panel"
          class="absolute right-0 mt-2 w-80 rounded-lg shadow-xl bg-white ring-1 ring-black ring-opacity-5 z-50 hidden"
          data-notification-panel>
          <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
            <div class="flex items-center gap-3">
              <img src="{{ asset('assets/logo/logo.png') }}" alt="Notification logo"
                class="h-8 w-8 rounded-full border border-gray-200 bg-white object-contain" loading="lazy">
              <div>
                <p class="text-sm font-semibold text-gray-800">Notifications</p>
                <p class="text-xs text-gray-500">Latest updates across the system</p>
              </div>
            </div>
            <span id="header-notification-total"
              class="inline-flex items-center justify-center px-2 py-0.5 text-xs font-semibold text-white bg-orange-500 rounded-full">0</span>
          </div>

          <div id="header-notification-loading" class="flex items-center justify-center py-6">
            <svg class="animate-spin h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none"
              viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
          </div>

          <div id="header-notification-list-container" class="max-h-80 overflow-y-auto hidden">
            <ul id="header-notification-list" class="divide-y divide-gray-100"></ul>
            <div id="header-notification-empty" class="p-4 text-sm text-gray-500 text-center">
              You're all caught up.
            </div>
            <div id="header-notification-error" class="hidden p-4 text-sm text-red-600 text-center"></div>
          </div>

          <div class="border-t border-gray-100 px-4 py-2 bg-gray-50 flex items-center justify-between">
            <a href="{{ route('notifications.index') }}"
              class="text-sm font-medium text-blue-600 hover:text-blue-700 hover:underline flex items-center gap-1">
              View all
              <i data-lucide="arrow-up-right" class="w-4 h-4"></i>
            </a>
            <button type="button" id="header-notification-refresh"
              class="text-xs text-gray-500 hover:text-gray-700 flex items-center gap-1">
              <i data-lucide="refresh-cw" class="w-4 h-4"></i>
              Refresh
            </button>
          </div>
        </div>
      </div>

      @php
        $userId = auth()->id();
        $profileUnreadCount = $userId ? \App\Models\Notification::forUser($userId)->where('is_read', false)->count() : 0;
      @endphp
      <!-- User Profile Dropdown -->
      <div class="relative" data-user-profile-dropdown>
        <!-- Auto Logout Status Indicator -->
        <div id="autoLogoutStatus"
          class="hidden absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full w-6 h-6 flex items-center justify-center z-10"
          title="Auto logout disabled">
          <i data-lucide="clock" class="w-3 h-3"></i>
        </div>

        <button class="flex items-center focus:outline-none" type="button" data-dropdown-toggle aria-haspopup="true"
          aria-expanded="false">
          <div
            class="w-10 h-10 rounded-full overflow-hidden border-2 border-gray-200 flex items-center justify-center bg-gray-100">
            @if(Auth::user()->profile)
              <img  src="{{ asset('storage') . '/' . auth()->user()->profile }}"
                class="w-full h-full object-cover">
            @else
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-500" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
            @endif
          </div>
          <span class="ml-2 text-gray-700 hidden md:block">{{ Auth::user()->first_name ?? Auth::user()->name }}</span>
          <svg xmlns="http://www.w3.org/2000/svg" class="ml-1 h-5 w-5 text-gray-400" viewBox="0 0 20 20"
            fill="currentColor">
            <path fill-rule="evenodd"
              d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
              clip-rule="evenodd" />
          </svg>
        </button>

        <!-- Dropdown Menu -->
        <div
          class="absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 divide-y divide-gray-100 z-50 hidden"
          data-dropdown-menu>

          <div class="px-4 py-3">
            <p class="text-sm leading-5">Signed in as</p>
            <p class="text-sm font-medium leading-5 text-gray-900 truncate">{{ Auth::user()->email }}</p>
          </div>

          <div class="py-1">
            <a href="{{ route('profile.index') }}"
              class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-gray-500" viewBox="0 0 20 20"
                fill="currentColor">
                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
              </svg>
              My Profile
            </a>
          </div>

          <div class="py-1">
            <a href="{{ route('notifications.index') }}"
              class="flex items-center justify-between px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
              <span class="flex items-center">
                <i data-lucide="bell-ring" class="h-5 w-5 mr-3 text-amber-500"></i>
                Notifications
              </span>
              <span
                class="inline-flex items-center justify-center rounded-full bg-blue-100 text-blue-700 text-xs font-semibold px-2 py-0.5">
                {{ $profileUnreadCount }}
              </span>
            </a>
          </div>

          <div class="py-1">
            <form method="POST" action="{{ route('logout') }}" id="autoLogoutForm">
              @csrf
              <button type="submit" class="flex w-full items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-3 text-gray-500" viewBox="0 0 20 20"
                  fill="currentColor">
                  <path fill-rule="evenodd"
                    d="M3 3a1 1 0 00-1 1v12a1 1 0 001 1h12a1 1 0 001-1V7.414a1 1 0 00-.293-.707L11.414 2.414A1 1 0 0010.707 2H4a1 1 0 00-1 1zm9 2.5V5a.5.5 0 01.5-.5h2a.5.5 0 01.5.5v2a.5.5 0 01-.5.5h-2a.5.5 0 01-.5-.5V5.5zm0 7V10a.5.5 0 01.5-.5h2a.5.5 0 01.5.5v2a.5.5 0 01-.5.5h-2a.5.5 0 01-.5-.5v-2.5z"
                    clip-rule="evenodd" />
                </svg>
                Logout
              </button>
            </form>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- Tailwind CDN must come BEFORE our configuration -->
<script src="https://cdn.tailwindcss.com"></script>
<script src="{{ asset('js/header-tailwind.js') }}"></script>
<!-- Welcome Popup -->
<div id="welcomePopup"
  class="popup-overlay welcome-popup fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50"
  data-username="{{ Auth::user()->first_name ?? Auth::user()->name ?? Auth::user()->email ?? 'User' }}"
  data-mark-url="{{ route('markWelcomePopupShown') }}"
  data-should-show="{{ session()->pull('show_welcome_popup', false) ? 'true' : 'false' }}" data-force-show="false"
  data-test-enabled="false">
  <div class="popup-content bg-white rounded-xl shadow-2xl w-11/12 max-w-md mx-auto overflow-hidden">
    <!-- Brand Logos Header -->
    @php
      $defaultLogo = asset('assets/logo/logo.png');
      $storageLogoPng = null;
      $storageLogoJpeg = null;
      if (class_exists(\Illuminate\Support\Facades\Storage::class)) {
        if (\Illuminate\Support\Facades\Storage::disk('public')->exists('upload/logo/logo.png')) {
          $storageLogoPng = asset('storage/upload/logo/logo.png');
        }
        if (\Illuminate\Support\Facades\Storage::disk('public')->exists('uploads/logo.jpeg')) {
          $storageLogoJpeg = asset('storage/uploads/logo.jpeg');
        }
      }
      $primaryLogo = $storageLogoPng ?? $defaultLogo;
      $secondaryLogo = $storageLogoJpeg ?? $primaryLogo;
    @endphp
    <div class="flex justify-center items-center space-x-6 pt-4">
      <img src="{{ $primaryLogo }}" alt="KLAES Logo" class="h-12">
      <img src="{{ $secondaryLogo }}" alt="LAAD-Sys Logo" class="h-12">
    </div>


    <!-- Popup Content -->
    <div class="p-6">
      <div class="mb-8 text-center">
        <div class="flex justify-center mb-4">
          <div class="w-16 h-1 rounded-full welcome-popup-divider"></div>
        </div>

        <p class="text-gray-600 mb-4">We're excited to have you here!</p>

        <div class="flash-text py-3 px-4 rounded-lg welcome-popup-banner">
          <h3 class="text-2xl md:text-3xl font-extrabold text-gray-900">
            WELCOME TO KLAES
          </h3>
          <p class="text-xl md:text-2xl font-bold mt-2">
            Dear <span id="username" class="text-brand-green-fallback">USERNAME</span>
          </p>
        </div>
      </div>

      <div class="space-y-3">
        <button id="continueBtn"
          class="w-full bg-brand-green bg-brand-green-fallback hover-brand-green-fallback text-white font-semibold py-3 px-4 rounded-lg focus:outline-none focus:ring-2 focus:ring-opacity-50 transition welcome-popup-cta">
          Continue to Site
        </button>
      </div>
    </div>
  </div>
</div>

<script src="{{ asset('js/push-notification-center.js') }}" defer></script>
<script src="{{ asset('js/file-tracker-notifications.js') }}" defer></script>
<script src="{{ asset('js/welcome-popup.js') }}" defer></script>
<script src="{{ asset('js/auto-logout.js') }}" defer></script>
<script src="{{ asset('js/user-profile-dropdown.js') }}" defer></script>
@php
  $flashSessionKey = 'notificationFlash-' . (session('last_login_time') ?? time());
@endphp
<script>
  window.NotificationFlashConfig = {
    endpoint: "{{ route('notifications.api.list') }}",
    settingsEndpoint: "{{ route('notifications.api.settings.show') }}",
    limit: 5,
    sessionKey: "{{ $flashSessionKey }}"
  };
</script>
<script src="{{ asset('js/notification-flash.js') }}" defer></script>

@php
  if (!session()->has('last_login_time')) {
    session(['last_login_time' => time()]);
  }
@endphp


<!--
  Global DD/MM/YYYY date display.

  Native <input type="date"> always renders in the browser/OS locale (e.g. MM/DD/YYYY)
  and cannot be reformatted with CSS/JS — the previous ::before mask never worked because
  browsers do not render pseudo-elements on <input> (a replaced element).

  Instead we enhance every native date input with flatpickr:
    - The VISIBLE field shows DD/MM/YYYY (altFormat).
    - The ORIGINAL input keeps its Y-m-d value (dateFormat), so form submissions and any
      JS that reads the value stay unchanged for the backend.
  Pages that init their own pickers use type="text" inputs, so this only touches type="date".
-->
 
 
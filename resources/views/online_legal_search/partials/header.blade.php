<nav class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <a href="{{ route('ols.landing') }}" class="flex items-center gap-3">
                    <img src="{{ asset('assets/logo/klaes-logo.png') }}" alt="KLAES" class="h-10 w-auto">
                    <span class="text-lg font-bold text-gray-900 dark:text-white">Online Legal Search</span>
                </a>
            </div>
            <div class="flex items-center gap-4">
                @auth('online_ls')
                    <a href="{{ route('ols.dashboard') }}" class="text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">
                        {{ Auth::guard('online_ls')->user()->name }}
                    </a>
                    <form method="POST" action="{{ route('ols.logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-sm font-medium text-red-600 dark:text-red-400 hover:text-red-800">
                            Logout
                        </button>
                    </form>
                @else
                    <a href="{{ route('ols.login') }}" class="text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">
                        Sign In
                    </a>
                    <a href="{{ route('ols.register') }}" class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700">
                        Create Account
                    </a>
                @endauth
            </div>
        </div>
    </div>
</nav>

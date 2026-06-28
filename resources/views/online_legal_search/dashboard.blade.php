@extends('online_legal_search.layout')

@section('title', 'Dashboard')

@section('body')
<div class="min-h-screen bg-slate-100 dark:bg-gray-900">
    <!-- Dashboard Header -->
    <nav class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center gap-4">
                    <img src="{{ asset('assets/logo/klaes-logo.png') }}" alt="KLAES" class="h-10 w-auto">
                    <span class="text-lg font-bold text-gray-900 dark:text-white">Online Legal Search</span>
                </div>
                <div class="flex items-center gap-4">
                    <a href="{{ route('ols.history') }}" class="text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-cyan-600">
                        <i data-lucide="history" class="inline h-4 w-4 mr-1"></i> History
                    </a>
                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ $user->name }}</span>
                    <form method="POST" action="{{ route('ols.logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-sm font-medium text-red-600 dark:text-red-400 hover:text-red-800">
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if(session('status'))
            <div class="mb-6 rounded-lg border border-green-200 dark:border-green-700 bg-green-50 dark:bg-green-900/30 p-4">
                <p class="text-sm text-green-700 dark:text-green-400">{{ session('status') }}</p>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Search Form -->
            <div class="lg:col-span-2">
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        <i data-lucide="search" class="inline h-5 w-5 mr-2 text-cyan-600"></i>
                        Conduct Legal Search
                    </h2>

                    <form id="searchForm" class="space-y-4">
                        @csrf

                        <div>
                            <label for="file_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">File Number</label>
                            <input
                                id="file_number" type="text" name="file_number" required
                                class="mt-2 block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-200"
                                placeholder="e.g. KANGIS/COM/2023/00001">
                        </div>

                        <div>
                            <label for="district" class="block text-sm font-medium text-gray-700 dark:text-gray-300">District <span class="text-gray-400">(optional)</span></label>
                            <select id="district" name="district" class="mt-2 block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-gray-900 dark:text-gray-100 focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-200">
                                <option value="">All Districts</option>
                                @foreach($districtOptions as $district)
                                    <option value="{{ $district }}">{{ $district }}</option>
                                @endforeach
                            </select>
                        </div>

                        <button type="submit" id="searchBtn" class="w-full rounded-lg bg-cyan-600 px-4 py-2.5 font-semibold text-white shadow hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed">
                            <i data-lucide="search" class="inline h-4 w-4 mr-2"></i>
                            Search
                        </button>
                    </form>

                    <!-- Results Area -->
                    <div id="searchResults" class="mt-6 hidden">
                        <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Search Results</h3>
                            <div id="resultsContent" class="space-y-3"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Account Info -->
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Account</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Name</span>
                            <span class="text-gray-900 dark:text-white font-medium">{{ $user->name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Email</span>
                            <span class="text-gray-900 dark:text-white font-medium">{{ $user->email }}</span>
                        </div>
                        @if($user->organization)
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Organization</span>
                            <span class="text-gray-900 dark:text-white font-medium">{{ $user->organization }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Recent Searches -->
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Recent Searches</h3>
                    @if($recentSearches->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">No searches yet.</p>
                    @else
                        <div class="space-y-2">
                            @foreach($recentSearches as $search)
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-900 dark:text-white font-medium">{{ $search->file_number ?? 'N/A' }}</span>
                                    <span class="text-gray-500 dark:text-gray-400">{{ \Carbon\Carbon::parse($search->paid_at)->diffForHumans() }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.getElementById('searchForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const btn = document.getElementById('searchBtn');
        const resultsDiv = document.getElementById('searchResults');
        const contentDiv = document.getElementById('resultsContent');

        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader-2" class="inline h-4 w-4 mr-2 animate-spin"></i> Searching...';
        if (window.lucide) window.lucide.createIcons();

        fetch('{{ route("ols.search") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                file_number: document.getElementById('file_number').value,
                district: document.getElementById('district').value,
            }),
        })
        .then(r => r.json())
        .then(data => {
            resultsDiv.classList.remove('hidden');
            if (data && data.length > 0) {
                contentDiv.innerHTML = data.map(item => `
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">${item.file_number || 'N/A'}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">${item.property_details || ''}</p>
                    </div>
                `).join('');
            } else {
                contentDiv.innerHTML = '<p class="text-sm text-gray-500 dark:text-gray-400">No results found for this file number.</p>';
            }
        })
        .catch(err => {
            resultsDiv.classList.remove('hidden');
            contentDiv.innerHTML = '<p class="text-sm text-red-600">An error occurred. Please try again.</p>';
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="search" class="inline h-4 w-4 mr-2"></i> Search';
            if (window.lucide) window.lucide.createIcons();
        });
    });
</script>
@endpush
@endsection

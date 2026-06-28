@extends('online_legal_search.layout')

@section('title', 'Search History')

@section('body')
<div class="min-h-screen bg-slate-100 dark:bg-gray-900">
    <nav class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center gap-4">
                    <img src="{{ asset('assets/logo/klaes-logo.png') }}" alt="KLAES" class="h-10 w-auto">
                    <span class="text-lg font-bold text-gray-900 dark:text-white">Online Legal Search</span>
                </div>
                <div class="flex items-center gap-4">
                    <a href="{{ route('ols.dashboard') }}" class="text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-cyan-600">
                        &larr; Back to Dashboard
                    </a>
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

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                <i data-lucide="history" class="inline h-5 w-5 mr-2 text-cyan-600"></i>
                Search History
            </h2>

            @if($searches->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No search history yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-left py-3 px-2 text-gray-500 dark:text-gray-400 font-medium">File Number</th>
                                <th class="text-left py-3 px-2 text-gray-500 dark:text-gray-400 font-medium">Results</th>
                                <th class="text-left py-3 px-2 text-gray-500 dark:text-gray-400 font-medium">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($searches as $search)
                                <tr class="border-b border-gray-100 dark:border-gray-700/50">
                                    <td class="py-3 px-2 text-gray-900 dark:text-white font-medium">{{ $search->file_number ?: 'N/A' }}</td>
                                    <td class="py-3 px-2 text-gray-600 dark:text-gray-400">{{ $search->results_count }}</td>
                                    <td class="py-3 px-2 text-gray-500 dark:text-gray-400">{{ \Carbon\Carbon::parse($search->created_at)->format('M d, Y H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    {{ $searches->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

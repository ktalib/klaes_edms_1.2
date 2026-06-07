@extends('layouts.app')

@section('page-title')
    File Indexing Activity Log
@endsection

@section('content')
    <div class="flex-1 overflow-auto">
        @include('admin.header')

        <div class="p-6" x-data="{ open: {} }">
            <!-- Page heading -->
            <div class="mb-6">
                <div class="flex items-center gap-3 mb-2">
                    <i data-lucide="activity" class="h-5 w-5 text-indigo-600"></i>
                    <h2 class="text-xl font-semibold text-gray-800">File Indexing Activity Log</h2>
                </div>
                <p class="text-gray-600">
                    Per-user file indexing productivity. Weights &mdash;
                    Files: <span class="font-semibold">{{ $weights['files'] }}</span>,
                    Indexed: <span class="font-semibold">{{ $weights['indexed'] }}</span>,
                    Transactions: <span class="font-semibold">{{ $weights['transactions'] }}</span>.
                </p>
            </div>

            <!-- Filters -->
            <form method="GET" action="{{ route('fileindexing.activity-log') }}"
                class="bg-white rounded-lg border border-gray-200 p-4 mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">User</label>
                    <select name="user"
                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="">All users</option>
                        @foreach($users as $id => $name)
                            <option value="{{ $id }}" {{ (string) $filters['user'] === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">From</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] }}"
                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">To</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] }}"
                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit"
                        class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        <i data-lucide="filter" class="h-4 w-4"></i> Apply
                    </button>
                    <a href="{{ route('fileindexing.activity-log') }}"
                        class="inline-flex items-center gap-2 rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        Reset
                    </a>
                </div>
            </form>

            <!-- Summary cards -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                @php
                    $cards = [
                        ['Files', $totals['files'], 'files'],
                        ['Indexing Done', $totals['indexed'], 'file-check-2'],
                      
                      
                        ['Related File Numbers', $totals['records'], 'hash'],
  ['Transactions', $totals['transactions'], 'receipt-text'],
                          ['Total Weight', $totals['weight'], 'scale'],
                    ];
                @endphp
                @foreach($cards as [$label, $value, $icon])
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                        <div class="flex items-center gap-2 text-gray-500 mb-1">
                            <i data-lucide="{{ $icon }}" class="h-4 w-4"></i>
                            <span class="text-xs uppercase tracking-wide">{{ $label }}</span>
                        </div>
                        <div class="text-2xl font-bold text-gray-800">{{ number_format($value) }}</div>
                    </div>
                @endforeach
            </div>

            <!-- Grouped table -->
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-gray-600">
                            <th class="px-4 py-3 font-semibold">File Number</th>
                            <th class="px-4 py-3 font-semibold text-right">Number of Files</th>
                            <th class="px-4 py-3 font-semibold text-right">Indexing Done</th>
                            <th class="px-4 py-3 font-semibold text-right">File History / Transactions</th>
                            <th class="px-4 py-3 font-semibold text-right">Total Weight</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($groups as $i => $group)
                            <!-- User group header -->
                            <tr class="bg-indigo-50/60 cursor-pointer hover:bg-indigo-50"
                                @click="open[{{ $i }}] = !open[{{ $i }}]">
                                <td class="px-4 py-3 font-semibold text-gray-800">
                                    <span class="inline-flex items-center gap-2">
                                        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform"
                                            :class="open[{{ $i }}] ? 'rotate-90' : ''"></i>
                                        <i data-lucide="user" class="h-4 w-4 text-indigo-600"></i>
                                        {{ $group['user'] }}
                                        <span class="text-xs font-normal text-gray-500">({{ count($group['rows']) }} files)</span>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($group['files']) }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($group['indexed']) }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($group['transactions']) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-indigo-700">{{ number_format($group['weight']) }}</td>
                            </tr>
                            <!-- File rows (nested) -->
                            @foreach($group['rows'] as $row)
                                <tr x-show="open[{{ $i }}]" x-cloak class="hover:bg-gray-50">
                                    <td class="px-4 py-2 pl-12 text-gray-700">
                                        <span class="font-medium">{{ $row['file_number'] }}</span>
                                        @if($row['file_title'])
                                            <span class="block text-xs text-gray-400 truncate max-w-md">{{ $row['file_title'] }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right text-gray-700">{{ number_format($row['number_of_files']) }}</td>
                                    <td class="px-4 py-2 text-right text-gray-700">{{ number_format($row['indexing_done']) }}</td>
                                    <td class="px-4 py-2 text-right text-gray-700">{{ number_format($row['transactions']) }}</td>
                                    <td class="px-4 py-2 text-right text-gray-700">{{ number_format($row['weight']) }}</td>
                                </tr>
                            @endforeach
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-gray-400">
                                    No file indexing activity found for the selected filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if(count($groups))
                        <tfoot class="bg-gray-50 font-semibold text-gray-800">
                            <tr>
                                <td class="px-4 py-3">Grand Total</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['files']) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['indexed']) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['transactions']) }}</td>
                                <td class="px-4 py-3 text-right text-indigo-700">{{ number_format($totals['weight']) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    <style>[x-cloak]{display:none !important;}</style>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                window.lucide.createIcons();
            }
        });
    </script>
@endsection

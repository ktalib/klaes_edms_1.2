@extends('layouts.app')

@section('page-title')
    {{ __('ToT Intelligence Dashboard') }}
@endsection

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle' => 'ToT Dashboard',
        'PageDescription' => 'Every Transfer of Title on record'
    ])

    <div class="p-6 space-y-8 max-w-7xl mx-auto">
        <!-- Header & Actions -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-900"></h1>
                <p class="text-slate-500 mt-1">Every Transfer of Title recorded in the deeds register</p>
            </div>
            <div class="flex gap-3">
                {{-- Match OP — the same job one file at a time, and where the records
                     this dashboard lists come from. Kept beside the bulk actions so an
                     officer who arrives here to find "All Caught Up" can go and match
                     the files nobody has reached yet, instead of leaving empty-handed. --}}
                {{-- `from=tot` marks the origin so the DMS sidebar entry does NOT light
                     up: the officer came from here, and a sidebar highlighting a
                     section they did not open reads as having navigated somewhere
                     they did not go. See edms.blade.php, which checks for it. --}}
                <a href="{{ route('land-recommendations.create') }}?match-op&from=tot"
                   class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-white border border-amber-300 text-amber-700 font-semibold shadow-sm hover:bg-amber-50 transition">
                    <i data-lucide="git-merge" class="h-5 w-5"></i>
                    <span>Match OP</span>
                </a>
                {{-- Both bulk actions are disabled. Transfers are written one file at a
                     time through Match OP, where the officer sees the file's whole
                     chain and confirms the two names before anything is recorded — a
                     dealing on somebody's title is not something to generate in bulk
                     off a staging list. The buttons are kept, disabled, rather than
                     removed: they say plainly that the capability exists and is shut,
                     and the JS that binds them still finds them. --}}
                <button id="btnIgnore" disabled
                        title="Archiving in bulk is disabled — use Match OP to work these files one at a time."
                        class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-white border border-slate-200 text-slate-400 font-semibold shadow-sm opacity-60 cursor-not-allowed">
                    <i data-lucide="eye-off" class="h-5 w-5"></i>
                    <span>Archive Selected</span>
                </button>
                <button id="btnGenerate" disabled
                        title="Bulk ToT generation is disabled — use Match OP, which shows the file's chain and confirms both names before writing."
                        class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-slate-300 text-white font-semibold shadow opacity-60 cursor-not-allowed">
                    <i data-lucide="zap" class="h-5 w-5"></i>
                    <span>Execute ToT Generation</span>
                </button>
            </div>
        </div>

        <!-- Stats Grid -->
        {{-- Counted off the same query the table lists from, so the cards and the rows
             can never disagree about what a Transfer of Title is. --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <a href="{{ route('maintenance.tot.index') }}"
               class="bg-white p-6 rounded-xl shadow-sm border {{ $filter ? 'border-slate-100' : 'border-blue-200 ring-1 ring-blue-100' }} flex items-center gap-4 hover:border-blue-200 transition">
                <div class="h-12 w-12 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center">
                    <i data-lucide="file-symlink" class="h-6 w-6"></i>
                </div>
                <div>
                    <p class="text-sm text-slate-500 font-medium uppercase tracking-wider">Transfers of Title</p>
                    <h3 class="text-2xl font-bold text-slate-800">{{ number_format($total) }}</h3>
                </div>
            </a>

            <a href="{{ route('maintenance.tot.index', ['filter' => 'system']) }}"
               class="bg-white p-6 rounded-xl shadow-sm border {{ $filter === 'system' ? 'border-blue-200 ring-1 ring-blue-100' : 'border-slate-100' }} flex items-center gap-4 hover:border-blue-200 transition">
                <div class="h-12 w-12 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center">
                    <i data-lucide="git-merge" class="h-6 w-6"></i>
                </div>
                <div>
                    <p class="text-sm text-slate-500 font-medium uppercase tracking-wider">System Generated</p>
                    <h3 class="text-2xl font-bold text-slate-800">{{ number_format($systemGenerated) }}</h3>
                    <p class="text-[11px] text-slate-400 mt-0.5">Written by Match OP</p>
                </div>
            </a>

            <a href="{{ route('maintenance.tot.index', ['filter' => 'captured']) }}"
               class="bg-white p-6 rounded-xl shadow-sm border {{ $filter === 'captured' ? 'border-blue-200 ring-1 ring-blue-100' : 'border-slate-100' }} flex items-center gap-4 hover:border-blue-200 transition">
                <div class="h-12 w-12 bg-amber-50 text-amber-600 rounded-lg flex items-center justify-center">
                    <i data-lucide="edit-3" class="h-6 w-6"></i>
                </div>
                <div>
                    <p class="text-sm text-slate-500 font-medium uppercase tracking-wider">Captured</p>
                    <h3 class="text-2xl font-bold text-slate-800">{{ number_format($captured) }}</h3>
                    <p class="text-[11px] text-slate-400 mt-0.5">Keyed from a document</p>
                </div>
            </a>
        </div>

        <!-- Data Table Section -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                <h2 class="text-lg font-semibold text-slate-800">
                    Transfers of Title
                    @if($filter === 'system') <span class="text-sm font-normal text-slate-500">— system generated</span>
                    @elseif($filter === 'captured') <span class="text-sm font-normal text-slate-500">— captured</span>
                    @endif
                </h2>
                <div class="flex items-center gap-3">
                    {{-- 1,300+ rows: a register this size is unusable without a way in. --}}
                    <form method="GET" action="{{ route('maintenance.tot.index') }}" class="flex items-center gap-2">
                        @if($filter)<input type="hidden" name="filter" value="{{ $filter }}">@endif
                        <div class="relative">
                            <i data-lucide="search" class="h-4 w-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                            <input type="text" name="q" value="{{ $search }}" placeholder="File number or party name"
                                   class="pl-9 pr-3 py-2 w-64 rounded-lg border border-slate-200 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <button type="submit" class="px-3 py-2 rounded-lg bg-slate-800 text-white text-sm font-semibold hover:bg-slate-900 transition">Search</button>
                        @if($search !== '')
                            <a href="{{ route('maintenance.tot.index', $filter ? ['filter' => $filter] : []) }}"
                               class="px-3 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm hover:bg-slate-50 transition">Clear</a>
                        @endif
                    </form>
                    <span class="text-sm text-slate-600 whitespace-nowrap">{{ $records->count() }} of {{ number_format($records->total()) }}</span>
                </div>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto -mx-6 px-6">
                    <table id="totStagingTable" class="w-full text-left border-collapse" style="min-width: 1000px;">
                        <thead>
                            <tr class="text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200">
                                <th class="pb-4 font-semibold whitespace-nowrap">File No</th>
                                <th class="pb-4 font-semibold whitespace-nowrap">Transferred From</th>
                                <th class="pb-4 font-semibold whitespace-nowrap">Transferred To</th>
                                <th class="pb-4 font-semibold whitespace-nowrap">Instrument</th>
                                <th class="pb-4 font-semibold whitespace-nowrap">Date</th>
                                <th class="pb-4 font-semibold whitespace-nowrap">Reg. Particulars</th>
                                <th class="pb-4 font-semibold whitespace-nowrap">Origin</th>
                            </tr>
                        </thead>
                        <tbody class="text-slate-600 text-sm">
                            @forelse($records->items() as $record)
                            @php
                                $fileNo = $record->mlsFNo ?: ($record->fileno ?: ($record->kangisFileNo ?: $record->NewKANGISFileno));
                                $isSystem = trim((string) $record->system_source) === 'OPHOLDERMATCH';
                            @endphp
                            <tr class="hover:bg-slate-50 transition-colors border-b border-slate-100 align-top">
                                <td class="py-4">
                                    <span class="font-mono text-xs font-bold text-blue-700 bg-blue-50 px-2 py-0.5 rounded-full inline-block whitespace-nowrap">{{ $fileNo ?: '—' }}</span>
                                    @if($record->prop_id)
                                        <div class="text-[10px] text-slate-400 mt-1">prop {{ $record->prop_id }}</div>
                                    @endif
                                </td>
                                <td class="py-4">
                                    <span class="text-slate-800 block truncate max-w-[220px]" title="{{ $record->party_1 }}">{{ $record->party_1 ?: '—' }}</span>
                                </td>
                                <td class="py-4">
                                    <div class="flex items-start gap-1.5">
                                        <i data-lucide="move-right" class="h-4 w-4 text-slate-300 shrink-0 mt-0.5"></i>
                                        <span class="font-semibold text-slate-900 block truncate max-w-[220px]" title="{{ $record->party_2 }}">{{ $record->party_2 ?: '—' }}</span>
                                    </div>
                                </td>
                                <td class="py-4 whitespace-nowrap">{{ $record->transaction_type ?: $record->instrument_type }}</td>
                                <td class="py-4 whitespace-nowrap text-slate-500">{{ $record->transaction_date ?: '—' }}</td>
                                <td class="py-4 whitespace-nowrap font-mono text-xs text-slate-500">{{ $record->regNo ?: '—' }}</td>
                                <td class="py-4 whitespace-nowrap">
                                    @if($isSystem)
                                        {{-- Reconstructed by Match OP, not read off a document. Worth saying
                                             on the row: it carries no registration particulars and never had any. --}}
                                        <span class="px-2 py-0.5 rounded-full bg-blue-600 text-white text-[10px] font-black uppercase">System Generated</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 text-[10px] font-black uppercase">{{ $record->source ?: 'Captured' }}</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="py-20 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <div class="h-16 w-16 bg-slate-50 text-slate-400 rounded-full flex items-center justify-center">
                                            <i data-lucide="file-search" class="h-8 w-8"></i>
                                        </div>
                                        <h3 class="text-xl font-bold text-slate-800">Nothing to show</h3>
                                        <p class="text-slate-500">
                                            @if($search !== '')
                                                No Transfer of Title matches “{{ $search }}”.
                                            @else
                                                No Transfer of Title is recorded yet.
                                            @endif
                                        </p>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div class="flex justify-between items-center mt-6">
            <p class="text-sm text-slate-600">Total: {{ number_format($records->total()) }} transfer(s) of title</p>
            <div>
                {{ $records->links() }}
            </div>
        </div>

@push('scripts')
<script>
{{-- The bulk generate / archive handlers lived here and have been removed with the
     work queue they drove: this page is now a register of what was written, and the
     two buttons above are disabled. Transfers are written one file at a time through
     Match OP, which confirms both names against the file's chain before recording a
     dealing on somebody's title.

     The POST endpoints (maintenance.tot.generate / .ignore) and the artisan command
     tot:generate-from-staging still exist and still work — nothing here closes them. --}}
document.addEventListener('DOMContentLoaded', function () {
    if (window.lucide) window.lucide.createIcons();
});
</script>
@endpush
@endsection

@extends('layouts.app')

@section('content')
@php
    use App\Services\RegrantTermService;

    // An overdue span of decades is a records-cleanup case, not an urgent one; colour by
    // how far past the term the title has run.
    $overdueTone = fn ($years) => $years >= 30
        ? 'bg-red-50 text-red-700 border-red-200'
        : ($years >= 10 ? 'bg-orange-50 text-orange-700 border-orange-200'
                        : 'bg-amber-50 text-amber-700 border-amber-200');

    $dash = fn ($v) => filled($v) ? e($v) : '<span class="text-slate-300">—</span>';
@endphp

<div class="flex-1 overflow-auto bg-slate-50/60">
@include('admin.header', [
    'PageTitle'       => 'Re-grant Management',
    'PageDescription' => 'Every Re-grant on record, and the files whose statutory term has already run out.',
])

<div class="p-4 sm:p-6 max-w-[1600px] mx-auto">

    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-4 mb-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                <i data-lucide="refresh-cw" class="w-5 h-5 text-indigo-600"></i>
                Re-grant Management
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Every Re-grant on record, and the files whose statutory term has already run out.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <div class="px-3 py-2 rounded-lg bg-white border border-slate-200 text-center min-w-[92px]">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">On Record</p>
                <p class="text-lg font-bold text-slate-800">{{ number_format($stats['register_total']) }}</p>
            </div>
            <div class="px-3 py-2 rounded-lg bg-white border border-slate-200 text-center min-w-[92px]">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Pending</p>
                <p class="text-lg font-bold text-amber-600">{{ number_format($stats['register_pending']) }}</p>
            </div>
            <div class="px-3 py-2 rounded-lg bg-white border border-slate-200 text-center min-w-[92px]">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">To Be Re-granted</p>
                <p class="text-lg font-bold text-red-600">{{ number_format($stats['due_total']) }}</p>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="flex items-center gap-1 border-b border-slate-200 mb-4">
        @php
            $tabBase   = 'px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px transition flex items-center gap-2';
            $tabOn     = 'border-indigo-600 text-indigo-700';
            $tabOff    = 'border-transparent text-slate-500 hover:text-slate-700';
        @endphp
        <a href="{{ route('regrant.index') }}?tab=register"
           class="{{ $tabBase }} {{ $tab === 'register' ? $tabOn : $tabOff }}">
            <i data-lucide="list" class="w-4 h-4"></i>
            Re-grant Register
            <span class="px-1.5 py-0.5 rounded text-[11px] bg-slate-100 text-slate-600">{{ number_format($stats['register_total']) }}</span>
        </a>
        <a href="{{ route('regrant.index') }}?tab=due"
           class="{{ $tabBase }} {{ $tab === 'due' ? $tabOn : $tabOff }}">
            <i data-lucide="alarm-clock" class="w-4 h-4"></i>
            To Be Re-granted
            <span class="px-1.5 py-0.5 rounded text-[11px] bg-red-100 text-red-700">{{ number_format($stats['due_total']) }}</span>
        </a>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('regrant.index') }}" class="flex flex-wrap items-end gap-3 mb-4">
        <input type="hidden" name="tab" value="{{ $tab }}">

        <div class="flex-1 min-w-[220px]">
            <label class="block text-[11px] font-semibold text-slate-500 mb-1">Search</label>
            <input type="text" name="search" value="{{ $search }}"
                   placeholder="{{ $tab === 'due' ? 'File number, holder, land use…' : 'File number, title, holder…' }}"
                   class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
        </div>

        @if ($tab === 'due')
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Instrument</label>
                <select name="source" class="px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white">
                    <option value="">All</option>
                    <option value="cofo" @selected($filters['source'] === 'cofo')>CofO ({{ number_format($stats['due_cofo']) }})</option>
                    <option value="rofo" @selected($filters['source'] === 'rofo')>RofO ({{ number_format($stats['due_rofo']) }})</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Term</label>
                <select name="term" class="px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white">
                    <option value="">All</option>
                    <option value="99" @selected($filters['term'] === '99')>99 yrs — Res / Agric</option>
                    <option value="40" @selected($filters['term'] === '40')>40 yrs — Com / Ind</option>
                </select>
            </div>
        @endif

        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg flex items-center gap-1.5">
            <i data-lucide="search" class="w-4 h-4"></i> Filter
        </button>
        @if ($search || $filters['source'] || $filters['term'])
            <a href="{{ route('regrant.index') }}?tab={{ $tab }}" class="px-3 py-2 text-sm text-slate-500 hover:text-slate-700">Reset</a>
        @endif
    </form>

    {{-- ─────────────── Tab: Re-grant Register ─────────────── --}}
    @if ($tab === 'register')
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr class="text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                            <th class="px-4 py-3">File No</th>
                            <th class="px-4 py-3">Re-granted From</th>
                            <th class="px-4 py-3">File Title / Holder</th>
                            <th class="px-4 py-3">Plot</th>
                            <th class="px-4 py-3">District</th>
                            <th class="px-4 py-3">LGA</th>
                            <th class="px-4 py-3">Land Use</th>
                            <th class="px-4 py-3">Date Created</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($records as $r)
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-4 py-3 font-semibold text-slate-800 whitespace-nowrap">{{ $r->file_no }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if (filled($r->see_fileno))
                                        <span class="inline-flex items-center gap-1 text-indigo-700">
                                            <i data-lucide="corner-down-right" class="w-3.5 h-3.5"></i>{{ $r->see_fileno }}
                                        </span>
                                    @else
                                        <span class="text-slate-300">not linked</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-700 max-w-[260px] truncate"
                                    title="{{ $r->file_title ?: $r->applicant_name }}">{!! $dash($r->file_title ?: $r->applicant_name) !!}</td>
                                <td class="px-4 py-3 text-slate-600">{!! $dash($r->plot_no) !!}</td>
                                <td class="px-4 py-3 text-slate-600">{!! $dash($r->district) !!}</td>
                                <td class="px-4 py-3 text-slate-600">{!! $dash($r->lga) !!}</td>
                                <td class="px-4 py-3 text-slate-600">{!! $dash($r->land_use) !!}</td>
                                <td class="px-4 py-3 text-slate-500 whitespace-nowrap">
                                    {{ $r->created_at ? \Carbon\Carbon::parse($r->created_at)->format('d/m/Y') : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-10 text-center text-slate-400">No Re-grant records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    {{-- ─────────────── Tab: To Be Re-granted ─────────────── --}}
    @else
        <div class="mb-3 flex flex-wrap items-center gap-2 text-xs text-slate-500">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100">
                <i data-lucide="info" class="w-3.5 h-3.5"></i>
                Term expires {{ $currentYear }} or earlier &middot; RES/AG = 99 yrs &middot; COM/IND = 40 yrs
            </span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100">
                Term runs from <code class="font-mono">transaction_date</code> &mdash; CofO supersedes RofO
            </span>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr class="text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                            <th class="px-4 py-3">File No</th>
                            <th class="px-4 py-3">Instrument</th>
                            <th class="px-4 py-3">Holder</th>
                            <th class="px-4 py-3">Transaction Date</th>
                            <th class="px-4 py-3 text-center">Term</th>
                            <th class="px-4 py-3 text-center">Expired</th>
                            <th class="px-4 py-3 text-center">Overdue</th>
                            <th class="px-4 py-3">Land Use</th>
                            <th class="px-4 py-3">Location</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($records as $r)
                            <tr class="hover:bg-slate-50/70" data-file-no="{{ $r->file_no }}">
                                <td class="px-4 py-3 font-semibold text-slate-800 whitespace-nowrap">{{ $r->file_no }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase
                                        {{ $r->source === 'cofo' ? 'bg-violet-50 text-violet-700' : 'bg-sky-50 text-sky-700' }}">
                                        {{ $r->source === 'cofo' ? 'CofO' : 'RofO' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-700 max-w-[220px] truncate" title="{{ $r->holder }}">{!! $dash($r->holder) !!}</td>
                                <td class="px-4 py-3 text-slate-600 whitespace-nowrap" title="stored as: {{ $r->grant_date_raw }}">
                                    {{ $r->grant_date_display ?: $r->grant_date_raw }}
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-semibold
                                        {{ (int) $r->term_years === 99 ? 'bg-emerald-50 text-emerald-700' : 'bg-blue-50 text-blue-700' }}">
                                        {{ $r->term_years }} yrs
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center font-semibold text-slate-700">{{ $r->expiry_year }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold border {{ $overdueTone((int) $r->years_overdue) }}">
                                        {{ $r->years_overdue }} yr{{ (int) $r->years_overdue === 1 ? '' : 's' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{!! $dash($r->land_use) !!}</td>
                                <td class="px-4 py-3 text-slate-600 max-w-[300px] truncate" title="{{ $r->location }}">{!! $dash($r->location) !!}</td>
                                <td class="px-4 py-3 text-right">
                                    <button type="button"
                                            onclick="regrantRaise('{{ addslashes($r->file_no) }}', this)"
                                            class="px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white inline-flex items-center gap-1">
                                        <i data-lucide="plus" class="w-3.5 h-3.5"></i> Raise
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="px-4 py-10 text-center text-slate-400">No files are currently due for re-grant.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Rows that could not be assessed, shown so the gap is visible rather than silent. --}}
        <div class="mt-4 p-4 rounded-xl bg-amber-50/60 border border-amber-200 text-xs text-amber-900">
            <p class="font-bold mb-1.5 flex items-center gap-1.5">
                <i data-lucide="alert-triangle" class="w-4 h-4"></i> Not assessed
            </p>
            <p class="mb-1">
                Of {{ number_format($unassessable['with_instrument']) }} indexed files holding a CofO or RofO,
                <strong>{{ number_format($unassessable['no_date']) }}</strong> have no usable grant date
                (<code class="font-mono">cofo_date</code> / <code class="font-mono">transaction_date</code> empty or unparseable)
                and <strong>{{ number_format($unassessable['no_term']) }}</strong> have a land use that carries no single term.
            </p>
            <p class="mt-1.5 text-amber-700">
                These are excluded from the list above — a missing grant date makes the term impossible to compute,
                and a mixed land use (e.g. "Residential/Commercial") carries two different terms.
            </p>
        </div>
    @endif

    {{-- Pagination --}}
    <div class="mt-4">
        {{ $records->links() }}
    </div>
</div>

@include('admin.footer')
</div>
@endsection

@push('scripts')
<script>
const REGRANT_RAISE_URL = '{{ route('regrant.raise') }}';
const REGRANT_CSRF      = '{{ csrf_token() }}';

function regrantRaise(fileNo, btn) {
    Swal.fire({
        title: 'Raise Re-grant',
        html: `Record a Re-grant for <b>${fileNo}</b>?`,
        input: 'textarea',
        inputLabel: 'Reason (optional)',
        inputPlaceholder: 'e.g. RE-GRANT OF EXPIRED RIGHT OF OCCUPANCY',
        showCancelButton: true,
        confirmButtonText: 'Yes, raise it',
        confirmButtonColor: '#4f46e5',
    }).then(result => {
        if (!result.isConfirmed) return;

        btn.disabled = true;
        btn.innerHTML = 'Saving…';

        fetch(REGRANT_RAISE_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': REGRANT_CSRF,
            },
            body: JSON.stringify({ file_no: fileNo, reason: result.value || '' }),
        })
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                btn.disabled = false;
                btn.innerHTML = '<i data-lucide="plus" class="w-3.5 h-3.5"></i> Raise';
                if (typeof lucide !== 'undefined') lucide.createIcons();
                Swal.fire({ icon: 'warning', title: 'Not raised', text: res.message || 'Could not raise the Re-grant.' });
                return;
            }
            Swal.fire({ icon: 'success', title: 'Re-grant raised', text: res.message, timer: 1600, showConfirmButton: false })
                .then(() => window.location.reload());
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="plus" class="w-3.5 h-3.5"></i> Raise';
            if (typeof lucide !== 'undefined') lucide.createIcons();
            Swal.fire({ icon: 'error', title: 'Error', text: 'Request failed.' });
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    if (typeof lucide !== 'undefined') lucide.createIcons();
});
</script>
@endpush

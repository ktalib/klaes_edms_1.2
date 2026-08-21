@extends('layouts.app')

@section('styles')
<style>
    .swal2-container { z-index: 20000 !important; }

    /* Rank badge colours follow the ORDER OF SELECTION, not the type. The officer's
       tick order is the execution order, so the badge has to be the thing that moves. */
    .rank-badge { min-width: 26px; }
    .rank-1 { background:#fee2e2; color:#b91c1c; border-color:#fecaca; }
    .rank-2 { background:#dbeafe; color:#1d4ed8; border-color:#bfdbfe; }
    .rank-3 { background:#dcfce7; color:#15803d; border-color:#bbf7d0; }
    .rank-4 { background:#fef3c7; color:#b45309; border-color:#fde68a; }
    .rank-5 { background:#e2e8f0; color:#334155; border-color:#cbd5e1; }

    .type-row { transition: all .18s ease; }
    .type-row.picked { border-color:#3b82f6; background:#f8fbff; }

    .wizard-step { display:none; }
    .wizard-step.active { display:block; animation: fadeIn .25s ease-out; }
    @keyframes fadeIn { from { opacity:0; transform:translateY(5px);} to {opacity:1; transform:translateY(0);} }

    .stage-pill.done { background:#dcfce7; color:#15803d; }
    .stage-pill.current { background:#dbeafe; color:#1d4ed8; }
    .stage-pill.locked { background:#f1f5f9; color:#94a3b8; }

    .holding-no { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
</style>
@endsection

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle' => 'Duplex Parcel Update',
        'PageDescription' => 'Several parcel updates carried as one instruction.'
    ])

    <div class="py-10 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Header --}}
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.4em] text-slate-400">{{ request('mode') === 'land' ? 'Land' : 'Deeds' }}</p>
                    <h1 class="text-2xl font-black text-slate-800 mt-1">Duplex Parcel Update</h1>
                    <p class="text-sm text-slate-500 mt-1">
                        Subdivision, Merger, Change of Purpose, Extension and Separation in one instruction —
                        one approval, one memo, one commissioning. A single update on its own is fine too.
                    </p>
                </div>
                <button onclick="openDuplexWizard()"
                    class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white rounded-xl text-sm font-semibold shadow-sm hover:bg-blue-700 transition">
                    <i data-lucide="layers" class="w-4 h-4"></i>
                    New Duplex
                </button>
            </div>

            {{-- Stats --}}
            <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
                @foreach ([
                    ['Total', $stats['total'], 'layers', 'text-slate-500'],
                    ['Today', $stats['daily'], 'calendar', 'text-blue-500'],
                    ['Draft', $stats['draft'], 'pencil', 'text-amber-500'],
                    ['Pending', $stats['pending'], 'clock', 'text-orange-500'],
                    ['Approved', $stats['approved'], 'check-circle', 'text-emerald-500'],
                    ['Committed', $stats['committed'], 'landmark', 'text-indigo-500'],
                ] as [$label, $value, $icon, $tone])
                <div class="bg-white rounded-2xl border border-slate-100 p-4 flex items-center gap-3">
                    <i data-lucide="{{ $icon }}" class="w-5 h-5 {{ $tone }}"></i>
                    <div>
                        <p class="text-[11px] uppercase tracking-wider text-slate-400 font-bold">{{ $label }}</p>
                        <p class="text-lg font-black text-slate-800">{{ $value }}</p>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Register --}}
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-4">
                    <h2 class="text-sm font-black uppercase tracking-wider text-slate-500">Duplex Register</h2>
                    <form method="GET" class="flex items-center gap-2">
                        <input type="text" name="search" value="{{ $search }}" placeholder="Duplex ID, applicant, title"
                            class="px-3 py-2 rounded-xl border border-slate-200 text-sm w-64">
                        <button class="px-3 py-2 rounded-xl bg-slate-800 text-white text-sm font-semibold">Search</button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-4 py-3 text-left">Duplex ID</th>
                                <th class="px-4 py-3 text-left">Applicant</th>
                                <th class="px-4 py-3 text-left">Source File(s)</th>
                                <th class="px-4 py-3 text-left">Stages (execution order)</th>
                                <th class="px-4 py-3 text-left">Date</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($records as $record)
                            <tr class="hover:bg-slate-50/60">
                                <td class="px-4 py-3 font-black text-slate-700 whitespace-nowrap holding-no">{{ $record->duplex_id }}</td>
                                <td class="px-4 py-3 text-slate-700 font-bold">{{ $record->applicant_name ?: '—' }}</td>
                                <td class="px-4 py-3 text-slate-600 text-xs">
                                    {{ implode(', ', (array) ($record->source_file_nos ?? [])) ?: '—' }}
                                </td>
                                <td class="px-4 py-3 text-slate-600 text-xs font-semibold">{{ $record->stageSummary() }}</td>
                                <td class="px-4 py-3 text-slate-500 text-xs">{{ $record->created_at?->format('M d, Y') }}</td>
                                <td class="px-4 py-3">
                                    @php
                                        $tone = match ($record->status) {
                                            'committed' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                            'approved', 'in_land' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                            'rejected' => 'bg-red-50 text-red-700 border-red-200',
                                            'draft' => 'bg-amber-50 text-amber-700 border-amber-200',
                                            default => 'bg-slate-50 text-slate-600 border-slate-200',
                                        };
                                    @endphp
                                    <span class="px-2 py-0.5 rounded-lg border text-[11px] font-bold uppercase {{ $tone }}">
                                        {{ str_replace('_', ' ', $record->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="relative inline-block text-left">
                                        <button onclick="toggleRowMenu({{ $record->id }})"
                                            class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                                            Actions
                                        </button>
                                        <div id="row-menu-{{ $record->id }}"
                                            class="hidden absolute right-0 z-50 mt-2 w-60 rounded-xl bg-white shadow-xl ring-1 ring-black/5 divide-y divide-slate-100 text-left">
                                            <div class="py-1">
                                                <button onclick="viewDuplex({{ $record->id }})" class="menu-item">View stages</button>
                                                <button onclick="openKnupda({{ $record->id }})" class="menu-item">KNUPDA</button>
                                            </div>
                                            <div class="py-1">
                                                <button onclick="generateDoc({{ $record->id }}, 'application')" class="menu-item">Generate Application</button>
                                                <a href="{{ route('duplex-parcel-update.print-application', $record->id) }}" target="_blank" class="menu-item block">Print Application</a>
                                                <button onclick="generateDoc({{ $record->id }}, 'recommendation')" class="menu-item">Generate Memo</button>
                                                <a href="{{ route('duplex-parcel-update.print-recommendation', $record->id) }}" target="_blank" class="menu-item block">Print Memo</a>
                                                <button onclick="generateDoc({{ $record->id }}, 'conveyance')" class="menu-item">Generate Conveyance</button>
                                                <a href="{{ route('duplex-parcel-update.print-conveyance', $record->id) }}" target="_blank" class="menu-item block">Print Conveyance</a>
                                            </div>
                                            <div class="py-1">
                                                <button onclick="approveDuplex({{ $record->id }})" class="menu-item text-emerald-700">Approve</button>
                                                <button onclick="rejectDuplex({{ $record->id }})" class="menu-item text-red-600">Reject</button>
                                                <button onclick="sendToLand({{ $record->id }})" class="menu-item">Send to Land</button>
                                                <a href="{{ route('duplex-parcel-update.commission', $record->id) }}" class="menu-item block font-bold text-indigo-700">Commission (Land)</a>
                                            </div>
                                            <div class="py-1">
                                                <button onclick="deleteDuplex({{ $record->id }})" class="menu-item text-red-600">Delete</button>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">No duplex records yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="px-4 py-3 bg-slate-50/50 border-t border-slate-100">
                    {{ $records->withQueryString()->links() }}
                </div>
            </div>
        </div>
    </div>

    @include('deeds.parcel_update.duplex.partials.wizard')
    @include('deeds.parcel_update.duplex.partials.knupda_modal')
    @include('deeds.parcel_update.duplex.partials.view_modal')
</div>

@include('components.global-fileno-modal')
@endsection

@section('footer-scripts')
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
@include('deeds.parcel_update.duplex.js', [
    'types' => $types,
    'streetNames' => $streetNames,
    'lgas' => $lgas,
    'districts' => $districts,
])
<style>
    .menu-item { display:block; width:100%; text-align:left; padding:.55rem 1rem; font-size:.8rem; color:#334155; }
    .menu-item:hover { background:#f8fafc; }
</style>
@endsection

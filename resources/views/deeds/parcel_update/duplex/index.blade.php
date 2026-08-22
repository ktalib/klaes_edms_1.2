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
    .type-row.picked { border-color:#3b82f6; background:#f8fbff; box-shadow:0 1px 2px rgba(59,130,246,.08); }
    .type-row.picked .type-icon { background:#dbeafe; color:#1d4ed8; }

    .wizard-step { display:none; }
    .wizard-step.active { display:block; animation: fadeIn .25s ease-out; }
    @keyframes fadeIn { from { opacity:0; transform:translateY(5px);} to {opacity:1; transform:translateY(0);} }

    /* Stepper tabs. Three states: done (behind you), active (here), ahead (greyed).
       Colour alone would not separate done from active, so the underline moves too. */
    .dx-step-tab .dx-step-dot  { background:#f1f5f9; color:#94a3b8; }
    .dx-step-tab .dx-step-text { color:#94a3b8; }
    .dx-step-tab.is-done .dx-step-dot  { background:#dcfce7; color:#15803d; }
    .dx-step-tab.is-done .dx-step-text { color:#475569; }
    .dx-step-tab.is-active > div       { border-bottom-color:#2563eb; }
    .dx-step-tab.is-active .dx-step-dot  { background:#2563eb; color:#fff; }
    .dx-step-tab.is-active .dx-step-text { color:#1e293b; }

    .stage-pill.done { background:#dcfce7; color:#15803d; }
    .stage-pill.current { background:#dbeafe; color:#1d4ed8; }
    .stage-pill.locked { background:#f1f5f9; color:#94a3b8; }

    .holding-no { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; letter-spacing:-.01em; }
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
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-7 py-6 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
                    <div class="flex items-start gap-4 min-w-0">
                        <span class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-blue-600/10 text-blue-600 shrink-0">
                            <i data-lucide="layers" class="w-5 h-5"></i>
                        </span>
                        <div class="min-w-0">
                            <p class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-400">{{ request('mode') === 'land' ? 'Land' : 'Deeds' }} · Parcel Update</p>
                            <h1 class="text-2xl font-black text-slate-800 mt-1 leading-tight">Duplex Parcel Update</h1>
                            <p class="text-sm text-slate-500 mt-1.5 max-w-2xl leading-relaxed">
                                Subdivision, Merger, Change of Purpose, Extension and Separation in one
                                instruction — one approval, one memo, one commissioning. A single update on
                                its own is fine too.
                            </p>
                        </div>
                    </div>
                    <button onclick="openDuplexWizard()"
                        class="shrink-0 inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white rounded-xl text-sm font-bold shadow-sm shadow-blue-600/20 hover:bg-blue-700 transition">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        New Duplex
                    </button>
                </div>

                {{-- The pipeline, spelled out. New officers cannot infer these steps from
                     the row menu alone, and skipping one is the usual support call. --}}
                <div class="px-7 py-3.5 border-t border-slate-100 bg-slate-50/70 flex flex-wrap items-center gap-x-2 gap-y-2 text-[11px] font-bold text-slate-400">
                    @foreach (['Capture stages', 'KNUPDA', 'Approve', 'Memo + Conveyance', 'Send to Land', 'Commission'] as $i => $step)
                        @if ($i)<i data-lucide="chevron-right" class="w-3.5 h-3.5 text-slate-300"></i>@endif
                        <span class="{{ $i === 5 ? 'text-indigo-600' : '' }}">{{ $step }}</span>
                    @endforeach
                </div>
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
                <div class="bg-white rounded-2xl border border-slate-100 p-4 hover:border-slate-200 transition">
                    <div class="flex items-center justify-between">
                        <p class="text-[10px] uppercase tracking-wider text-slate-400 font-black">{{ $label }}</p>
                        <i data-lucide="{{ $icon }}" class="w-4 h-4 {{ $tone }}"></i>
                    </div>
                    <p class="text-2xl font-black text-slate-800 mt-2 leading-none">{{ $value }}</p>
                </div>
                @endforeach
            </div>

            {{-- Register --}}
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-baseline gap-2.5">
                        <h2 class="text-sm font-black uppercase tracking-wider text-slate-600">Duplex Register</h2>
                        <span class="text-xs font-bold text-slate-400">{{ $records->total() }} record{{ $records->total() === 1 ? '' : 's' }}</span>
                    </div>
                    <form method="GET" class="flex items-center gap-2">
                        <div class="relative">
                            <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                            <input type="text" name="search" value="{{ $search }}" placeholder="Duplex ID, applicant, title"
                                class="pl-9 pr-3 py-2 rounded-xl border border-slate-200 text-sm w-72 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition">
                        </div>
                        <button class="px-4 py-2 rounded-xl bg-slate-800 text-white text-sm font-bold hover:bg-slate-900 transition">Search</button>
                        @if ($search)
                            <a href="{{ route('duplex-parcel-update.index') }}" class="text-xs font-bold text-slate-400 hover:text-slate-600 px-2">Clear</a>
                        @endif
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
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ((array) ($record->source_file_nos ?? []) as $src)
                                            <span class="holding-no text-[10px] font-bold px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 border border-slate-200">{{ $src }}</span>
                                        @empty
                                            <span class="text-xs text-slate-300">—</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    {{-- Ranked badges, in execution order — the same colours the
                                         wizard uses, so the two screens read as one thing. --}}
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        @foreach (collect($record->stages ?? [])->sortBy('rank') as $stage)
                                            <span class="inline-flex items-center gap-1.5 pl-1 pr-2 py-1 rounded-lg border border-slate-200 bg-white">
                                                <span class="rank-badge rank-{{ min($stage['rank'] ?? 1, 5) }} w-4 h-4 rounded flex items-center justify-center text-[9px] font-black border">{{ $stage['rank'] ?? '?' }}</span>
                                                <span class="text-[11px] font-bold text-slate-600">{{ $types[$stage['type'] ?? ''] ?? '?' }}</span>
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
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
                            <tr>
                                <td colspan="7" class="px-4 py-16 text-center">
                                    <i data-lucide="layers" class="w-8 h-8 text-slate-300 mx-auto"></i>
                                    <p class="text-sm font-bold text-slate-500 mt-3">No duplex records yet</p>
                                    <p class="text-xs text-slate-400 mt-1">Start one to carry several parcel updates as a single instruction.</p>
                                    <button onclick="openDuplexWizard()" class="mt-4 px-4 py-2 rounded-xl bg-blue-600 text-white text-xs font-bold hover:bg-blue-700 transition">
                                        New Duplex
                                    </button>
                                </td>
                            </tr>
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

@extends('layouts.app')

@php
    use App\Models\MasterJsiReport;
    use App\Support\MasterJsiPortionTemplates;
    use App\Support\MasterJsiSubjectResolver;

    $statusTones = [
        MasterJsiReport::STATUS_DRAFT     => 'bg-slate-100 text-slate-600',
        MasterJsiReport::STATUS_GENERATED => 'bg-blue-100 text-blue-700',
        MasterJsiReport::STATUS_SUBMITTED => 'bg-amber-100 text-amber-700',
        MasterJsiReport::STATUS_APPROVED  => 'bg-emerald-100 text-emerald-700',
        MasterJsiReport::STATUS_REJECTED  => 'bg-red-100 text-red-700',
    ];
@endphp

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle'       => 'Master JSI ' . $report->jsi_ref,
        'PageDescription' => 'Physical Planning site observation for a parcel update.',
    ])

    <div class="py-10 bg-slate-50 min-h-screen">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

        {{-- Header --}}
        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm px-7 py-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-red-50 flex items-center justify-center shrink-0">
                        <i data-lucide="ruler" class="w-6 h-6 text-red-600"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h1 class="text-xl font-black text-slate-800 leading-tight">{{ $report->jsi_ref }}</h1>
                            <span class="text-[10px] font-black px-2 py-1 rounded-md {{ $statusTones[$report->status] ?? 'bg-slate-100 text-slate-600' }}">
                                {{ strtoupper($report->status) }}
                            </span>
                            <span class="text-[10px] font-black px-2 py-1 rounded-md {{ $report->category === 'APU' ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600' }}">
                                {{ $report->category }}
                            </span>
                            @if ($report->sent_to_deeds_at)
                                <span class="text-[10px] font-black px-2 py-1 rounded-md bg-indigo-50 text-indigo-600">
                                    SENT TO DEEDS {{ $report->sent_to_deeds_at->format('d M Y') }}
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-slate-500 mt-1">
                            {{ MasterJsiPortionTemplates::heading($report->parcel_update_type) }}
                            @if ($report->file_number) · File {{ $report->file_number }} @endif
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('master-jsi.print', $report->id) }}" target="_blank"
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-800 text-white rounded-xl text-xs font-bold hover:bg-slate-900 transition">
                        <i data-lucide="printer" class="w-4 h-4"></i> Print sheet
                    </a>
                    <a href="{{ route('master-jsi.index') }}" class="text-xs font-bold text-slate-400 hover:text-slate-600 px-3 py-2">Back</a>
                </div>
            </div>

            @if ($report->status === MasterJsiReport::STATUS_REJECTED && $report->rejected_reason)
            <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3">
                <p class="text-[10px] uppercase tracking-wider font-black text-red-500">Rejected</p>
                <p class="text-sm text-red-700 mt-1">{{ $report->rejected_reason }}</p>
            </div>
            @endif
        </div>

        {{-- Narrative --}}
        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-600">Site Observation</h2>
            </div>
            <div class="p-6 space-y-4">
                <p class="text-sm text-slate-600 leading-relaxed">{{ MasterJsiPortionTemplates::narrative($report->parcel_update_type) }}</p>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-4">
                    @foreach ([
                        ['Applicant', \App\Support\PersonName::display($report->applicant_name)],
                        ['File Title', $report->file_title],
                        ['Inspection Date', $report->inspection_date?->format('d M Y')],
                        ['Plot Number', $report->plot_number],
                        ['Location', $report->location],
                        ['District', $report->district],
                        ['LGA', $report->lga],
                        ['Available on Ground', $report->available_on_ground],
                        ['Conforms with surrounding land use', $report->conformity === null ? null : ($report->conformity ? 'Yes' : 'No')],
                        ['Prevailing Land Use', $report->prevailing_land_use],
                        ['Road Reservation', $report->road_reservation],
                        ['Number of Units', $report->number_of_units],
                        ['Existing Land Use', $report->existing_land_use],
                        ['Recommended Land Use', $report->recommended_land_use],
                        ['Inspection Officer', $report->inspection_officer],
                    ] as [$label, $val])
                        @if ($val !== null && $val !== '')
                        <div>
                            <p class="text-[10px] uppercase tracking-wider font-black text-slate-400">{{ $label }}</p>
                            <p class="text-sm text-slate-700 font-bold mt-0.5">{{ $val }}</p>
                        </div>
                        @endif
                    @endforeach
                </div>

                @if ($report->boundary_description)
                <div class="pt-2">
                    <p class="text-[10px] uppercase tracking-wider font-black text-slate-400">Bounded by</p>
                    <p class="text-sm text-slate-700 mt-1 leading-relaxed">{{ $report->boundary_description }}</p>
                </div>
                @endif

                @if ($report->additional_observations)
                <div class="pt-2">
                    <p class="text-[10px] uppercase tracking-wider font-black text-slate-400">Additional Observations</p>
                    <p class="text-sm text-slate-700 mt-1 leading-relaxed">{{ $report->additional_observations }}</p>
                </div>
                @endif
            </div>
        </div>

        {{-- Measurements --}}
        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-600">Measurements</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left w-14">S/N</th>
                            <th class="px-4 py-3 text-left">Portion</th>
                            <th class="px-4 py-3 text-left">Dimension</th>
                            <th class="px-4 py-3 text-left">Measurement (m²/ha)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($report->portions as $portion)
                        <tr>
                            <td class="px-4 py-3 font-black text-slate-500">{{ $portion->sn }}</td>
                            <td class="px-4 py-3 font-bold text-slate-700">{{ $portion->label }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ $portion->dimensions ?: ($portion->land_use ?: ($portion->unit_count !== null ? $portion->unit_count : '—')) }}
                            </td>
                            <td class="px-4 py-3 text-slate-700 font-bold">{{ $portion->measurementText() ?: '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="px-4 py-12 text-center text-sm text-slate-400">No measurements recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- What the record links to --}}
        @if ($subject)
        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm px-6 py-5">
            <p class="text-[10px] uppercase tracking-wider font-black text-slate-400">Parcel Update Record</p>
            <p class="text-sm text-slate-700 font-bold mt-1">
                {{ MasterJsiSubjectResolver::label($report->subject_type, $subject) }}
                <span class="text-xs font-normal text-slate-400 ml-2">
                    ({{ MasterJsiReport::SUBJECTS[$report->subject_type][1] ?? $report->subject_type }})
                </span>
            </p>
        </div>
        @endif
    </div>
    </div>

    @include('admin.footer')
</div>
@endsection

@section('footer-scripts')
<script>if (window.lucide) lucide.createIcons();</script>
@endsection

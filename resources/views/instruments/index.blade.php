@extends('layouts.app')

@section('page-title')
    {{ __('Instrument Capture') }}
@endsection

@push('scripts')
    {{-- Select2 JS --}}
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    {{-- PDF Generation Libraries --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.7.1/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>


    @include('instrument_registration.modals.export_preview')
    <script src="{{ asset('js/instrument_capture_export.js') }}"></script>
@endpush

@section('content') 
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- DataTables CSS + Buttons --}}
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

    {{-- Select2 CSS --}}
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <!-- Global Config for RDS/CoR  -->
    <script>
        window.KlaesConfig = {
            baseUrl: "{{ url('/') }}",
            csrfToken: "{{ csrf_token() }}",
            urls: {
                base: "{{ url('instrument_registration') }}",
                delete: "{{ url('instrument_registration/delete') }}",
                generateRds: "{{ url('instrument_registration/generate-rds') }}",
                viewRds: "{{ url('instrument_registration/view-rds') }}",
                rdsStatus: "{{ url('instrument_registration/rds-status') }}",
                corIndex: "{{ route('coroi.index') }}",
                generateCor: "{{ url('coroi/generate') }}"
            }
        };

        window.baseUrl = "{{ url('') }}";
        window.instrumentRegistrationBase = "{{ url('') }}";

        window.serverCofoData = @json($fullDataForJs).map(item => {
            item.registered_instrument_id = item.registered_instrument_id || null;
            return item;
        });

        // Use window and var to avoid redeclaration errors with other scripts
        // Initialize global variables only if they don't exist
        window.appData = window.appData || {};
        window.instrumentLookup = window.instrumentLookup || new Map();
        
        // Define local references without potential redeclaration issues
        var currentAppData = window.appData;
        var currentInstrumentLookup = window.instrumentLookup;

        function populateInstrumentCaches(data) {
            window.instrumentLookup.clear();
            data.forEach(entry => {
                const idKey = String(entry.id);
                window.appData[idKey] = entry;
                window.instrumentLookup.set(idKey, entry);
                if (entry.registered_instrument_id) {
                    window.instrumentLookup.set(String(entry.registered_instrument_id), entry);
                }
            });
        }
        populateInstrumentCaches(window.serverCofoData);
    </script>

    @include('instruments.partial.css')
    <!-- Main Content -->
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6">

            <main class="space-y-6">
                <!-- Stats Cards -->
                @include('instruments.partial.stats_cards')

                <!-- Instruments Table -->
                <div class="table-container">
                    <div class="p-4 border-b border-gray-200">
                        <div class="flex justify-between items-center">
                            <h2 class="text-lg font-semibold">Instrument Capture</h2>
                            <div class="flex items-center gap-2">
                                {{-- Instrument Type Filter --}}
                                <div class="flex items-center gap-2">
                                    <label for="instrumentTypeFilter" class="text-xs font-semibold text-gray-700">Type:</label>
                                    <select id="instrumentTypeFilter"
                                        class="border border-gray-300 rounded px-2 py-1.5 text-xs w-48 bg-white focus:ring-2 focus:ring-blue-500 transition-all outline-none"
                                        onchange="filterInstrumentsTable()">
                                        <option value="">All Types</option>
                                        @foreach($instrumentTypes as $type)
                                            <option value="{{ $type }}">{{ $type }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Volume Filter --}}
                                <div class="flex items-center gap-2">
                                    <label for="volumeFilter" class="text-xs font-semibold text-gray-700">Vol:</label>
                                    <select id="volumeFilter"
                                        class="border border-gray-300 rounded px-2 py-1.5 text-xs w-24 bg-white focus:ring-2 focus:ring-blue-500 transition-all outline-none"
                                        onchange="filterInstrumentsTable()">
                                        <option value="">All</option>
                                        @for ($i = 1; $i <= 999; $i++)
                                            <option value="{{ $i }}">{{ $i }}</option>
                                        @endfor
                                    </select>
                                </div>

                                {{-- Export Button --}}
                                <button id="exportRegistryBtn" onclick="openCaptureExportModal()"
                                    class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg flex items-center gap-2 text-sm shadow-sm transition-all hover:shadow-md">
                                    <i data-lucide="file-export" class="w-4 h-4"></i>
                                    <span>Export Instruments</span>
                                </button>

                                <a class="btn btn-primary" href="{{ route('instruments.create') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 4v16m8-8H4" />
                                    </svg>
                                    Capture New Instrument
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="p-4">
                        <div class="table-responsive">
                            <table id="instrumentsTable" class="w-full">
                                <thead>
                                    <tr>
                                        <th class="text-left whitespace-nowrap">File No</th>
                                        <th class="text-left whitespace-nowrap">Reg Particulars</th>
                                        <th class="text-left whitespace-nowrap">OP SerialNo</th>
                                        <th class="text-left">Party 1</th>
                                        <th class="text-left">Party 2</th>
                                        <th class="text-left">Party 3</th>
                                       
                                        <th class="text-left">Solicitor</th>
                                         <th class="text-left">Instrument Type</th>
                                        <th class="text-left">Land Use</th>
                                         <th class="text-left">Reg Time/Date</th>
                                        <th class="text-left">Time/Date Captured</th>
                                       
                                        <th class="text-left">Property Description</th>
                                        <th class="text-left">Registered By</th>
                                        <th class="text-left">Actions</th>
                                    </tr>
                                </thead>
                            <tbody>
                                @forelse($instruments as $instrument)
                                    <tr class="instrument-row" data-volume="{{ $instrument->volume_no ?? '' }}">
                                        <td class="align-top">
                                            <span class="badge bg-gray-100 text-gray-800 border border-gray-200 rounded-full px-3 py-1 whitespace-nowrap font-mono text-xs tracking-wide">
                                                {{ $instrument->mlsFNo ?: $instrument->kangisFileNo ?: $instrument->NewKANGISFileno ?: $instrument->temp_fileno }}
                                            </span>
                                        </td>
                                        <td class="align-top whitespace-nowrap text-sm">
                                            @if($instrument->registered_instrument_id)
                                                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-md font-mono text-xs">
                                                    {{ $instrument->serial_no ?? '0' }}/{{ $instrument->page_no ?? '0' }}/{{ $instrument->volume_no ?? '0' }}
                                                </span>
                                            @else
                                                <span class="text-gray-400 text-xs">Unregistered</span>
                                            @endif
                                        </td>
                                        <td class="align-top whitespace-nowrap">
                                            @if(!empty($instrument->op_serial_number))
                                                <span class="px-2 py-1 bg-orange-50 text-orange-700 border border-orange-200 rounded-md font-mono text-xs">
                                                    {{ $instrument->op_serial_number }}
                                                </span>
                                            @else
                                                <span class="text-gray-300 text-xs">—</span>
                                            @endif
                                        </td>
                                        <td class="align-top text-proper">{{ $instrument->party_1_name ? ucwords(strtolower($instrument->party_1_name)) : '' }}</td>
                                        <td class="align-top text-proper">{{ $instrument->party_2_name ? ucwords(strtolower($instrument->party_2_name)) : '' }}</td>
                                        <td class="align-top text-proper">{{ $instrument->party_3_name ? ucwords(strtolower($instrument->party_3_name)) : '' }}</td>
                                        <td class="align-top">
                                            <div class="flex flex-col" style="max-width: 180px;" x-data="{ expanded: false }">
                                                <div :class="expanded ? 'whitespace-normal' : 'truncate block'" class="font-bold text-gray-800 text-proper leading-tight w-full" :title="expanded ? '' : '{{ addslashes($instrument->solicitor_name) }}'">
                                                    {{ $instrument->solicitor_name ? ucwords(strtolower($instrument->solicitor_name)) : '' }}
                                                </div>
                                                <div :class="expanded ? 'whitespace-normal' : 'truncate block'" class="text-xs text-gray-500 text-proper mt-0.5 w-full" style="font-size: 11px;" :title="expanded ? '' : '{{ addslashes($instrument->solicitor_address) }}'">
                                                    {{ $instrument->solicitor_address ? ucwords(strtolower($instrument->solicitor_address)) : '' }}
                                                </div>
                                                @if((strlen($instrument->solicitor_name ?? '') > 25) || (strlen($instrument->solicitor_address ?? '') > 35))
                                                    <button type="button" @click.stop="expanded = !expanded" class="text-xs text-blue-600 font-bold hover:underline mt-1 self-start flex items-center bg-blue-50/50 px-1.5 py-0.5 rounded border border-blue-100/50" style="font-size: 10px;">
                                                        <span x-text="expanded ? 'View Less' : 'View More'"></span>
                                                        <i :data-lucide="expanded ? 'chevron-up' : 'chevron-down'" class="ml-1" style="width: 12px; height: 12px;"></i>
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="align-top">
                                            @php
                                                $itype = $instrument->instrument_type ?? '';
                                                $itypeBadge = match(true) {
                                                    stripos($itype, 'Occupancy Permit') !== false
                                                        => 'bg-orange-100 text-orange-800 border-orange-200',
                                                    stripos($itype, 'Irrevocable Power of Attorney') !== false
                                                        => 'bg-green-100 text-green-800 border-green-200',
                                                    stripos($itype, 'Tripartite Mortgage') !== false
                                                        => 'bg-red-100 text-red-800 border-red-200',
                                                    stripos($itype, 'Deed of Mortgage') !== false
                                                        => 'bg-purple-100 text-purple-800 border-purple-200',
                                                    stripos($itype, 'Deed of Assignment') !== false
                                                        => 'bg-amber-100 text-amber-800 border-amber-200',
                                                    stripos($itype, 'Deed of Sub-Lease') !== false
                                                        => 'bg-pink-100 text-pink-800 border-pink-200',
                                                    stripos($itype, 'Deed of Lease') !== false
                                                        => 'bg-indigo-100 text-indigo-800 border-indigo-200',
                                                    stripos($itype, 'Surrender') !== false || stripos($itype, 'Release') !== false
                                                        => 'bg-lime-100 text-lime-800 border-lime-200',
                                                    stripos($itype, 'Deed of Gift') !== false
                                                        => 'bg-rose-100 text-rose-800 border-rose-200',
                                                    stripos($itype, 'Court Order') !== false
                                                        => 'bg-slate-100 text-slate-800 border-slate-200',
                                                    stripos($itype, 'Certificate of Occupancy') !== false || stripos($itype, 'CofO') !== false
                                                        => 'bg-teal-100 text-teal-800 border-teal-200',
                                                    default => 'bg-blue-100 text-blue-800 border-blue-200',
                                                };
                                            @endphp
                                            <div class="flex flex-col gap-1">
                                                <span class="badge border {{ $itypeBadge }} self-start whitespace-nowrap">
                                                    {{ $itype }}
                                                </span>
                                                @if(stripos($itype, 'Occupancy Permit') !== false && !empty($instrument->op_type))
                                                    <span class="text-[10px] tracking-wider text-orange-600 font-bold px-1 uppercase">
                                                        {{ ucwords(strtolower($instrument->op_type)) }}
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                    
                                        <td class="align-top">
                                            @php
                                                $detailLandUse = $instrument->land_use;
                                                if (!$detailLandUse) {
                                                    $fileno = $instrument->mlsFNo ?: $instrument->kangisFileNo ?: $instrument->NewKANGISFileno;
                                                    if ($fileno) {
                                                        if (Str::contains($fileno, 'RES')) $detailLandUse = 'Residential';
                                                        elseif (Str::contains($fileno, 'COM')) $detailLandUse = 'Commercial';
                                                        elseif (Str::contains($fileno, 'IND')) $detailLandUse = 'Industrial';
                                                        elseif (Str::contains($fileno, 'AG')) $detailLandUse = 'Agriculture';
                                                        else $detailLandUse = '-';
                                                    } else {
                                                        $detailLandUse = '-';
                                                    }
                                                }

                                                $badgeClass = match (true) {
                                                    in_array($detailLandUse, ['Residential', 'RESIDENTIAL'])
                                                        => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                                    in_array($detailLandUse, ['Commercial', 'COMMERCIAL'])
                                                        => 'bg-purple-100 text-purple-800 border-purple-200',
                                                    in_array($detailLandUse, ['Industrial', 'INDUSTRIAL'])
                                                        => 'bg-amber-100 text-amber-800 border-amber-200',
                                                    in_array($detailLandUse, ['Agriculture', 'Agricultural', 'AGRICULTURE'])
                                                        => 'bg-lime-100 text-lime-800 border-lime-200',
                                                    in_array($detailLandUse, ['Educational', 'EDUCATIONAL'])
                                                        => 'bg-pink-100 text-pink-800 border-pink-200',
                                                    in_array($detailLandUse, ['Government', 'GOVERNMENT'])
                                                        => 'bg-slate-100 text-slate-800 border-slate-200',
                                                    in_array($detailLandUse, ['Mixed', 'Mixed Use', 'MIXED'])
                                                        => 'bg-cyan-100 text-cyan-800 border-cyan-200',
                                                    in_array($detailLandUse, ['Religious', 'RELIGIOUS'])
                                                        => 'bg-violet-100 text-violet-800 border-violet-200',
                                                    default => 'bg-gray-100 text-gray-500 border-gray-200',
                                                };

                                                $purposeDisplay = $instrument->purpose ?? '';
                                                
                                                $purposeColor = match (strtolower(trim($purposeDisplay))) {
                                                    'residential', 'dwelling' => 'text-emerald-600',
                                                    'commercial', 'shop', 'office' => 'text-purple-600',
                                                    'industrial', 'factory', 'warehouse' => 'text-amber-600',
                                                    'agricultural', 'farming' => 'text-lime-600',
                                                    'educational', 'school' => 'text-pink-600',
                                                    'religious', 'worship' => 'text-violet-600',
                                                    'mixed', 'mixed use' => 'text-cyan-600',
                                                    'resettlement' => 'text-orange-600',
                                                    'government', 'public' => 'text-slate-600',
                                                    'hospital', 'health', 'clinic' => 'text-red-600',
                                                    'recreation', 'leisure' => 'text-teal-600',
                                                    default => 'text-blue-600',
                                                };
                                            @endphp
                                            <div class="flex flex-col gap-0.5">
                                                <span class="badge border {{ $badgeClass }} rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap">
                                                    {{ $detailLandUse }}
                                                </span>
                                                @if($purposeDisplay)
                                                    <span class="text-[10px] {{ $purposeColor }} font-medium pl-1">{{ ucwords(strtolower($purposeDisplay)) }}</span>
                                                @endif
                                            </div>
                                        </td>   

                                              <td class="align-top whitespace-nowrap">
                                            @if($instrument->deeds_date || $instrument->deeds_time)
                                                <div class="flex flex-col">
                                                    @if($instrument->deeds_time)
                                                        <span class="text-blue-700 text-xs">{{ \Carbon\Carbon::parse($instrument->deeds_time)->format('g:i A') }}</span>
                                                    @endif
                                                    @if($instrument->deeds_date)
                                                        <span class="text-blue-500 text-xs">{{ \Carbon\Carbon::parse($instrument->deeds_date)->format('M d, Y') }}</span>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-gray-300 text-xs">—</span>
                                            @endif
                                        </td>

                                        
                                        <td class="align-top whitespace-nowrap" data-order="{{ \Carbon\Carbon::parse($instrument->created_at)->format('Y-m-d H:i:s') }}">
                                            <div class="flex flex-col">
                                                <span class="text-gray-700 text-xs">{{ \Carbon\Carbon::parse($instrument->created_at)->format('g:i A') }}</span>
                                                <span class="text-gray-500 text-xs">{{ \Carbon\Carbon::parse($instrument->created_at)->format('M d, Y') }}</span>
                                            </div>
                                        </td>
                                  
                                        <td class="align-top">
                                            @if($instrument->property_description)
                                                <div x-data="{ expanded: false }" class="flex flex-col" style="max-width: 180px;">
                                                    <div :class="expanded ? 'whitespace-normal' : 'truncate block'" 
                                                         class="text-gray-600 text-sm leading-tight text-proper w-full"
                                                         :title="expanded ? '' : '{{ addslashes($instrument->property_description) }}'">
                                                        {{ ucwords(strtolower($instrument->property_description)) }}
                                                    </div>
                                                    @if(strlen($instrument->property_description) > 30)
                                                        <button type="button" @click.stop="expanded = !expanded" 
                                                                class="text-xs text-blue-600 font-bold hover:underline mt-1 self-start flex items-center bg-blue-50/50 px-1.5 py-0.5 rounded border border-blue-100/50" 
                                                                style="font-size: 10px;">
                                                            <span x-text="expanded ? 'View Less' : 'View More'"></span>
                                                            <i :data-lucide="expanded ? 'chevron-up' : 'chevron-down'" class="ml-1" style="width: 12px; height: 12px;"></i>
                                                        </button>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-gray-300 text-xs">—</span>
                                            @endif
                                        </td>
                                        <td class="align-top whitespace-nowrap">
                                            @if(!empty(trim($instrument->created_by_name)))
                                                <span class="text-gray-700 text-xs font-medium">{{ ucwords(strtolower(trim($instrument->created_by_name))) }}</span>
                                            @else
                                                <span class="text-gray-300 text-xs">—</span>
                                            @endif
                                        </td>
                                        <td class="relative group text-right">
                                            <button class="text-gray-500 hover:text-gray-700 focus:outline-none flex items-center justify-center w-8 h-8 rounded-full hover:bg-gray-100 transition ml-auto"
                                                data-dropdown-toggle="dropdown-{{ $instrument->id }}"
                                                onclick="toggleDropdown('dropdown-{{ $instrument->id }}')" aria-haspopup="true"
                                                aria-expanded="false" aria-controls="dropdown-{{ $instrument->id }}" type="button">
                                                <i data-lucide="ellipsis-vertical" class="w-4 h-4"></i>
                                            </button>
                                            <div id="dropdown-{{ $instrument->id }}"
                                                class="dropdown-menu hidden absolute right-0 mt-1 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-[100]"
                                                role="menu">
                                                <a href="javascript:void(0)" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-400 opacity-50 pointer-events-none cursor-not-allowed transition" role="menuitem">
                                                    <i data-lucide="eye" class="w-4 h-4"></i> Views
                                                </a>
                                                <a href="javascript:void(0)" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-400 opacity-50 pointer-events-none cursor-not-allowed transition" role="menuitem">
                                                    <i data-lucide="edit-3" class="w-4 h-4"></i> Edit
                                                </a>

                                                <form action="{{ route('instruments.destroy', $instrument->id) }}" method="POST" role="menuitem">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" disabled class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-300 opacity-50 pointer-events-none cursor-not-allowed transition">
                                                        <i data-lucide="trash-2" class="w-4 h-4"></i> Delete
                                                    </button>
                                                </form>

                                                @if(!empty($instrument->registered_instrument_id))
                                                    <div class="border-t border-gray-100 my-1"></div>
                                                    
                                                    @php
                                                        // Check if RDS has been generated
                                                        $rdsGenerated = false;
                                                        try {
                                                            $rdsRecord = DB::connection('sqlsrv')
                                                                ->table('rds_tracking')
                                                                ->where('instrument_id', $instrument->registered_instrument_id)
                                                                ->first();
                                                            $rdsGenerated = !empty($rdsRecord);
                                                        } catch (\Exception $e) {
                                                            // Handle error silently
                                                        }
                                                        
                                                        // Check if CoR has been generated 
                                                        $corGenerated = false;
                                                        if (stripos($instrument->registered_instrument_id, 'deed_reg_') === 0) {
                                                            try {
                                                                $realId = substr($instrument->registered_instrument_id, 9);
                                                                $deedRecord = DB::connection('sqlsrv')
                                                                    ->table('deed_registrations')
                                                                    ->where('id', $realId)
                                                                    ->first();
                                                                $corGenerated = !empty($deedRecord) && $deedRecord->cor_exists == 1;
                                                            } catch (\Exception $e) {
                                                                // Handle error silently
                                                            }
                                                        }
                                                    @endphp
                                                    
                                                    <!-- RDS Actions -->
                                                    <div id="rds-actions-{{ $instrument->id }}">
                                                        <!-- Generate RDS Button -->
                                                        @if(!$rdsGenerated)
                                                            <a href="#" id="gen-rds-{{ $instrument->id }}" onclick="showGenerateRDSModal('{{ $instrument->id }}'); return false;" 
                                                               class="flex items-center gap-2 px-4 py-2 text-sm text-blue-600 hover:bg-blue-50 transition">
                                                                <i data-lucide="file-text" class="w-4 h-4"></i> Generate RDS
                                                            </a>
                                                        @else
                                                            <span class="flex items-center gap-2 px-4 py-2 text-sm text-gray-400 cursor-not-allowed">
                                                                <i data-lucide="file-text" class="w-4 h-4"></i> Generate RDS
                                                                <i data-lucide="check-circle" class="w-3 h-3 text-green-500"></i>
                                                            </span>
                                                        @endif
                                                        
                                                        <!-- View RDS Button -->
                                                        @if($rdsGenerated)
                                                            <a href="#" id="view-rds-{{ $instrument->id }}" onclick="viewRDS('{{ $instrument->id }}'); return false;" 
                                                               class="flex items-center gap-2 px-4 py-2 text-sm text-blue-600 hover:bg-blue-50 transition">
                                                                <i data-lucide="eye" class="w-4 h-4"></i> View RDS
                                                            </a>
                                                        @else
                                                            <span class="flex items-center gap-2 px-4 py-2 text-sm text-gray-400 cursor-not-allowed">
                                                                <i data-lucide="eye" class="w-4 h-4"></i> View RDS
                                                                <i data-lucide="x-circle" class="w-3 h-3 text-red-400"></i>
                                                            </span>
                                                        @endif
                                                    </div>

                                                    <!-- CoR Actions -->
                                                    <div id="cor-actions-{{ $instrument->id }}">
                                                        <!-- Generate CoR Button -->
                                                        @if(!$corGenerated)
                                                            <a href="#" id="gen-cor-{{ $instrument->id }}" onclick="showGenerateCoRModal('{{ $instrument->id }}'); return false;" 
                                                               class="flex items-center gap-2 px-4 py-2 text-sm text-green-600 hover:bg-green-50 transition">
                                                                <i data-lucide="check-square" class="w-4 h-4"></i>
                                                                Generate CoR
                                                            </a>
                                                        @else
                                                            <span class="flex items-center gap-2 px-4 py-2 text-sm text-gray-400 cursor-not-allowed">
                                                                <i data-lucide="check-square" class="w-4 h-4"></i>
                                                                Generate CoR
                                                                <i data-lucide="check-circle" class="w-3 h-3 text-green-500"></i>
                                                            </span>
                                                        @endif
                                                        
                                                        <!-- View CoR Button -->
                                                        @if($corGenerated)
                                                            <a href="#" id="view-cor-{{ $instrument->id }}" onclick="viewCOR('{{ $instrument->id }}'); return false;" 
                                                               class="flex items-center gap-2 px-4 py-2 text-sm text-green-600 hover:bg-green-50 transition">
                                                                <i data-lucide="file-check" class="w-4 h-4"></i>
                                                                View CoR
                                                            </a>
                                                        @else
                                                            <span class="flex items-center gap-2 px-4 py-2 text-sm text-gray-400 cursor-not-allowed">
                                                                <i data-lucide="file-check" class="w-4 h-4"></i>
                                                                View CoR
                                                                <i data-lucide="x-circle" class="w-3 h-3 text-red-400"></i>
                                                            </span>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="11" class="text-center py-4">No instruments found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                        </div>

                        @if(method_exists($instruments, 'links'))
                            <div class="mt-4">
                                {{ $instruments->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </main>

            <!-- JavaScript -->
            @include('instruments.partial.js')
        </div>

        <!-- Footer -->
        @include('admin.footer')
    </div>

    {{-- DataTables JS (jQuery first, then DT, then Buttons) --}}
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

    <!-- Reuse Instrument Registration Logic -->
    <script src="{{ asset('js/instrument_registration_index.js') }}"></script>
    
    <script>
        // ─── DataTables Initialisation ───────────────────────────────────────────
        $(document).ready(function () {
            var table = $('#instrumentsTable').DataTable({
                dom: "<'dt-top-bar'f>t",
                paging: false,
                info: false,
                lengthChange: false,
                order: [[9, 'desc']], // sort by Date Captured descending by default
                columnDefs: [
                    { orderable: false, targets: [10] }  // Actions column not sortable
                ],
                // Move the native DT search & export buttons into our header bar
                initComplete: function () {
                    // Render export buttons into the dedicated container
                    new $.fn.dataTable.Buttons(table, {
                        buttons: [
                            {
                                extend: 'copyHtml5',
                                text: '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg> Copy',
                                className: 'btn btn-outline btn-sm',
                                exportOptions: { columns: ':not(:last-child)' }
                            },
                            {
                                extend: 'csvHtml5',
                                text: '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg> CSV',
                                className: 'btn btn-outline btn-sm',
                                exportOptions: { columns: ':not(:last-child)' }
                            },
                            {
                                extend: 'excelHtml5',
                                text: '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/></svg> Excel',
                                className: 'btn btn-outline btn-sm',
                                exportOptions: { columns: ':not(:last-child)' }
                            },
                            {
                                extend: 'pdfHtml5',
                                text: '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg> PDF',
                                className: 'btn btn-outline btn-sm',
                                orientation: 'landscape',
                                pageSize: 'A4',
                                exportOptions: { columns: ':not(:last-child)' }
                            },
                            {
                                extend: 'print',
                                text: '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg> Print',
                                className: 'btn btn-outline btn-sm',
                                exportOptions: { columns: ':not(:last-child)' }
                            }
                        ]
                    });

                    table.buttons().container().appendTo('#dt-export-buttons');

                    // Re-initialise lucide icons after DT renders
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                }
            });
        });

        // ─── Filter Logic ──────────────────────────────────────────────────────────
        function filterInstrumentsTable() {
            var selectedType = $('#instrumentTypeFilter').val();
            var selectedVolume = $('#volumeFilter').val();
            
            var table = $('#instrumentsTable').DataTable();
            
            // Clear existing filters
            $.fn.dataTable.ext.search.push(
                function(settings, data, dataIndex) {
                    if (settings.nTable.id !== 'instrumentsTable') return true;
                    
                    var row = table.row(dataIndex).node();
                    var typeContent = $(row).find('td:eq(6)').text().trim(); // Index 6 is now Instrument Type
                    var rowVolume = $(row).data('volume') ? String($(row).data('volume')) : '';
                    
                    // Matches if type matches OR if it's an OP sub-type
                    var matchesType = selectedType === "" || typeContent.includes(selectedType);
                    
                    // Volume filter
                    var matchesVolume = selectedVolume === "" || rowVolume === selectedVolume;
                    
                    return matchesType && matchesVolume;
                }
            );
            
            table.draw();
            $.fn.dataTable.ext.search.pop();
        }

        // Local dropdown toggles for per-row menus on this page.
        function closeAllDropdowns(exceptId) {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                if (!exceptId || menu.id !== exceptId) {
                    menu.classList.add('hidden');
                    menu.classList.remove('drop-up');
                }
            });
        }

        function toggleDropdown(dropdownId) {
            const menu = document.getElementById(dropdownId);
            const trigger = document.querySelector(`[data-dropdown-toggle="${dropdownId}"]`);
            if (!menu || !trigger) return;

            const isHidden = menu.classList.contains('hidden');
            closeAllDropdowns(dropdownId);

            if (isHidden) {
                menu.classList.remove('hidden', 'drop-up');

                requestAnimationFrame(() => {
                    const menuRect = menu.getBoundingClientRect();
                    const triggerRect = trigger.getBoundingClientRect();
                    const spaceBelow = window.innerHeight - triggerRect.bottom;
                    const spaceAbove = triggerRect.top;

                    if (menuRect.height > spaceBelow && spaceAbove > menuRect.height) {
                        menu.classList.add('drop-up');
                    }
                });
            } else {
                menu.classList.add('hidden');
                menu.classList.remove('drop-up');
            }
        }

        document.addEventListener('click', function (event) {
            if (event.target.closest('.dropdown-menu')) return;
            if (event.target.closest('[data-dropdown-toggle]')) return;
            closeAllDropdowns();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeAllDropdowns();
            }
        });
    </script>

    <style>
        /* ── DataTables layout overrides ─────────────────────────────── */
        .dt-top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 0 1rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .dt-bottom-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 0 0;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        /* Search Input */
        div.dataTables_filter input {
            padding: 0.45rem 0.75rem;
            font-size: 0.875rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            background-color: white;
            outline: none;
            margin-left: 0.5rem;
        }
        div.dataTables_filter input:focus {
            border-color: #111827;
            box-shadow: 0 0 0 2px rgba(17,24,39,0.12);
        }
        div.dataTables_filter label {
            font-size: 0.875rem;
            color: #4b5563;
        }

        /* Length dropdown */
        div.dataTables_length select {
            padding: 0.45rem 1.75rem 0.45rem 0.75rem;
            font-size: 0.875rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            background-color: white;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%239ca3af'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.5rem center;
            background-size: 1rem;
        }
        div.dataTables_length label {
            font-size: 0.875rem;
            color: #4b5563;
        }

        /* Info text */
        div.dataTables_info {
            font-size: 0.875rem;
            color: #6b7280;
        }

        /* Pagination */
        div.dataTables_paginate .paginate_button {
            padding: 0.4rem 0.7rem;
            margin: 0 1px;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            cursor: pointer;
            background: white;
            color: #4b5563 !important;
        }
        div.dataTables_paginate .paginate_button:hover {
            background: #f9fafb !important;
            color: #111827 !important;
            border-color: #d1d5db !important;
        }
        div.dataTables_paginate .paginate_button.current,
        div.dataTables_paginate .paginate_button.current:hover {
            background: #111827 !important;
            color: white !important;
            border-color: #111827 !important;
        }
        div.dataTables_paginate .paginate_button.disabled,
        div.dataTables_paginate .paginate_button.disabled:hover {
            color: #d1d5db !important;
            background: white !important;
            cursor: default;
        }

        /* Export buttons strip */
        .dt-buttons .btn {
            font-size: 0.8rem;
            padding: 0.35rem 0.7rem;
            border-radius: 0.375rem;
        }
        .dt-buttons .btn + .btn {
            margin-left: 4px;
        }

        /* Table overrides */
        .table-container {
            position: relative;
            overflow: visible !important;
        }
        table th, table td {
            vertical-align: top !important;
            text-align: left !important;
            padding: 1rem 0.75rem !important;
        }
        .dropdown-menu {
            position: absolute;
            z-index: 100;
        }

        /* Sorting arrows colour */
        table.dataTable thead th.sorting:after,
        table.dataTable thead th.sorting_asc:after,
        table.dataTable thead th.sorting_desc:after {
            color: #9ca3af;
        }
    </style>
@endsection

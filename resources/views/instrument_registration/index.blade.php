@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? __('Instrument Registration (New Registration)') }}
@endsection


@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/lucide@latest"></script>
    
    <!-- Select2 & PDF Generation -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

    <!-- Inline script to make sure critical functions are defined early -->
    <script>
        // Define global configuration and data FIRST
        window.KlaesConfig = {
            baseUrl: "{{ url('/') }}",
            csrfToken: "{{ csrf_token() }}",
            urls: {
                base: "{{ url('instrument_registration') }}",
                delete: "{{ url('instrument_registration/delete') }}",
                batchPrintSessions: "{{ route('instrument_registration.batch-print-sessions') }}",
                batchGenerateRds: "{{ route('instrument_registration.batch-generate-rds') }}",
                batchDownloadRdsPdf: "{{ route('instrument_registration.batch-download-rds-pdf') }}",
                batchGenerateCor: "{{ route('instrument_registration.batch-generate-cor') }}",
                batchDownloadCorPdf: "{{ route('instrument_registration.batch-download-cor-pdf') }}",
                batchPrintRunner: "{{ route('instrument_registration.batch-print-runner') }}",
                generateRds: "{{ url('instrument_registration/generate-rds') }}",
                viewRds: "{{ url('instrument_registration/view-rds') }}",
                rdsStatus: "{{ url('instrument_registration/rds-status') }}",
                corIndex: "{{ route('coroi.index') }}",
                generateCor: "{{ url('coroi/generate') }}"
            }
        };

        // Helper function to format multiple owner names
        function formatMultipleOwners(ownerString) {
            if (!ownerString) return '-';
            if (typeof ownerString === 'string' && ownerString.trim().startsWith('[')) {
                try {
                    const owners = JSON.parse(ownerString);
                    if (Array.isArray(owners) && owners.length > 0) {
                        return owners.length > 1 ? `${owners[0]} +${owners.length - 1} more` : owners[0];
                    }
                } catch (e) { return ownerString; }
            }
            return ownerString;
        }

        // Pass PHP data to JavaScript globally
        window.serverCofoData = @json($fullDataForJs).map(item => {
            // Format Grantor and Grantee if they contain JSON arrays
            if (item.Grantor) item.Grantor = formatMultipleOwners(item.Grantor);
            if (item.Grantee) item.Grantee = formatMultipleOwners(item.Grantee);
            return item;
        });

        // Base URL for instrument registration AJAX endpoints
        window.baseUrl = "{{ url('') }}";
        window.instrumentRegistrationBase = "{{ url('') }}";


        // Define critical functions in the global scope first
        function openBatchRegisterModal() {
            // Opening batch registration modal from inline script

            // Check if there are multiple instruments selected
            const checkedBoxes = document.querySelectorAll('.main-table-checkbox:checked:not([disabled])');
            const checkedCount = checkedBoxes.length;

            if (checkedCount >= 2) {
                // Multiple instruments selected - open quick batch modal
                // Opening quick batch modal for selected instruments

                // Get the selected instrument data
                const selectedInstruments = Array.from(checkedBoxes).map(checkbox => {
                    const id = checkbox.getAttribute('data-id');
                    const status = checkbox.getAttribute('data-status');

                    // Find the instrument data from serverCofoData
                    const instrumentData = serverCofoData.find(item => String(item.id) === String(id));

                    if (instrumentData) {
                        return {
                            id: instrumentData.id,
                            fileNo: instrumentData.fileno,
                            grantor: instrumentData.Grantor || '',
                            grantee: instrumentData.Grantee || '',
                            status: status,
                            instrumentType: instrumentData.instrument_type || '',
                            lga: instrumentData.lga || '',
                            district: instrumentData.district || '',
                            plotNumber: instrumentData.plotNumber || '',
                            plotSize: instrumentData.size || '',
                            plotDescription: instrumentData.propertyDescription || '',
                            duration: instrumentData.duration || instrumentData.leasePeriod || '',
                            deeds_date: instrumentData.deeds_date || instrumentData.instrumentDate || '',
                            deeds_time: instrumentData.deeds_time || '',
                            rootRegistrationNumber: instrumentData.rootRegistrationNumber || instrumentData.Deeds_Serial_No || '',
                            solicitorName: instrumentData.solicitorName || '',
                            solicitorAddress: instrumentData.solicitorAddress || '',
                            landUseType: instrumentData.landUseType || instrumentData.land_use || ''
                        };
                    }
                    return null;
                }).filter(item => item !== null);

                // Open quick batch modal with selected instruments
                if (typeof window.openQuickBatchModal === 'function') {
                    window.openQuickBatchModal(selectedInstruments);
                } else {
                    // Quick batch modal function not available
                }
            } else {
                // No selection or single selection - open normal batch modal
                // Opening normal batch modal
                if (typeof window.openBatchRegisterModalImplementation === 'function') {
                    window.openBatchRegisterModalImplementation();
                } else {
                    // Fallback implementation if main JS hasn't loaded yet
                    document.getElementById('batchRegisterModal').style.display = 'block';
                    // We'll reload the page after a slight delay to ensure JS is properly loaded
                    setTimeout(() => {
                        location.reload();
                    }, 500);
                }
            }
        }
    </script>
    @include('instrument_registration.partials.css')

    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include($headerPartial ?? 'admin.header')

        <!-- Main Content -->
        <div class="container mx-auto py-6 space-y-6 px-4">
            <!-- Page Header -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <h1 class="text-2xl font-bold">{{ $PageTitle ?? 'Instrument Registration' }}</h1>
                <div class="flex gap-2">
                    @if(Auth::user()->assign_role == 'Supper Admin' && !($isStDeeds ?? false))
                        <!-- Manage Instrument Types Button -->
                        <div x-data="{ open: false }" class="relative">
                            <button @click="$dispatch('open-instrument-types-modal')"
                                class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded-lg flex items-center gap-2 border border-gray-300">
                                <i class="fas fa-list"></i>
                                <span>Instrument Types</span>
                            </button>
                        </div>
                    @endif

                    @if(!($isStDeeds ?? false))
                    <button id="batchRegisterBtn" onclick="openBatchRegisterModal()"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg flex items-center gap-2">
                        <i class="fas fa-layer-group"></i>
                        <span id="batchBtnText">Registration</span>
                    </button>
                    @endif
                    <div class='hidden'>
                        <button id="exportRegistryBtn" onclick="openExportModal()"
                            class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg flex items-center gap-2">
                            <i class="fas fa-file-export"></i>
                            <span>Export Instruments</span>
                        </button>
                    </div>

                </div>
            </div>

            @if(Auth::user()->assign_role == 'Supper Admin')
                <!-- Instrument Types Management Modal -->
                @include('instrument_registration.modals.instrument_types_manager')
                <script src="{{ asset('js/instrument-types-manager.js') }}"></script>
            @endif

            <!-- Stats Cards -->
            @include('instrument_registration.partials.statistic_card')

            <!-- Main Content Table -->
            <div class="table-container">
                <!-- Table tabs & controls -->
                <div class="table-header px-6 py-4 flex justify-between items-center flex-wrap gap-4">
                    <div class="flex items-center gap-4">
                        <h2 class="text-lg font-semibold text-gray-900">Instruments</h2>
                        <div class="flex items-center gap-2 text-sm text-gray-600">
                            <i class="fas fa-database text-blue-500"></i>
                            <span id="totalRecordsCount">{{ $totalCount ?? 0 }} Total Records</span>
                        </div>
                        @if(!($isStDeeds ?? false))
                        <button type="button" onclick="openBatchPrintSessionModal()"
                            class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-2 px-4 rounded-lg flex items-center gap-2">
                            <i class="fas fa-print"></i>
                            <span>Print Batch RDS and CoR</span>
                        </button>
                        @endif
                    </div>


                    <!-- Search and Pagination Controls -->
                    <div class="flex items-center gap-4">
                        <!-- Records per page selector -->
                        <div class="flex items-center gap-2">
                            <label for="recordsPerPage" class="text-sm text-gray-600">Show:</label>
                            <select id="recordsPerPage" class="border border-gray-300 rounded px-2 py-1 text-sm">
                                <option value="10">10</option>
                                <option value="25" selected>25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>

                        <!-- Instrument Type Filter -->
                        <div class="flex items-center gap-2">
                            <label for="instrumentTypeFilter" class="text-sm text-gray-600">Type:</label>
                            <select id="instrumentTypeFilter"
                                class="border border-gray-300 rounded px-2 py-1 text-sm max-w-[200px]"
                                onchange="filterTable()">
                                <option value="">All Types</option>
                                @if(isset($instrumentTypes) && count($instrumentTypes) > 0)
                                    @foreach($instrumentTypes as $type)
                                        <option value="{{ is_object($type) ? ($type->name ?? $type) : $type }}">
                                            {{ is_object($type) ? ($type->name ?? $type) : $type }}
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                        </div>

                        <!-- Volume Filter -->
                        <div class="flex items-center gap-2">
                            <label for="volumeFilter" class="text-sm font-semibold text-gray-700">Vol:</label>
                            <select id="volumeFilter"
                                class="border border-gray-300 rounded px-2 py-1.5 text-sm w-24 bg-white focus:ring-2 focus:ring-blue-500 transition-all outline-none"
                                onchange="typeof filterTable === 'function' ? filterTable() : null">
                                <option value="">All</option>
                                @for ($i = 1; $i <= 999; $i++)
                                    <option value="{{ $i }}">{{ $i }}</option>
                                @endfor
                            </select>
                        </div>

                        <!-- Search -->
                        <div class="relative">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            <input id="searchInput" type="search" placeholder="Search by File No..."
                                class="search-input pl-10 pr-4 py-2.5 text-sm w-80 rounded-lg">
                        </div>
                    </div>
                </div>

                <!-- Table with Fixed Header -->
                <div class="table-wrapper" style="max-height: 600px; overflow-y: auto;">
                    <table class="min-w-full enhanced-table compact-table" id="instrumentTable">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <input type="checkbox" class="rounded" id="selectAll" onchange="toggleSelectAll(this)">
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                                    onclick="sortTable(1)">
                                    Reg Particulars
                                    <span class="inline-block align-middle" id="sortIcon-1"></span>
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                                    onclick="sortTable(2)">
                                    Instrument Type
                                    <span class="inline-block align-middle" id="sortIcon-2"></span>
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                                    onclick="sortTable(3)">
                                    Reg Time
                                    <span class="inline-block align-middle" id="sortIcon-3"></span>
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                                    onclick="sortTable(4)">
                                    REG DATE
                                    <span class="inline-block align-middle" id="sortIcon-4"></span>
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                                    onclick="sortTable(5)">
                                    Captured Time
                                    <span class="inline-block align-middle" id="sortIcon-5"></span>
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                                    onclick="sortTable(6)">
                                    Captured Date
                                    <span class="inline-block align-middle" id="sortIcon-6"></span>
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                                    onclick="sortTable(7)">
                                    FileNo
                                    <span class="inline-block align-middle" id="sortIcon-7"></span>
                                </th>
                                <!-- <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                                                                            onclick="sortTable(7)">
                                                                            Parent FileNo
                                                                            <span class="inline-block align-middle" id="sortIcon-7"></span>
                                                                        </th> -->
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                                    onclick="sortTable(8)">
                                    Status
                                    <span class="inline-block align-middle" id="sortIcon-8"></span>
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                                    onclick="sortTable(9)">
                                    Party 1
                                    <span class="inline-block align-middle" id="sortIcon-9"></span>
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                                    onclick="sortTable(10)">
                                    Party 2
                                    <span class="inline-block align-middle" id="sortIcon-10"></span>
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                                    onclick="sortTable(11)">
                                    LGA
                                    <span class="inline-block align-middle" id="sortIcon-11"></span>
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                                    onclick="sortTable(12)">
                                    District
                                    <span class="inline-block align-middle" id="sortIcon-12"></span>
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                                    onclick="sortTable(13)">
                                    Plot Number
                                    <span class="inline-block align-middle" id="sortIcon-13"></span>
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                                    onclick="sortTable(14)">
                                    Plot Size
                                    <span class="inline-block align-middle" id="sortIcon-14"></span>
                                </th>

                                @if(!($isStDeeds ?? false))
                                <th scope="col"
                                    class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Action
                                </th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="cofoTableBody">
                            @forelse($approvedApplications as $app)
                                <tr class="cofo-row" data-status="{{ $app->status }}" data-id="{{ $app->id }}">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @php
                                            $isDisabled = ($app->status === 'registered') && !($app->status === 'pending' && $app->instrument_type === 'Sectional Titling CofO');
                                        @endphp
                                        <input type="checkbox" class="rounded main-table-checkbox" data-id="{{ $app->id }}"
                                            data-status="{{ $app->status }}" data-instrument-type="{{ $app->instrument_type }}"
                                            data-fileno="{{ $app->fileno }}" {{ $isDisabled ? 'disabled' : '' }}
                                            onchange="handleMainTableCheckboxChange()">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        @if($app->status === 'registered')
                                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-md font-mono text-xs">{{ $app->Deeds_Serial_No ?? ($app->instrument_type === 'ST Fragmentation' ? '0/0/0' : '-') }}</span>
                                        @else
                                            <span class="text-gray-400 text-xs">-</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <div class="flex flex-col items-start space-y-1">
                                            @php
                                                $badgeTypeClass = match($app->instrument_type) {
                                                    'ST Fragmentation' => 'badge-st-fragmentation',
                                                    'ST Assignment (Transfer of Title)' => 'badge-st-assignment',
                                                    'Sectional Titling CofO' => 'badge-sectional-titling',
                                                    default => 'badge-other-instrument'
                                                };
                                                $badgeIcon = match($app->instrument_type) {
                                                    'ST Fragmentation' => 'fa-puzzle-piece',
                                                    'ST Assignment (Transfer of Title)' => 'fa-exchange-alt',
                                                    'Sectional Titling CofO' => 'fa-building',
                                                    default => 'fa-file-alt'
                                                };
                                            @endphp
                                            <span class="badge {{ $badgeTypeClass }}"><i class="fas {{ $badgeIcon }} mr-1"></i>{{ $app->instrument_type ?? 'Other' }}</span>
                                            @php
                                                $normType = strtoupper(trim($app->application_type ?? ''));
                                                $dispType = in_array($normType, ['PUA', 'SUA', 'PRIMARY']) ? $normType : 'DEEDS';
                                                $typeClass = match($normType) {
                                                    'PUA' => 'bg-blue-100 text-blue-800 border-blue-200',
                                                    'SUA' => 'bg-green-100 text-green-800 border-green-200',
                                                    'PRIMARY' => 'bg-orange-100 text-orange-800 border-orange-200',
                                                    default => 'bg-gray-100 text-gray-800 border-gray-200'
                                                };
                                            @endphp
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold tracking-wide border {{ $typeClass }}">{{ $dispType }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        @if($app->reg_time ?? null)<div class="flex items-center"><i class="fas fa-clock text-gray-400 mr-2"></i>{{ $app->reg_time }}</div>@else<span class="text-gray-400">-</span>@endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        @if($app->reg_date ?? null)<div class="flex items-center"><i class="fas fa-calendar-check text-green-400 mr-2"></i>{{ date('M d, Y', strtotime($app->reg_date)) }}</div>@else<span class="text-gray-400">-</span>@endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        @if($app->captured_date ?? null)<div class="flex items-center"><i class="fas fa-clock text-gray-400 mr-2"></i>{{ date('g:i A', strtotime($app->captured_date)) }}</div>@else<span class="text-gray-400">-</span>@endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        @if($app->captured_date ?? null)<div class="flex items-center"><i class="fas fa-calendar-plus text-blue-400 mr-2"></i>{{ date('M d, Y', strtotime($app->captured_date)) }}</div>@else<span class="text-gray-400">-</span>@endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="file-number">{{ $app->fileno ?? '-' }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="status-badge badge-{{ $app->status }}">{{ ucfirst($app->status) }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                                        @php
                                            $g1 = $app->Grantor ?? '-';
                                            $g1Arr = is_string($g1) && str_starts_with(trim($g1), '[') ? json_decode($g1, true) : (is_array($g1) ? $g1 : [$g1]);
                                            if (json_last_error() !== JSON_ERROR_NONE) $g1Arr = [$g1];
                                        @endphp
                                        @if(count($g1Arr) > 1)
                                            <span class="cursor-pointer underline decoration-dotted" onclick="Swal.fire({title: 'Grantors', html: `{!! implode('<br>', array_map('e', $g1Arr)) !!}` , icon: 'info'})">{{ $g1Arr[0] }} +{{ count($g1Arr)-1 }} more</span>
                                        @else
                                            {{ $g1Arr[0] ?? '-' }}
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                                        @php
                                            $g2 = $app->Grantee ?? '-';
                                            $g2Arr = is_string($g2) && str_starts_with(trim($g2), '[') ? json_decode($g2, true) : (is_array($g2) ? $g2 : [$g2]);
                                            if (json_last_error() !== JSON_ERROR_NONE) $g2Arr = [$g2];
                                        @endphp
                                        @if(count($g2Arr) > 1)
                                            <span class="cursor-pointer underline decoration-dotted" onclick="Swal.fire({title: 'Grantees', html: `{!! implode('<br>', array_map('e', $g2Arr)) !!}` , icon: 'info'})">{{ $g2Arr[0] }} +{{ count($g2Arr)-1 }} more</span>
                                        @else
                                            {{ $g2Arr[0] ?? '-' }}
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $app->lga ?? '-' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $app->district ?? '-' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $app->plotNumber ?? '-' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $app->size ?? '-' }}</td>
                                    @if(!($isStDeeds ?? false))
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                        <div class="dropdown-wrapper">
                                            <button class="action-button text-gray-500 hover:text-gray-700 p-2 rounded-md transition-colors duration-200" onclick="toggleDropdown(this, '{{ $app->id }}')" type="button">
                                                <i data-lucide="more-vertical" class="w-4 h-4"></i>
                                            </button>
                                        </div>
                                    </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="16" class="px-6 py-10 text-center text-gray-500">
                                        No instrument registrations available.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Controls -->
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-between">
                    <div class="flex items-center text-sm text-gray-700">
                        <span>Showing</span>
                        <span class="font-medium mx-1" id="showingStart">1</span>
                        <span>to</span>
                        <span class="font-medium mx-1" id="showingEnd">25</span>
                        <span>of</span>
                        <span class="font-medium mx-1" id="showingTotal">{{ $totalCount ?? 0 }}</span>
                        <span>results</span>
                    </div>

                    <div class="flex items-center space-x-2">
                        <button id="prevPage"
                            class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-chevron-left mr-1"></i>
                            Previous
                        </button>

                        <div id="pageNumbers" class="flex items-center space-x-1">
                            <!-- Page numbers will be dynamically generated -->
                        </div>

                        <button id="nextPage"
                            class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                            Next
                            <i class="fas fa-chevron-right ml-1"></i>
                        </button>
                    </div>
                </div>

                <script>
                    let sortDirections = {};
                    function sortTable(colIndex) {
                        const table = document.getElementById('instrumentTable');
                        const tbody = table.tBodies[0];
                        const rows = Array.from(tbody.querySelectorAll('tr')).filter(row => !row.querySelector('td[colspan]'));
                        const numericColumns = [14]; // Plot Size column (index 14) is numeric
                        const dateColumns = [4, 5]; // REG DATE (4), Captured Date (5)
                        const timeColumns = [3, 6]; // Reg Time (3), Captured Time (6)
                        
                        sortDirections[colIndex] = !sortDirections[colIndex];
                        rows.sort((a, b) => {
                            let aText = a.children[colIndex]?.innerText.trim() || '';
                            let bText = b.children[colIndex]?.innerText.trim() || '';
                            
                            if (numericColumns.includes(colIndex)) {
                                aText = parseFloat(aText.replace(/[^0-9.]/g, '')) || 0;
                                bText = parseFloat(bText.replace(/[^0-9.]/g, '')) || 0;
                            } else if (dateColumns.includes(colIndex)) {
                                const parseDate = (value) => value === '-' ? new Date(0) : new Date(value);
                                aText = parseDate(aText);
                                bText = parseDate(bText);
                            } else if (timeColumns.includes(colIndex)) {
                                const parseTime = (value) => {
                                    if (value === '-') return 0;
                                    // Try to parse AM/PM time
                                    const match = value.match(/(\d+):(\d+)\s*(AM|PM)/i);
                                    if (match) {
                                        let hours = parseInt(match[1]);
                                        const minutes = parseInt(match[2]);
                                        const ampm = match[3].toUpperCase();
                                        if (ampm === 'PM' && hours < 12) hours += 12;
                                        if (ampm === 'AM' && hours === 12) hours = 0;
                                        return hours * 60 + minutes;
                                    }
                                    return 0;
                                };
                                aText = parseTime(aText);
                                bText = parseTime(bText);
                            }
                            if (aText < bText) return sortDirections[colIndex] ? -1 : 1;
                            if (aText > bText) return sortDirections[colIndex] ? 1 : -1;
                            return 0;
                        });
                        // Remove all rows and re-append sorted
                        rows.forEach(row => tbody.appendChild(row));
                        // Update sort icons
                        document.querySelectorAll('[id^="sortIcon-"]').forEach(icon => {
                            icon.innerHTML = '';
                        });
                        const icon = document.getElementById('sortIcon-' + colIndex);
                        if (icon) icon.innerHTML = sortDirections[colIndex] ? '▲' : '▼';
                    }
                </script>
            </div>

            <!-- Application Type Legend -->
            <div class="bg-white border border-gray-200 rounded-lg px-6 py-4 flex flex-wrap items-center gap-6 shadow-sm">
                <div class="flex items-center gap-2 text-sm text-gray-700">
                    <span class="inline-flex h-3 w-3 rounded-full"
                        style="background-color: #dcfce7; border: 1px solid #bbf7d0;"></span>
                    <span><span class="font-semibold text-gray-900">SUA</span> = Standalone Unit Application</span>
                </div>
                <div class="flex items-center gap-2 text-sm text-gray-700">
                    <span class="inline-flex h-3 w-3 rounded-full"
                        style="background-color: #dbeafe; border: 1px solid #bfdbfe;"></span>
                    <span><span class="font-semibold text-gray-900">PUA</span> = Parented Unit Application</span>
                </div>
                <div class="flex items-center gap-2 text-sm text-gray-700">
                    <span class="inline-flex h-3 w-3 rounded-full"
                        style="background-color: #fed7aa; border: 1px solid #fdba74;"></span>
                    <span><span class="font-semibold text-gray-900">Primary</span> = Primary Application</span>
                </div>


                <div class="flex items-center gap-2 text-sm text-gray-700">
                    <span class="inline-flex h-3 w-3 rounded-full"
                        style="background-color: #038018ff; border: 1px solid #077210ff;"></span>
                    <span><span class="font-semibold text-gray-900">Deeds</span> = Other Instruments</span>
                </div>
            </div>
        </div>

        <!-- Mobile Dropdown Backdrop -->
        <div id="dropdown-backdrop" class="dropdown-backdrop hidden"></div>

        <!-- Dropdown Menu Container -->
        <div id="dropdown-menu" class="dropdown-menu hidden">
            <!-- Dynamic content will be populated here -->
        </div>

        <!-- Include Modals -->
        @include('instrument_registration.partials.singleregistermodal')
        @include('instrument_registration.partials.batchregistermodal')
        @include('instrument_registration.partials.quickbatchmodal')
        @include('instrument_registration.partials.batchprintmodal')

        <!-- Page Footer -->
        @include($footerPartial ?? 'admin.footer')
    </div>

    <script>
        // Initialize caches with the globally defined data
        window.appData = window.appData || {};
        window.instrumentLookup = window.instrumentLookup || new Map();

        function populateInstrumentCaches(data) {
            window.instrumentLookup.clear();
            // Clear appData object without reassignment
            for (let key in window.appData) {
                if (window.appData.hasOwnProperty(key)) delete window.appData[key];
            }

            data.forEach(entry => {
                const idKey = String(entry.id);
                window.appData[idKey] = entry;
                window.instrumentLookup.set(idKey, entry);
                if (entry.registered_instrument_id !== null && entry.registered_instrument_id !== undefined) {
                    window.instrumentLookup.set(String(entry.registered_instrument_id), entry);
                }
            });
        }

        if (window.serverCofoData) {
            populateInstrumentCaches(window.serverCofoData);
        }

        let sortDirections = {};
        function sortTable(colIndex) {
            const table = document.getElementById('instrumentTable');
            const tbody = table.tBodies[0];
            const rows = Array.from(tbody.querySelectorAll('tr')).filter(row => !row.querySelector('td[colspan]'));
            const numericColumns = [14]; // Plot Size
            const dateColumns = [4, 6]; // REG DATE, Captured Date
            const timeColumns = [3, 5]; // Reg Time, Captured Time
            
            sortDirections[colIndex] = !sortDirections[colIndex];
            const direction = sortDirections[colIndex] ? 1 : -1;

            rows.sort((a, b) => {
                let aCell = a.children[colIndex];
                let bCell = b.children[colIndex];
                let aText = aCell ? aCell.innerText.trim() : '';
                let bText = bCell ? bCell.innerText.trim() : '';
                
                if (numericColumns.includes(colIndex)) {
                    let aNum = parseFloat(aText.replace(/,/g, '')) || 0;
                    let bNum = parseFloat(bText.replace(/,/g, '')) || 0;
                    return (aNum - bNum) * direction;
                }
                
                if (dateColumns.includes(colIndex)) {
                    let aDate = new Date(aText);
                    let bDate = new Date(bText);
                    if (isNaN(aDate)) aDate = new Date(0);
                    if (isNaN(bDate)) bDate = new Date(0);
                    return (aDate - bDate) * direction;
                }

                if (timeColumns.includes(colIndex)) {
                    // Convert PM/AM to 24h for sorting
                    const parseTime = (t) => {
                        if (!t || t === '-') return 0;
                        let [time, modifier] = t.split(' ');
                        let [hours, minutes] = time.split(':');
                        if (hours === '12') hours = '00';
                        if (modifier === 'PM') hours = parseInt(hours, 10) + 12;
                        return parseInt(hours, 10) * 60 + parseInt(minutes, 10);
                    };
                    return (parseTime(aText) - parseTime(bText)) * direction;
                }
                
                return aText.localeCompare(bText) * direction;
            });

            // Update sort icons
            document.querySelectorAll('[id^="sortIcon-"]').forEach(icon => icon.innerHTML = '');
            const icon = document.getElementById(`sortIcon-${colIndex}`);
            if (icon) {
                icon.innerHTML = sortDirections[colIndex] ? ' <i class="fas fa-sort-up"></i>' : ' <i class="fas fa-sort-down"></i>';
            }

            rows.forEach(row => tbody.appendChild(row));
        }
    </script>

    <!-- Include SweetAlert -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Include the external JavaScript file -->
    <script src="{{ asset('js/instrument_registration.js') }}?v={{ time() }}"></script>

    <!-- Include the updated JavaScript file for single registration modal -->
    <script src="{{ asset('js/instrument_registration_updated.js') }}?v={{ time() }}"></script>

    <!-- Include the FINAL batch registration handler -->
    <script src="{{ asset('js/batch_registration_handler_final.js') }}?v={{ time() }}"></script>

    <!-- Include the batch fix to handle empty selectedBatchProperties -->

    <!-- Include the quick batch handler -->
    <script src="{{ asset('js/quick_batch_handler.js') }}?v={{ time() }}">

    </script>


    @if(session('success'))
        <script>
            Swal.fire({
                title: 'Success!',
                text: "{{ session('success') }}",
                icon: 'success',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
        </script>
    @endif

    @if(session('error'))
        <script>
            Swal.fire({
                title: 'Error!',
                text: "{{ session('error') }}",
                icon: 'error',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
        </script>
    @endif

    <!-- Floating UI CDN -->
    <script src="https://cdn.jsdelivr.net/npm/@floating-ui/dom@1.5.3/dist/floating-ui.dom.min.js"></script>
    @include('instrument_registration.modals.export_preview')

    <script src="{{ asset('js/property-timeline-modal.js') }}"></script>
    <script src="{{ asset('js/instrument_registration_index.js') }}"></script>
    <script src="{{ asset('js/instrument_registration_batch_print.js') }}?v={{ time() }}"></script>
@endsection
@extends('layouts.app')
@section('page-title')
{{ __('Users') }}
@endsection
@php
    $profile = asset(Storage::url('upload/profile/'));

    $normalizeStaffTypeCategory = function ($value) {
        return strtoupper(trim($value ?? ''));
    };

    $mlppUsers = $users->filter(function ($user) use ($normalizeStaffTypeCategory) {
        $category = $normalizeStaffTypeCategory($user->staff_type_category ?? null);

        return $category === 'MLPP' || $category === '';
    })->values();

    $mdcUsers = $users->filter(function ($user) use ($normalizeStaffTypeCategory) {
        $category = $normalizeStaffTypeCategory($user->staff_type_category ?? null);
        return $category === 'MDC' || $category === 'MDCM';
    })->values();
@endphp
 
@section('content')
     <!-- Hide Alpine x-cloak elements until initialized -->
     <style>
        [x-cloak] { display: none !important; }
        .user-avatar {
            transition: transform 0.2s ease-in-out;
        }
        .user-avatar:hover {
            transform: scale(1.1);
        }
        .table-row {
            transition: all 0.2s ease-in-out;
        }
        .table-row:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .action-btn {
            transition: all 0.2s ease-in-out;
        }
        .action-btn:hover {
            transform: scale(1.1);
        }
        .search-input {
            transition: all 0.3s ease;
        }
        .search-input:focus {
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        /* DataTables custom styling */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            margin-bottom: 1rem;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.5rem 0.75rem;
            margin: 0 0.25rem;
            border-radius: 0.375rem;
            border: 1px solid #e5e7eb;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background-color: #4f46e5;
            color: white;
            border-color: #4f46e5;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background-color: #e0e7ff;
            color: #4f46e5;
        }
        .dataTable thead th {
            background-color: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
        }
        .dataTable tbody tr {
            border-bottom: 1px solid #e5e7eb;
        }
        .dataTable tbody tr:hover {
            background-color: #f0f4ff;
        }
        .dt-buttons {
            margin-bottom: 1rem;
        }
        .dt-buttons .btn {
            padding: 0.5rem 1rem;
            margin-right: 0.5rem;
            border-radius: 0.375rem;
            background-color: #6b7280;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
        }
        .dt-buttons .btn:hover {
            background-color: #4b5563;
        }
     </style>
    <!-- Main Content -->
    <div class="flex-1 overflow-auto" x-data="{ 
        rolesModalOpen: false, 
        modalRoles: '',
        activeTab: 'mdc'
    }">
    <!-- Enhanced Roles Modal -->
    <div x-show="rolesModalOpen" x-cloak 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" 
         @click.away="rolesModalOpen = false">
        <div x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform scale-95"
             x-transition:enter-end="opacity-100 transform scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform scale-100"
             x-transition:leave-end="opacity-0 transform scale-95"
             class="bg-white rounded-xl shadow-2xl overflow-hidden w-full max-w-md mx-auto">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-indigo-500 to-purple-600 px-6 py-4 flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <div class="bg-white bg-opacity-20 rounded-lg p-2">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-white">{{ __('All User Roles') }}</h3>
                </div>
                <button class="text-white hover:text-gray-200 transition-colors duration-200 p-1 rounded-full hover:bg-white hover:bg-opacity-20" 
                        @click="rolesModalOpen = false">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            
            <!-- Modal Body -->
            <div class="p-6">
                <div class="space-y-3">
                    <p class="text-sm text-gray-600 mb-4">{{ __('Complete list of roles assigned to this user:') }}</p>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex flex-wrap gap-2" x-data="{ roles: modalRoles.split(', ') }">
                            <template x-for="role in roles" :key="role">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-indigo-100 text-indigo-800 border border-indigo-200">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    <span x-text="role.trim()"></span>
                                </span>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="bg-gray-50 px-6 py-3 flex justify-end">
                <button @click="rolesModalOpen = false" 
                        class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded-lg transition-colors duration-200">
                    {{ __('Close') }}
                </button>
            </div>
        </div>
    </div>
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6 space-y-6">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm font-medium">{{ __('Total Users') }}</p>
                            <p class="text-3xl font-bold">{{ $users->count() }}</p>
                        </div>
                        <div class="bg-blue-400 bg-opacity-30 rounded-full p-3">
                            <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm font-medium">{{ __('Admin Users') }}</p>
                            <p class="text-3xl font-bold">{{ $users->where('type', 'admin')->count() }}</p>
                        </div>
                        <div class="bg-purple-400 bg-opacity-30 rounded-full p-3">
                            <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-orange-500 to-orange-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-orange-100 text-sm font-medium">{{ __('Regular Users') }}</p>
                            <p class="text-3xl font-bold">{{ $users->where('type', 'user')->count() }}</p>
                        </div>
                        <div class="bg-orange-400 bg-opacity-30 rounded-full p-3">
                            <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-indigo-500 to-indigo-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-indigo-100 text-sm font-medium">{{ __('New This Month') }}</p>
                            <p class="text-3xl font-bold">{{ $users->where('created_at', '>=', now()->startOfMonth())->count() }}</p>
                        </div>
                        <div class="bg-indigo-400 bg-opacity-30 rounded-full p-3">
                            <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200">
                <!-- Enhanced Header -->
                <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b border-gray-200">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
                        <div class="flex items-center space-x-3">
                            <div class="bg-indigo-100 rounded-lg p-2">
                                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                                </svg>
                            </div>
                            <div>
                                <h5 class="text-xl font-bold text-gray-900">{{ __('User Management') }}</h5>
                                <p class="text-sm text-gray-600">{{ __('Manage and monitor all system users') }}</p>
                            </div>
                        </div>
                         
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center space-y-3 sm:space-y-0 sm:space-x-3">
                            <!-- Import Users Button -->
                            <button onclick="openImportModal()" class="inline-flex items-center justify-center px-6 py-2.5 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white text-sm font-semibold rounded-lg shadow-md hover:shadow-lg transition-all duration-200 transform hover:scale-105">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" />
                                </svg>
                                {{ __('Import CSV') }}
                            </button>

                            <!-- Create User Button -->
                            <a href="#" class="inline-flex items-center justify-center px-6 py-2.5 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white text-sm font-semibold rounded-lg shadow-md hover:shadow-lg transition-all duration-200 transform hover:scale-105 customModal" 
                               data-size="lg" data-url="{{ route('users.create') }}" data-title="{{ __('Create New User') }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
                                </svg>
                                {{ __('Create User') }}
                            </a>
                        </div>
                    </div>
                </div>
                <div class="px-6 pt-6 border-b border-gray-200 bg-white">
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button"
                                class="inline-flex items-center px-4 py-2 rounded-lg border text-sm font-semibold transition-all duration-200"
                                :class="activeTab === 'mdc' ? 'bg-indigo-600 text-white border-indigo-600 shadow-lg shadow-indigo-200/60' : 'bg-white text-gray-600 border-gray-200 hover:text-indigo-600 hover:border-indigo-300'"
                                @click="activeTab = 'mdc'; $nextTick(() => window.dispatchEvent(new CustomEvent('users-tab-change', { detail: 'mdcUsersDataTable' })))">
                            <span>{{ __('MDC - Mass Data Capture') }}</span>
                            <span class="ml-2 inline-flex items-center justify-center px-2 py-0.5 text-xs font-semibold rounded-full"
                                  :class="activeTab === 'mdc' ? 'bg-white text-indigo-600' : 'bg-indigo-50 text-indigo-600'">
                                {{ $mdcUsers->count() }}
                            </span>
                        </button>

                        <button type="button"
                                class="inline-flex items-center px-4 py-2 rounded-lg border text-sm font-semibold transition-all duration-200"
                                :class="activeTab === 'mlpp' ? 'bg-indigo-600 text-white border-indigo-600 shadow-lg shadow-indigo-200/60' : 'bg-white text-gray-600 border-gray-200 hover:text-indigo-600 hover:border-indigo-300'"
                                @click="activeTab = 'mlpp'; $nextTick(() => window.dispatchEvent(new CustomEvent('users-tab-change', { detail: 'mlppUsersDataTable' })))">
                            <span>{{ __('MLPP - Ministry of Land and Physical Planning') }}</span>
                            <span class="ml-2 inline-flex items-center justify-center px-2 py-0.5 text-xs font-semibold rounded-full"
                                  :class="activeTab === 'mlpp' ? 'bg-white text-indigo-600' : 'bg-indigo-50 text-indigo-600'">
                                {{ $mlppUsers->count() }}
                            </span>
                        </button>
                    </div>
                </div>
                <!-- Enhanced Table -->
                <div class="overflow-hidden">
                    <div x-show="activeTab === 'mdc'" x-cloak class="users-tab-panel">
                        @include('user.partials.users-table', [
                            'users' => $mdcUsers,
                            'tableId' => 'mdcUsersDataTable',
                            'emptyStateTitle' => __('No MDC users found'),
                            'emptyStateSubtitle' => __('Invite new MDC users or adjust filters.')
                        ])
                    </div>
                    <div x-show="activeTab === 'mlpp'" x-cloak class="users-tab-panel">
                        @include('user.partials.users-table', [
                            'users' => $mlppUsers,
                            'tableId' => 'mlppUsersDataTable',
                            'emptyStateTitle' => __('No MLPP users found'),
                            'emptyStateSubtitle' => __('Create a new MLPP user or adjust filters.')
                        ])
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        @include('admin.footer')
    </div>

<script>
$(document).ready(function() {
    function attachModalHandlers() {
        $('.customModal').off('click').on('click', function(e) {
            e.preventDefault();
            var url = $(this).data('url');
            var size = $(this).data('size') || 'md';
            var title = $(this).data('title');
            if (typeof showModal === 'function') {
                showModal(url, size, title);
            }
        });
    }

    function attachConfirmDialogs() {
        $('.confirm_dialog').off('click').on('click', function(e) {
            if (!confirm($(this).data('dialog-text'))) {
                e.preventDefault();
            }
        });
    }

    const dataTableOptions = {
        responsive: true,
        lengthChange: true,
        autoWidth: false,
        pageLength: 10,
        lengthMenu: [ [10, 25, 50, 100, -1], [10, 25, 50, 100, "All"] ],
        order: [[0, 'asc']],
        dom: '<"row mb-3"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        language: {
            search: "{{ __('Search:') }}",
            lengthMenu: "{{ __('Show _MENU_ entries') }}",
            info: "{{ __('Showing _START_ to _END_ of _TOTAL_ entries') }}",
            infoEmpty: "{{ __('Showing 0 to 0 of 0 entries') }}",
            paginate: {
                first: "{{ __('First') }}",
                last: "{{ __('Last') }}",
                next: "{{ __('Next') }}",
                previous: "{{ __('Previous') }}"
            },
            loadingRecords: "{{ __('Loading...') }}",
            processing: "{{ __('Processing...') }}",
            emptyTable: "{{ __('No data available in table') }}"
        },
        columnDefs: [
            {
                targets: -1,
                orderable: false,
                searchable: false
            }
        ],
        initComplete: function() {
            const api = this.api();
            $('.dataTables_length label').addClass('text-sm text-gray-600');
            $('.dataTables_filter label').addClass('text-sm text-gray-600');
            $('.dataTables_filter input').addClass('rounded border-gray-300 pl-3 pr-3 py-2');

            attachModalHandlers();
            attachConfirmDialogs();
            api.columns.adjust();
        },
        drawCallback: function() {
            attachModalHandlers();
            attachConfirmDialogs();
            this.api().columns.adjust();
        }
    };

    const tableIds = ['mdcUsersDataTable', 'mlppUsersDataTable'];
    const dataTablesInstances = {};

    tableIds.forEach(function(tableId) {
        const selector = '#' + tableId;
        if ($(selector).length) {
            dataTablesInstances[tableId] = $(selector).DataTable($.extend(true, {}, dataTableOptions));
        }
    });

    if (dataTablesInstances['mdcUsersDataTable']) {
        setTimeout(function() {
            dataTablesInstances['mdcUsersDataTable'].columns.adjust();
        }, 100);
    }

    window.addEventListener('users-tab-change', function(event) {
        const tableId = event.detail;
        if (dataTablesInstances[tableId]) {
            setTimeout(function() {
                dataTablesInstances[tableId].columns.adjust();
            }, 100);
        }
    });

    $(window).on('resize', function() {
        Object.keys(dataTablesInstances).forEach(function(key) {
            dataTablesInstances[key].columns.adjust();
        });
    });

    attachModalHandlers();
    attachConfirmDialogs();

    window.openImportModal = function() {
        $.ajax({
            url: '{{ route("users.import.form") }}',
            type: 'GET',
            dataType: 'html',
            success: function(html) {
                let modalId = 'importModal_' + Date.now();
                let modalHTML = `
                    <div id="${modalId}" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4 overflow-y-auto">
                        <div class="my-auto w-full">
                            ${html}
                        </div>
                    </div>
                `;
                
                $('body').append(modalHTML);
                window.currentModalId = modalId;
            },
            error: function() {
                alert('{{ __("Failed to load import form") }}');
            }
        });
    };

    window.clearTestData = function() {
        if (!confirm('{{ __("Are you sure you want to delete all TEST environment users? This action cannot be undone.") }}')) {
            return;
        }

        $.ajax({
            url: '{{ route("users.clear.test") }}',
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    Object.keys(dataTablesInstances).forEach(function(key) {
                        var instance = dataTablesInstances[key];
                        if (instance.ajax) {
                            instance.ajax.reload();
                        } else {
                            instance.draw(false);
                        }
                    });
                    location.reload();
                } else {
                    alert(response.message);
                }
            },
            error: function(xhr) {
                let errorMsg = '{{ __("Error clearing test data") }}';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                alert(errorMsg);
            }
        });
    };
});
</script>
    
@endsection

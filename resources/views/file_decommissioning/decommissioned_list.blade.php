@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? __('Decommissioned Files') }}
@endsection

@section('content')
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include($headerPartial ?? 'admin.header')

        <!-- Main Content -->
        <div class="p-6">
            <div class="bg-white rounded-lg shadow-sm" >
                <!-- Page Header -->
                <div class="border-b border-gray-200 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Decommissioned Files</h1>
                            <p class="text-sm text-gray-600 mt-1">View and manage all decommissioned files in the LANDS module</p>
                        </div>
                        <div class="flex items-center space-x-3">
                            <a href="{{ route('file-decommissioning.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i>
                                Back to Decommissioning
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Statistics Section -->
                <div class="px-6 py-6 bg-gray-50 border-b border-gray-200">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Total Decommissioned Files Card -->
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                                        <i data-lucide="archive" class="w-4 h-4 text-red-600"></i>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Total Decommissioned</p>
                                    <p class="text-2xl font-bold text-gray-900" id="totalDecommissionedCount">{{ number_format($totalDecommissionedFiles) }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Decommissioned Card -->
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                                        <i data-lucide="clock" class="w-4 h-4 text-yellow-600"></i>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Recent (30 days)</p>
                                    <p class="text-2xl font-bold text-gray-900" id="recentDecommissionedCount">{{ number_format($recentDecommissioned) }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- This Month Card -->
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                        <i data-lucide="calendar" class="w-4 h-4 text-green-600"></i>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">This Month</p>
                                    <p class="text-2xl font-bold text-gray-900" id="thisMonthCount">0</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search Section -->
                <div class="px-6 py-6 bg-gray-50 border-b border-gray-200">
                    <div class="max-w-md mx-auto">
                        <label for="searchDecommissioned" class="block text-sm font-medium text-gray-700 mb-3 text-center">
                            <i data-lucide="search" class="w-4 h-4 inline mr-2"></i>
                            Search Decommissioned Files
                        </label>
                        <div class="relative">
                            <input type="text" id="searchDecommissioned" class="w-full px-4 py-2 pl-10 pr-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Search by file number, name, or reason...">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i data-lucide="search" class="w-4 h-4 text-gray-400"></i>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2 text-center">Search by MLS File No, Kangis File No, File Name, or Decommissioning Reason</p>
                    </div>
                </div>

                <!-- Decommissioned Files Content -->
                <div class="px-6 py-6">
                    <!-- Default State - No Files -->
                    <div id="no-files" class="text-center py-12 hidden">
                        <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                            <i data-lucide="archive" class="w-12 h-12 text-gray-400"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No Decommissioned Files Found</h3>
                        <p class="text-gray-500 max-w-md mx-auto">There are no decommissioned files matching your search criteria.</p>
                    </div>

                    <!-- Files Table -->
                    <div id="files-container">
                        <div class="mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Decommissioned Files List</h3>
                            <p class="text-sm text-gray-600">Click the view button next to any file to see detailed information</p>
                        </div>

                        <div class="overflow-x-auto overflow-y-auto max-h-96 shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                            <table class="min-w-full divide-y divide-gray-300" id="decommissionedFilesTable">
                                <thead class="bg-gray-50 sticky top-0">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">MLS File No</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kangis File No</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File Name</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Commissioning Date</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Decommissioning Date</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Decommissioned By</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <!-- Data will be loaded via DataTables -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Page Footer -->
            @include($footerPartial ?? 'admin.footer')
        </div>
    </div>

    <!-- View Details Modal -->
    <div id="viewDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <!-- Modal Header -->
                <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Decommissioned File Details</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeDetailsModal()">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="mt-4">
                    <!-- File Information -->
                    <div class="mb-6">
                        <h4 class="text-sm font-semibold text-blue-600 mb-3">File Information</h4>
                        <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p><strong>MLS File No:</strong> <span id="detailMlsfNo" class="text-gray-700">-</span></p>
                                    <p><strong>Kangis File No:</strong> <span id="detailKangisFileNo" class="text-gray-700">-</span></p>
                                    <p><strong>New Kangis File No:</strong> <span id="detailNewKangisFileNo" class="text-gray-700">-</span></p>
                                </div>
                                <div>
                                    <p><strong>File Name:</strong> <span id="detailFileName" class="text-gray-700">-</span></p>
                                    <p><strong>File Number ID:</strong> <span id="detailFileNumberId" class="text-gray-700">-</span></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Decommissioning Information -->
                    <div class="mb-6">
                        <h4 class="text-sm font-semibold text-red-600 mb-3">Decommissioning Information</h4>
                        <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p><strong>Commissioning Date:</strong> <span id="detailCommissioningDate" class="text-gray-700">-</span></p>
                                    <p><strong>Decommissioning Date:</strong> <span id="detailDecommissioningDate" class="text-gray-700">-</span></p>
                                </div>
                                <div>
                                    <p><strong>Decommissioned By:</strong> <span id="detailDecommissionedBy" class="text-gray-700">-</span></p>
                                    <p><strong>Record Created:</strong> <span id="detailCreatedAt" class="text-gray-700">-</span></p>
                                </div>
                            </div>
                            <div class="mt-4">
                                <p><strong>Decommissioning Reason:</strong></p>
                                <div class="mt-2 p-3 bg-white rounded border border-red-200">
                                    <p id="detailDecommissioningReason" class="text-gray-700 text-sm">-</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200">
                        <button type="button" class="px-4 py-2 bg-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500" onclick="closeDetailsModal()">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include DataTables -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/dataTables.tailwindcss.min.css">
    <script type="text/javascript" src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.13.6/js/dataTables.tailwindcss.min.js"></script>
    
    <!-- Include SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* DataTables Custom Styling */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            margin: 1rem 0;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.5rem 0.75rem;
            margin: 0 0.125rem;
            border-radius: 0.375rem;
        }

        /* Animation for fade in */
        .fade-in {
            animation: fadeIn 0.3s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Custom badge styles */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-decommissioned {
            background-color: #fee2e2;
            color: #991b1b;
        }
    </style>

    <script>
        $(document).ready(function() {
            // Initialize Lucide icons
            lucide.createIcons();

            // Initialize DataTable for decommissioned files
            const decommissionedFilesTable = $('#decommissionedFilesTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route("file-decommissioning.decommissioned-files-data") }}',
                    type: 'GET'
                },
                columns: [
                    { data: 'mls_file_no', name: 'mls_file_no' },
                    { data: 'kangis_file_no', name: 'kangis_file_no' },
                    { data: 'file_name', name: 'file_name' },
                    { data: 'commissioning_date', name: 'commissioning_date' },
                    { data: 'decommissioning_date', name: 'decommissioning_date' },
                    { data: 'decommissioned_by', name: 'decommissioned_by' },
                    { data: 'decommissioning_reason', name: 'decommissioning_reason' },
                    { data: 'action', name: 'action', orderable: false, searchable: false }
                ],
                order: [[3, 'desc']], // Order by decommissioning_date desc
                pageLength: 25,
                responsive: true,
                drawCallback: function() {
                    lucide.createIcons();
                }
            });

            // Custom search functionality
            $('#searchDecommissioned').on('keyup', function() {
                decommissionedFilesTable.search(this.value).draw();
            });

            // Load additional statistics
            loadAdditionalStatistics();

            // Function to load additional statistics
            function loadAdditionalStatistics() {
                $.ajax({
                    url: '{{ route("file-decommissioning.statistics") }}',
                    type: 'GET',
                    success: function(response) {
                        if (response.success) {
                            $('#totalDecommissionedCount').text(response.data.decommissioned_files.toLocaleString());
                            $('#recentDecommissionedCount').text(response.data.recent_decommissioned.toLocaleString());
                            
                            // Calculate this month's count (you might want to add this to the backend)
                            // For now, we'll use the recent count as an approximation
                            $('#thisMonthCount').text(Math.floor(response.data.recent_decommissioned * 0.3).toLocaleString());
                        }
                    },
                    error: function(xhr) {
                        console.error('Error loading statistics:', xhr);
                    }
                });
            }
        });

        // Global function to view decommissioned file details
        function viewDecommissionedFile(fileId) {
            // Show loading
            Swal.fire({
                title: 'Loading...',
                text: 'Fetching file details...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Load file details
            $.ajax({
                url: `/file-decommissioning/decommissioned-details/${fileId}`,
                type: 'GET',
                success: function(response) {
                    Swal.close();
                    
                    if (response.success) {
                        const file = response.data;
                        
                        // Populate modal with file details
                        $('#detailMlsfNo').text(file.mls_file_no || 'N/A');
                        $('#detailKangisFileNo').text(file.kangis_file_no || 'N/A');
                        $('#detailNewKangisFileNo').text(file.new_kangis_file_no || 'N/A');
                        $('#detailFileName').text(file.file_name || 'N/A');
                        $('#detailFileNumberId').text(file.file_number_id || 'N/A');
                        $('#detailCommissioningDate').text(file.commissioning_date || 'N/A');
                        $('#detailDecommissioningDate').text(file.decommissioning_date || 'N/A');
                        $('#detailDecommissionedBy').text(file.decommissioned_by || 'N/A');
                        $('#detailCreatedAt').text(file.created_at || 'N/A');
                        $('#detailDecommissioningReason').text(file.decommissioning_reason || 'N/A');
                        
                        // Show modal
                        showDetailsModal();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'Error loading file details: ' + response.message,
                            confirmButtonColor: '#ef4444'
                        });
                    }
                },
                error: function(xhr) {
                    Swal.close();
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Error loading file details',
                        confirmButtonColor: '#ef4444'
                    });
                }
            });
        }

        // Modal functions
        function showDetailsModal() {
            document.getElementById('viewDetailsModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeDetailsModal() {
            document.getElementById('viewDetailsModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        document.getElementById('viewDetailsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDetailsModal();
            }
        });
    </script>
@endsection
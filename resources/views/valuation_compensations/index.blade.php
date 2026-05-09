@extends('layouts.app')

@section('page-title')
    {{ __('Valuation for Compensation') }}
@endsection

@section('content')
<div x-data="{}" class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle' => 'Valuation for Compensation',
        'PageDescription' => 'Manage and generate property compensation valuations'
    ])

    <div class="p-6 space-y-8 max-w-7xl mx-auto">
        <!-- Header & Statistics -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-900">Valuation for Compensation</h1>
                <p class="text-slate-500 mt-1">Manage property compensation records and valuations</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('valuation-compensations.projects.index') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-white border border-slate-200 text-slate-700 font-semibold shadow-sm hover:bg-slate-50 transition">
                    <i data-lucide="layout-dashboard" class="h-5 w-5"></i>
                    <span>Project Manager Console</span>
                </a>
                <button type="button" onclick="openCreateModal()" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-blue-600 text-white font-semibold shadow hover:bg-blue-700 transition">
                    <i data-lucide="plus-circle" class="h-5 w-5"></i>
                    <span>Add New Valuation</span>
                </button>
            </div>
        </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100 flex items-center gap-4">
            <div class="h-12 w-12 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center">
                <i data-lucide="file-text" class="h-6 w-6"></i>
            </div>
            <div>
                <p class="text-sm text-slate-500 font-medium uppercase tracking-wider">Total Records</p>
                <h3 class="text-2xl font-bold text-slate-800">{{ number_format($stats['total_valuations']) }}</h3>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100 flex items-center gap-4">
            <div class="h-12 w-12 bg-green-50 text-green-600 rounded-lg flex items-center justify-center">
                <i data-lucide="banknote" class="h-6 w-6"></i>
            </div>
            <div>
                <p class="text-sm text-slate-500 font-medium uppercase tracking-wider">Total Compensation</p>
                <h3 class="text-2xl font-bold text-slate-800">₦{{ number_format($stats['total_amount'], 2) }}</h3>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100 flex items-center gap-4">
            <div class="h-12 w-12 bg-amber-50 text-amber-600 rounded-lg flex items-center justify-center">
                <i data-lucide="home" class="h-6 w-6"></i>
            </div>
            <div>
                <p class="text-sm text-slate-500 font-medium uppercase tracking-wider">Total Buildings</p>
                <h3 class="text-2xl font-bold text-slate-800">{{ number_format($stats['total_buildings']) }}</h3>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100 flex items-center gap-4">
            <div class="h-12 w-12 bg-purple-50 text-purple-600 rounded-lg flex items-center justify-center">
                <i data-lucide="calendar" class="h-6 w-6"></i>
            </div>
            <div>
                <p class="text-sm text-slate-500 font-medium uppercase tracking-wider">Recorded Today</p>
                <h3 class="text-2xl font-bold text-slate-800">{{ number_format($stats['today_count']) }}</h3>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-6 border-b border-slate-100 bg-slate-50/50">
            <h2 class="text-lg font-semibold text-slate-800">Valuation Records</h2>
        </div>
        <div class="p-6">
            <table id="valuationTable" class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-slate-500 text-sm uppercase tracking-wider">
                        <th class="pb-4 font-semibold">Our Ref</th>
                        <th class="pb-4 font-semibold">Owner Name</th>
                        <th class="pb-4 font-semibold">Location</th>
                        <th class="pb-4 font-semibold">Building Type</th>
                        <th class="pb-4 font-semibold">Amount (₦)</th>
                        <th class="pb-4 font-semibold">Date</th>
                        <th class="pb-4 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-slate-600 text-sm divide-y divide-slate-100">
                    @foreach($records as $record)
                    <tr class="hover:bg-slate-50/80 transition-colors group">
                        <td class="py-4 font-mono font-bold text-blue-600">{{ $record->our_ref ?? 'N/A' }}</td>
                        <td class="py-4 font-medium text-slate-800 uppercase">{{ $record->owner_name }}</td>
                        <td class="py-4 text-xs">{{ Str::limit($record->location, 40) }}</td>
                        <td class="py-4">{{ $record->building_type }} ({{ $record->building_count }})</td>
                        <td class="py-4 font-bold text-teal-600">₦{{ number_format($record->compensation_amount, 2) }}</td>
                        <td class="py-4">{{ $record->valuation_date->format('d M, Y') }}</td>
                        <td class="py-4 text-right">
                            <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                <a href="{{ route('valuation-compensations.show', $record->id) }}" target="_blank" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg" title="View/Print">
                                    <i data-lucide="printer" class="h-4 w-4"></i>
                                </a>
                                <button onclick="openEditModal({{ json_encode($record) }})" class="p-2 text-amber-600 hover:bg-amber-50 rounded-lg" title="Edit">
                                    <i data-lucide="edit-3" class="h-4 w-4"></i>
                                </button>
                                <button onclick="deleteRecord({{ $record->id }}, '{{ $record->owner_name }}')" class="p-2 text-red-600 hover:bg-red-50 rounded-lg" title="Delete">
                                    <i data-lucide="trash-2" class="h-4 w-4"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@include('valuation_compensations.partials.modal')

@push('scripts')
<script>
    let allBanks = [];

    async function fetchBanks() {
        try {
            const response = await fetch('https://api.nigerianbanklogos.xyz');
            allBanks = await response.json();
            // Sort by title
            allBanks.sort((a, b) => a.title.localeCompare(b.title));
            console.log('Banks loaded from API:', allBanks.length);
        } catch (error) {
            console.error('Error fetching banks:', error);
            // Fallback basic list if API fails
            allBanks = [
                { title: 'Access Bank', route: 'https://nbl.xyz/library/access-bank.svg' },
                { title: 'Zenith Bank', route: 'https://nbl.xyz/library/zenith-bank.svg' },
                { title: 'First Bank', route: 'https://nbl.xyz/library/first-bank.svg' },
                { title: 'GTBank', route: 'https://nbl.xyz/library/gtbank.svg' },
                { title: 'UBA', route: 'https://nbl.xyz/library/uba.svg' },
            ];
        }
    }

    let projectsData = [];

    async function loadProjectsForSelection() {
        try {
            const response = await fetch("{{ route('valuation-compensations.projects.selection') }}");
            projectsData = await response.json();
            const $projSelect = $('#vfc_project_id');
            $projSelect.html('<option value="">Select Project</option>');
            projectsData.forEach(p => {
                $projSelect.append(`<option value="${p.id}">${p.name} (${p.code})</option>`);
            });
        } catch (error) {
            console.error('Error loading projects:', error);
        }
    }

    $('#vfc_project_id').on('change', function() {
        const projId = $(this).val();
        if (!projId) {
            $('#project-info').addClass('hidden');
            $('#vfc_worker_id').html('<option value="">Select Worker (Select Project First)</option>');
            return;
        }

        const proj = projectsData.find(p => p.id == projId);
        if (proj) {
            $('#proj_total').text(proj.total_items);
            $('#proj_rem').text(proj.remaining);
            $('#project-info').removeClass('hidden');
        }

        // Fetch workers for this project
        $.get(`/valuation-compensations/projects/${projId}/workers`, function(workers) {
            const $workerSelect = $('#vfc_worker_id');
            $workerSelect.html('<option value="">Select Worker</option>');
            workers.forEach(w => {
                $workerSelect.append(`<option value="${w.worker_code}">${w.user.first_name} ${w.user.last_name} (${w.worker_code})</option>`);
            });
        });
    });

    $(document).ready(function() {
        loadProjectsForSelection();
        // Initialize Lucide
        if (window.lucide) window.lucide.createIcons();

        // Fetch banks on load
        fetchBanks();

        // Initialize DataTable
        const table = $('#valuationTable').DataTable({
            responsive: true,
            order: [[5, 'desc']],
            pageLength: 10,
            dom: '<"flex flex-col md:flex-row justify-between gap-4 mb-4"fB>rt<"flex flex-col md:flex-row justify-between items-center gap-4 mt-4"ip>',
            buttons: [
                'copy', 'csv', 'excel', 'pdf', 'print'
            ]
        });

        // Location Auto-builder
        $('.loc-trigger, #loc_district, #loc_lga, #loc_state').on('change input', function() {
            buildLocation();
        });

        // Auto-calculation
        $('.calc-trigger').on('input', function() {
            calculateCompensation();
        });

        // Building Type 'Other' logic
        $('#building_type').on('change', function() {
            if ($(this).val() === 'Other') {
                $('#building_type_other').removeClass('hidden').prop('required', true).focus();
            } else {
                $('#building_type_other').addClass('hidden').prop('required', false);
            }
        });

        // Compensated Items logic
        $('.item-checkbox, #item-other-check').on('change', function() {
            if ($('#item-other-check').is(':checked')) {
                $('#compensated_items_other').removeClass('hidden').focus();
            } else {
                $('#compensated_items_other').addClass('hidden');
            }
            updateCompensatedItemsValue();
        });

        $('#compensated_items_other').on('input', function() {
            updateCompensatedItemsValue();
        });

        // Bank Search logic
        const $bankSearch = $('#bank_search');
        const $bankDropdown = $('#bank_dropdown');
        const $bankNameHidden = $('#bank_name_val');
        const $bankLogo = $('#selected_bank_logo');

        function renderBanks(filter = '') {
            const filtered = allBanks.filter(b => b.title.toLowerCase().includes(filter.toLowerCase()));
            let html = '';
            filtered.forEach(bank => {
                html += `
                    <div class="bank-option flex items-center gap-3 px-4 py-3 hover:bg-blue-50 cursor-pointer transition border-b border-slate-50 last:border-0" data-name="${bank.title}" data-logo="${bank.route}">
                        <div class="w-8 h-8 rounded-lg overflow-hidden border border-slate-100 bg-white flex-shrink-0 flex items-center justify-center p-1">
                            <img src="${bank.route}" alt="${bank.title}" class="w-full h-full object-contain" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(bank.title)}&background=f1f5f9&color=64748b'">
                        </div>
                        <span class="text-sm font-medium text-slate-700">${bank.title}</span>
                    </div>
                `;
            });
            if (!html) html = '<div class="px-4 py-3 text-sm text-slate-400 italic text-center">No banks found...</div>';
            $bankDropdown.html(html);
        }

        $bankSearch.on('focus', function() {
            renderBanks($(this).val());
            $bankDropdown.removeClass('hidden');
        });

        $bankSearch.on('input', function() {
            renderBanks($(this).val());
        });

        $(document).on('click', '.bank-option', function() {
            const name = $(this).data('name');
            const logo = $(this).data('logo');
            $bankSearch.val(name);
            $bankNameHidden.val(name);
            $bankLogo.html(`<img src="${logo}" alt="${name}" class="w-full h-full object-contain">`);
            $bankDropdown.addClass('hidden');
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('.relative.group').length) {
                $bankDropdown.addClass('hidden');
            }
        });

        // Modal close buttons
        $('.close-modal, #modal-overlay').on('click', function() {
            closeModal();
        });

        // Form submission
        $('#valuation-form').on('submit', function(e) {
            e.preventDefault();
            saveRecord();
        });
    });

    function updateCompensatedItemsValue() {
        let selected = [];
        $('.item-checkbox:checked').each(function() {
            selected.push($(this).val());
        });
        
        if ($('#item-other-check').is(':checked')) {
            const other = $('#compensated_items_other').val();
            if (other) selected.push(other);
        }
        
        $('#compensated_items_val').val(selected.join(', '));
    }

    function openCreateModal() {
        $('#modal-title').text('New Valuation for Compensation');
        $('#valuation-form')[0].reset();
        $('#record_id').val('');
        $('#our_ref').val('').attr('placeholder', 'Generated on save...');
        
        // Reset project fields
        $('#project-selection-section').removeClass('hidden');
        $('#vfc_project_id').val('').trigger('change');
        $('#project-info').addClass('hidden');
        $('#hidden_project_id, #hidden_worker_id').val('');

        // Reset custom fields
        $('#selected_bank_logo').html('<i data-lucide="building-2" class="h-4 w-4 text-slate-400"></i>');
        if (window.lucide) window.lucide.createIcons();
        $('#building_type_other, #compensated_items_other').addClass('hidden');
        $('.item-checkbox, #item-other-check').prop('checked', false);
        
        $('#valuation-modal').removeClass('hidden').addClass('flex');
        setTimeout(() => $('#modal-overlay').addClass('opacity-100'), 10);
    }

    function openEditModal(record) {
        $('#modal-title').text('Edit Valuation Record');
        $('#valuation-form')[0].reset();
        $('#record_id').val(record.id);
        
        // Hide project selection during edit as it's usually immutable for a record
        $('#project-selection-section').addClass('hidden');
        $('#hidden_project_id').val(record.project_id);
        $('#hidden_worker_id').val(record.worker_id);
        
        // Fill basic fields
        $('#our_ref').val(record.our_ref);
        $('#your_ref').val(record.your_ref);
        $('#valuation_date').val(record.valuation_date.split('T')[0]);
        $('#owner_name').val(record.owner_name);
        
        // Handle Building Type
        const bTypeSelect = $('#building_type');
        const typeExists = bTypeSelect.find(`option[value="${record.building_type}"]`).length > 0;
        if (typeExists) {
            bTypeSelect.val(record.building_type);
            $('#building_type_other').addClass('hidden');
        } else {
            bTypeSelect.val('Other');
            $('#building_type_other').val(record.building_type).removeClass('hidden');
        }

        $('#building_count').val(record.building_count);
        $('#area_covered').val(record.area_covered);
        $('#rate_of_cost').val(record.rate_of_cost);
        $('#compensation_amount').val(record.compensation_amount);
        
        // Account Details
        $('#account_name').val(record.account_name);
        $('#account_number').val(record.account_number);
        $('#bank_name').val(record.bank_name);
        $('#bank_search').val(record.bank_name);
        
        const bank = allBanks.find(b => b.title === record.bank_name);
        if (bank) {
            $('#selected_bank_logo').html(`<img src="${bank.route}" alt="${bank.title}" class="w-full h-full object-contain">`);
        } else {
            $('#selected_bank_logo').html('<i data-lucide="building-2" class="h-4 w-4 text-slate-400"></i>');
            if (window.lucide) window.lucide.createIcons();
        }

        $('#phone_number').val(record.phone_number);
        $('#remarks').val(record.remarks);
        
        // Compensated Items
        if (record.compensated_items) {
            const items = record.compensated_items.split(', ').map(i => i.trim());
            let otherItems = [];
            
            items.forEach(item => {
                const $cb = $(`.item-checkbox[value="${item}"]`);
                if ($cb.length > 0) {
                    $cb.prop('checked', true);
                } else {
                    otherItems.push(item);
                }
            });
            
            if (otherItems.length > 0 || record.compensated_items_other) {
                $('#item-other-check').prop('checked', true);
                $('#compensated_items_other').val(record.compensated_items_other || otherItems.join(', ')).removeClass('hidden');
            }
            $('#compensated_items_val').val(record.compensated_items);
        }

        $('#plot_no').val(record.plot_no);
        $('#street_name').val(record.street_name);
        $('#location').val(record.location);

        $('#valuation-modal').removeClass('hidden').addClass('flex');
        setTimeout(() => $('#modal-overlay').addClass('opacity-100'), 10);
    }

    function closeModal() {
        $('#modal-overlay').removeClass('opacity-100');
        setTimeout(() => {
            $('#valuation-modal').addClass('hidden').removeClass('flex');
        }, 300);
    }

    function buildLocation() {
        const plot = $('#plot_no').val();
        const street = $('#street_name').val();
        const district = $('#loc_district').val();
        const lga = $('#loc_lga').val();
        const state = $('#loc_state').val();
        
        let loc = '';
        if (plot) loc += plot;
        if (street && street !== 'Other') loc += (loc ? ', ' : '') + street;
        if (district && district !== 'Other') loc += (loc ? ', ' : '') + district;
        if (lga) loc += (loc ? ', ' : '') + lga;
        if (state) loc += (loc ? ', ' : '') + state + ' State';
        
        if (loc) {
            $('#location').val(loc);
        }
    }

    function calculateCompensation() {
        const area = parseFloat($('#area_covered').val()) || 0;
        const rate = parseFloat($('#rate_of_cost').val()) || 0;
        const total = area * rate;
        $('#compensation_amount').val(total.toFixed(2));
    }

    function saveRecord() {
        const id = $('#record_id').val();
        const url = id ? `/valuation-compensations/${id}` : '/valuation-compensations';
        const method = id ? 'PUT' : 'POST';
        
        let formData = $('#valuation-form').serializeArray();

        // Handle 'Other' building type override
        if ($('#building_type').val() === 'Other') {
            const manualVal = $('#building_type_other').val();
            formData = formData.map(item => (item.name === 'building_type' ? { name: 'building_type', value: manualVal } : item));
        }

        Swal.fire({
            title: 'Saving...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.ajax({
            url: url,
            type: method,
            data: formData,
            success: function(response) {
                if (response.success) {
                    Swal.fire('Success!', response.message, 'success').then(() => {
                        window.location.reload();
                    });
                }
            },
            error: function(xhr) {
                let msg = 'Something went wrong.';
                if (xhr.status === 422) {
                    const errors = xhr.responseJSON.errors;
                    msg = Object.values(errors).flat().join('<br>');
                }
                Swal.fire('Error!', msg, 'error');
            }
        });
    }

    function deleteRecord(id, name) {
        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to delete the valuation for ${name}. This action is reversible by administrators.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/valuation-compensations/${id}`,
                    type: 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Deleted!', response.message, 'success').then(() => {
                                window.location.reload();
                            });
                        }
                    },
                    error: function(xhr) {
                        Swal.fire('Error!', 'Something went wrong.', 'error');
                    }
                });
            }
        });
    }
</script>
@endpush
@endsection

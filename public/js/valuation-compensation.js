/**
 * VFC Valuation & Compensation Dashboard Logic
 * Handles modal management, auto-calculations, location building, and AJAX submissions.
 */

const VFC = {
    config: window.VFC_CONFIG || {},
    allBanks: window.VFC_BANKS || [],
    records: window.VFC_RECORDS || {},
    projectsData: [],

    init: function() {
        this.loadProjects();
        this.initEventListeners();
        this.initDataTable();
        this.fetchBanks();
        
        if (window.lucide) window.lucide.createIcons();
    },

    fetchBanks: function() {
        console.log('Banks loaded from database:', this.allBanks.length);
    },

    async loadProjects() {
        try {
            const response = await fetch(this.config.routes.projectSelection);
            this.projectsData = await response.json();
            const $projSelect = $('#vfc_project_id');
            $projSelect.html('<option value="">Select Project</option>');
            this.projectsData.forEach(p => {
                const displayCode = p.fileno || p.code;
                $projSelect.append(`<option value="${p.id}">${p.name} (${displayCode})</option>`);
            });
        } catch (error) {
            console.error('Error loading projects:', error);
        }
    },

    initEventListeners: function() {
        const self = this;

        // Project Selection Change
        $('#vfc_project_id').on('change', function() {
            const projId = $(this).val();
            if (!projId) {
                $('#project-info').addClass('hidden');
                $('#vfc_worker_id').html('<option value="">Select Worker (Select Project First)</option>');
                $('#our_ref').val('');
                return;
            }

            const proj = self.projectsData.find(p => p.id == projId);
            if (proj) {
                $('#proj_id_summary').text(proj.id);
                $('#proj_fileno_summary').text(proj.fileno);
                $('#proj_code_summary').text(proj.code);
                $('#proj_total').text(proj.total_items);
                $('#proj_workers_summary').text(proj.workers_count || 0);
                $('#proj_rem').text(proj.valuations_count || 0);
                
                $('#project-info').removeClass('hidden');
                if (window.lucide) window.lucide.createIcons();
                
                $('#our_ref').val(proj.fileno || proj.code);
                $('#project_code_display').val(proj.code);
                $('#hidden_project_fileno').val(proj.fileno || proj.code);
                $('#manual_our_ref').val(proj.our_reference || '');
                $('#your_ref').val(proj.your_reference || '');
            }

            // Fetch workers for this project
            $.get(`${self.config.routes.projectWorkers}/${projId}/workers`, function(workers) {
                const $workerSelect = $('#vfc_worker_id');
                $workerSelect.html('<option value="">Select Worker</option>');
                workers.forEach(w => {
                    $workerSelect.append(`<option value="${w.worker_code}">${w.user.first_name} ${w.user.last_name} (${w.worker_code})</option>`);
                });
            });
        });

        // Location Auto-builder (Using delegated events for robustness)
        $(document).on('change input', '.loc-trigger, #loc_district, #loc_lga, #loc_state', function() {
            self.buildLocation();
        });

        // Auto-calculation
        $('.calc-trigger').on('input', function() {
            self.calculateCompensation();
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
        $(document).on('change', '.item-checkbox', function() {
            const val = $(this).val();
            const isOther = val.toLowerCase().includes('other');
            
            if (isOther) {
                if ($(this).is(':checked')) {
                    $('#compensated_items_other').removeClass('hidden').focus();
                } else {
                    $('#compensated_items_other').addClass('hidden');
                }
            }
            self.updateCompensatedItemsValue();
        });

        $('#compensated_items_other').on('input', function() {
            self.updateCompensatedItemsValue();
        });

        // Bank Search logic
        const $bankSearch = $('#bank_search');
        const $bankDropdown = $('#bank_dropdown');
        const $bankNameHidden = $('#bank_name_val');
        const $bankLogo = $('#selected_bank_logo');

        $(document).on('focus', '#bank_search', function() {
            self.renderBanks($(this).val());
            $('#bank_dropdown').removeClass('hidden');
        });

        $(document).on('input', '#bank_search', function() {
            self.renderBanks($(this).val());
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
            self.closeModal();
        });

        // Form submission (Using delegated binding for better reliability)
        $(document).on('submit', '#valuation-form', function(e) {
            e.preventDefault();
            self.saveRecord();
            return false;
        });

        // Project Group Toggle (Now opens modal)
        $(document).on('click', '.project-group-header', function() {
            const projectId = $(this).data('project-id');
            self.openRecordsModal(projectId);
        });

        // Records Modal Close
        $('.close-records-modal, #records-modal-overlay').on('click', function() {
            self.closeRecordsModal();
        });

        // Print Batch
        $(document).on('click', '#print-batch-btn', function() {
            const projectId = $(this).data('project-id');
            if (projectId) {
                window.open(`${self.config.routes.store}/project-print/${projectId}`, '_blank');
            }
        });
    },

    renderBanks: function(filter = '') {
        const filtered = this.allBanks.filter(b => b.title.toLowerCase().includes(filter.toLowerCase()));
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
        $('#bank_dropdown').html(html);
    },

    initDataTable: function() {
        $('#valuationTable').DataTable({
            responsive: true,
            order: [[5, 'desc']],
            pageLength: 10,
            dom: '<"flex flex-col md:flex-row justify-between gap-4 mb-4"fB>rt<"flex flex-col md:flex-row justify-between items-center gap-4 mt-4"ip>',
            buttons: [
                'copy', 'csv', 'excel', 'pdf', 'print'
            ]
        });
    },

    updateCompensatedItemsValue: function() {
        let selected = [];
        $('.item-checkbox:checked').each(function() {
            selected.push($(this).val());
        });
        
        const hasOther = selected.some(s => s.toLowerCase().includes('other'));
        if (hasOther) {
            const other = $('#compensated_items_other').val();
            if (other) selected.push(other);
        }
        
        $('#compensated_items_val').val(selected.join(', '));
    },

    buildLocation: function() {
        const plot = $('#plot_no').val();
        let street = $('#street_name').val();
        if (street === 'Other') {
            street = $('#street_name_other').val();
        }
        
        let district = $('#loc_district').val();
        const lga = $('#loc_lga').val();
        const state = $('#loc_state').val();
        
        let loc = '';
        if (plot) loc += plot;
        if (street) loc += (loc ? ', ' : '') + street;
        if (district && district !== 'Other') loc += (loc ? ', ' : '') + district;
        if (lga) loc += (loc ? ', ' : '') + lga;
        if (state) loc += (loc ? ', ' : '') + state + ' State';
        
        if (loc) {
            $('#location').val(loc);
        }
    },

    calculateCompensation: function() {
        const count = parseFloat($('#building_count').val()) || 0;
        const area = parseFloat($('#area_covered').val()) || 0;
        const rate = parseFloat($('#rate_of_cost').val()) || 0;
        const total = count * area * rate;
        $('#compensation_amount').val(total.toFixed(2));
    },

    saveRecord: function() {
        const id = $('#record_id').val();
        const url = id ? `${this.config.routes.store}/${id}` : this.config.routes.store;
        const method = 'POST';
        
        let formData = $('#valuation-form').serializeArray();
        if (id) {
            formData.push({ name: '_method', value: 'PUT' });
        }

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
            headers: {
                'X-CSRF-TOKEN': this.config.csrf
            },
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
    },

    deleteRecord: function(id, name) {
        const self = this;
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
                    url: `${self.config.routes.store}/${id}`,
                    type: 'DELETE',
                    data: {
                        _token: self.config.csrf
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
    },

    openCreateModal: function() {
        $('#modal-title').text('Valuation for Compensation Data Entry');
        $('#valuation-form')[0].reset();
        $('#record_id').val('');
        $('#our_ref').val('').attr('placeholder', 'Select project...');
        $('#project_code_display').val('').attr('placeholder', 'Select project...');
        $('#manual_our_ref').val('');
        $('#your_ref').val('');
        
        $('#project-selection-section').removeClass('hidden');
        $('#vfc_project_id').val('').trigger('change');
        $('#project-info').addClass('hidden');

        $('#selected_bank_logo').html('<i data-lucide="building-2" class="h-4 w-4 text-slate-400"></i>');
        if (window.lucide) window.lucide.createIcons();
        $('#building_type_other, #compensated_items_other').addClass('hidden');
        $('.item-checkbox').prop('checked', false);
        
        $('#valuation-modal').removeClass('hidden').addClass('flex');
        setTimeout(() => $('#modal-overlay').addClass('opacity-100'), 10);
    },

    openEditModal: function(record) {
        const self = this;
        $('#modal-title').text('Edit Valuation Record');
        $('#valuation-form')[0].reset();
        $('#record_id').val(record.id);
        
        $('#project-selection-section').removeClass('hidden');
        $('#vfc_project_id').val(record.project_id).trigger('change');
        
        setTimeout(() => {
            $('#vfc_worker_id').val(record.worker_id);
        }, 500);
        
        $('#our_ref').val(record.project_fileno || record.our_ref);
        $('#project_code_display').val(record.project ? record.project.project_code : 'N/A');
        $('#manual_our_ref').val(record.our_ref);
        $('#your_ref').val(record.your_ref);
        $('#valuation_date').val(record.valuation_date.split('T')[0]);
        $('#owner_name').val(record.owner_name);
        
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
        
        $('#account_name').val(record.account_name);
        $('#account_number').val(record.account_number);
        $('#bank_name').val(record.bank_name);
        $('#bank_search').val(record.bank_name);
        
        const bank = this.allBanks.find(b => b.title === record.bank_name);
        if (bank) {
            $('#selected_bank_logo').html(`<img src="${bank.route}" alt="${bank.title}" class="w-full h-full object-contain">`);
        } else {
            $('#selected_bank_logo').html('<i data-lucide="building-2" class="h-4 w-4 text-slate-400"></i>');
            if (window.lucide) window.lucide.createIcons();
        }

        $('#phone_number').val(record.phone_number);
        $('#nin').val(record.nin);
        $('#remarks').val(record.remarks);
        
        if (record.compensated_items) {
            const items = record.compensated_items.split(', ').map(i => i.trim());
            let otherItems = [];
            
            items.forEach(item => {
                const $cb = $(`.item-checkbox[value="${item}"]`);
                if ($cb.length > 0) {
                    $cb.prop('checked', true);
                    if (item.toLowerCase().includes('other')) {
                        $('#compensated_items_other').removeClass('hidden');
                    }
                } else {
                    otherItems.push(item);
                }
            });
            
            if (otherItems.length > 0 || record.compensated_items_other) {
                $('.item-checkbox').filter(function() {
                    return $(this).val().toLowerCase().includes('other');
                }).prop('checked', true);
                
                $('#compensated_items_other').val(record.compensated_items_other || otherItems.join(', ')).removeClass('hidden');
            }
            $('#compensated_items_val').val(record.compensated_items);
        }

        $('#plot_no').val(record.plot_no);
        $('#street_name').val(record.street_name);
        $('#location').val(record.location);

        $('#valuation-modal').removeClass('hidden').addClass('flex');
        setTimeout(() => $('#modal-overlay').addClass('opacity-100'), 10);
    },

    openRecordsModal: function(projectId) {
        const group = this.records[projectId];
        if (!group || group.length === 0) return;

        const project = group[0].project;
        $('#records-modal-title').text(project ? project.project_name : 'Individual Records');
        $('#records-modal-subtitle').text(project ? `${project.project_code} | ${project.project_fileno}` : '');
        $('#print-batch-btn').data('project-id', projectId);

        let html = '';
        group.forEach((record, index) => {
            const bType = record.building_type === 'Other' ? (record.building_type_other || 'Other') : record.building_type;
            html += `
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-2 py-4 text-xs font-bold text-slate-400">${index + 1}</td>
                    <td class="px-2 py-4 font-mono text-[10px] font-bold text-slate-400">${record.our_ref || 'N/A'}</td>
                    <td class="px-2 py-4 font-bold text-slate-700 uppercase text-xs">${record.owner_name}</td>
                    <td class="px-2 py-4 text-[10px] text-slate-500">${record.location ? (record.location.substring(0, 40) + (record.location.length > 40 ? '...' : '')) : 'N/A'}</td>
                    <td class="px-2 py-4 text-[11px] text-slate-600">${bType}</td>
                    <td class="px-2 py-4 font-bold text-teal-600 text-xs">₦${parseFloat(record.compensation_amount).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                    <td class="px-2 py-4 text-right">
                        <div class="flex justify-end gap-1">
                            <a href="${this.config.routes.store}/${record.id}" target="_blank" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg" title="View/Print">
                                <i data-lucide="printer" class="h-3.5 w-3.5"></i>
                            </a>
                            <button onclick='VFC.openEditModal(${JSON.stringify(record).replace(/'/g, "&apos;")})' class="p-1.5 text-amber-600 hover:bg-amber-50 rounded-lg" title="Edit">
                                <i data-lucide="edit-3" class="h-3.5 w-3.5"></i>
                            </button>
                            <button onclick="VFC.deleteRecord(${record.id}, '${record.owner_name}')" class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg" title="Delete">
                                <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        $('#project-records-body').html(html);
        if (window.lucide) window.lucide.createIcons();

        $('#records-modal').removeClass('hidden').addClass('flex');
        setTimeout(() => {
            $('#records-modal-overlay').addClass('opacity-100');
            $('#records-modal-container').removeClass('scale-95 opacity-0').addClass('scale-100 opacity-100');
        }, 10);
    },

    closeRecordsModal: function() {
        $('#records-modal-overlay').removeClass('opacity-100');
        $('#records-modal-container').addClass('scale-95 opacity-0').removeClass('scale-100 opacity-100');
        setTimeout(() => {
            $('#records-modal').addClass('hidden').removeClass('flex');
        }, 300);
    },

    closeModal: function() {
        $('#modal-overlay').removeClass('opacity-100');
        setTimeout(() => {
            $('#valuation-modal').addClass('hidden').removeClass('flex');
        }, 300);
    }
};

$(document).ready(() => VFC.init());

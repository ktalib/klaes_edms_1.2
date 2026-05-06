/**
 * allocation_list_entry.js
 * Handles all client-side logic for the Allocation List Entry CRUD page (Tailwind version).
 */

'use strict';

// Fallback to avoid crashes if global config is missing
window.ALE = window.ALE || { urls: {}, csrf: '', activeFilter: '' };

let aleTable   = null;   // DataTable instance
let aleEditId  = null;   // ID of record being edited (null = Add mode)

$(document).ready(function () {
    // ── Build DataTable ────────────────────────────────────────────────────
    aleTable = $('#allocationTable').DataTable({
        processing : true,
        serverSide : false,
        ajax       : { url: window.ALE.urls.data, dataSrc: 'data' },
        dom        : "<'dt-top-bar'fl>t<'dt-bottom-bar'ip>",
        pageLength : 25,
        lengthMenu : [[10, 25, 50, 100, -1], ['10', '25', '50', '100', 'All']],
        order      : [[4, 'desc']], // Orders by Date Added
        columns    : [
            { data: null, render: (d, t, r, m) => m.row + 1, orderable: false, searchable: false, width: '40px' },
            { data: 'title', defaultContent: '' },
            {
                data: null,
                render: r => [r.first_name, r.middle_name, r.last_name].filter(Boolean).join(' '),
                defaultContent: ''
            },
            {
                data: 'created_at',
                render: val => {
                    if (!val) return '—';
                    const d = new Date(val);
                    const dateStr = d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
                    const timeStr = d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', hour12: true });
                    return `<div class="flex flex-col"><span class="font-medium">${dateStr}</span><span class="text-[10px] text-gray-400 font-bold uppercase">${timeStr}</span></div>`;
                },
                defaultContent: ''
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: r => `
                    <div class="relative inline-block text-left ale-dropdown-container">
                        <button type="button" class="ale-btn-dropdown p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 transition-all focus:outline-none" 
                            onclick="aleToggleDropdown(event, ${r.id})">
                            <i data-lucide="more-vertical" class="h-4 w-4"></i>
                        </button>
                        <div id="ale-dropdown-${r.id}" class="ale-dropdown-menu hidden absolute right-0 mt-2 w-32 bg-white border border-gray-200 rounded-xl shadow-xl z-30 overflow-hidden">
                            <div class="py-1">
                                <button onclick="aleOpenEdit(${r.id})" class="flex items-center gap-2 w-full px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-all font-medium">
                                    <i data-lucide="pencil" class="h-4 w-4 text-blue-500"></i>
                                    Edit
                                </button>
                                <button onclick="aleDelete(${r.id})" class="flex items-center gap-2 w-full px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-all font-medium">
                                    <i data-lucide="trash-2" class="h-4 w-4 text-red-500"></i>
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                `
            }
        ],
        drawCallback: function() {
            if (window.lucide) window.lucide.createIcons();
        },
        initComplete: function () {
            // DataTable Buttons
            new $.fn.dataTable.Buttons(aleTable, {
                buttons: [
                    { extend: 'copyHtml5',  text: 'Copy', className: 'px-3 py-1.5 bg-white border border-gray-300 rounded-lg text-xs font-medium hover:bg-gray-50 transition-all' },
                    { extend: 'csvHtml5',   text: 'CSV',  className: 'px-3 py-1.5 bg-white border border-gray-300 rounded-lg text-xs font-medium hover:bg-gray-50 transition-all' },
                    { extend: 'excelHtml5', text: 'Excel',className: 'px-3 py-1.5 bg-white border border-gray-300 rounded-lg text-xs font-medium hover:bg-gray-50 transition-all' },
                    { extend: 'pdfHtml5',   text: 'PDF',  className: 'px-3 py-1.5 bg-white border border-gray-300 rounded-lg text-xs font-medium hover:bg-gray-50 transition-all' },
                ]
            });
            aleTable.buttons().container().appendTo('#dt-export-buttons');
            aleRefreshStats();
        }
    });

    // Close on backdrop click
    $('#aleModal').on('click', function(e) {
        if (e.target === this) aleCloseModal();
    });

    // Address Preview Trigger
    $(document).on('input change', '.ale-addr-trigger', function() {
        aleUpdateAddressPreview();
    });

    // Close dropdowns when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.ale-dropdown-container').length) {
            $('.ale-dropdown-menu').addClass('hidden');
        }
    });
});

/* ═══════════════════════════════════════════════════════════════════════════
   MODAL & DROPDOWN CONTROLS
═══════════════════════════════════════════════════════════════════════════ */

let rowCount = 0;

function aleToggleDropdown(event, id) {
    event.stopPropagation();
    const dropdown = $(`#ale-dropdown-${id}`);
    const isHidden = dropdown.hasClass('hidden');
    
    // Close all other dropdowns
    $('.ale-dropdown-menu').addClass('hidden');
    
    if (isHidden) {
        dropdown.removeClass('hidden');
    }
}

function aleOpenModal() {
    $('#aleModal').removeClass('hidden').addClass('flex');
    $('body').addClass('overflow-hidden');
    if (window.lucide) window.lucide.createIcons();
}

function aleCloseModal() {
    $('#aleModal').removeClass('flex').addClass('hidden');
    $('body').removeClass('overflow-hidden');
}

/**
 * Adds a new entry row to the modal form.
 */
function aleAddRow(data = null) {
    const container = document.getElementById('ale-entries-container');
    const template = document.getElementById('ale-row-template');
    const clone = template.content.cloneNode(true);
    
    // Update indexes in names
    const fields = clone.querySelectorAll('[name*="INDEX"]');
    fields.forEach(field => {
        field.name = field.name.replace('INDEX', rowCount);
        if (data) {
            const key = field.name.match(/\[([^\]]+)\]$/)[1];
            if (data[key] !== undefined) field.value = data[key];
        } else {
            // No pre-fill needed
        }
    });

    container.appendChild(clone);
    rowCount++;

    if (window.lucide) window.lucide.createIcons();
}

/**
 * Removes a specific row from the modal form.
 */
function aleRemoveRow(btn) {
    const container = document.getElementById('ale-entries-container');
    if (container.querySelectorAll('.ale-row').length <= 1) {
        Swal.fire({ icon: 'info', text: 'At least one row is required.' });
        return;
    }
    btn.closest('.ale-row').remove();
}

function aleOpenAdd() {
    aleEditId = null;
    rowCount = 0;
    document.getElementById('ale-entries-container').innerHTML = '';
    
    aleAddRow(); // Add first empty row
    
    $('#ale-modal-title').text('Add Allocation Entries');
    $('#ale-modal-subtitle').removeClass('hidden');
    $('#ale-add-row-btn').removeClass('hidden');
    $('#ale-save-btn-text').text('Save Entries');
    
    aleOpenModal();
}

async function aleOpenEdit(id) {
    try {
        const resp = await fetch(window.ALE.urls.fetchrowinfo.replace('__ID__', id), {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': window.ALE.csrf }
        });
        const json = await resp.json();
        if (!json.success) throw new Error(json.message);

        const r = json.data;
        aleEditId = id;
        rowCount = 0;
        document.getElementById('ale-entries-container').innerHTML = '';
        
        // Add single row with data
        aleAddRow(r);

        $('#ale-modal-title').text('Edit Allocation Entry');
        $('#ale-modal-subtitle').addClass('hidden');
        $('#ale-add-row-btn').addClass('hidden'); // Hide plus in edit mode
        $('#ale-save-btn-text').text('Update Entry');

        aleOpenModal();
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Error', text: err.message });
    }
}

function aleResetForm() {
    document.getElementById('ale-form').reset();
    $('#ale-entries-container').empty();
}

/* ═══════════════════════════════════════════════════════════════════════════
   OPERATIONS
   ═══════════════════════════════════════════════════════════════════════════ */

async function aleSubmit() {
    const form = document.getElementById('ale-form');
    if (!form.checkValidity()) { 
        form.reportValidity(); 
        $(form).find(':invalid').first().focus();
        return; 
    }

    const isEdit = aleEditId !== null;
    const url = isEdit
        ? window.ALE.urls.update.replace('__ID__', aleEditId)
        : window.ALE.urls.store;

    const btn = $('#ale-save-btn');
    btn.prop('disabled', true).addClass('opacity-50 cursor-not-allowed');
    const originalText = $('#ale-save-btn-text').text();
    $('#ale-save-btn-text').text('Saving...');

    // Collect Data
    let payload = {};
    if (isEdit) {
        // Single row for edit
        const row = document.querySelector('.ale-row');
        const inputs = row.querySelectorAll('.ale-field, input[type="hidden"]');
        inputs.forEach(input => {
            const name = input.name.match(/\[([^\]]+)\]$/)[1];
            payload[name] = input.value;
        });
        payload._method = 'PUT';
    } else {
        // Multi-rows for add
        payload.entries = [];
        const rows = document.querySelectorAll('.ale-row');
        rows.forEach(row => {
            let entry = {};
            const inputs = row.querySelectorAll('.ale-field, input[type="hidden"]');
            inputs.forEach(input => {
                const name = input.name.match(/\[([^\]]+)\]$/)[1];
                entry[name] = input.value;
            });
            payload.entries.push(entry);
        });
    }

    try {
        const resp = await fetch(url, {
            method : 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': window.ALE.csrf },
            body   : JSON.stringify(payload),
        });
        const json = await resp.json();

        if (!json.success) throw new Error(json.message);

        aleCloseModal();
        Swal.fire({ icon: 'success', title: 'Success', text: json.message, timer: 2000, showConfirmButton: false });
        aleTable.ajax.reload(null, false);
        aleRefreshStats();
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Error', text: err.message || 'An error occurred.' });
    } finally {
        btn.prop('disabled', false).removeClass('opacity-50 cursor-not-allowed');
        $('#ale-save-btn-text').text(originalText);
    }
}

function aleDelete(id) {
    Swal.fire({
        icon             : 'warning',
        title            : 'Delete Entry?',
        text             : 'This action cannot be undone.',
        showCancelButton : true,
        confirmButtonText: 'Yes, Delete',
        confirmButtonColor: '#dc2626',
        cancelButtonText : 'Cancel',
    }).then(async result => {
        if (!result.isConfirmed) return;
        try {
            const resp = await fetch(window.ALE.urls.destroy.replace('__ID__', id), {
                method : 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': window.ALE.csrf },
                body   : JSON.stringify({ _method: 'DELETE', _token: window.ALE.csrf }),
            });
            const json = await resp.json();
            if (!json.success) throw new Error(json.message);
            Swal.fire({ icon: 'success', title: 'Deleted', text: json.message, timer: 1800, showConfirmButton: false });
            aleTable.ajax.reload(null, false);
            aleRefreshStats();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: err.message });
        }
    });
}

/* ═══════════════════════════════════════════════════════════════════════════
   IMPORT / STATS
═══════════════════════════════════════════════════════════════════════════ */

function aleTriggerImport() {
    $('#import-csv-input').click();
}

function aleHandleImport(event) {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = async function (e) {
        const lines   = e.target.result.split(/\r?\n/).filter(l => l.trim());
        const headers = lines[0].split(',').map(h => h.replace(/["\u00ef\u00bb\u00bf]/g, '').trim());

        const rows = [];
        for (let i = 1; i < lines.length; i++) {
            const vals  = lines[i].split(',').map(v => v.replace(/"/g, '').trim());
            const entry = {};
            headers.forEach((h, idx) => { entry[h] = vals[idx] || ''; });
            rows.push(entry);
        }

        if (rows.length === 0) {
            Swal.fire({ icon: 'warning', text: 'The CSV contains no data rows.' });
            return;
        }

        if (rows.length > 100) {
            Swal.fire({ icon: 'error', text: `Limit 100 records. File has ${rows.length}.` });
            event.target.value = '';
            return;
        }

        const res = await Swal.fire({ title: `Import ${rows.length} Records?`, showCancelButton: true });
        if (!res.isConfirmed) { event.target.value = ''; return; }

        try {
            const resp = await fetch(window.ALE.urls.importCsv, {
                method : 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': window.ALE.csrf },
                body   : JSON.stringify({ records: rows, _token: window.ALE.csrf }),
            });
            const json = await resp.json();
            if (!json.success) throw new Error(json.message);
            Swal.fire({ icon: 'success', title: 'Imported', text: json.message });
            aleTable.ajax.reload(null, false);
            aleRefreshStats();
        } catch (err) {
            Swal.fire({ icon: 'error', text: err.message });
        } finally {
            event.target.value = '';
        }
    };
    reader.readAsText(file);
}

async function aleRefreshStats() {
    try {
        const resp  = await fetch(window.ALE.urls.data, { headers: { 'Accept': 'application/json' } });
        const json  = await resp.json();
        const total = json.total || 0;

        $('#stat-total').text(total);
    } catch (_) {}
}

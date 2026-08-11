/**
 * allocation_list_entry.js
 * Client-side logic for the Allocation List — capture of existing allocations.
 *
 * The capture form saves one entry at a time over AJAX and appends it to the
 * session table inside the modal, so the operator keeps filling and saving
 * without the form closing. Closing the modal resets the form and that table.
 */

'use strict';

// Fallback to avoid crashes if global config is missing
window.ALE = window.ALE || { urls: {}, csrf: '', activeFilter: '' };

let aleTable       = null;   // DataTable instance
let aleEditId      = null;   // ID of record being edited (null = capture mode)
let aleSession     = [];     // Entries captured since the modal was opened
let aleLookupTimer = null;   // Debounce for the hand-typed file number lookup

$(document).ready(function () {
    // ── Build DataTable ────────────────────────────────────────────────────
    aleTable = $('#allocationTable').DataTable({
        processing : true,
        serverSide : false,
        ajax       : { url: window.ALE.urls.data, dataSrc: 'data' },
        dom        : "<'dt-top-bar'fl>t<'dt-bottom-bar'ip>",
        pageLength : 25,
        lengthMenu : [[10, 25, 50, 100, -1], ['10', '25', '50', '100', 'All']],
        order      : [[6, 'desc']], // Date Added
        columns    : [
            { data: null, render: (d, t, r, m) => m.row + 1, orderable: false, searchable: false, width: '40px' },
            { data: 'file_no', render: v => v || '—', defaultContent: '—' },
            { data: 'file_title', render: v => v || '—', defaultContent: '—' },
            {
                data: null,
                render: r => r.allottee_name
                    || [r.first_name, r.middle_name, r.last_name].filter(Boolean).join(' ')
                    || '—',
                defaultContent: '—'
            },
            { data: 'location', render: v => v || '—', defaultContent: '—' },
            { data: 'allocation_year', render: v => v || '—', defaultContent: '—' },
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

    // Backdrop click closes the capture modal
    $('#aleModal').on('click', function(e) {
        if (e.target === this) aleCloseModal();
    });

    // Typing a file number by hand backfills the same way selecting one does,
    // once the operator stops typing.
    $('#ale-file-no').on('input', function () {
        clearTimeout(aleLookupTimer);
        const value = this.value;
        aleSetYearFromFileNo(value);
        aleLookupTimer = setTimeout(() => aleLookupFile(value), 450);
    });

    // Enter anywhere in the form saves, so capture stays keyboard-only.
    $('#ale-form').on('keydown', 'input', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            aleSubmit();
        }
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

/**
 * Closing ends the capture session: the form and the session table both reset.
 */
function aleCloseModal() {
    $('#aleModal').removeClass('flex').addClass('hidden');
    $('body').removeClass('overflow-hidden');

    aleEditId  = null;
    aleSession = [];
    aleResetForm();
    aleRenderSession();
}

function aleOpenAdd() {
    aleEditId  = null;
    aleSession = [];

    aleResetForm();
    aleRenderSession();

    $('#ale-modal-title').text('Capture Existing Allocation');
    $('#ale-modal-subtitle').text('Select a file number — the title, location and year fill in automatically.');
    $('#ale-save-btn-text').text('Save Entry');

    aleOpenModal();
    setTimeout(() => $('#ale-file-no').focus(), 100);
}

async function aleOpenEdit(id) {
    try {
        const resp = await fetch(window.ALE.urls.fetchrowinfo.replace('__ID__', id), {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': window.ALE.csrf }
        });
        const json = await resp.json();
        if (!json.success) throw new Error(json.message);

        const r = json.data;
        aleEditId  = id;
        aleSession = [];

        aleResetForm();
        aleRenderSession();

        $('#ale-entry-id').val(id);
        $('#ale-file-no').val(r.file_no || '');
        $('#ale-file-title').val(r.file_title || '');
        $('#ale-allottee-name').val(
            r.allottee_name || [r.first_name, r.middle_name, r.last_name].filter(Boolean).join(' ')
        );
        $('#ale-location').val(r.location || '');
        aleSetYearFromFileNo(r.file_no);
        if (!$('#ale-allocation-year').val() && r.allocation_year) {
            $('#ale-allocation-year').val(r.allocation_year);
        }

        $('#ale-modal-title').text('Edit Allocation Entry');
        $('#ale-modal-subtitle').text('Update this allocation record.');
        $('#ale-save-btn-text').text('Update Entry');

        aleOpenModal();
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Error', text: err.message });
    }
}

function aleResetForm() {
    // Drop any debounced lookup for the number being cleared, so it cannot land
    // after the next entry has started and overwrite its fields or status.
    clearTimeout(aleLookupTimer);

    $('#ale-entry-id').val(aleEditId || '');
    $('#ale-file-no').val('');
    $('#ale-file-title').val('');
    $('#ale-allottee-name').val('');
    $('#ale-location').val('');
    aleSetYearFromFileNo('');
    aleSetFileStatus('', '');
}

function aleSetFileStatus(message, tone) {
    const tones = {
        ok      : 'text-emerald-600',
        warn    : 'text-amber-600',
        loading : 'text-gray-400',
        error   : 'text-red-600',
    };
    $('#ale-file-status')
        .removeClass('text-emerald-600 text-amber-600 text-gray-400 text-red-600')
        .addClass(tones[tone] || 'text-gray-400')
        .text(message);
}

/* ═══════════════════════════════════════════════════════════════════════════
   FILE NUMBER SELECTION & BACKFILL
═══════════════════════════════════════════════════════════════════════════ */

/**
 * Read the allocation year out of a file number, e.g. RES-1982-2081 → 1982.
 *
 * Takes the leftmost plausible 4-digit group so a serial that happens to look
 * like a year (…-2081) never wins over the real one. Mirrors
 * AllocationListEntryController::detectYearFromFileNumber().
 */
function aleDetectYear(fileNo) {
    const value = String(fileNo || '').trim();
    if (!value) return '';

    const maxYear = new Date().getFullYear() + 1;

    for (const group of value.split(/[^0-9]+/)) {
        if (group.length !== 4) continue;
        const year = parseInt(group, 10);
        if (year >= 1900 && year <= maxYear) return group;
    }
    return '';
}

/**
 * The year belongs to the file number, so lock the field whenever one can be
 * read out of it — the server derives it the same way and would overwrite any
 * edit. Numbers that carry no year (KN1234) leave the field open to type in.
 */
function aleCurrentFileNo() {
    return ($('#ale-file-no').val() || '').trim().toUpperCase();
}

function aleSetYearFromFileNo(fileNo) {
    const year  = aleDetectYear(fileNo);
    const field = $('#ale-allocation-year');

    field.val(year);
    field.prop('readonly', !!year);
    field.attr('placeholder', year ? '' : 'No year in this file number');
    field.toggleClass('bg-gray-100 text-gray-500 cursor-not-allowed', !!year);
    field.toggleClass('bg-gray-50', !year);
}

/**
 * Open the shared file number selector and backfill from whatever is picked.
 */
function aleOpenFileSelector() {
    if (!window.GlobalFileNoModal) {
        Swal.fire({ icon: 'error', text: 'File number selector is unavailable. Type the number instead.' });
        return;
    }

    GlobalFileNoModal.open({
        callback: function (data) {
            const fileNo = (data.fileNumber || '').trim();
            if (!fileNo) return;

            $('#ale-file-no').val(fileNo);
            aleSetYearFromFileNo(fileNo);

            // The selector already carries the title for most registries — show
            // it immediately, then let the lookup fill in location and confirm.
            const title = data.file_title || data.file_name || (data.record && data.record.file_name) || '';
            if (title) $('#ale-file-title').val(title.toUpperCase());

            aleLookupFile(fileNo);
            setTimeout(() => $('#ale-allottee-name').focus(), 150);
        }
    });
}

/**
 * Backfill file title and location for a file number. Blank fields only —
 * anything the operator has already typed is left alone.
 */
async function aleLookupFile(fileNo) {
    const value = String(fileNo || '').trim();
    if (!value) {
        aleSetFileStatus('', '');
        return;
    }

    // The field may have moved on since this lookup was queued.
    if (aleCurrentFileNo() !== value.toUpperCase()) return;

    aleSetFileStatus('Looking up file…', 'loading');

    try {
        const url = `${window.ALE.urls.fileLookup}?file_no=${encodeURIComponent(value)}`;
        const resp = await fetch(url, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': window.ALE.csrf }
        });
        const json = await resp.json();

        // A slower earlier lookup must not overwrite a number typed since.
        if (aleCurrentFileNo() !== value.toUpperCase()) return;

        if (!json.success) throw new Error(json.message || 'Lookup failed.');

        const d = json.data || {};

        if (d.file_title && !$('#ale-file-title').val().trim()) {
            $('#ale-file-title').val(String(d.file_title).toUpperCase());
        }
        if (d.location && !$('#ale-location').val().trim()) {
            $('#ale-location').val(String(d.location).toUpperCase());
        }
        if (d.allocation_year) {
            $('#ale-allocation-year').val(d.allocation_year);
        }

        if (json.found) {
            aleSetFileStatus('File found — details backfilled.', 'ok');
        } else {
            aleSetFileStatus('No record for this file number — enter the details manually.', 'warn');
        }
    } catch (err) {
        aleSetFileStatus('Could not look up this file number.', 'error');
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   SESSION TABLE
═══════════════════════════════════════════════════════════════════════════ */

function aleRenderSession() {
    const body = $('#ale-session-body');
    body.empty();

    $('#ale-session-count').text(aleSession.length);
    $('#ale-session-empty').toggleClass('hidden', aleSession.length > 0);

    aleSession.forEach((entry, index) => {
        body.append(`
            <tr class="border-t border-gray-100 hover:bg-blue-50/40 transition-colors">
                <td class="px-4 py-2.5 text-gray-400 font-semibold">${index + 1}</td>
                <td class="px-4 py-2.5 font-semibold text-gray-800">${aleEscape(entry.file_no)}</td>
                <td class="px-4 py-2.5 text-gray-600">${aleEscape(entry.file_title) || '—'}</td>
                <td class="px-4 py-2.5 text-gray-800">${aleEscape(entry.allottee_name) || '—'}</td>
                <td class="px-4 py-2.5 text-gray-600">${aleEscape(entry.location) || '—'}</td>
                <td class="px-4 py-2.5 text-gray-600 font-semibold">${aleEscape(entry.allocation_year) || '—'}</td>
                <td class="px-4 py-2.5 text-center">
                    <button type="button" onclick="aleDeleteSessionEntry(${entry.id})"
                        title="Remove this entry"
                        class="p-1.5 rounded-lg text-red-500 hover:bg-red-50 transition-all">
                        <i data-lucide="trash-2" class="h-4 w-4"></i>
                    </button>
                </td>
            </tr>
        `);
    });

    if (window.lucide) window.lucide.createIcons();
}

function aleEscape(value) {
    return $('<div>').text(value == null ? '' : value).html();
}

/**
 * Remove an entry saved earlier in this session (it is already in the DB).
 */
function aleDeleteSessionEntry(id) {
    Swal.fire({
        icon             : 'warning',
        title            : 'Remove Entry?',
        text             : 'This deletes the saved allocation record.',
        showCancelButton : true,
        confirmButtonText: 'Yes, Remove',
        confirmButtonColor: '#dc2626',
    }).then(async result => {
        if (!result.isConfirmed) return;
        const ok = await aleDeleteRecord(id);
        if (!ok) return;
        aleSession = aleSession.filter(e => e.id !== id);
        aleRenderSession();
    });
}

/* ═══════════════════════════════════════════════════════════════════════════
   OPERATIONS
═══════════════════════════════════════════════════════════════════════════ */

async function aleSubmit() {
    const fileNo = $('#ale-file-no').val().trim();
    const name   = $('#ale-allottee-name').val().trim();

    if (!fileNo) {
        aleSetFileStatus('File number is required.', 'error');
        $('#ale-file-no').focus();
        return;
    }
    if (!name) {
        Swal.fire({ icon: 'warning', text: 'Name is required.' });
        $('#ale-allottee-name').focus();
        return;
    }

    const isEdit = aleEditId !== null;
    const payload = {
        file_no         : fileNo.toUpperCase(),
        allottee_name   : name.toUpperCase(),
        file_title      : $('#ale-file-title').val().trim().toUpperCase(),
        location        : $('#ale-location').val().trim().toUpperCase(),
        allocation_year : $('#ale-allocation-year').val().trim(),
    };
    if (isEdit) payload._method = 'PUT';

    const url = isEdit
        ? window.ALE.urls.update.replace('__ID__', aleEditId)
        : window.ALE.urls.storeExisting;

    const btn = $('#ale-save-btn');
    const originalText = $('#ale-save-btn-text').text();
    btn.prop('disabled', true).addClass('opacity-50 cursor-not-allowed');
    $('#ale-save-btn-text').text('Saving...');

    try {
        const resp = await fetch(url, {
            method : 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': window.ALE.csrf },
            body   : JSON.stringify(payload),
        });
        const json = await resp.json();

        if (!json.success) throw new Error(json.message);

        aleTable.ajax.reload(null, false);
        aleRefreshStats();

        if (isEdit) {
            aleCloseModal();
            Swal.fire({ icon: 'success', title: 'Updated', text: json.message, timer: 1800, showConfirmButton: false });
            return;
        }

        // Capture mode: the entry joins the session table and the form clears
        // for the next one, with the modal left open.
        aleSession.unshift({
            id              : json.data ? json.data.id : null,
            file_no         : payload.file_no,
            file_title      : payload.file_title || (json.data ? json.data.file_title : ''),
            allottee_name   : payload.allottee_name,
            location        : payload.location || (json.data ? json.data.location : ''),
            allocation_year : (json.data ? json.data.allocation_year : '') || payload.allocation_year,
        });
        aleRenderSession();

        aleResetForm();
        $('#ale-file-no').focus();
        aleSetFileStatus(`${payload.file_no} saved.`, 'ok');
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Error', text: err.message || 'An error occurred.' });
    } finally {
        btn.prop('disabled', false).removeClass('opacity-50 cursor-not-allowed');
        $('#ale-save-btn-text').text(originalText);
    }
}

/**
 * Delete one record. Returns true on success; surfaces its own error otherwise.
 */
async function aleDeleteRecord(id) {
    try {
        const resp = await fetch(window.ALE.urls.destroy.replace('__ID__', id), {
            method : 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': window.ALE.csrf },
            body   : JSON.stringify({ _method: 'DELETE', _token: window.ALE.csrf }),
        });
        const json = await resp.json();
        if (!json.success) throw new Error(json.message);

        aleTable.ajax.reload(null, false);
        aleRefreshStats();
        return true;
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Error', text: err.message });
        return false;
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
        const ok = await aleDeleteRecord(id);
        if (!ok) return;
        aleSession = aleSession.filter(e => e.id !== id);
        aleRenderSession();
        Swal.fire({ icon: 'success', title: 'Deleted', text: 'Entry deleted successfully.', timer: 1800, showConfirmButton: false });
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
        const headers = lines[0].split(',').map(h => h.replace(/["ï»¿]/g, '').trim());

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

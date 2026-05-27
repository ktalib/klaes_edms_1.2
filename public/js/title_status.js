/* Title Status */
'use strict';

let tsTable        = null;
let tsEditId       = null;
let tsSelectedType = '';

/* ── Type themes ── */
const TS_TYPE_THEMES = {
    'Withdrawal (Application)': {
        gradient: 'linear-gradient(135deg,#312e81 0%,#4338ca 60%,#6366f1 100%)',
        icon:     'undo-2',
        label:    'Withdrawal · New Files',
    },
    'Cancellation (RofO)': {
        gradient: 'linear-gradient(135deg,#78350f 0%,#d97706 60%,#fbbf24 100%)',
        icon:     'ban',
        label:    'Cancellation · Existing or New Files',
    },
    'Revoke (CofO)': {
        gradient: 'linear-gradient(135deg,#7f1d1d 0%,#dc2626 60%,#f87171 100%)',
        icon:     'shield-off',
        label:    'Revoke · Existing Files',
    },
    'Litigation': {
        gradient: 'linear-gradient(135deg,#881337 0%,#e11d48 60%,#fb7185 100%)',
        icon:     'scale',
        label:    'Litigation · Existing or New Files',
    },
    'Amendment/Reconsideration (Application/RofO/CofO)': {
        gradient: 'linear-gradient(135deg,#064e3b 0%,#059669 60%,#34d399 100%)',
        icon:     'file-edit',
        label:    'Amendment / Reconsideration',
    },
};

/* ────────────────────────────── Init ────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
    tsTable = $('#title-status-table').DataTable({
        paging: false, ordering: true, info: true, searching: false,
    });

    $('#ts-search').on('input', function () { reloadTsPage(); });
    $('#ts-length').on('change', function () { reloadTsPage(); });

    if (window.GlobalFileNoModal) GlobalFileNoModal.init();
    if (typeof lucide !== 'undefined') lucide.createIcons();
});

function reloadTsPage(page) {
    const params = new URLSearchParams(window.location.search);
    params.set('search', $('#ts-search').val() || '');
    params.set('limit',  $('#ts-length').val() || '50');
    params.set('url', TS_URL);
    if (page) params.set('page', page);
    window.location.href = window.location.pathname + '?' + params.toString();
}

/* ────────────────── Type Selection ────────────────── */
function tsOpenTypeSelect() {
    document.getElementById('ts-type-modal').classList.remove('hidden');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function tsCloseTypeModal() {
    document.getElementById('ts-type-modal').classList.add('hidden');
}

function tsSelectType(type) {
    tsCloseTypeModal();
    tsEditId = null;
    tsClearForm();
    tsSelectedType = type;
    tsApplyTheme(type);
    tsRefreshRemark();
    document.getElementById('ts-modal').classList.remove('hidden');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function tsApplyTheme(type) {
    const theme  = TS_TYPE_THEMES[type] || TS_TYPE_THEMES['Withdrawal (Application)'];
    const header = document.getElementById('ts-modal-header');
    if (header) header.style.background = theme.gradient;
    const lbl = document.getElementById('ts-modal-type-label');
    if (lbl) lbl.textContent = theme.label;
    const icon = document.getElementById('ts-modal-icon');
    if (icon) {
        icon.setAttribute('data-lucide', theme.icon);
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
}

function tsGoBack() {
    document.getElementById('ts-modal').classList.add('hidden');
    tsOpenTypeSelect();
}

function tsOpenCreate() { tsOpenTypeSelect(); }

/* ────────────────── File Search ────────────────── */
function tsOpenFileSearch() {
    if (!window.GlobalFileNoModal) return;
    GlobalFileNoModal.open({
        callback: function (data) {
            if (!data.fileNumber) return;
            tsFetchFileInfo(data.fileNumber);
        }
    });
}

function tsFetchFileInfo(fileNo) {
    fetch(`${TS_FILE_INFO_URL}?file_no=${encodeURIComponent(fileNo)}`, {
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': TS_CSRF },
    })
    .then(r => r.json())
    .then(res => {
        if (!res.success) {
            Swal.fire({ icon: 'warning', title: 'Not Found', text: res.message || 'File not found in records.' });
            return;
        }
        const d = res.data;

        // Store hidden values
        document.getElementById('ts-file_no').value        = d.file_no;
        document.getElementById('ts-file_title').value     = d.file_title || '';
        document.getElementById('ts-applicant_name').value = d.current_holder || d.file_title || '';
        document.getElementById('ts-plot_no').value        = d.plot_no || '';
        document.getElementById('ts-location').value       = d.location || '';
        document.getElementById('ts-land_use').value       = d.land_use || '';
        document.getElementById('ts-district').value       = d.district || '';
        document.getElementById('ts-lga').value            = d.lga || '';
        document.getElementById('ts-source_table').value   = d.source_table || '';
        document.getElementById('ts-source_id').value      = d.source_id || '';

        // Show file number in card
        document.getElementById('ts-file-no-display').textContent = d.file_no;
        document.getElementById('ts-file-placeholder').classList.add('hidden');
        document.getElementById('ts-file-selected').classList.remove('hidden');
        document.getElementById('ts-file-card').classList.add('selected');

        // Populate summary
        const setText = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = val || '—';
        };
        setText('ts-sum-title',    d.file_title);
        setText('ts-sum-holder',   d.current_holder);
        setText('ts-sum-plot',     d.plot_no);
        setText('ts-sum-location', d.location);
        setText('ts-sum-landuse',  d.land_use);
        setText('ts-sum-lga',      d.lga);
        document.getElementById('ts-file-summary').classList.remove('hidden');

        tsRefreshRemark();
        if (typeof lucide !== 'undefined') lucide.createIcons();
    })
    .catch(() => Swal.fire({ icon: 'error', title: 'Error', text: 'Could not fetch file info.' }));
}

function tsClearFileSelection(event) {
    event.stopPropagation();
    ['ts-file_no','ts-file_title','ts-applicant_name','ts-plot_no',
     'ts-location','ts-land_use','ts-district','ts-lga','ts-source_table','ts-source_id']
    .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });

    document.getElementById('ts-file-placeholder').classList.remove('hidden');
    document.getElementById('ts-file-selected').classList.add('hidden');
    document.getElementById('ts-file-card').classList.remove('selected');
    document.getElementById('ts-file-summary').classList.add('hidden');
    document.getElementById('ts-remark').value = '';
}

/* ────────────────── Auto Remark ────────────────── */
function tsRefreshRemark() {
    const fileNo    = (document.getElementById('ts-file_no')?.value    || '').trim();
    const fileTitle = (document.getElementById('ts-file_title')?.value || '').trim();
    const authority = (document.getElementById('ts-authority')?.value  || '').trim();
    const date      = new Date().toLocaleDateString('en-GB');

    const fn   = fileNo    || '[File No]';
    const ft   = fileTitle || '[File Title]';
    const auth = authority || '[Authority]';

    const templates = {
        'Withdrawal (Application)':
            `File ${fn} — ${ft} has been Withdrawn on ${date}. Authority: ${auth}.`,
        'Cancellation (RofO)':
            `File ${fn} — ${ft} RofO has been Cancelled on ${date}. Authority: ${auth}.`,
        'Revoke (CofO)':
            `File ${fn} — ${ft} has been Revoked on ${date}. Authority: ${auth}.`,
        'Litigation':
            `File ${fn} — ${ft} is under Litigation as of ${date}. Authority: ${auth}.`,
        'Amendment/Reconsideration (Application/RofO/CofO)':
            `File ${fn} — ${ft} Application/RofO/CofO has been Amended/Reconsidered on ${date}. Authority: ${auth}.`,
    };

    const remark = templates[tsSelectedType]
        || `File ${fn} status updated on ${date}. Authority: ${auth}.`;

    document.getElementById('ts-remark').value = remark;
}

/* ────────────────── Edit ────────────────── */
function tsOpenEdit(btn) {
    const record = JSON.parse(btn.closest('tr').dataset.record);
    tsEditId       = record.id;
    tsSelectedType = record.title_type ?? '';

    tsApplyTheme(tsSelectedType);

    // File card
    document.getElementById('ts-file_no').value          = record.file_no ?? '';
    document.getElementById('ts-source_table').value      = record.source_table ?? '';
    document.getElementById('ts-source_id').value         = record.source_id ?? '';
    document.getElementById('ts-file-no-display').textContent    = record.file_no ?? '';
    document.getElementById('ts-file-title-display').textContent = record.file_title ?? '';
    document.getElementById('ts-file-placeholder').classList.add('hidden');
    document.getElementById('ts-file-selected').classList.remove('hidden');
    document.getElementById('ts-file-card').classList.add('selected');

    const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val ?? ''; };
    setVal('ts-file_title',          record.file_title);
    setVal('ts-applicant_name',      record.applicant_name);
    setVal('ts-title_no',            record.title_no);
    setVal('ts-plot_no',             record.plot_no);
    setVal('ts-location',            record.location);
    setVal('ts-land_use',            record.land_use);
    setVal('ts-date_of_issue',       record.date_of_issue);
    setVal('ts-date_of_expiry',      record.date_of_expiry);
    setVal('ts-authority',           record.authority);
    setVal('ts-authority_reference', record.authority_reference);
    setVal('ts-remark',              record.remark);

    document.getElementById('ts-modal').classList.remove('hidden');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

/* ────────────────── View ────────────────── */
function tsView(btn) {
    const record = JSON.parse(btn.closest('tr').dataset.record);
    const body   = document.getElementById('ts-view-body');

    const row = (label, value) => `
        <div class="flex gap-2 border-b border-slate-50 pb-2">
            <span class="w-44 shrink-0 text-xs font-semibold text-slate-500 uppercase tracking-wide">${label}</span>
            <span class="text-slate-800">${value || '—'}</span>
        </div>`;

    body.innerHTML = [
        row('File No',           record.file_no),
        row('File Title',        record.file_title),
        row('Applicant',         record.applicant_name),
        row('Title Type',        record.title_type),
        row('Title No',          record.title_no),
        row('Plot No',           record.plot_no),
        row('Location',          record.location),
        row('Land Use',          record.land_use),
        row('Date of Issue',     record.date_of_issue),
        row('Date of Expiry',    record.date_of_expiry),
        row('Authority',         record.authority),
        row('Reference',         record.authority_reference),
        row('Remark',            record.remark),
        row('Status',            record.status),
    ].join('');

    document.getElementById('ts-view-modal').classList.remove('hidden');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function tsCloseViewModal() {
    document.getElementById('ts-view-modal').classList.add('hidden');
}

/* ────────────────── Submit ────────────────── */
function tsSubmit() {
    const fileNo = document.getElementById('ts-file_no').value.trim();
    if (!fileNo) {
        Swal.fire({ icon: 'warning', title: 'File Required', text: 'Please select a file number first.' });
        return;
    }

    const url    = tsEditId ? `${TS_BASE_URL}/${tsEditId}` : TS_STORE_URL;
    const method = tsEditId ? 'PUT' : 'POST';

    const g = id => (document.getElementById(id)?.value || '').trim();

    const payload = {
        url:             TS_URL,
        title_type:      tsSelectedType,
        file_no:         fileNo,
        source_table:    g('ts-source_table'),
        source_id:       g('ts-source_id'),
        file_title:      g('ts-file_title'),
        applicant_name:  g('ts-applicant_name'),
        plot_no:         g('ts-plot_no'),
        location:        g('ts-location'),
        land_use:        g('ts-land_use'),
        district:        g('ts-district'),
        lga:             g('ts-lga'),
        authority:       g('ts-authority'),
        remark:          g('ts-remark'),
    };

    fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': TS_CSRF,
            'Accept':       'application/json',
        },
        body: JSON.stringify(payload),
    })
    .then(r => r.json())
    .then(res => {
        if (!res.success) {
            const msgs = res.errors
                ? Object.values(res.errors).flat().join('\n')
                : (res.message || 'Something went wrong.');
            Swal.fire({ icon: 'error', title: 'Error', text: msgs });
            return;
        }
        tsCloseModal();
        const detail = res.data?.remark || res.message;
        Swal.fire({ icon: 'success', title: 'Saved', text: detail, timer: 4000, showConfirmButton: true, confirmButtonText: 'OK' })
            .then(() => reloadTsPage());
    })
    .catch(() => Swal.fire({ icon: 'error', title: 'Error', text: 'Something went wrong.' }));
}

/* ────────────────── Approve / Reject / Delete ────────────────── */
function tsApprove(id) {
    Swal.fire({
        title: 'Approve Application?', icon: 'question',
        showCancelButton: true, confirmButtonText: 'Yes, Approve', confirmButtonColor: '#10b981',
    }).then(result => {
        if (!result.isConfirmed) return;
        fetch(`${TS_BASE_URL}/${id}/approve`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': TS_CSRF, 'Accept': 'application/json' },
        })
        .then(r => r.json())
        .then(res => {
            Swal.fire({ icon: res.success ? 'success' : 'error', title: res.message, timer: 1800, showConfirmButton: false })
                .then(() => reloadTsPage());
        });
    });
}

function tsReject(id) {
    Swal.fire({
        title: 'Reject Application', input: 'textarea',
        inputLabel: 'Reason (optional)', inputPlaceholder: 'Enter rejection reason...',
        showCancelButton: true, confirmButtonText: 'Reject', confirmButtonColor: '#ef4444',
    }).then(result => {
        if (!result.isConfirmed) return;
        fetch(`${TS_BASE_URL}/${id}/reject`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': TS_CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ reason: result.value || '' }),
        })
        .then(r => r.json())
        .then(res => {
            Swal.fire({ icon: res.success ? 'success' : 'error', title: res.message, timer: 1800, showConfirmButton: false })
                .then(() => reloadTsPage());
        });
    });
}

function tsDelete(id) {
    Swal.fire({
        title: 'Delete Application?', text: 'This action cannot be undone.',
        icon: 'warning', showCancelButton: true,
        confirmButtonText: 'Yes, Delete', confirmButtonColor: '#ef4444',
    }).then(result => {
        if (!result.isConfirmed) return;
        fetch(`${TS_BASE_URL}/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': TS_CSRF, 'Accept': 'application/json' },
        })
        .then(r => r.json())
        .then(res => {
            Swal.fire({ icon: res.success ? 'success' : 'error', title: res.message, timer: 1800, showConfirmButton: false })
                .then(() => reloadTsPage());
        });
    });
}

/* ────────────────── Helpers ────────────────── */
function tsCloseModal() {
    document.getElementById('ts-modal').classList.add('hidden');
    tsClearForm();
}

function tsClearForm() {
    ['ts-file_no','ts-file_title','ts-applicant_name','ts-plot_no','ts-location',
     'ts-land_use','ts-district','ts-lga','ts-source_table','ts-source_id',
     'ts-authority','ts-remark']
    .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });

    document.getElementById('ts-file-placeholder').classList.remove('hidden');
    document.getElementById('ts-file-selected').classList.add('hidden');
    document.getElementById('ts-file-card').classList.remove('selected');
    document.getElementById('ts-file-summary').classList.add('hidden');

    tsEditId       = null;
    tsSelectedType = '';
}

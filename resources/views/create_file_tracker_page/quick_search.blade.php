@extends('layouts.app')
@section('page-title')
    {{ __('Quick Search & File Location') }}
@endsection
@section('content')
@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endpush

    <div class="flex-1 overflow-y-auto overflow-x-hidden">
        @include('admin.header')

        <div class="bg-gradient-to-r from-red-700 via-red-600 to-red-800 px-6 py-3 flex items-center gap-3 shadow-sm">
            <i data-lucide="search" class="h-5 w-5 text-white shrink-0"></i>
            <div class="flex items-center gap-2">
                <span class="text-white font-bold text-sm uppercase tracking-widest">Quick Search</span>
                <span class="text-red-100 text-sm">·</span>
                <span class="text-white text-sm font-medium">File Location &amp; Status</span>
            </div>
            {{-- Hidden for now: Send File Search Request --}}
            <button type="button" class="js-fr-open ml-auto hidden items-center gap-1.5 rounded-lg bg-white/15 hover:bg-white/25 px-3 py-1.5 text-xs font-semibold text-white border border-white/30 transition-all"
                title="Send a File Search Request to SCB Monitors">
                <i data-lucide="send" class="h-3.5 w-3.5"></i>
                Send File Search Request
            </button>
        </div>

        <div class="max-w-7xl mx-auto px-4 py-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                <!-- Left: Search + Result -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Enter File Number</label>
                        <div class="flex gap-2">
                            <input id="qs-input" type="text" readonly autocomplete="off"
                                placeholder="Click “Select” to choose a file number"
                                class="flex-1 rounded-lg border border-gray-300 bg-gray-50 px-4 py-3 text-sm uppercase cursor-pointer focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <button type="button" id="qs-pick"
                                class="inline-flex items-center gap-2 rounded-lg bg-gray-800 px-4 py-3 text-sm font-semibold text-white hover:bg-gray-900 whitespace-nowrap">
                                <i data-lucide="folder-search" class="h-4 w-4"></i><span>Select</span>
                            </button>
                            <button type="button" id="qs-btn"
                                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-3 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                                <i data-lucide="search" class="h-4 w-4"></i><span>Search</span>
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-gray-500">Every search returns a clear outcome and next action.</p>
                    </div>

                    <div id="qs-result" class="hidden"></div>

                    <!-- SCB Feedback table -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                            <h3 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                                <i data-lucide="message-square-reply" class="h-4 w-4 text-indigo-600"></i> SCB Feedback
                            </h3>
                            <button type="button" id="qs-tbl-refresh" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 inline-flex items-center gap-1">
                                <i data-lucide="refresh-cw" class="h-3.5 w-3.5"></i> Refresh
                            </button>
                        </div>
                        <div id="qs-feedback-table" class="overflow-x-auto"></div>
                    </div>
                </div>

                <!-- Right: File Request Log panel -->
                <aside class="lg:col-span-1 lg:sticky lg:top-6">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                                    <i data-lucide="clipboard-list" class="h-4 w-4 text-indigo-600"></i> File Search History <span id="qs-log-total" class="text-gray-400 font-semibold"></span>
                                </h3>
                                <button type="button" id="qs-fb-refresh" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 inline-flex items-center gap-1">
                                    <i data-lucide="refresh-cw" class="h-3.5 w-3.5"></i> Refresh
                                </button>
                            </div>
                            <div id="qs-log-filters" class="mt-3 flex flex-wrap items-center gap-1.5"></div>
                        </div>
                        <div id="qs-feedback" class="max-h-[70vh] overflow-y-auto"></div>
                    </div>
                </aside>
            </div>
        </div>
    </div>

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
    @include('components.global-fileno-modal')
    @include('create_file_tracker_page.partials.fr-modal')
    <script>
    (function () {
        const RESOLVE_URL = "{{ route('create-file-tracker.quick-search.resolve') }}";
        const SLIP_URL    = "{{ route('create-file-tracker.slip') }}";
        const FR_URL      = "{{ route('create-file-tracker.file-request') }}";
        const UPDATE_URL  = "{{ route('create-file-tracker.quick-search.update-status') }}";
        const FEEDBACK_URL= "{{ route('create-file-tracker.quick-search.scb-feedback') }}";
        const CSRF        = "{{ csrf_token() }}";

        const STATUS_OPTIONS = [
            ['IN_TRANSIT', 'In Transit'],
            ['IN_ARCHIVE', 'In Archive'],
            ['IN_POOL_OFFICE', 'In Pool Office'],
            ['FILE_NOT_FOUND', 'File Not Found'],
            ['REFER_TO_ORIGINAL_REGISTRY', 'Refer to Original Registry'],
        ];
        const STATUS_META = {
            IN_TRANSIT:                 { label: 'In Transit',                 cls: 'bg-amber-100 text-amber-800 border-amber-300',  icon: 'truck' },
            IN_ARCHIVE:                 { label: 'In Archive',                 cls: 'bg-green-100 text-green-800 border-green-300',  icon: 'archive' },
            IN_ARCHIVE_FOUND:           { label: 'In Archive — Found',         cls: 'bg-green-100 text-green-800 border-green-300',  icon: 'check-circle' },
            IN_ARCHIVE_NOT_FOUND:       { label: 'Not Found In Archive',       cls: 'bg-red-100 text-red-800 border-red-300',       icon: 'alert-triangle' },
            IN_POOL_OFFICE:             { label: 'In Pool Office',             cls: 'bg-blue-100 text-blue-800 border-blue-300',    icon: 'folder-search' },
            IN_POOL_OFFICE_FOUND:       { label: 'In Pool Office — Found',     cls: 'bg-green-100 text-green-800 border-green-300',  icon: 'check-circle' },
            IN_POOL_OFFICE_NOT_FOUND:   { label: 'In Pool Office — Not Found', cls: 'bg-red-100 text-red-800 border-red-300',       icon: 'alert-triangle' },
            PENDING_FILE:               { label: 'Pending (Not Indexed)',      cls: 'bg-gray-100 text-gray-800 border-gray-300',    icon: 'help-circle' },
            BLIND_REQUEST_SENT:         { label: 'Blind Request Sent',         cls: 'bg-indigo-100 text-indigo-800 border-indigo-300', icon: 'send' },
            FILE_NOT_FOUND:             { label: 'File Not Found',             cls: 'bg-red-100 text-red-800 border-red-300',       icon: 'alert-triangle' },
            REFER_TO_ORIGINAL_REGISTRY: { label: 'Refer to Original Registry', cls: 'bg-gray-100 text-gray-800 border-gray-300',    icon: 'corner-up-right' },
        };

        const input  = document.getElementById('qs-input');
        const btn    = document.getElementById('qs-btn');
        const pickBtn= document.getElementById('qs-pick');
        const result = document.getElementById('qs-result');

        const esc = v => (v == null ? '' : String(v)).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        const row = (l, v) => v ? `<div class="flex justify-between gap-4 py-2 border-b border-gray-100 last:border-0">
            <span class="text-xs font-medium text-gray-500">${esc(l)}</span>
            <span class="text-sm text-gray-800 text-right">${esc(v)}</span></div>` : '';

        function pickFileNumber() {
            if (typeof GlobalFileNoModal === 'undefined') { search(); return; }
            GlobalFileNoModal.open({
                callback: function (data) {
                    const fileNumber = (data.fileNumber || '').toString().trim();
                    if (!fileNumber) return;
                    input.value = fileNumber;
                    search();
                }
            });
        }

        function actionButtons(d) {
            const out = [];
            const fno = encodeURIComponent(d.file_number);
            if (d.status === 'IN_TRANSIT' && d.file_tracker_id) {
                out.push(`<a href="/create-file-tracker/${d.file_tracker_id}/request-sheet" target="_blank"
                    class="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                    <i data-lucide="printer" class="h-4 w-4"></i> Print Tracking Confirmation Slip</a>`);
            }
            // SCB confirmed Found -> log the file (redirect to create-file-tracker, prefilled).
            if (d.can_log) {
                out.push(`<button type="button" data-log class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    <i data-lucide="file-plus" class="h-4 w-4"></i> Log File</button>`);
            }
            if (d.slip_variant) {
                const labels = { tracking_sheet:'Print Tracking Sheet', missing:'Print Missing File Slip', refer_registry:'Print Refer-to-Original-Registry Slip', tracking_confirmation:'Print Slip' };
                out.push(`<a href="${SLIP_URL}?file_number=${fno}&variant=${d.slip_variant}" target="_blank"
                    class="inline-flex items-center gap-2 rounded-lg bg-slate-700 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                    <i data-lucide="printer" class="h-4 w-4"></i> ${labels[d.slip_variant] || 'Print Slip'}</a>`);
            }
            if (d.can_send_fr) {
                const label = d.is_blind ? 'Send Blind Request to SCB Monitor' : 'Send File Search Request to SCB Monitor';
                const frCls = d.is_blind ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700';
                out.push(`<button type="button" data-fr class="inline-flex items-center gap-2 rounded-lg ${frCls} px-4 py-2 text-sm font-semibold text-white">
                    <i data-lucide="send" class="h-4 w-4"></i> ${label}</button>`);
            }
            return out.join('');
        }

        function logFile(d) {
            const params = new URLSearchParams({ file_number: d.file_number || '' });
            if (d.file_title) params.set('file_title', d.file_title);
            window.location = '/create-file-tracker?' + params.toString();
        }

        function render(d) {
            const meta = STATUS_META[d.status] || { label:d.status, cls:'bg-gray-100 text-gray-800 border-gray-300', icon:'file' };
            result.innerHTML = `
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <div class="text-lg font-bold text-gray-900">${esc(d.file_number)}</div>
                            <div class="text-sm text-gray-500">${esc(d.file_title || '—')}</div>
                        </div>
                        <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-bold ${meta.cls}">
                            <i data-lucide="${meta.icon}" class="h-3.5 w-3.5"></i> ${esc(meta.label)}
</span>
                    </div>
                    <div class="px-6 py-3">
                        ${row('Registry', d.registry)}
                        ${row('Current Location', d.current_location)}
                        ${row('Rack / Shelf', d.rack_shelf)}
                        ${row('Receiving Officer', d.receiving_officer_name)}
                        ${row('Tracking ID', d.tracking_id)}
                        <div class="mt-3 rounded-lg bg-indigo-50 border border-indigo-100 px-4 py-3">
                            <div class="text-xs font-semibold text-indigo-700 uppercase tracking-wide">Next Action</div>
                            <div class="text-sm text-indigo-900 mt-0.5">${esc(d.next_action)}</div>
                        </div>

                        <details class="mt-3 rounded-lg border border-gray-200">
                            <summary class="cursor-pointer select-none px-4 py-2 text-xs font-semibold text-gray-600 flex items-center gap-2">
                                <i data-lucide="pencil" class="h-3.5 w-3.5"></i> Update Location Status
                            </summary>
                            <div class="px-4 pb-4 pt-1 space-y-2">
                                <select data-us-status class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                    ${STATUS_OPTIONS.map(o => `<option value="${o[0]}" ${o[0]===d.status?'selected':''}>${o[1]}</option>`).join('')}
                                </select>
                                <input data-us-loc type="text" placeholder="Location / note (optional)" value="${esc(d.current_location || '')}"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                <button type="button" data-us-save class="inline-flex items-center gap-2 rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-900">
                                    <i data-lucide="save" class="h-4 w-4"></i> Save Status
                                </button>
                                <div data-us-msg class="text-xs"></div>
                            </div>
                        </details>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-100 flex flex-wrap gap-2">${actionButtons(d)}</div>
                </div>`;
            result.classList.remove('hidden');

            const frBtn = result.querySelector('[data-fr]');
            if (frBtn) frBtn.addEventListener('click', () => sendFR(d, frBtn));
            const logBtn = result.querySelector('[data-log]');
            if (logBtn) logBtn.addEventListener('click', () => logFile(d));
            const saveBtn = result.querySelector('[data-us-save]');
            if (saveBtn) saveBtn.addEventListener('click', () => updateStatus(d, result.querySelector('[data-us-status]').value, result.querySelector('[data-us-loc]').value, saveBtn));
            if (window.lucide) window.lucide.createIcons();
        }

        function renderError(msg) {
            result.innerHTML = `<div class="rounded-xl border border-red-200 bg-red-50 px-6 py-4 text-sm text-red-700">${esc(msg)}</div>`;
            result.classList.remove('hidden');
        }

        async function search() {
            const q = input.value.trim();
            if (!q) { input.focus(); return; }
            btn.disabled = true; result.classList.add('hidden');
            try {
                const res  = await fetch(`${RESOLVE_URL}?query=${encodeURIComponent(q)}`, { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                json.success ? render(json.data) : renderError(json.message || 'Search failed.');
            } catch (e) { renderError('Network error — please try again.'); }
            finally { btn.disabled = false; }
        }

        async function updateStatus(d, status, location, saveBtn) {
            const msg = result.querySelector('[data-us-msg]');
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i data-lucide="loader" class="h-4 w-4 animate-spin"></i> Saving…';
            if (window.lucide) window.lucide.createIcons();
            try {
                const res = await fetch(UPDATE_URL, {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
                    body: JSON.stringify({ file_number: d.file_number, status, current_location: location }),
                });
                const json = await res.json();
                if (json.success) { render(json.data); }
                else if (msg) { msg.className = 'text-xs text-red-600'; msg.textContent = json.message || 'Could not update.'; saveBtn.disabled = false; saveBtn.innerHTML = '<i data-lucide="save" class="h-4 w-4"></i> Save Status'; if (window.lucide) window.lucide.createIcons(); }
            } catch (e) {
                if (msg) { msg.className = 'text-xs text-red-600'; msg.textContent = 'Network error — please try again.'; }
                saveBtn.disabled = false; saveBtn.innerHTML = '<i data-lucide="save" class="h-4 w-4"></i> Save Status';
                if (window.lucide) window.lucide.createIcons();
            }
        }

        async function sendFR(d, frBtn) {
            frBtn.disabled = true;
            frBtn.innerHTML = '<i data-lucide="loader" class="h-4 w-4 animate-spin"></i> Sending…';
            if (window.lucide) window.lucide.createIcons();
            try {
                const res = await fetch(FR_URL, {
                    method:'POST',
                    headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
                    body: JSON.stringify({ file_number:d.file_number, file_title:d.file_title, current_location:d.current_location, resolved_status:d.status }),
                });
                const json = await res.json();
                if (json.success) {
                    frBtn.outerHTML = `<span class="inline-flex items-center gap-2 rounded-lg bg-green-100 px-4 py-2 text-sm font-semibold text-green-800">
                        <i data-lucide="check" class="h-4 w-4"></i> File Request ${esc(json.data?.request_no || '')} sent to SCB Monitors</span>`;
                } else {
                    frBtn.disabled = false; frBtn.innerHTML = '<i data-lucide="send" class="h-4 w-4"></i> Retry Send';
                    alert(json.message || 'Could not send the request.');
                }
                if (window.lucide) window.lucide.createIcons();
            } catch (e) {
                frBtn.disabled = false; frBtn.innerHTML = '<i data-lucide="send" class="h-4 w-4"></i> Retry Send';
                alert('Network error — please try again.');
            }
        }

        // ── File Request Log + SCB Feedback ──
        const LOG_URL    = "{{ route('create-file-tracker.quick-search.file-request-log') }}";
        const ACTED_BASE = "{{ url('create-file-tracker/quick-search/file-request') }}";
        const fbBox      = document.getElementById('qs-feedback');
        const tblBox     = document.getElementById('qs-feedback-table');
        const logFilters = document.getElementById('qs-log-filters');
        let   logStatus  = 'FOUND';   // default active tab

        const LOG_BADGE = {
            'Awaiting':  'bg-amber-100 text-amber-800',
            'Found':     'bg-green-100 text-green-800',
            'Not Found': 'bg-red-100 text-red-800',
        };

        // Front Desk acts on an SCB-responded request (Log File / Refer), then it
        // drops out of the SCB Feedback queue and lives in the File Request Log.
        async function frontDeskAct(btn) {
            const id = btn.dataset.id, kind = btn.dataset.fbAct;
            const fno = btn.dataset.fno, title = btn.dataset.title || '';
            btn.disabled = true;
            try {
                await fetch(`${ACTED_BASE}/${id}/front-desk-acted`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                });
            } catch (e) { /* non-fatal — still let the Front Desk proceed */ }

            if (kind === 'log') {
                logFile({ file_number: fno, file_title: title });   // navigates to Create File Tracker
            } else {
                window.open(`${SLIP_URL}?file_number=${encodeURIComponent(fno)}&variant=refer_registry`, '_blank');
                loadScbFeedback();
                loadLog();
            }
        }

        function renderLogFilters(counts) {
            counts = counts || {};
            const totalEl = document.getElementById('qs-log-total');
            if (totalEl) totalEl.textContent = `[${counts.all ?? 0}]`;
            const chips = [
                ['FOUND',     'Found',     counts.found],
                ['NOT_FOUND', 'Not Found', counts.not_found],
                ['PENDING',   'Awaiting',  counts.pending],
                // ['', 'FSR History', counts.all],  // hidden for now
            ];
            logFilters.innerHTML = chips.map(([val, label, n]) => {
                const on = logStatus === val;
                return `<button type="button" data-log-filter="${val}"
                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold border transition-colors ${
                        on ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'}">
                    ${label}<span class="inline-flex items-center justify-center min-w-[1.1rem] h-4 px-1 rounded-full text-[10px] font-bold ${
                        on ? 'bg-white/25 text-white' : 'bg-gray-100 text-gray-500'}">${n ?? 0}</span></button>`;
            }).join('');
            logFilters.querySelectorAll('[data-log-filter]').forEach(b =>
                b.addEventListener('click', () => { logStatus = b.dataset.logFilter; loadLog(); }));
        }

        // SCB Feedback queue — SCB has responded, Front Desk hasn't acted yet.
        function renderTable(rows) {
            if (!rows.length) {
                tblBox.innerHTML = '<div class="px-4 py-10 text-center text-xs text-gray-400 flex flex-col items-center gap-2"><i data-lucide="inbox" class="h-6 w-6 text-gray-300"></i> Nothing awaiting your action.</div>';
                if (window.lucide) window.lucide.createIcons();
                return;
            }
            tblBox.innerHTML = `
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] uppercase tracking-wide text-gray-400 bg-gray-50 border-b border-gray-100">
                            <th class="px-4 py-2.5">File No</th>
                            <th class="px-4 py-2.5">Title</th>
                            <th class="px-4 py-2.5">Location</th>
                            <th class="px-4 py-2.5">SCB Response</th>
                            <th class="px-4 py-2.5 whitespace-nowrap">Responded</th>
                            <th class="px-4 py-2.5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows.map(r => {
                            const badge  = LOG_BADGE[r.scb_response] || 'bg-gray-100 text-gray-700';
                            const icon   = r.found ? 'check' : (r.not_found ? 'x' : 'clock');
                            const action = r.found
                                ? `<button type="button" data-fb-act="log" data-id="${r.id}" data-fno="${esc(r.file_number)}" data-title="${esc(r.file_title || '')}" class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700"><i data-lucide="file-plus" class="h-3.5 w-3.5"></i> Log File</button>`
                                : (r.not_found
                                    ? `<button type="button" data-fb-act="refer" data-id="${r.id}" data-fno="${esc(r.file_number)}" class="inline-flex items-center gap-1 rounded-lg bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800"><i data-lucide="printer" class="h-3.5 w-3.5"></i> Print</button>`
                                    : `<span class="text-[11px] text-gray-400 italic">Awaiting SCB…</span>`);
                            return `
                            <tr class="border-b border-gray-50 hover:bg-gray-50/60">
                                <td class="px-4 py-2.5">
                                    <div class="font-semibold text-gray-800">${esc(r.file_number)}</div>
                                    <div class="text-[11px] text-gray-400">${esc(r.request_no)}</div>
                                    <span class="mt-1 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold ${r.is_dfr ? 'bg-gray-200 text-gray-600' : (r.is_blind ? 'bg-purple-100 text-purple-700' : 'bg-sky-100 text-sky-700')}">
                                        <i data-lucide="${r.is_dfr ? 'file-text' : (r.is_blind ? 'eye-off' : 'folder-search')}" class="h-2.5 w-2.5"></i> ${esc(r.request_type)}</span>
                                </td>
                                <td class="px-4 py-2.5 text-gray-600 max-w-[200px] truncate" title="${esc(r.file_title || '')}">${esc(r.file_title || '—')}</td>
                                <td class="px-4 py-2.5 text-gray-500">${esc(r.current_location || r.location_type)}</td>
                                <td class="px-4 py-2.5">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold ${badge}">
                                        <i data-lucide="${icon}" class="h-3 w-3"></i> ${esc(r.scb_response)}</span>
                                </td>
                                <td class="px-4 py-2.5 text-xs text-gray-500 whitespace-nowrap">${esc(r.responded_at || '—')}</td>
                                <td class="px-4 py-2.5 text-right">${action}</td>
                            </tr>`;
                        }).join('')}
                    </tbody>
                </table>`;
            tblBox.querySelectorAll('[data-fb-act]').forEach(b =>
                b.addEventListener('click', () => frontDeskAct(b)));
            if (window.lucide) window.lucide.createIcons();
        }

        async function loadScbFeedback() {
            tblBox.innerHTML = '<div class="px-4 py-8 text-center text-xs text-gray-400">Loading…</div>';
            try {
                const res  = await fetch(FEEDBACK_URL, { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                renderTable((json && json.data) || []);
            } catch (e) {
                tblBox.innerHTML = '<div class="px-4 py-8 text-center text-xs text-red-500">Could not load the SCB feedback.</div>';
            }
        }

        // File Request Log — read-only history of every request the user raised.
        async function loadLog() {
            fbBox.innerHTML = '<div class="px-4 py-8 text-center text-xs text-gray-400">Loading…</div>';
            try {
                const url  = LOG_URL + (logStatus ? ('?status=' + encodeURIComponent(logStatus)) : '');
                const res  = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                renderLogFilters(json.counts);
                const rows = (json && json.data) || [];
                if (!rows.length) {
                    fbBox.innerHTML = '<div class="px-4 py-10 text-center text-xs text-gray-400 flex flex-col items-center gap-2"><i data-lucide="inbox" class="h-6 w-6 text-gray-300"></i> No file requests in this view.</div>';
                    if (window.lucide) window.lucide.createIcons();
                    return;
                }
                fbBox.innerHTML = `
                    <ul class="divide-y divide-gray-100">
                        ${rows.map(r => {
                            const badge = LOG_BADGE[r.scb_response] || 'bg-gray-100 text-gray-700';
                            const icon  = r.found ? 'check' : (r.not_found ? 'x' : 'clock');
                            const done  = r.front_desk_acted
                                ? `<span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold bg-gray-200 text-gray-600"><i data-lucide="check-check" class="h-2.5 w-2.5"></i> Completed</span>`
                                : '';
                            return `
                            <li class="px-5 py-4 hover:bg-gray-50/60">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="font-semibold text-gray-800 truncate">${esc(r.file_number)}</div>
                                        <div class="text-[11px] text-gray-400">${esc(r.request_no)}</div>
                                    </div>
                                    <span class="shrink-0 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold ${badge}">
                                        <i data-lucide="${icon}" class="h-3 w-3"></i> ${esc(r.scb_response)}</span>
                                </div>
                                <div class="mt-1.5 text-xs text-gray-600 truncate" title="${esc(r.file_title || '')}">${esc(r.file_title || '—')}</div>
                                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-gray-500">
                                    <span class="inline-flex items-center gap-1"><i data-lucide="map-pin" class="h-3 w-3 text-gray-400"></i> ${esc(r.current_location || r.location_type || '—')}</span>
                                    <span class="inline-flex items-center gap-1"><i data-lucide="clock" class="h-3 w-3 text-gray-400"></i> ${esc(r.requested_at || '—')}</span>
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold ${r.is_dfr ? 'bg-gray-200 text-gray-600' : (r.is_blind ? 'bg-purple-100 text-purple-700' : 'bg-sky-100 text-sky-700')}">
                                        <i data-lucide="${r.is_dfr ? 'file-text' : (r.is_blind ? 'eye-off' : 'folder-search')}" class="h-2.5 w-2.5"></i> ${esc(r.request_type)}</span>
                                    ${done}
                                </div>
                            </li>`;
                        }).join('')}
                    </ul>`;
                if (window.lucide) window.lucide.createIcons();
            } catch (e) {
                fbBox.innerHTML = '<div class="px-4 py-8 text-center text-xs text-red-500">Could not load the file request log.</div>';
            }
        }

        function reloadPanels() { loadScbFeedback(); loadLog(); }

        btn.addEventListener('click', search);
        if (pickBtn) pickBtn.addEventListener('click', pickFileNumber);
        input.addEventListener('click', pickFileNumber);   // read-only: open picker instead of typing
        document.getElementById('qs-fb-refresh').addEventListener('click', loadLog);
        document.getElementById('qs-tbl-refresh').addEventListener('click', loadScbFeedback);
        reloadPanels();
    })();
    </script>
@endpush
@endsection

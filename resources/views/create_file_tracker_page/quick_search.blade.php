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
            <!-- Reporting summary -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mb-6">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <h3 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                        <i data-lucide="bar-chart-3" class="h-4 w-4 text-indigo-600"></i> File Search Report
                    </h3>
                    <span id="qs-rep-date" class="text-xs font-medium text-gray-400"></span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    <div class="rounded-lg border border-indigo-100 bg-indigo-50/60 px-4 py-3">
                        <div class="text-[11px] font-semibold uppercase tracking-wide text-indigo-700">Requests Today</div>
                        <div id="qs-rep-today" class="mt-1 text-2xl font-bold text-indigo-900">—</div>
                    </div>
                    <div class="rounded-lg border border-sky-100 bg-sky-50/60 px-4 py-3">
                        <div class="text-[11px] font-semibold uppercase tracking-wide text-sky-700">Blind / Open</div>
                        <div id="qs-rep-blind" class="mt-1 text-2xl font-bold text-sky-900">—</div>
                    </div>
                    <div class="rounded-lg border border-emerald-100 bg-emerald-50/60 px-4 py-3">
                        <div class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700">Found</div>
                        <div id="qs-rep-found" class="mt-1 text-2xl font-bold text-emerald-900">—</div>
                    </div>
                    <div class="rounded-lg border border-amber-100 bg-amber-50/60 px-4 py-3">
                        <div class="text-[11px] font-semibold uppercase tracking-wide text-amber-700">Not Found</div>
                        <div id="qs-rep-notfound" class="mt-1 text-2xl font-bold text-amber-900">—</div>
                    </div>
                    <div class="rounded-lg border border-red-100 bg-red-50/60 px-4 py-3">
                        <div class="text-[11px] font-semibold uppercase tracking-wide text-red-700">Missing</div>
                        <div id="qs-rep-missing" class="mt-1 text-2xl font-bold text-red-900">—</div>
                    </div>
                </div>
            </div>

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
                        <div class="px-6 py-2.5 border-b border-gray-100">
                            <div class="relative">
                                <i data-lucide="search" class="h-3.5 w-3.5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                                <input id="qs-tbl-search" type="text" placeholder="Search file no, title, requester…" class="w-full rounded-lg border border-gray-300 pl-9 pr-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                            </div>
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
                            <div class="mt-2.5 relative">
                                <i data-lucide="search" class="h-3.5 w-3.5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                                <input id="qs-log-search" type="text" placeholder="Search file no, title, requester…" class="w-full rounded-lg border border-gray-300 pl-9 pr-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                            </div>
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
    @php
        $reqOffices = ($offices ?? collect())->map(fn ($o) => [
            'code'       => $o->office_code,
            'name'       => $o->office_name,
            'department' => $o->department,
        ])->values();
        $reqOfficers = ($receivingOfficers ?? collect())->map(fn ($o) => [
            'id'            => $o->id,
            'name'          => trim(($o->first_name ?? '') . ' ' . ($o->last_name ?? '')),
            'department_id' => $o->department_id,
        ])->filter(fn ($o) => $o['name'] !== '')->values();
        $reqDeptNameToId = ($departmentIds ?? collect())->mapWithKeys(fn ($d) => [$d->name => $d->id]);
    @endphp
    <script>
    (function () {
        const RESOLVE_URL = "{{ route('create-file-tracker.quick-search.resolve') }}";
        const SLIP_URL    = "{{ route('create-file-tracker.slip') }}";
        const FR_URL      = "{{ route('create-file-tracker.file-request') }}";
        const UPDATE_URL  = "{{ route('create-file-tracker.quick-search.update-status') }}";
        const FEEDBACK_URL= "{{ route('create-file-tracker.quick-search.scb-feedback') }}";
        const CSRF        = "{{ csrf_token() }}";
        const IS_SUPER_ADMIN = @json(auth()->user()->isSuperAdmin());
        // ── Requester cascade data (mirrors Create File Tracker) ──
        const REQ_DEPARTMENTS = @json(($departments ?? collect())->values());
        const REQ_OFFICES     = @json($reqOffices);
        const REQ_OFFICERS    = @json($reqOfficers);
        const REQ_DEPT_NAME_TO_ID = @json($reqDeptNameToId);
        const ADD_OFFICER_URL = "{{ route('create-file-tracker.receiving-officers.store') }}";

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
            // For In-Transit files the dedicated "Print Tracking Confirmation Slip" button
            // (above) already covers printing, so suppress the generic "Print Slip" button.
            if (d.slip_variant && d.status !== 'IN_TRANSIT') {
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
            // Backfill the requester details captured on the file request into the
            // Create File Tracker form.
            if (d.receiving_officer)     params.set('req_officer', d.receiving_officer);
            if (d.requester_office)      params.set('req_office', d.requester_office);
            if (d.requester_office_code) params.set('req_office_code', d.requester_office_code);
            if (d.requester_department)  params.set('req_department', d.requester_department);
            window.location = '/create-file-tracker?' + params.toString();
        }

        function render(d) {
            const meta = STATUS_META[d.status] || { label:d.status, cls:'bg-gray-100 text-gray-800 border-gray-300', icon:'file' };
            // SCB has confirmed the file is physically present (…_FOUND, but not …NOT_FOUND).
            const isFound = /_FOUND$/.test(d.status || '') && !/NOT_FOUND/.test(d.status || '');
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
                        ${row('Logged Out', d.logged_out_at)}
                        ${row('Request Sent', d.fr_sent_at ? (d.fr_sent_at + (d.fr_request_no ? ' · ' + d.fr_request_no : '')) : '')}
                        ${isFound ? `
                        <div class="mt-3 rounded-lg bg-green-50 border border-green-200 px-4 py-3 flex items-center gap-2">
                            <i data-lucide="check-circle" class="h-4 w-4 text-green-600 shrink-0"></i>
                            <span class="text-sm font-bold text-green-800">File Found</span>
                        </div>` : ''}
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
                    ${d.can_send_fr ? `
                    <div class="px-6 pt-4 border-t border-gray-100">
                        <div class="text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">Requester</div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-600 mb-1">Requester Office (Departments) <span class="text-red-500">*</span></label>
                                <select data-fr-dept class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                    <option value="">— Select Department —</option>
                                    ${REQ_DEPARTMENTS.map(dn => `<option value="${esc(dn)}">${esc(dn)}</option>`).join('')}
                                    <option value="${DEPT_OTHER}">Other…</option>
                                </select>
                                <input data-fr-dept-other type="text" placeholder="Specify department *" class="hidden mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-600 mb-1">Requester Office <span class="text-red-500">*</span></label>
                                <select data-fr-office class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" disabled>
                                    <option value="">— Select Office —</option>
                                </select>
                                <input data-fr-office-other type="text" placeholder="Specify office *" class="hidden mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-600 mb-1">Requester Officer <span class="text-red-500">*</span></label>
                                <select data-fr-officer class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" disabled>
                                    <option value="">— Select Officer —</option>
                                </select>
                            </div>
                        </div>

                        <!-- Add Receiving Officer card (shown when the office/officer is not listed) -->
                        <div data-fr-addcard class="hidden mt-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3">
                            <div class="flex items-center gap-2 text-amber-800 mb-2">
                                <i data-lucide="user-plus" class="h-4 w-4"></i>
                                <span class="text-xs font-bold">Add Receiving Officer</span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <input data-ao-first type="text" placeholder="First name *" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                <input data-ao-last type="text" placeholder="Last name *" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div class="mt-2 flex items-center gap-2">
                                <button type="button" data-ao-save class="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700">
                                    <i data-lucide="user-plus" class="h-3.5 w-3.5"></i> Add Officer</button>
                                <button type="button" data-ao-cancel class="inline-flex items-center rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 border border-gray-200 hover:bg-gray-50">Cancel</button>
                                <span data-ao-msg class="text-[11px]"></span>
                            </div>
                        </div>
                        <p class="mt-2 text-[11px] text-gray-400">The file is logged back to whoever is honored first by seniority.</p>
                    </div>` : ''}
                    <div class="px-6 py-4 ${d.can_send_fr ? '' : 'border-t border-gray-100'} flex flex-wrap gap-2">${actionButtons(d)}</div>
                </div>`;
            result.classList.remove('hidden');

            if (d.can_send_fr) initRequesterCascade();

            const frBtn = result.querySelector('[data-fr]');
            if (frBtn) frBtn.addEventListener('click', () => sendFR(d, frBtn));
            const logBtn = result.querySelector('[data-log]');
            if (logBtn) logBtn.addEventListener('click', () => logFile(d));
            const saveBtn = result.querySelector('[data-us-save]');
            if (saveBtn) saveBtn.addEventListener('click', () => updateStatus(d, result.querySelector('[data-us-status]').value, result.querySelector('[data-us-loc]').value, saveBtn));
            if (window.lucide) window.lucide.createIcons();

            // File already has an open request → surface the duplicate prompt immediately
            // (instead of waiting for the user to fill the form and click Send).
            if (frBtn && d.existing_request) {
                showFrDuplicate(d, frBtn, d.existing_request);
            }
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

        // Inline duplicate-request warning shown next to the Send button.
        const DEPT_OTHER   = '__DEPT_OTHER__';
        const OFFICE_OTHER = '__OFFICE_OTHER__';
        const OFFICER_ADD  = '__OFFICER_ADD__';

        // Wire the Requester cascade: Department → Office → Officer (mirrors Create File Tracker).
        function initRequesterCascade() {
            const deptSel    = result.querySelector('[data-fr-dept]');
            const officeSel  = result.querySelector('[data-fr-office]');
            const officerSel = result.querySelector('[data-fr-officer]');
            const addCard    = result.querySelector('[data-fr-addcard]');
            if (!deptSel || !officeSel || !officerSel) return;

            const deptOther   = result.querySelector('[data-fr-dept-other]');
            const officeOther = result.querySelector('[data-fr-office-other]');

            deptSel.addEventListener('change', () => {
                const dept = deptSel.value;
                // "Other" department → reveal a free-text specify input.
                if (deptOther) {
                    deptOther.classList.toggle('hidden', dept !== DEPT_OTHER);
                    if (dept !== DEPT_OTHER) deptOther.value = '';
                }
                officeSel.innerHTML = '<option value="">— Select Office —</option>' +
                    REQ_OFFICES.filter(o => o.department === dept)
                        .map(o => `<option value="${esc(o.code)}" data-name="${esc(o.name)}">${esc(o.name)}</option>`).join('') +
                    `<option value="${OFFICE_OTHER}">Other…</option>`;
                officeSel.disabled = false;
                if (officeOther) { officeOther.classList.add('hidden'); officeOther.value = ''; }
                officerSel.innerHTML = '<option value="">— Select Officer —</option>';
                officerSel.disabled = true;
                hideAddCard();
            });

            officeSel.addEventListener('change', () => {
                // "Other" office → reveal a free-text specify input (officer still picked below).
                if (officeOther) {
                    officeOther.classList.toggle('hidden', officeSel.value !== OFFICE_OTHER);
                    if (officeSel.value !== OFFICE_OTHER) officeOther.value = '';
                }
                hideAddCard();
                const dept   = deptSel.value;
                const deptId = REQ_DEPT_NAME_TO_ID[dept];
                const matches = REQ_OFFICERS.filter(o => deptId == null || String(o.department_id) === String(deptId));
                officerSel.innerHTML = '<option value="">— Select Officer —</option>' +
                    matches.map(o => `<option value="${esc(o.name)}">${esc(o.name)}</option>`).join('') +
                    `<option value="${OFFICER_ADD}" class="font-semibold">+ Add Receiving Officer…</option>`;
                officerSel.disabled = false;
            });

            officerSel.addEventListener('change', () => {
                if (officerSel.value === OFFICER_ADD) { showAddCard(); }
                else hideAddCard();
            });

            function showAddCard() { if (addCard) { addCard.classList.remove('hidden'); if (window.lucide) window.lucide.createIcons(); } }
            function hideAddCard() { if (addCard) addCard.classList.add('hidden'); }

            // Add Receiving Officer (reuses the Create File Tracker endpoint).
            const saveBtn = addCard?.querySelector('[data-ao-save]');
            const cancel  = addCard?.querySelector('[data-ao-cancel]');
            if (cancel) cancel.addEventListener('click', () => { addCard.classList.add('hidden'); officeSel.value = ''; officerSel.value = ''; });
            if (saveBtn) saveBtn.addEventListener('click', async () => {
                const first = addCard.querySelector('[data-ao-first]').value.trim();
                const last  = addCard.querySelector('[data-ao-last]').value.trim();
                const msg   = addCard.querySelector('[data-ao-msg]');
                if (!first || !last) { msg.className = 'text-[11px] text-red-600'; msg.textContent = 'First and last name are required.'; return; }
                saveBtn.disabled = true; saveBtn.innerHTML = '<i data-lucide="loader" class="h-3.5 w-3.5 animate-spin"></i> Saving…';
                if (window.lucide) window.lucide.createIcons();
                try {
                    const res = await fetch(ADD_OFFICER_URL, {
                        method: 'POST',
                        headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
                        body: JSON.stringify({ first_name:first, last_name:last }),
                    });
                    const json = await res.json();
                    if (json.success) {
                        const name = (first + ' ' + last).trim();
                        const opt = document.createElement('option');
                        opt.value = name; opt.textContent = name;
                        officerSel.insertBefore(opt, officerSel.querySelector(`option[value="${OFFICER_ADD}"]`));
                        officerSel.value = name; officerSel.disabled = false;
                        addCard.classList.add('hidden');
                        addCard.querySelectorAll('input').forEach(i => i.value = '');
                        msg.textContent = '';
                    } else {
                        msg.className = 'text-[11px] text-red-600'; msg.textContent = json.message || 'Could not add officer.';
                    }
                } catch (e) {
                    msg.className = 'text-[11px] text-red-600'; msg.textContent = 'Network error — please try again.';
                } finally {
                    saveBtn.disabled = false; saveBtn.innerHTML = '<i data-lucide="user-plus" class="h-3.5 w-3.5"></i> Add Officer';
                    if (window.lucide) window.lucide.createIcons();
                }
            });
        }

        function showFrDuplicate(d, frBtn, ex) {
            const wrap = document.createElement('div');
            wrap.className = 'mt-2 w-full rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm';
            wrap.innerHTML = `
                <div class="flex items-start gap-2 text-amber-800">
                    <i data-lucide="alert-triangle" class="h-4 w-4 mt-0.5 shrink-0"></i>
                    <div>
                        <div class="font-semibold">This file has already been requested.</div>
                        <div class="text-xs mt-0.5">Requested by <span class="font-semibold">${esc(ex.requester_name || '—')}</span>${ex.requester_office ? ' · ' + esc(ex.requester_office) : ''}${ex.requester_department ? ' (' + esc(ex.requester_department) + ')' : ''}
                            ${ex.request_no ? '· ' + esc(ex.request_no) : ''} · ${esc(ex.status || '')}${ex.requested_at ? ' · ' + esc(ex.requested_at) : ''}.
                            It has already been sent to SCB. You can update the requester details on the existing request instead of sending a duplicate.</div>
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-2">
                    <button type="button" data-fr-update class="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700">
                        <i data-lucide="pencil" class="h-3.5 w-3.5"></i> Update Requester Details</button>
                    <button type="button" data-fr-cancel class="inline-flex items-center gap-1.5 rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 border border-gray-200 hover:bg-gray-50">
                        Cancel</button>
                </div>`;
            frBtn.disabled = false;
            frBtn.innerHTML = '<i data-lucide="send" class="h-4 w-4"></i> Send File Search Request to SCB Monitor';
            frBtn.insertAdjacentElement('afterend', wrap);
            wrap.querySelector('[data-fr-update]').addEventListener('click', () => { wrap.remove(); sendFR(d, frBtn, false, ex.id); });
            wrap.querySelector('[data-fr-cancel]').addEventListener('click', () => wrap.remove());
            if (window.lucide) window.lucide.createIcons();
        }

        async function sendFR(d, frBtn, force = false, updateId = null) {
            const deptSel    = result.querySelector('[data-fr-dept]');
            const officeSel  = result.querySelector('[data-fr-office]');
            const officerSel = result.querySelector('[data-fr-officer]');
            const deptOther   = result.querySelector('[data-fr-dept-other]');
            const officeOther = result.querySelector('[data-fr-office-other]');

            // Department: "Other" → use the typed value.
            const deptIsOther = deptSel && deptSel.value === DEPT_OTHER;
            const requesterDept = deptIsOther
                ? (deptOther ? deptOther.value.trim() : '')
                : (deptSel ? deptSel.value.trim() : '');

            // Office: "Other" → use the typed value (no code); otherwise the listed office name + code.
            const officeOpt     = officeSel ? officeSel.selectedOptions[0] : null;
            const officeIsOther = officeOpt && officeOpt.value === OFFICE_OTHER;
            const requesterOffice = officeIsOther
                ? (officeOther ? officeOther.value.trim() : '')
                : ((officeOpt && officeOpt.value) ? (officeOpt.dataset.name || officeOpt.textContent.trim()) : '');
            const requesterOfficeCode = officeIsOther ? '' : ((officeOpt && officeOpt.value) ? officeOpt.value : '');

            let receivingOfficer  = officerSel ? officerSel.value.trim() : '';
            if (receivingOfficer === OFFICER_ADD) receivingOfficer = '';

            const flag = (el) => { if (el) { el.classList.add('ring-2','ring-red-400','border-red-400'); } };
            const unflag = (el) => { if (el) el.classList.remove('ring-2','ring-red-400','border-red-400'); };
            [deptSel, officeSel, officerSel, deptOther, officeOther].forEach(unflag);
            if (deptIsOther && !requesterDept) { flag(deptOther); deptOther.focus(); return; }
            if (officeIsOther && !requesterOffice) { flag(officeOther); officeOther.focus(); return; }
            if (officerSel && !receivingOfficer) { flag(officerSel); officerSel.focus(); return; }

            frBtn.disabled = true;
            frBtn.innerHTML = updateId
                ? '<i data-lucide="loader" class="h-4 w-4 animate-spin"></i> Updating…'
                : '<i data-lucide="loader" class="h-4 w-4 animate-spin"></i> Sending…';
            if (window.lucide) window.lucide.createIcons();
            try {
                const res = await fetch(FR_URL, {
                    method:'POST',
                    headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
                    body: JSON.stringify({ file_number:d.file_number, file_title:d.file_title, current_location:d.current_location, resolved_status:d.status, receiving_officer: receivingOfficer, requester_department: requesterDept, requester_office: requesterOffice, requester_office_code: requesterOfficeCode, force: force ? 1 : 0, update_existing_id: updateId || null }),
                });
                const json = await res.json();
                if (json.success) {
                    const reqNo = esc(json.data?.request_no || '');
                    frBtn.outerHTML = json.updated
                        ? `<span class="inline-flex items-center gap-2 rounded-lg bg-green-100 px-4 py-2 text-sm font-semibold text-green-800">
                        <i data-lucide="check" class="h-4 w-4"></i> Requester details updated on ${reqNo}</span>`
                        : `<span class="inline-flex items-center gap-2 rounded-lg bg-green-100 px-4 py-2 text-sm font-semibold text-green-800">
                        <i data-lucide="check" class="h-4 w-4"></i> File Request ${reqNo} sent to SCB Monitors</span>`;
                    loadLog();
                } else if (json.duplicate) {
                    showFrDuplicate(d, frBtn, json.existing || {});
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

        // Loaded rows kept client-side so the search boxes can filter without a refetch.
        let   scbRows = [], logRows = [];
        let   scbQuery = '', logQuery = '';
        let   scbPage = 1;
        const SCB_PAGE_SIZE = 20;

        // Render the SCB Feedback table from the current filter + page.
        function renderScb() {
            renderTable(scbRows.filter(r => rowMatches(r, scbQuery)));
        }

        // Case-insensitive match across the searchable fields of a request row.
        function rowMatches(r, q) {
            if (!q) return true;
            q = q.toLowerCase();
            return [r.file_number, r.request_no, r.file_title, r.receiving_officer,
                    r.requested_by, r.requester_office, r.requester_department,
                    r.current_location, r.location_type, r.scb_response]
                .some(v => v && String(v).toLowerCase().includes(q));
        }

        const LOG_BADGE = {
            'Awaiting':  'bg-amber-100 text-amber-800',
            'Found':     'bg-green-100 text-green-800',
            'Not Found': 'bg-red-100 text-red-800',
        };

        // Front Desk acts on an SCB-responded request (Log File / Refer), then it
        // drops out of the SCB Feedback queue and lives in the File Request Log.
        async function frontDeskAct(btn) {
            const id = btn.dataset.id, kind = btn.dataset.fbAct;
            const fno = btn.dataset.fno, title = btn.dataset.title || '', officer = btn.dataset.officer || '';
            const office = btn.dataset.office || '', dept = btn.dataset.dept || '';
            btn.disabled = true;
            try {
                await fetch(`${ACTED_BASE}/${id}/front-desk-acted`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                });
            } catch (e) { /* non-fatal — still let the Front Desk proceed */ }

            if (kind === 'log') {
                logFile({ file_number: fno, file_title: title, receiving_officer: officer, requester_office: office, requester_department: dept });   // navigates to Create File Tracker
            } else {
                window.open(`${SLIP_URL}?file_number=${encodeURIComponent(fno)}&variant=refer_registry`, '_blank');
                loadScbFeedback();
                loadLog();
            }
        }

        // Reporting summary tiles.
        function renderReport(report) {
            report = report || {};
            const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = (v ?? 0); };
            const dateEl = document.getElementById('qs-rep-date');
            if (dateEl) dateEl.textContent = report.date ? `Today · ${report.date}` : '';
            set('qs-rep-today',    report.submitted_today);
            set('qs-rep-blind',    report.blind_open);
            set('qs-rep-found',    report.found);
            set('qs-rep-notfound', report.not_found);
            set('qs-rep-missing',  report.missing);
        }

        function renderLogFilters(counts) {
            counts = counts || {};
            const totalEl = document.getElementById('qs-log-total');
            if (totalEl) totalEl.textContent = `${counts.all ?? 0}`;
            const chips = [
                ['FOUND',     'Found',      counts.found],
                ['NOT_FOUND', 'Not Found',  counts.not_found],
                ['MISSING',   'Missing',    counts.missing],
                ['BLIND',     'Blind/Open', counts.blind_open],
                ['PENDING',   'Awaiting',   counts.pending],
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
            const dash = '<span class="text-gray-300">—</span>';
            const splitDT = (s) => { if (!s) return null; const i = s.indexOf(' '); return i < 0 ? { d:s, t:'' } : { d:s.slice(0, i), t:s.slice(i + 1) }; };

            // Paginate — 20 rows per page.
            const total = rows.length;
            const pages = Math.max(1, Math.ceil(total / SCB_PAGE_SIZE));
            if (scbPage > pages) scbPage = pages;
            if (scbPage < 1) scbPage = 1;
            const start  = (scbPage - 1) * SCB_PAGE_SIZE;
            const pageRows = rows.slice(start, start + SCB_PAGE_SIZE);

            tblBox.innerHTML = `
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] uppercase tracking-wide text-gray-400 bg-gray-50 border-b border-gray-100">
                            <th class="px-3 py-2.5">File No</th>
                            <th class="px-3 py-2.5">Title</th>
                            <th class="px-3 py-2.5">Requester</th>
                            <th class="px-3 py-2.5">Location</th>
                            <th class="px-3 py-2.5">SCB Response</th>
                            <th class="px-3 py-2.5 whitespace-nowrap">Sent</th>
                            <th class="px-3 py-2.5 whitespace-nowrap">Responded</th>
                            <th class="px-3 py-2.5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${pageRows.map(r => {
                            const badge  = LOG_BADGE[r.scb_response] || 'bg-gray-100 text-gray-700';
                            const icon   = r.found ? 'check' : (r.not_found ? 'x' : 'clock');
                            const sentDt = splitDT(r.requested_at);
                            const dt     = splitDT(r.responded_at);
                            const action = r.found
                                ? `<button type="button" data-fb-act="log" data-id="${r.id}" data-fno="${esc(r.file_number)}" data-title="${esc(r.file_title || '')}" data-officer="${esc(r.receiving_officer || '')}" data-office="${esc(r.requester_office || '')}" data-dept="${esc(r.requester_department || '')}" class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700"><i data-lucide="file-plus" class="h-3.5 w-3.5"></i> Log File</button>`
                                : (r.not_found
                                    ? `<button type="button" data-fb-act="refer" data-id="${r.id}" data-fno="${esc(r.file_number)}" class="inline-flex items-center gap-1 rounded-lg bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800"><i data-lucide="printer" class="h-3.5 w-3.5"></i> Print</button>`
                                    : `<span class="text-[11px] text-gray-400 italic">Awaiting SCB…</span>`);
                            const delBtn = IS_SUPER_ADMIN
                                ? `<button type="button" data-fb-del data-id="${r.id}" title="Delete request" class="inline-flex items-center gap-1 rounded-lg bg-red-100 px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-200"><i data-lucide="trash-2" class="h-3.5 w-3.5"></i></button>`
                                : '';
                            return `
                            <tr class="border-b border-gray-50 hover:bg-gray-50/60 align-top">
                                <td class="px-3 py-3 whitespace-nowrap">
                                    <div class="font-semibold text-gray-800">${esc(r.file_number)}</div>
                                    <div class="text-[11px] text-gray-400">${esc(r.request_no)}</div>
                                    <span class="mt-1 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold ${r.is_dfr ? 'bg-gray-200 text-gray-600' : (r.is_blind ? 'bg-red-100 text-red-700' : 'bg-sky-100 text-sky-700')}">
                                        <i data-lucide="${r.is_dfr ? 'file-text' : (r.is_blind ? 'eye-off' : 'folder-search')}" class="h-2.5 w-2.5"></i> ${esc(r.request_type)}</span>
                                </td>
                                <td class="px-3 py-3 text-gray-600 max-w-[180px] truncate" title="${esc(r.file_title || '')}">${r.file_title ? esc(r.file_title) : dash}</td>
                                <td class="px-3 py-3 text-gray-600 max-w-[150px] truncate" title="${esc(r.receiving_officer || '')}">${r.receiving_officer ? esc(r.receiving_officer) : dash}</td>
                                <td class="px-3 py-3 text-gray-500 max-w-[150px] truncate" title="${esc(r.current_location || r.location_type || '')}">${(r.current_location || r.location_type) ? esc(r.current_location || r.location_type) : dash}</td>
                                <td class="px-3 py-3 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold ${badge}">
                                        <i data-lucide="${icon}" class="h-3 w-3"></i> ${esc(r.scb_response)}</span>
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap text-xs">
                                    ${sentDt ? `<div class="text-gray-700 font-medium">${esc(sentDt.d)}</div><div class="text-gray-400">${esc(sentDt.t)}</div>` : dash}
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap text-xs">
                                    ${dt ? `<div class="text-gray-700 font-medium">${esc(dt.d)}</div><div class="text-gray-400">${esc(dt.t)}</div>` : dash}
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2">${action}${delBtn}</div>
                                </td>
                            </tr>`;
                        }).join('')}
                    </tbody>
                </table>
                ${pages > 1 ? `
                <div class="flex items-center justify-between gap-3 px-4 py-3 border-t border-gray-100 text-xs text-gray-500">
                    <span>Showing <span class="font-semibold text-gray-700">${start + 1}–${Math.min(start + SCB_PAGE_SIZE, total)}</span> of <span class="font-semibold text-gray-700">${total}</span></span>
                    <div class="flex items-center gap-1.5">
                        <button type="button" data-scb-page="prev" ${scbPage <= 1 ? 'disabled' : ''} class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2.5 py-1.5 font-semibold ${scbPage <= 1 ? 'text-gray-300 cursor-not-allowed' : 'text-gray-600 hover:bg-gray-50'}"><i data-lucide="chevron-left" class="h-3.5 w-3.5"></i> Prev</button>
                        <span class="px-1">Page ${scbPage} / ${pages}</span>
                        <button type="button" data-scb-page="next" ${scbPage >= pages ? 'disabled' : ''} class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2.5 py-1.5 font-semibold ${scbPage >= pages ? 'text-gray-300 cursor-not-allowed' : 'text-gray-600 hover:bg-gray-50'}">Next <i data-lucide="chevron-right" class="h-3.5 w-3.5"></i></button>
                    </div>
                </div>` : ''}`;
            tblBox.querySelectorAll('[data-fb-act]').forEach(b =>
                b.addEventListener('click', () => frontDeskAct(b)));
            tblBox.querySelectorAll('[data-fb-del]').forEach(b =>
                b.addEventListener('click', () => deleteFileRequest(b)));
            tblBox.querySelectorAll('[data-scb-page]').forEach(b =>
                b.addEventListener('click', () => {
                    if (b.disabled) return;
                    scbPage += (b.dataset.scbPage === 'next' ? 1 : -1);
                    renderScb();
                    tblBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }));
            if (window.lucide) window.lucide.createIcons();
        }

        // Delete a File Search Request (Super Admins only).
        async function deleteFileRequest(btn) {
            if (!confirm('Delete this file request? This cannot be undone.')) return;
            const id = btn.dataset.id;
            btn.disabled = true;
            try {
                const res = await fetch(`${ACTED_BASE}/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                });
                const json = await res.json();
                if (json.success) {
                    reloadPanels();
                } else {
                    btn.disabled = false;
                    alert(json.message || 'Could not delete the request.');
                }
            } catch (e) {
                btn.disabled = false;
                alert('Network error — please try again.');
            }
        }

        async function loadScbFeedback() {
            tblBox.innerHTML = '<div class="px-4 py-8 text-center text-xs text-gray-400">Loading…</div>';
            try {
                const res  = await fetch(FEEDBACK_URL, { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                scbRows = (json && json.data) || [];
                // Most recent on top — sort by SCB response time, then by sent time.
                scbRows.sort((a, b) =>
                    ((b.responded_ts || b.requested_ts || 0) - (a.responded_ts || a.requested_ts || 0)));
                scbPage = 1;
                renderScb();
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
                renderReport(json.report);
                renderLogFilters(json.counts);
                logRows = (json && json.data) || [];
                renderLog(logRows.filter(r => rowMatches(r, logQuery)));
            } catch (e) {
                fbBox.innerHTML = '<div class="px-4 py-8 text-center text-xs text-red-500">Could not load the file request log.</div>';
            }
        }

        function renderLog(rows) {
            if (!rows.length) {
                fbBox.innerHTML = '<div class="px-4 py-10 text-center text-xs text-gray-400 flex flex-col items-center gap-2"><i data-lucide="inbox" class="h-6 w-6 text-gray-300"></i> No file requests in this view.</div>';
                if (window.lucide) window.lucide.createIcons();
                return;
            }
            fbBox.innerHTML = `
                    <ul class="divide-y divide-gray-100">
                        ${rows.map(r => {
                            // A blind / not-indexed file reported Not Found is surfaced as "Missing".
                            const isMissing = r.not_found && r.is_blind;
                            const respLabel = isMissing ? 'Missing' : r.scb_response;
                            const badge = isMissing ? 'bg-red-100 text-red-800' : (LOG_BADGE[r.scb_response] || 'bg-gray-100 text-gray-700');
                            const icon  = r.found ? 'check' : (r.not_found ? (isMissing ? 'help-circle' : 'x') : 'clock');
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
                                        <i data-lucide="${icon}" class="h-3 w-3"></i> ${esc(respLabel)}</span>
                                </div>
                                <div class="mt-1.5 text-xs text-gray-600 truncate" title="${esc(r.file_title || '')}">${esc(r.file_title || '—')}</div>
                                ${r.receiving_officer ? `<div class="mt-0.5 text-[11px] text-gray-400 truncate">for ${esc(r.receiving_officer)}</div>` : ''}
                                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-gray-500">
                                    ${r.requested_by ? `<span class="inline-flex items-center gap-1"><i data-lucide="user" class="h-3 w-3 text-gray-400"></i> ${esc(r.requested_by)}</span>` : ''}
                                    <span class="inline-flex items-center gap-1"><i data-lucide="map-pin" class="h-3 w-3 text-gray-400"></i> ${esc(r.current_location || r.location_type || '—')}</span>
                                    <span class="inline-flex items-center gap-1"><i data-lucide="clock" class="h-3 w-3 text-gray-400"></i> ${esc(r.requested_at || '—')}</span>
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold ${r.is_dfr ? 'bg-gray-200 text-gray-600' : (r.is_blind ? 'bg-red-100 text-red-700' : 'bg-sky-100 text-sky-700')}">
                                        <i data-lucide="${r.is_dfr ? 'file-text' : (r.is_blind ? 'eye-off' : 'folder-search')}" class="h-2.5 w-2.5"></i> ${esc(r.request_type)}</span>
                                    ${done}
                                </div>
                            </li>`;
                        }).join('')}
                    </ul>`;
            if (window.lucide) window.lucide.createIcons();
        }

        function reloadPanels() { loadScbFeedback(); loadLog(); }

        btn.addEventListener('click', search);
        if (pickBtn) pickBtn.addEventListener('click', pickFileNumber);
        input.addEventListener('click', pickFileNumber);   // read-only: open picker instead of typing
        document.getElementById('qs-fb-refresh').addEventListener('click', loadLog);
        document.getElementById('qs-tbl-refresh').addEventListener('click', loadScbFeedback);

        // Live, client-side filtering of the already-loaded rows.
        document.getElementById('qs-tbl-search').addEventListener('input', (e) => {
            scbQuery = e.target.value.trim();
            scbPage = 1;
            renderScb();
        });
        document.getElementById('qs-log-search').addEventListener('input', (e) => {
            logQuery = e.target.value.trim();
            renderLog(logRows.filter(r => rowMatches(r, logQuery)));
        });
        reloadPanels();
    })();
    </script>
@endpush
@endsection

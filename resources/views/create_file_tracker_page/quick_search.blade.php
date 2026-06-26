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

        @php
            $urlCtx = request('url');
            $isStContext = $urlCtx === 'st';
            $isSltrContext = $urlCtx === 'sltr';
            $isSurveyContext = $urlCtx === 'survey';
            $isKangisContext = $urlCtx === 'kangis';
            $isCadastralContext = $urlCtx === 'cadastral';
            if ($isStContext) {
                $headerGradient = 'bg-gradient-to-r from-blue-700 via-blue-600 to-blue-800';
                $headerStyle = 'background-image: linear-gradient(to right, #1d4ed8, #2563eb, #1e40af);';
                $dotClass = 'text-blue-100';
            } elseif ($isSltrContext) {
                $headerGradient = 'bg-gradient-to-r from-lime-600 via-green-500 to-lime-600';
                $headerStyle = 'background-image: linear-gradient(to right, #65a30d, #22c55e, #65a30d);';
                $dotClass = 'text-lime-100';
            } elseif ($isSurveyContext) {
                $headerGradient = 'bg-gradient-to-r from-pink-600 via-pink-500 to-pink-700';
                $headerStyle = 'background-image: linear-gradient(to right, #db2777, #ec4899, #be185d);';
                $dotClass = 'text-pink-100';
            } elseif ($isKangisContext) {
                $headerGradient = 'bg-gradient-to-r from-yellow-600 via-amber-500 to-yellow-600';
                $headerStyle = 'background-image: linear-gradient(to right, #ca8a04, #f59e0b, #ca8a04);';
                $dotClass = 'text-yellow-100';
            } elseif ($isCadastralContext) {
                $headerGradient = 'bg-gradient-to-r from-rose-700 via-rose-600 to-rose-800';
                $headerStyle = 'background-image: linear-gradient(to right, #be123c, #e11d48, #9f1239);';
                $dotClass = 'text-rose-100';
            } else {
                $headerGradient = 'bg-gradient-to-r from-red-700 via-red-600 to-red-800';
                $headerStyle = '';
                $dotClass = 'text-red-100';
            }
        @endphp
        <div class="px-6 py-3 flex items-center gap-3 shadow-sm {{ $headerGradient }}"
            @if($headerStyle) style="{{ $headerStyle }}" @endif>
            <i data-lucide="search" class="h-5 w-5 text-white shrink-0"></i>
            <div class="flex items-center gap-2">
                <span class="text-white font-bold text-sm uppercase tracking-widest">Quick Search</span>
                <span class="{{ $dotClass }} text-sm">·</span>
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
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <h3 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                        <i data-lucide="bar-chart-3" class="h-4 w-4 text-indigo-600"></i> File Search Report
                    </h3>
                    <div class="flex items-center gap-2 flex-wrap">
                        <div class="flex items-center gap-1.5 text-xs text-gray-500">
                            <i data-lucide="calendar" class="h-3.5 w-3.5 text-gray-400"></i>
                            <input id="qs-rep-from" type="date" class="rounded-lg border border-gray-300 px-2 py-1 text-xs focus:ring-2 focus:ring-indigo-500" title="From date">
                            <span class="text-gray-400">–</span>
                            <input id="qs-rep-to" type="date" class="rounded-lg border border-gray-300 px-2 py-1 text-xs focus:ring-2 focus:ring-indigo-500" title="To date">
                            <button type="button" id="qs-rep-clear" class="hidden inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2 py-1 text-xs font-semibold text-gray-500 hover:bg-gray-50" title="Clear date range">
                                <i data-lucide="x" class="h-3 w-3"></i> Clear
                            </button>
                        </div>
                        <span class="hidden sm:inline text-gray-200">|</span>
                        <button type="button" id="qs-export-csv" class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100" title="Export current view to CSV">
                            <i data-lucide="file-spreadsheet" class="h-3.5 w-3.5"></i> CSV
                        </button>
                        <button type="button" id="qs-export-pdf" class="inline-flex items-center gap-1 rounded-lg border border-red-200 bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100" title="Export current view to PDF">
                            <i data-lucide="file-text" class="h-3.5 w-3.5"></i> PDF
                        </button>
                        <span id="qs-rep-date" class="text-xs font-medium text-gray-400"></span>
                    </div>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    <div class="rounded-lg border border-indigo-100 bg-indigo-50/60 px-4 py-3">
                        <div class="text-[11px] font-semibold uppercase tracking-wide text-indigo-700">Requests Today</div>
                        <div id="qs-rep-today" class="mt-1 text-2xl font-bold text-indigo-900">—</div>
                        <div class="mt-2 space-y-0.5 text-[10px] font-medium border-t border-indigo-100 pt-1.5">
                            <div class="flex items-center justify-between">
                                <span class="inline-flex items-center gap-1 text-emerald-700"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Found</span>
                                <span id="qs-rep-today-found" class="font-bold text-emerald-800">—</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="inline-flex items-center gap-1 text-amber-700"><span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>Not Found</span>
                                <span id="qs-rep-today-notfound" class="font-bold text-amber-800">—</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="inline-flex items-center gap-1 text-slate-600"><span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>Awaiting</span>
                                <span id="qs-rep-today-awaiting" class="font-bold text-slate-700">—</span>
                            </div>
                        </div>
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
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-600">Awaiting</div>
                        <div id="qs-rep-awaiting" class="mt-1 text-2xl font-bold text-slate-800">—</div>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.7.1/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
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
        const REDIRECT_LAND_URL = "{{ route('create-file-tracker.quick-search.redirect-director-land') }}";
        const FEEDBACK_URL= "{{ route('create-file-tracker.quick-search.scb-feedback') }}";
        // Module context (?url=kangis|sltr|cadastral|st|…) scopes the report, SCB
        // Feedback queue and File Search History to that registry's files only.
        const URL_CTX     = @json(request('url'));
        const CSRF        = "{{ csrf_token() }}";
        const IS_SUPER_ADMIN = @json(auth()->user()->isSuperAdmin());
        // SCB Monitors receive File Search Requests — they don't raise them, so the
        // "Send (Blind) Request to SCB Monitor" buttons are hidden for them. Everyone
        // else (incl. OFS ranked officers) sees them. OFS overrides the SCB hide —
        // an OFS user (even one who is also SCB) sees all.
        const IS_SCB_MONITOR = @json(auth()->user()->isScbMonitor());
        const IS_OFS         = @json(auth()->user()->isOfs());
        // Show the "Send Request" buttons unless the user is an SCB-only monitor.
        const CAN_SEND_FR    = IS_OFS || !IS_SCB_MONITOR;
        // ── Requester cascade data (mirrors Create File Tracker) ──
        const REQ_DEPARTMENTS = @json(($departments ?? collect())->values());
        const REQ_OFFICES     = @json($reqOffices);
        const REQ_OFFICERS    = @json($reqOfficers);
        const REQ_DEPT_NAME_TO_ID = @json($reqDeptNameToId);
        const REGISTRIES      = @json($registries ?? []);
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
        const money = (l, v) => (v == null || v === '') ? '' : row(l, '₦' + Number(v).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));

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
                // Re-direct the request straight to the office currently holding the file
                // (its last receiving officer) instead of routing it to the SCB Monitor.
                const showRedirect = d.can_redirect && CAN_SEND_FR;
                if (showRedirect) {
                    const office = d.current_location || 'Current Office';
                    out.push(`<button type="button" data-redirect
                        class="inline-flex items-center gap-2 rounded-lg bg-orange-500 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-600">
                        <i data-lucide="user-check" class="h-4 w-4"></i> Re-direct Request to ${esc(office)}</button>`);
                }
                // The slip can only be printed once the re-direct request has been sent —
                // disabled/greyed until then (only gated when a redirect button is shown).
                const slipDisabled = showRedirect;
                out.push(`<a href="/create-file-tracker/${d.file_tracker_id}/request-sheet" target="_blank" data-print-slip
                    class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-white ${slipDisabled ? 'bg-gray-300 cursor-not-allowed pointer-events-none opacity-60' : 'bg-amber-600 hover:bg-amber-700'}"
                    ${slipDisabled ? 'aria-disabled="true" tabindex="-1"' : ''}>
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
            if (d.can_send_fr && CAN_SEND_FR) {
                if (d.duplicate_flag) {
                    // Files registered in the duplicate_fileno table must NOT be blind-searched
                    // by the SCB — they are re-directed to the Director Land (Land Department) to
                    // resolve the duplication.
                    out.push(`<button type="button" data-redirect-land class="inline-flex items-center gap-2 rounded-lg bg-purple-600 hover:bg-purple-700 px-4 py-2 text-sm font-semibold text-white">
                        <i data-lucide="user-check" class="h-4 w-4"></i> Re-direct To Director Land (Land Department)</button>`);
                } else {
                    const label = d.is_blind ? 'Send Blind Request to SCB Monitor' : 'Send File Search Request to SCB Monitor';
                    const frCls = d.is_blind ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700';
                    out.push(`<button type="button" data-fr class="inline-flex items-center gap-2 rounded-lg ${frCls} px-4 py-2 text-sm font-semibold text-white">
                        <i data-lucide="send" class="h-4 w-4"></i> ${label}</button>`);
                }
            }
            return out.join('');
        }

        // In-transit files: a one-line note under the actions naming the Receiving Officer
        // (and their department) currently holding the file — mirrors the mobile redirect note.
        function redirectSubline(d) {
            if (d.status !== 'IN_TRANSIT' || !d.can_redirect) return '';
            const officer = (d.receiving_officer_name || '').trim();
            let dept = (d.receiving_department || '').trim();
            if (dept && !/department$/i.test(dept)) dept = dept + ' Department';
            if (officer) {
                const deptPart = (dept && dept.toLowerCase() !== officer.toLowerCase()) ? ` (${esc(dept)})` : '';
                return `<p class="px-6 pb-4 -mt-1 text-xs text-gray-500">Receiving Officer: <strong class="text-gray-700">${esc(officer)}</strong>${deptPart} · currently holding the file.</p>`;
            }
            const office = d.current_location;
            if (office) {
                return `<p class="px-6 pb-4 -mt-1 text-xs text-gray-500">This request will be sent to <strong class="text-gray-700">${esc(office)}</strong>, which currently holds the file.</p>`;
            }
            return '';
        }

        // Map a registry name to its Create File Tracker module (?url=…) so the Log
        // File button lands on the registry-appropriate page (KANGIS → ?url=kangis,
        // Cadastral → ?url=cadastral, …). Land/Deeds registries use the base page.
        function registryToModule(registry) {
            const r = String(registry || '').toLowerCase();
            if (r.includes('kangis'))  return 'kangis';
            if (r.includes('sltr'))    return 'sltr';
            if (r.includes('cadastr')) return 'cadastral';
            if (r.includes('dciv'))    return 'dciv';
            if (r.includes('survey'))  return 'survey';
            if (r.includes('sit') || r === 'st registry') return 'st';
            return ''; // Land / Deeds / other → base Create File Tracker page
        }

        function logFile(d) {
            const params = new URLSearchParams({ file_number: d.file_number || '' });
            if (d.file_title) params.set('file_title', d.file_title);
            // Land the user on the page that matches the file's registry.
            const mod = registryToModule(d.registry);
            if (mod) params.set('url', mod);
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

            // Ownership timeline — chronological holder chain from the cross-table
            // property timeline. Falls back to the two flat indexing rows when the
            // file has no transaction history.
            const holderHistoryHtml = (() => {
                let hist = Array.isArray(d.holder_history) ? d.holder_history : [];
                // Hide Mortgage and Surrender And Release nodes from the holders list.
                hist = hist.filter((h) => {
                    const t = String(h.transaction_type || '').toLowerCase();
                    return !t.includes('mortgage') && !t.includes('surrender');
                });
                if (!hist.length) {
                    return row('Original Holder', d.original_holder) + row('Current Holder', d.current_holder);
                }
                // Shorten a transaction type to its instrument label (R of O, C of O,
                // Assignment, Mortgage, …) for the compact ownership list.
                const abbrevType = (t) => {
                    if (!t) return '';
                    const s = String(t).toLowerCase();
                    if (s.includes('right of occupancy')) return 'R of O';
                    if (s.includes('certificate of occupancy')) return 'C of O';
                    return String(t).replace(/^deed of\s+/i, '').trim();
                };
                const nodes = hist.map((h, i) => {
                    const isFirst = i === 0, isLast = i === hist.length - 1;
                    const dot = isFirst ? 'bg-emerald-600' : (isLast ? 'bg-indigo-600' : 'bg-gray-300');
                    const dotIcon = (isFirst || isLast) ? 'text-white' : 'text-gray-500';
                    const line = !isLast ? `<span class="absolute left-[6px] top-[18px] -bottom-0.5 w-0.5 bg-gray-200"></span>` : '';
                    const name = h.to || h.holder;
                    const roleLabel = isFirst ? 'Original Holder' : (isLast ? 'Current Holder' : 'Holder');
                    const roleColor = isFirst ? 'text-emerald-700' : (isLast ? 'text-indigo-700' : 'text-gray-500');
                    const type = abbrevType(h.transaction_type);
                    return `
                        <div class="relative pl-6 ${isLast ? '' : 'pb-2'}">
                            ${line}
                            <span class="absolute left-0 top-px h-[15px] w-[15px] rounded-full ${dot} border-2 border-gray-200 flex items-center justify-center">
                                <i data-lucide="user" class="h-2 w-2 ${dotIcon}"></i>
                            </span>
                            <div class="flex items-baseline gap-2 flex-wrap">
                                <span class="text-[13px] font-bold text-gray-900 leading-tight">${esc(name)}</span>
                                <span class="text-[11px] font-semibold ${roleColor}">${roleLabel}${type ? ` <span class="text-gray-400 font-medium">(${esc(type)})</span>` : ''}</span>
                            </div>
                        </div>`;
                }).join('');
                return `
                    <div class="text-[10px] font-extrabold uppercase tracking-wider text-gray-500 mb-2.5">Ownership</div>
                    <div class="pb-0.5">${nodes}</div>`;
            })();

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
                        ${d.duplicate_flag ? `
                        <div class="mb-3 rounded-lg px-4 py-3 flex items-center gap-2" style="background:${d.duplicate_flag.color}14;border:1px solid ${d.duplicate_flag.color}55;">
                            <i data-lucide="copy" class="h-4 w-4 shrink-0" style="color:${d.duplicate_flag.color};"></i>
                            <span class="text-sm font-bold" style="color:${d.duplicate_flag.color};">${esc(d.duplicate_flag.label)}</span>
                            ${d.duplicate_flag.comment ? `<span class="text-xs font-semibold px-2 py-0.5 rounded-full whitespace-nowrap" style="color:${d.duplicate_flag.color};background:${d.duplicate_flag.color}1a;">${esc(d.duplicate_flag.comment)}</span>` : ''}
                        </div>` : ''}
                        ${Number(d.dciv_status) === 1 ? `
                        <div class="mb-3 rounded-lg bg-rose-50 border border-rose-200 px-4 py-3">
                            <div class="flex items-center gap-2">
                                <i data-lucide="alert-triangle" class="h-4 w-4 text-rose-600 shrink-0"></i>
                                <span class="text-sm font-bold text-rose-800">Under Investigation</span>
                                ${d.dciv_fileno ? `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-700 border border-rose-200 whitespace-nowrap">${esc(d.dciv_fileno)}</span>` : ''}
                            </div>
                            ${d.dciv_reason ? `<div class="text-xs text-rose-700 mt-1.5">${esc(d.dciv_reason)}</div>` : ''}
                        </div>` : ''}
                        <details class="mb-3 rounded-lg border border-gray-200">
                            <summary class="cursor-pointer select-none px-4 py-2 text-xs font-semibold text-gray-600 flex items-center gap-2">
                                <i data-lucide="users" class="h-3.5 w-3.5"></i> Holder &amp; Bill Details
                            </summary>
                            <div class="px-4 pb-3 pt-1">
                                ${holderHistoryHtml}
                                ${d.bill_balance ? `
                                <div class="mt-2 rounded-lg bg-gray-50 border border-gray-200 px-3 py-2">
                                    <div class="mb-1">
                                        <span class="text-xs font-bold text-gray-700">Bill Ref ID: ${d.bill_balance.reference ? `<span class="text-red-600">${esc(d.bill_balance.reference)}</span>` : '<span class="text-gray-400">—</span>'}</span>
                                    </div>
                                    ${money('Rent / Annum', d.bill_balance.amount)}
                                    ${row('Rent Period', [d.bill_balance.rent_from_year, d.bill_balance.rent_to_year].filter(Boolean).join(' – '))}
                                    ${row('Expiry', d.bill_balance.expiry)}
                                    ${row('Receipt', d.bill_balance.receipt)}
                                    ${Object.entries(d.bill_balance.fees || {}).map(([k, v]) => money(k, v)).join('')}
                                    ${money('Total', d.bill_balance.fees_total)}
                                </div>` : `
                                <div class="mt-2 rounded-lg bg-gray-50 border border-gray-200 px-3 py-2">
                                    <span class="text-xs font-bold text-gray-700">Bill Ref ID: <span class="text-gray-400">—</span></span>
                                </div>`}
                                ${(d.indexing_bills && (d.indexing_bills.bill_balance != null || d.indexing_bills.grant_rent != null)) ? `
                                <div class="mt-2 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2">
                                    ${money('Bill Balance', d.indexing_bills.bill_balance)}
                                    ${money('Grant Rent', d.indexing_bills.grant_rent)}
                                </div>` : ''}
                            </div>
                        </details>
                        ${row('Registry', d.registry)}
                        ${row('Current Location (Expected)', d.current_location)}
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
                    ${(d.can_send_fr && CAN_SEND_FR && !d.duplicate_flag) ? `
                    <div class="px-6 pt-4 border-t border-gray-100">
                        <div class="text-xs font-bold text-gray-700 uppercase tracking-wide mb-2">Requester</div>
                        <div class="mb-3">
                            <label class="block text-[11px] font-semibold text-gray-600 mb-1">Registry (Origin) <span class="text-red-500">*</span></label>
                            <div class="flex items-center gap-2">
                                <select data-fr-registry class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                    <option value="">— Select Registry (Origin) —</option>
                                    ${REGISTRIES.map(rg => `<option value="${esc(rg.name)}" data-code="${esc(rg.registry_code || '')}">${esc(rg.name)}</option>`).join('')}
                                </select>
                                <span data-fr-registry-code class="inline-flex items-center justify-center min-w-[56px] rounded-lg bg-indigo-50 border border-indigo-100 px-2 py-2 text-xs font-bold text-indigo-700">—</span>
                            </div>
                        </div>
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
                        <div data-fr-registry-preview class="mt-3"></div>
                    </div>` : ''}
                    <div id="qs-fsDigital" class="px-6 pt-4 border-t border-gray-100"></div>
                    <div class="px-6 py-4 flex flex-wrap gap-2">${actionButtons(d)}</div>
                    ${redirectSubline(d)}
                </div>`;
            result.classList.remove('hidden');

            if (d.can_send_fr && !d.duplicate_flag) initRequesterCascade(d);

            const frBtn = result.querySelector('[data-fr]');
            if (frBtn) frBtn.addEventListener('click', () => sendFR(d, frBtn));
            const redirectBtn = result.querySelector('[data-redirect]');
            if (redirectBtn) redirectBtn.addEventListener('click', () => sendRedirect(d, redirectBtn));
            const redirectLandBtn = result.querySelector('[data-redirect-land]');
            if (redirectLandBtn) redirectLandBtn.addEventListener('click', () => sendRedirectToDirectorLand(d, redirectLandBtn));
            const logBtn = result.querySelector('[data-log]');
            if (logBtn) logBtn.addEventListener('click', () => logFile(d));
            const saveBtn = result.querySelector('[data-us-save]');
            if (saveBtn) saveBtn.addEventListener('click', () => updateStatus(d, result.querySelector('[data-us-status]').value, result.querySelector('[data-us-loc]').value, saveBtn));
            if (window.lucide) window.lucide.createIcons();

            // Load the File Digital Library (same FileIndexing source as the DFR / mobile
            // File Search preview) inline beneath the location details.
            loadFsDigital(d.file_number);

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
        function initRequesterCascade(fileData = {}) {
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

            // Registry (Origin): reflect the selected registry's short code live and
            // load the digital copy from that registry's folder (if one exists).
            const registrySel     = result.querySelector('[data-fr-registry]');
            const registryCode    = result.querySelector('[data-fr-registry-code]');
            const registryPreview = result.querySelector('[data-fr-registry-preview]');
            if (registrySel && registryCode) {
                registrySel.addEventListener('change', () => {
                    const opt = registrySel.selectedOptions[0];
                    registryCode.textContent = (opt && opt.dataset.code) ? opt.dataset.code : '—';
                    loadRegistryPreview(fileData.file_number, registrySel.value, registryPreview);
                });
            }

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

            // Origin Registry (required) + its short code.
            const registrySel  = result.querySelector('[data-fr-registry]');
            const registry     = registrySel ? registrySel.value.trim() : '';
            const registryCode = registrySel && registrySel.selectedOptions[0] ? (registrySel.selectedOptions[0].dataset.code || '') : '';

            const flag = (el) => { if (el) { el.classList.add('ring-2','ring-red-400','border-red-400'); } };
            const unflag = (el) => { if (el) el.classList.remove('ring-2','ring-red-400','border-red-400'); };
            [deptSel, officeSel, officerSel, deptOther, officeOther, registrySel].forEach(unflag);
            if (deptIsOther && !requesterDept) { flag(deptOther); deptOther.focus(); return; }
            if (officeIsOther && !requesterOffice) { flag(officeOther); officeOther.focus(); return; }
            if (officerSel && !receivingOfficer) { flag(officerSel); officerSel.focus(); return; }
            if (registrySel && !registry) { flag(registrySel); registrySel.focus(); return; }

            frBtn.disabled = true;
            frBtn.innerHTML = updateId
                ? '<i data-lucide="loader" class="h-4 w-4 animate-spin"></i> Updating…'
                : '<i data-lucide="loader" class="h-4 w-4 animate-spin"></i> Sending…';
            if (window.lucide) window.lucide.createIcons();
            try {
                const res = await fetch(FR_URL, {
                    method:'POST',
                    headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
                    body: JSON.stringify({ file_number:d.file_number, file_title:d.file_title, current_location:d.current_location, resolved_status:d.status, receiving_officer: receivingOfficer, requester_department: requesterDept, requester_office: requesterOffice, requester_office_code: requesterOfficeCode, registry: registry || null, registry_code: registryCode || null, force: force ? 1 : 0, update_existing_id: updateId || null }),
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

        // ── Registry digital preview ────────────────────────────────────────────
        // When a Registry (Origin) is selected, look up the file folder in that
        // registry's on-disk source (SLTR / Cadastral / KANGIS / Physical Planning)
        // and show the scanned image(s)/document(s) if the folder exists.
        const REGISTRY_FILES_URL = "{{ route('digital-request.registry-files') }}";
        const REG_IMG_EXT = ['jpg','jpeg','png','gif','webp','bmp','tif','tiff'];

        async function loadRegistryPreview(fileNo, registry, box) {
            if (!box) return;
            box.innerHTML = '';
            if (!fileNo || !registry) return;
            box.innerHTML = `<div class="flex items-center gap-2 text-[11px] text-gray-400"><i data-lucide="loader" class="h-3.5 w-3.5 animate-spin"></i> Checking ${esc(registry)} digital copy…</div>`;
            if (window.lucide) window.lucide.createIcons();
            try {
                const res  = await fetch(REGISTRY_FILES_URL, {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
                    body: JSON.stringify({ file_no: fileNo, registry }),
                });
                const json = await res.json();
                const files = (json && json.available && Array.isArray(json.files)) ? json.files : [];
                if (!files.length) {
                    box.innerHTML = `<div class="flex items-center gap-2 rounded-lg bg-gray-50 border border-gray-200 px-3 py-2 text-[11px] text-gray-500"><i data-lucide="folder-x" class="h-3.5 w-3.5"></i> No digital copy found in ${esc(registry)} for this file.</div>`;
                    if (window.lucide) window.lucide.createIcons();
                    return;
                }
                const thumbs = files.map((f, i) => {
                    const isImg = qsIsImg(f);
                    const isPdf = (f.ext || '').toLowerCase() === 'pdf';
                    const inner = isImg
                        ? `<img src="${esc(f.url)}" alt="${esc(f.name)}" loading="lazy" class="h-16 w-16 object-cover rounded-md border border-gray-200 group-hover:ring-2 group-hover:ring-indigo-400">`
                        : `<span class="flex h-16 w-16 items-center justify-center rounded-md border border-gray-200 bg-gray-50 text-gray-500 group-hover:ring-2 group-hover:ring-indigo-400"><i data-lucide="${isPdf ? 'file-text' : 'file'}" class="h-6 w-6"></i></span>`;
                    return `<button type="button" data-reg-i="${i}" class="group relative cursor-pointer" title="${esc(f.name)}${f.category ? ' · ' + esc(f.category) : ''}">${inner}</button>`;
                }).join('');
                box.innerHTML = `
                    <div class="rounded-lg border border-indigo-100 bg-indigo-50/40 px-3 py-2.5">
                        <div class="flex items-center gap-1.5 text-[11px] font-bold text-indigo-700 mb-2">
                            <i data-lucide="images" class="h-3.5 w-3.5"></i> ${esc(registry)} digital copy · ${files.length} page${files.length > 1 ? 's' : ''}
                        </div>
                        <div class="flex flex-wrap gap-2">${thumbs}</div>
                    </div>`;
                // Open the registry pages in the same in-page lightbox (images + PDFs)
                // rather than a new browser tab.
                box.querySelectorAll('[data-reg-i]').forEach(el =>
                    el.addEventListener('click', () => qsOpenDig(files, parseInt(el.dataset.regI, 10))));
                if (window.lucide) window.lucide.createIcons();
            } catch (e) {
                box.innerHTML = `<div class="text-[11px] text-red-600">Could not load the registry digital copy.</div>`;
            }
        }

        // ── File Digital Library (cover card + lightbox; FileIndexing source) ─────
        // Mirrors the mobile File Search / DFR preview: shows the scanned pages held
        // for this file number in the digital library, with a full-screen viewer.
        const DIGITAL_FILES_URL = "{{ route('digital-request.digital-files') }}";
        let qsDigFiles = [], qsDigIndex = 0, qsDigStripBuilt = false;
        // The list currently shown in the lightbox. May be the File Digital Library
        // (qsDigFiles) or a registry digital copy — both share the same viewer so PDFs
        // and images open inline in the modal instead of a new browser tab.
        let qsViewFiles = [];
        let qsDigRotation = 0, qsPdfToken = 0;
        const qsIsImg = f => REG_IMG_EXT.includes((f.ext || '').toLowerCase());

        async function loadFsDigital(fileNo) {
            const box = document.getElementById('qs-fsDigital');
            if (!box) return;
            box.innerHTML = `<div class="flex items-center gap-2 text-[11px] text-gray-400"><i data-lucide="loader" class="h-3.5 w-3.5 animate-spin"></i> Checking the digital library…</div>`;
            if (window.lucide) window.lucide.createIcons();
            try {
                const res = await fetch(DIGITAL_FILES_URL, {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
                    body: JSON.stringify({ file_no: fileNo }),
                });
                const json = await res.json();
                let files = (json && json.available && Array.isArray(json.files)) ? json.files : [];
                if (files.length) files = await qsPruneMissing(files);
                qsDigFiles = files;
                renderFsDigital();
            } catch (e) {
                qsDigFiles = [];
                box.innerHTML = `<div class="flex items-center gap-2 rounded-lg bg-gray-50 border border-gray-200 px-3 py-2 text-[11px] text-gray-500"><i data-lucide="folder-x" class="h-3.5 w-3.5"></i> Could not load the digital file.</div>`;
                if (window.lucide) window.lucide.createIcons();
            }
        }

        // Drop pages whose soft copy is missing — only on a definitive 404 (fail open).
        async function qsPruneMissing(files) {
            const present = new Array(files.length).fill(true);
            let i = 0;
            const worker = async () => {
                while (i < files.length) {
                    const idx = i++;
                    try { const r = await fetch(files[idx].url, { method: 'HEAD' }); if (r.status === 404) present[idx] = false; }
                    catch (e) {}
                }
            };
            await Promise.all(Array.from({ length: Math.min(8, files.length) }, worker));
            return files.filter((_, idx) => present[idx]);
        }

        function renderFsDigital() {
            qsDigStripBuilt = false;
            const box = document.getElementById('qs-fsDigital');
            if (!box) return;
            if (!qsDigFiles.length) {
                // No library copy → render nothing. The registry-based preview below the
                // Registry (Origin) dropdown already covers the "not found" message.
                box.innerHTML = '';
                box.classList.add('hidden');
                return;
            }
            box.classList.remove('hidden');
            const cover = qsDigFiles[0];
            const n = qsDigFiles.length;
            const coverInner = qsIsImg(cover)
                ? `<img src="${esc(cover.url)}" alt="cover" loading="lazy" class="h-full w-full object-cover">`
                : `<i data-lucide="file-text" class="h-7 w-7 text-gray-400"></i>`;
            box.innerHTML = `
                <button type="button" data-dig-open class="group w-full flex items-center gap-3 rounded-xl border border-indigo-100 bg-indigo-50/40 px-3 py-2.5 text-left hover:bg-indigo-50 transition">
                    <span class="h-14 w-14 shrink-0 rounded-lg overflow-hidden border border-indigo-200 bg-white flex items-center justify-center">${coverInner}</span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center gap-1.5 text-[12px] font-bold text-indigo-700"><i data-lucide="images" class="h-3.5 w-3.5"></i> File Digital Library</span>
                        <span class="block text-[11px] text-gray-500 mt-0.5">${n} page${n > 1 ? 's' : ''} in the digital library</span>
                        <span class="block text-[11px] font-semibold text-indigo-600 mt-0.5"><i data-lucide="expand" class="inline h-3 w-3 mr-1 align-text-bottom"></i>Click to open gallery</span>
                    </span>
                    <i data-lucide="chevron-right" class="h-4 w-4 text-indigo-400 group-hover:translate-x-0.5 transition"></i>
                </button>`;
            const openBtn = box.querySelector('[data-dig-open]');
            if (openBtn) openBtn.addEventListener('click', () => qsOpenDig(qsDigFiles, 0));
            if (window.lucide) window.lucide.createIcons();
        }

        // Lazily build the full-screen viewer once and reuse it across searches.
        function qsEnsureDigViewer() {
            if (document.getElementById('qsDigViewer')) return;
            const el = document.createElement('div');
            el.id = 'qsDigViewer';
            el.className = 'fixed inset-0 z-[60] hidden flex-col bg-black/90';
            el.innerHTML = `
                <div class="flex items-center justify-between gap-3 px-4 py-3 text-white">
                    <div class="min-w-0">
                        <div id="qsDigVName" class="text-sm font-semibold truncate"></div>
                        <div id="qsDigVCount" class="text-[11px] text-white/60"></div>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <button type="button" id="qsDigRotateLeft" title="Rotate left" class="hidden p-2 rounded-lg hover:bg-white/10 text-white"><i data-lucide="rotate-ccw" class="h-5 w-5"></i></button>
                        <button type="button" id="qsDigRotateRight" title="Rotate right" class="hidden p-2 rounded-lg hover:bg-white/10 text-white"><i data-lucide="rotate-cw" class="h-5 w-5"></i></button>
                        <button type="button" data-dig-close class="p-2 rounded-lg hover:bg-white/10 text-white"><i data-lucide="x" class="h-5 w-5"></i></button>
                    </div>
                </div>
                <div id="qsDigStage" class="relative flex-1 flex items-center justify-center overflow-hidden px-2">
                    <button type="button" id="qsDigPrev" class="absolute left-3 top-1/2 -translate-y-1/2 p-2.5 rounded-full bg-white/10 hover:bg-white/20 text-white disabled:opacity-30"><i data-lucide="chevron-left" class="h-6 w-6"></i></button>
                    <div id="qsDigStageInner" class="flex items-center justify-center"></div>
                    <button type="button" id="qsDigNext" class="absolute right-3 top-1/2 -translate-y-1/2 p-2.5 rounded-full bg-white/10 hover:bg-white/20 text-white disabled:opacity-30"><i data-lucide="chevron-right" class="h-6 w-6"></i></button>
                </div>
                <div id="qsDigStrip" class="flex gap-2 overflow-x-auto px-4 py-3 bg-black/40"></div>`;
            document.body.appendChild(el);
            el.querySelector('[data-dig-close]').addEventListener('click', qsCloseDig);
            el.addEventListener('click', (e) => { if (e.target === el) qsCloseDig(); });
            document.getElementById('qsDigPrev').addEventListener('click', qsDigPrev);
            document.getElementById('qsDigNext').addEventListener('click', qsDigNext);
            document.getElementById('qsDigRotateLeft').addEventListener('click', () => qsDigRotate(-1));
            document.getElementById('qsDigRotateRight').addEventListener('click', () => qsDigRotate(1));
        }

        function qsOpenDig(files, i) {
            // Back-compat: qsOpenDig(index) defaults to the File Digital Library list.
            if (typeof files === 'number') { i = files; files = qsDigFiles; }
            qsViewFiles = Array.isArray(files) ? files : [];
            if (!qsViewFiles.length) return;
            qsEnsureDigViewer();
            qsDigStripBuilt = false;
            qsDigIndex = Math.max(0, Math.min(i, qsViewFiles.length - 1));
            const v = document.getElementById('qsDigViewer');
            v.classList.remove('hidden'); v.classList.add('flex');
            document.body.style.overflow = 'hidden';
            qsDigBuildStrip();
            qsDigRenderViewer();
            if (window.lucide) window.lucide.createIcons();
        }
        function qsCloseDig() {
            const v = document.getElementById('qsDigViewer');
            if (!v) return;
            qsPdfToken++; // cancel any in-flight PDF render
            v.classList.add('hidden'); v.classList.remove('flex');
            const inner = document.getElementById('qsDigStageInner');
            if (inner) inner.innerHTML = '';
            document.body.style.overflow = '';
        }
        function qsDigNext() { if (qsDigIndex < qsViewFiles.length - 1) { qsDigIndex++; qsDigRenderViewer(); } }
        function qsDigPrev() { if (qsDigIndex > 0) { qsDigIndex--; qsDigRenderViewer(); } }
        function qsDigGoto(i) { qsDigIndex = Math.max(0, Math.min(i, qsViewFiles.length - 1)); qsDigRenderViewer(); }

        function qsDigRenderViewer() {
            const f = qsViewFiles[qsDigIndex];
            if (!f) return;
            qsPdfToken++;        // cancel any in-flight PDF render
            qsDigRotation = 0;   // each page starts upright
            const stage = document.getElementById('qsDigStageInner');
            const isImg = qsIsImg(f);
            const isPdf = (f.ext || '').toLowerCase() === 'pdf';
            // Rotate tools apply to images only.
            document.getElementById('qsDigRotateLeft').classList.toggle('hidden', !isImg);
            document.getElementById('qsDigRotateRight').classList.toggle('hidden', !isImg);
            if (isImg) {
                stage.innerHTML = `<i data-lucide="loader" class="h-8 w-8 text-white animate-spin"></i>`;
                if (window.lucide) window.lucide.createIcons();
                const img = new Image();
                img.alt = f.name;
                img.style.objectFit = 'contain'; img.style.transition = 'transform .2s ease';
                img.onload = () => { stage.innerHTML = ''; stage.appendChild(img); qsApplyImgRotation(); };
                img.onerror = () => { stage.innerHTML = `<div style="color:#fff;font-size:13px;">Could not load this page.</div>`; };
                img.src = f.url;
            } else if (isPdf) {
                // Browsers/webviews that won't embed a PDF in an <iframe> get a blank
                // frame, so paint the pages to <canvas> with PDF.js instead.
                qsRenderPdf(f);
            } else {
                // Other doc types embed in a frame, with a fallback link in case the
                // browser can't display the format inline.
                stage.innerHTML = `<div style="display:flex;flex-direction:column;gap:8px;align-items:center;">
                    <iframe src="${esc(f.url)}" title="${esc(f.name)}" style="width:92vw;height:78vh;background:#fff;border:0;border-radius:6px;"></iframe>
                    <a href="${esc(f.url)}" target="_blank" rel="noopener" style="color:#fff;font-size:12px;text-decoration:underline;">Open in new tab</a>
                </div>`;
            }
            document.getElementById('qsDigVName').textContent = f.name;
            document.getElementById('qsDigVCount').textContent = `Page ${qsDigIndex + 1} of ${qsViewFiles.length}`;
            document.getElementById('qsDigPrev').disabled = qsDigIndex === 0;
            document.getElementById('qsDigNext').disabled = qsDigIndex === qsViewFiles.length - 1;
            [qsDigIndex - 1, qsDigIndex + 1].forEach(j => { const nf = qsViewFiles[j]; if (nf && qsIsImg(nf)) { const im = new Image(); im.src = nf.url; } });
            const strip = document.getElementById('qsDigStrip');
            strip.querySelectorAll('[data-strip-item]').forEach((el, j) => {
                el.classList.toggle('ring-2', j === qsDigIndex);
                el.classList.toggle('ring-indigo-400', j === qsDigIndex);
                if (j === qsDigIndex) el.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
            });
        }

        function qsDigBuildStrip() {
            if (qsDigStripBuilt) return;
            const strip = document.getElementById('qsDigStrip');
            strip.innerHTML = qsViewFiles.map((f, i) => {
                const thumb = qsIsImg(f)
                    ? `<img src="${esc(f.url)}" alt="" loading="lazy" class="h-full w-full object-cover">`
                    : `<i data-lucide="file-text" class="h-4 w-4 text-white/60"></i>`;
                return `<button type="button" data-strip-item data-dig-i="${i}" class="relative h-14 w-12 shrink-0 rounded-md overflow-hidden border border-white/20 bg-white/5 flex items-center justify-center ring-indigo-400">${thumb}<span class="absolute bottom-0 right-0 px-1 text-[9px] font-bold text-white bg-black/50">${i + 1}</span></button>`;
            }).join('');
            strip.querySelectorAll('[data-strip-item]').forEach(el =>
                el.addEventListener('click', () => qsDigGoto(parseInt(el.dataset.digI, 10))));
            qsDigStripBuilt = true;
            if (window.lucide) window.lucide.createIcons();
        }

        // ── Image rotate tool ───────────────────────────────────────────────
        // Rotate the current image in 90° steps. When sideways (90°/270°) the
        // max width/height caps are swapped so the rotated image still fits.
        function qsDigRotate(dir) {
            qsDigRotation = (((qsDigRotation + (dir < 0 ? -90 : 90)) % 360) + 360) % 360;
            qsApplyImgRotation();
        }
        function qsApplyImgRotation() {
            const img = document.querySelector('#qsDigStageInner img');
            if (!img) return;
            img.style.transformOrigin = 'center center';
            img.style.transform = `rotate(${qsDigRotation}deg)`;
            if (qsDigRotation % 180 === 0) {
                img.style.maxWidth = '92vw'; img.style.maxHeight = '80vh';
            } else {
                img.style.maxWidth = '80vh'; img.style.maxHeight = '92vw';
            }
        }

        // ── PDF rendering (PDF.js) ──────────────────────────────────────────
        if (window.pdfjsLib) {
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        }
        function qsPdfFallback(f) {
            return `<div style="display:flex;flex-direction:column;gap:8px;align-items:center;">
                <iframe src="${esc(f.url)}" title="${esc(f.name)}" style="width:92vw;height:78vh;background:#fff;border:0;border-radius:6px;"></iframe>
                <a href="${esc(f.url)}" target="_blank" rel="noopener" style="color:#fff;font-size:12px;text-decoration:underline;">Open PDF in new tab</a>
            </div>`;
        }
        async function qsRenderPdf(f) {
            const token = ++qsPdfToken;
            const stage = document.getElementById('qsDigStageInner');
            stage.innerHTML = `<i data-lucide="loader" class="h-8 w-8 text-white animate-spin"></i>`;
            if (window.lucide) window.lucide.createIcons();
            if (!window.pdfjsLib) { stage.innerHTML = qsPdfFallback(f); return; }
            try {
                const pdf = await pdfjsLib.getDocument({ url: f.url }).promise;
                if (token !== qsPdfToken) return; // a newer page took over
                const wrap = document.createElement('div');
                wrap.style.cssText = 'width:92vw;height:80vh;display:flex;flex-direction:column;';
                const pages = document.createElement('div');
                pages.style.cssText = 'flex:1;overflow-y:auto;display:flex;flex-direction:column;align-items:center;gap:10px;padding:10px;';
                const link = document.createElement('a');
                link.href = f.url; link.target = '_blank'; link.rel = 'noopener';
                link.textContent = 'Open PDF in new tab';
                link.style.cssText = 'flex-shrink:0;text-align:center;padding:8px;color:#fff;font-size:12px;text-decoration:underline;';
                wrap.appendChild(pages); wrap.appendChild(link);
                stage.innerHTML = ''; stage.appendChild(wrap);
                const scale = Math.min(2, (window.devicePixelRatio || 1) * 1.4);
                for (let p = 1; p <= pdf.numPages; p++) {
                    if (token !== qsPdfToken) return;
                    const page = await pdf.getPage(p);
                    const viewport = page.getViewport({ scale });
                    const canvas = document.createElement('canvas');
                    canvas.width = viewport.width; canvas.height = viewport.height;
                    canvas.style.cssText = 'max-width:100%;height:auto;background:#fff;box-shadow:0 2px 10px rgba(0,0,0,.4);';
                    pages.appendChild(canvas);
                    await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
                }
            } catch (e) {
                if (token !== qsPdfToken) return;
                stage.innerHTML = qsPdfFallback(f);
            }
        }

        document.addEventListener('keydown', (e) => {
            const v = document.getElementById('qsDigViewer');
            if (!v || v.classList.contains('hidden')) return;
            if (e.key === 'Escape') qsCloseDig();
            else if (e.key === 'ArrowRight') qsDigNext();
            else if (e.key === 'ArrowLeft') qsDigPrev();
            else if (e.key === 'r' || e.key === 'R') qsDigRotate(e.shiftKey ? -1 : 1);
        });

        // Re-direct an in-transit file's request straight to the office currently holding
        // it (its last receiving officer) instead of routing it to the SCB Monitor.
        // Mirrors the Digital File Request "Send Request to {office}" redirect.
        const REDIRECT_URL = "{{ route('digital-request.store') }}";
        async function sendRedirect(d, btn) {
            const office = d.current_location || 'Current Office';
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader" class="h-4 w-4 animate-spin"></i> Sending…';
            if (window.lucide) window.lucide.createIcons();
            try {
                const res = await fetch(REDIRECT_URL, {
                    method:'POST',
                    headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
                    body: JSON.stringify({
                        file_no: d.file_number,
                        file_title: d.file_title,
                        request_type: 'Physical',
                        is_redirected: true,
                        receiving_officer: d.receiving_officer_name || null,
                        current_file_location: d.current_location || null,
                        current_file_holder: d.receiving_officer_name || null,
                    }),
                });
                const json = await res.json();
                if (json.success) {
                    // Grey out / disable the re-direct button now that the request is sent,
                    // keeping its original label. Show the confirmation as feedback below.
                    btn.disabled = true;
                    btn.className = 'inline-flex items-center gap-2 rounded-lg bg-gray-300 px-4 py-2 text-sm font-semibold text-white cursor-not-allowed opacity-60';
                    btn.innerHTML = `<i data-lucide="user-check" class="h-4 w-4"></i> Re-direct Request to ${esc(office)}`;
                    Swal.fire('Request re-directed to ' + office, '', 'success');
                    // Enable the Print Tracking Confirmation Slip now that the request is sent.
                    const slip = result.querySelector('[data-print-slip]');
                    if (slip) {
                        slip.classList.remove('bg-gray-300','cursor-not-allowed','pointer-events-none','opacity-60');
                        slip.classList.add('bg-amber-600','hover:bg-amber-700');
                        slip.removeAttribute('aria-disabled');
                        slip.removeAttribute('tabindex');
                    }
                } else if (json.duplicate) {
                    btn.disabled = false; btn.innerHTML = '<i data-lucide="user-check" class="h-4 w-4"></i> Re-direct Request to ' + esc(office);
                    alert(json.message || 'This file already has an open request.');
                } else {
                    btn.disabled = false; btn.innerHTML = '<i data-lucide="user-check" class="h-4 w-4"></i> Retry Re-direct';
                    alert(json.message || 'Could not re-direct the request.');
                }
                if (window.lucide) window.lucide.createIcons();
            } catch (e) {
                btn.disabled = false; btn.innerHTML = '<i data-lucide="user-check" class="h-4 w-4"></i> Retry Re-direct';
                alert('Network error — please try again.');
            }
        }

        // Re-direct a duplicate file (in the duplicate_fileno table) to the Director Land
        // (Land Department) instead of raising a blind File Search Request to the SCB.
        async function sendRedirectToDirectorLand(d, btn) {
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader" class="h-4 w-4 animate-spin"></i> Re-directing…';
            if (window.lucide) window.lucide.createIcons();
            try {
                const res = await fetch(REDIRECT_LAND_URL, {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
                    body: JSON.stringify({ file_number: d.file_number, file_title: d.file_title }),
                });
                const json = await res.json();
                if (json.success) {
                    btn.disabled = true;
                    btn.className = 'inline-flex items-center gap-2 rounded-lg bg-gray-300 px-4 py-2 text-sm font-semibold text-white cursor-not-allowed opacity-60';
                    btn.innerHTML = '<i data-lucide="check" class="h-4 w-4"></i> Re-directed To Director Land (Land Department)';
                    if (window.Swal) Swal.fire(json.message || 'Re-directed to Director Land (Land Department).', '', 'success');
                    loadLog();
                } else {
                    btn.disabled = false;
                    btn.innerHTML = '<i data-lucide="user-check" class="h-4 w-4"></i> Re-direct To Director Land (Land Department)';
                    alert(json.message || 'Could not re-direct to Director Land.');
                }
                if (window.lucide) window.lucide.createIcons();
            } catch (e) {
                btn.disabled = false;
                btn.innerHTML = '<i data-lucide="user-check" class="h-4 w-4"></i> Re-direct To Director Land (Land Department)';
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
        let   logFrom    = '';        // date-range filter (YYYY-MM-DD)
        let   logTo      = '';
        let   lastReport = {};        // last report summary (for exports)

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
            const registry = btn.dataset.registry || '';
            btn.disabled = true;
            try {
                await fetch(`${ACTED_BASE}/${id}/front-desk-acted`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                });
            } catch (e) { /* non-fatal — still let the Front Desk proceed */ }

            if (kind === 'log') {
                logFile({ file_number: fno, file_title: title, receiving_officer: officer, requester_office: office, requester_department: dept, registry: registry });   // navigates to Create File Tracker (?url=… by registry)
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
            if (dateEl) dateEl.textContent = report.range_label
                ? `Range · ${report.range_label}`
                : (report.date ? `Today · ${report.date}` : '');
            set('qs-rep-today',    report.submitted_today);
            set('qs-rep-today-found',    report.today_found);
            set('qs-rep-today-notfound', report.today_not_found);
            set('qs-rep-today-awaiting', report.today_awaiting);
            set('qs-rep-blind',    report.blind_open);
            set('qs-rep-found',    report.found);
            set('qs-rep-notfound', report.not_found);
            set('qs-rep-missing',  report.missing);
            set('qs-rep-awaiting', report.awaiting);
            lastReport = report;
        }

        function renderLogFilters(counts) {
            counts = counts || {};
            const totalEl = document.getElementById('qs-log-total');
            if (totalEl) totalEl.textContent = `${counts.all ?? 0}`;
            const chips = [
                ['BLIND',     'Blind/Open', counts.blind_open],
                ['FOUND',     'Found',      counts.found],
                ['NOT_FOUND', 'Not Found',  counts.not_found],
                ['MISSING',   'Missing',    counts.missing],
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
                            <th class="px-3 py-2.5 w-10">S/N</th>
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
                        ${pageRows.map((r, i) => {
                            const badge  = LOG_BADGE[r.scb_response] || 'bg-gray-100 text-gray-700';
                            const icon   = r.found ? 'check' : (r.not_found ? 'x' : 'clock');
                            const sn     = start + i + 1;
                            const sentDt = splitDT(r.requested_at);
                            const dt     = splitDT(r.responded_at);
                            const action = r.found
                                ? `<button type="button" data-fb-act="log" data-id="${r.id}" data-fno="${esc(r.file_number)}" data-title="${esc(r.file_title || '')}" data-officer="${esc(r.receiving_officer || '')}" data-office="${esc(r.requester_office || '')}" data-dept="${esc(r.requester_department || '')}" data-registry="${esc(r.registry || '')}" class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700"><i data-lucide="file-plus" class="h-3.5 w-3.5"></i> Log File</button>`
                                : (r.not_found
                                    ? `<button type="button" data-fb-act="refer" data-id="${r.id}" data-fno="${esc(r.file_number)}" class="inline-flex items-center gap-1 rounded-lg bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800"><i data-lucide="printer" class="h-3.5 w-3.5"></i> Print</button>`
                                    : `<span class="text-[11px] text-gray-400 italic">Awaiting SCB…</span>`);
                            const delBtn = IS_SUPER_ADMIN
                                ? `<button type="button" data-fb-del data-id="${r.id}" title="Delete request" class="inline-flex items-center gap-1 rounded-lg bg-red-100 px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-200"><i data-lucide="trash-2" class="h-3.5 w-3.5"></i></button>`
                                : '';
                            // Revert an SCB Found/Not-Found response (e.g. tapped by mistake),
                            // sending the request back to the open queue. Super Admins only.
                            const revertBtn = (IS_SUPER_ADMIN && (r.found || r.not_found))
                                ? `<button type="button" data-fb-revert data-id="${r.id}" title="Revert SCB response" class="inline-flex items-center gap-1 rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700"><i data-lucide="rotate-ccw" class="h-3.5 w-3.5"></i> Revert response</button>`
                                : '';
                            // OFS (Office Priority Search) requests get a dedicated amber/gold
                            // treatment so they stand out from regular front-desk requests.
                            const ofsBadge = r.is_ofs
                                ? `<span class="mt-1 ml-1 inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 ring-1 ring-amber-300" title="Office Priority Search${r.ofs_rank ? ' · ' + esc(r.ofs_rank) : ''}"><i data-lucide="crown" class="h-2.5 w-2.5"></i> OFS${r.ofs_rank ? ' · ' + esc(r.ofs_rank) : ''}</span>`
                                : '';
                            // Tint the whole row by the SCB outcome — green for Found, red for
                            // Not Found — so the queue reads at a glance. Rows still awaiting a
                            // response keep the OFS amber highlight (if any).
                            const rowTint = r.found
                                ? 'bg-emerald-50/70 border-l-4 border-l-emerald-400 hover:bg-emerald-50'
                                : (r.not_found
                                    ? 'bg-red-50/70 border-l-4 border-l-red-400 hover:bg-red-50'
                                    : (r.is_ofs ? 'bg-amber-50/60 border-l-4 border-l-amber-400 hover:bg-gray-50/60' : 'hover:bg-gray-50/60'));
                            return `
                            <tr class="border-b border-gray-200 align-top ${rowTint}">
                                <td class="px-3 py-3 whitespace-nowrap text-gray-400 font-medium">${sn}</td>
                                <td class="px-3 py-3 whitespace-nowrap">
                                    <div class="font-semibold text-gray-800">${esc(r.file_number)}</div>
                                    <div class="text-[11px] text-gray-400">${esc(r.request_no)}</div>
                                    <span class="mt-1 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold ${r.is_dfr ? 'bg-gray-200 text-gray-600' : (r.is_blind ? 'bg-red-100 text-red-700' : 'bg-sky-100 text-sky-700')}">
                                        <i data-lucide="${r.is_dfr ? 'file-text' : (r.is_blind ? 'eye-off' : 'folder-search')}" class="h-2.5 w-2.5"></i> ${esc(r.request_type)}</span>${ofsBadge}
                                </td>
                                <td class="px-3 py-3 text-gray-600 max-w-[180px] truncate" title="${esc(r.file_title || '')}">${r.file_title ? esc(r.file_title) : dash}</td>
                                <td class="px-3 py-3 text-gray-600 max-w-[170px] align-top">
                                    <div class="truncate font-medium text-gray-800" title="${esc(r.receiving_officer || '')}">${r.receiving_officer ? esc(r.receiving_officer) : dash}</div>
                                    ${(() => {
                                        const chips = [];
                                        if (r.requester_department) chips.push(`<span class="inline-flex items-center gap-1 rounded-full bg-violet-50 border border-violet-100 px-1.5 py-px text-[10px] font-semibold text-violet-700" title="Department"><i data-lucide="building-2" class="h-2.5 w-2.5"></i>${esc(r.requester_department)}</span>`);
                                        if (r.requester_office)     chips.push(`<span class="inline-flex items-center gap-1 rounded-full bg-sky-50 border border-sky-100 px-1.5 py-px text-[10px] font-semibold text-sky-700" title="Office"><i data-lucide="map-pin" class="h-2.5 w-2.5"></i>${esc(r.requester_office)}</span>`);
                                        return chips.length ? `<div class="mt-1 flex flex-wrap gap-1">${chips.join('')}</div>` : '';
                                    })()}
                                    ${r.registry ? `<div class="mt-0.5"><span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 border border-indigo-100 px-1.5 py-px text-[10px] font-semibold text-indigo-700" title="Origin Registry"><i data-lucide="library" class="h-2.5 w-2.5"></i>${esc(r.registry)}${r.registry_code ? ` · ${esc(r.registry_code)}` : ''}</span></div>` : ''}
                                </td>
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
                                    <div class="flex items-center justify-end gap-2">${action}${revertBtn}${delBtn}</div>
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
            tblBox.querySelectorAll('[data-fb-revert]').forEach(b =>
                b.addEventListener('click', () => revertFileRequest(b)));
            tblBox.querySelectorAll('[data-scb-page]').forEach(b =>
                b.addEventListener('click', () => {
                    if (b.disabled) return;
                    scbPage += (b.dataset.scbPage === 'next' ? 1 : -1);
                    renderScb();
                    tblBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }));
            if (window.lucide) window.lucide.createIcons();
        }

        // Revert an SCB Found/Not-Found response (tapped by mistake) — sends the request
        // back to the open queue so the SCB can respond afresh.
        async function revertFileRequest(btn) {
            if (!confirm('Revert this SCB response and send the request back to the open queue?')) return;
            const id = btn.dataset.id;
            btn.disabled = true;
            const original = btn.innerHTML;
            btn.innerHTML = '<i data-lucide="loader" class="h-3.5 w-3.5 animate-spin"></i> Reverting…';
            if (window.lucide) window.lucide.createIcons();
            try {
                const res = await fetch(`${ACTED_BASE}/${id}/revert`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                });
                const json = await res.json();
                if (json.success) {
                    reloadPanels();
                } else {
                    btn.disabled = false; btn.innerHTML = original;
                    alert(json.message || 'Could not revert this response.');
                    if (window.lucide) window.lucide.createIcons();
                }
            } catch (e) {
                btn.disabled = false; btn.innerHTML = original;
                alert('Network error — please try again.');
                if (window.lucide) window.lucide.createIcons();
            }
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
                const fbUrl = FEEDBACK_URL + (URL_CTX ? ('?url=' + encodeURIComponent(URL_CTX)) : '');
                const res  = await fetch(fbUrl, { headers: { 'Accept': 'application/json' } });
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
                const params = new URLSearchParams();
                if (logStatus) params.set('status', logStatus);
                if (logFrom)   params.set('from', logFrom);
                if (logTo)     params.set('to', logTo);
                if (URL_CTX)   params.set('url', URL_CTX);
                const qs   = params.toString();
                const url  = LOG_URL + (qs ? ('?' + qs) : '');
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

        // ── Export (CSV / PDF) — Instrument-Capture-style consolidated report ──
        // Exports exactly what's on screen: the current status chip + date range +
        // search box, applied to the loaded File Search History rows.
        const EXPORT_COLS = [
            { key: 'sn',                label: 'S/N',           pdfWidth: 10 },
            { key: 'request_no',        label: 'Request No',    pdfWidth: 26 },
            { key: 'file_number',       label: 'File No',       pdfWidth: 28 },
            { key: 'file_title',        label: 'Title',         pdfWidth: 'auto' },
            { key: 'request_type',      label: 'Type',          pdfWidth: 22 },
            { key: 'scb_response',      label: 'Response',      pdfWidth: 20 },
            { key: 'requested_by',      label: 'Sent By',       pdfWidth: 28 },
            { key: 'receiving_officer', label: 'Requester',     pdfWidth: 28 },
            { key: 'current_location',  label: 'File Location',  pdfWidth: 28 },
            { key: 'requested_at',      label: 'Requested',     pdfWidth: 26 },
            { key: 'responded_at',      label: 'Responded',     pdfWidth: 26 },
        ];

        // Build flat export rows from the currently filtered history.
        function exportRows() {
            return logRows.filter(r => rowMatches(r, logQuery)).map((r, i) => {
                const isMissing = r.not_found && r.is_blind;
                return {
                    sn:                i + 1,
                    request_no:        r.request_no || '',
                    file_number:       r.file_number || '',
                    file_title:        r.file_title || '',
                    request_type:      r.request_type || '',
                    scb_response:      isMissing ? 'Missing' : (r.scb_response || ''),
                    requested_by:      r.requested_by || '',
                    receiving_officer: r.receiving_officer || '',
                    current_location:  r.current_location || r.location_type || '',
                    requested_at:      r.requested_at || '',
                    responded_at:      r.responded_at || '',
                };
            });
        }

        // Human label for the active filter, used in filenames + the PDF subtitle.
        function exportScopeLabel() {
            const map = { FOUND:'Found', NOT_FOUND:'Not Found', MISSING:'Missing', BLIND:'Blind-Open', PENDING:'Awaiting' };
            return map[logStatus] || 'All';
        }
        function exportRangeLabel() {
            if (logFrom && logTo) return `${logFrom} to ${logTo}`;
            if (logFrom) return `From ${logFrom}`;
            if (logTo)   return `To ${logTo}`;
            return '';
        }
        function csvCell(v) { return `"${String(v ?? '').replace(/"/g, '""')}"`; }

        function exportHistoryCsv() {
            const rows = exportRows();
            if (!rows.length) { Swal.fire('No Data', 'There is nothing to export in the current view.', 'warning'); return; }
            const lines = [
                EXPORT_COLS.map(c => c.label).join(','),
                ...rows.map(r => EXPORT_COLS.map(c => csvCell(r[c.key])).join(',')),
            ];
            const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `File_Search_Report_${exportScopeLabel()}_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a); a.click(); document.body.removeChild(a);
        }

        function loadImg(url) {
            return fetch(url).then(r => r.ok ? r.blob() : Promise.reject())
                .then(b => new Promise(res => { const fr = new FileReader(); fr.onloadend = () => res(fr.result); fr.onerror = () => res(null); fr.readAsDataURL(b); }))
                .catch(() => null);
        }

        function exportHistoryPdf() {
            const rows = exportRows();
            if (!rows.length) { Swal.fire('No Data', 'There is nothing to export in the current view.', 'warning'); return; }
            if (!window.jspdf || !window.jspdf.jsPDF) { Swal.fire('Error', 'PDF engine not loaded. Please refresh and try again.', 'error'); return; }

            Promise.all([
                loadImg('/assets/logo/ministry1.jpg'),
                loadImg('/assets/logo/ministry2.jpeg'),
                loadImg('/assets/logo/Nigerian-Coat-of-Arms.png'),
            ]).then(([leftLogo, rightLogo, watermark]) => {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
                const pageW = doc.internal.pageSize.getWidth();
                const pageH = doc.internal.pageSize.getHeight();
                const center = pageW / 2;
                const logo = 22;

                function header() {
                    if (leftLogo)  doc.addImage(leftLogo, 'JPEG', 10, 8, logo, logo);
                    if (rightLogo) doc.addImage(rightLogo, 'JPEG', pageW - 10 - logo, 8, logo, logo);
                    doc.setFont('helvetica', 'bold'); doc.setTextColor(0, 0, 0);
                    doc.setFontSize(16); doc.text('KANO STATE GOVERNMENT', center, 14, { align: 'center' });
                    doc.setFontSize(12); doc.text('MINISTRY OF LAND AND PHYSICAL PLANNING', center, 20, { align: 'center' });
                    doc.setFontSize(11); doc.text('FILE SEARCH REPORT', center, 26, { align: 'center' });
                    doc.setLineWidth(0.5); doc.line(10, 32, pageW - 10, 32);
                }
                function watermarkDraw() {
                    if (!watermark) return;
                    try {
                        const s = 120, x = (pageW - s) / 2, y = (pageH - s) / 2;
                        if (typeof doc.GState === 'function') { doc.setGState(new doc.GState({ opacity: 0.08 })); doc.addImage(watermark, 'PNG', x, y, s, s); doc.setGState(new doc.GState({ opacity: 1 })); }
                        else doc.addImage(watermark, 'PNG', x, y, s, s);
                    } catch (e) {}
                }

                const scope = exportScopeLabel();
                const range = exportRangeLabel();

                const columnStyles = {};
                EXPORT_COLS.forEach((c, i) => { if (c.pdfWidth) columnStyles[i] = { cellWidth: c.pdfWidth }; });

                doc.autoTable({
                    head: [EXPORT_COLS.map(c => c.label)],
                    body: rows.map(r => EXPORT_COLS.map(c => r[c.key] ?? '')),
                    startY: 52,
                    theme: 'grid',
                    styles: { fontSize: 8, cellPadding: 1.5, overflow: 'linebreak', valign: 'middle' },
                    headStyles: { fillColor: [79, 70, 229], textColor: [255, 255, 255], fontStyle: 'bold', halign: 'center' },
                    columnStyles,
                    margin: { top: 52, left: 10, right: 10 },
                    didDrawPage: function () {
                        header();
                        doc.setFont('helvetica', 'bold'); doc.setFontSize(11); doc.setTextColor(0, 0, 0);
                        doc.text(`File Search History — ${scope}${range ? ' (' + range + ')' : ''}`, 14, 39);
                        doc.setFont('helvetica', 'normal');
                        doc.setFontSize(8); doc.setTextColor(120, 120, 120);
                        doc.text('Generated on: ' + new Date().toLocaleString(), pageW - 10, 45, { align: 'right' });
                        watermarkDraw();
                    },
                });

                const total = doc.internal.getNumberOfPages();
                for (let p = 1; p <= total; p++) {
                    doc.setPage(p);
                    doc.setFontSize(8); doc.setFont('helvetica', 'normal'); doc.setTextColor(120, 120, 120);
                    doc.text(`Page ${p} of ${total}`, pageW - 10, pageH - 5, { align: 'right' });
                }

                doc.save(`File_Search_Report_${scope}_${new Date().toISOString().split('T')[0]}.pdf`);
            }).catch(e => Swal.fire('Error', 'Failed to generate PDF: ' + (e && e.message ? e.message : e), 'error'));
        }

        btn.addEventListener('click', search);
        if (pickBtn) pickBtn.addEventListener('click', pickFileNumber);
        input.addEventListener('click', pickFileNumber);   // read-only: open picker instead of typing
        document.getElementById('qs-fb-refresh').addEventListener('click', loadLog);

        // Date-range filter for the report + history.
        const repFrom  = document.getElementById('qs-rep-from');
        const repTo    = document.getElementById('qs-rep-to');
        const repClear = document.getElementById('qs-rep-clear');
        function syncDateRange() {
            logFrom = repFrom.value || '';
            logTo   = repTo.value || '';
            repClear.classList.toggle('hidden', !(logFrom || logTo));
            loadLog();
        }
        repFrom.addEventListener('change', syncDateRange);
        repTo.addEventListener('change', syncDateRange);
        repClear.addEventListener('click', () => {
            repFrom.value = ''; repTo.value = '';
            syncDateRange();
        });

        document.getElementById('qs-export-csv').addEventListener('click', exportHistoryCsv);
        document.getElementById('qs-export-pdf').addEventListener('click', exportHistoryPdf);
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

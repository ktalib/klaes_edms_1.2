@extends('layouts.app')

@section('page-title')
    Information Products Verification
@endsection

@section('content')
<div class="flex-1 overflow-auto">
    @include('admin.header', [
        'PageTitle'       => 'Information Products Verification',
        'PageDescription' => 'Verify the authenticity of Letters of Grant/RofO, Occupancy Permits, Certificates of Occupancy and Site/Parcel Plans issued by the Ministry.'
    ])

    <div class="p-6 space-y-6">

        {{-- ============================= VERIFICATION CONSOLE ============================= --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-100 flex items-center gap-2">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-100 text-indigo-600">
                    <i data-lucide="shield-check" class="w-4 h-4"></i>
                </span>
                <div>
                    <div class="text-sm font-bold text-slate-800">Verify a Document</div>
                    <div class="text-[11px] text-slate-400">Search by serial number, registration number, file number or scan the document QR code.</div>
                </div>
            </div>

            <div class="p-5 space-y-4">
                {{-- Product type pills --}}
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-2">Product Type</label>
                    <div class="flex flex-wrap gap-2" id="ipv-type-pills">
                        <button type="button" data-type="all"      class="ipv-pill ipv-pill-active">All Products</button>
                        <button type="button" data-type="rofo"     class="ipv-pill">Letter of Grant / RofO</button>
                        <button type="button" data-type="op"       class="ipv-pill">Occupancy Permit (OP)</button>
                        <button type="button" data-type="cofo"     class="ipv-pill">Certificate of Occupancy</button>
                        <button type="button" data-type="siteplan" class="ipv-pill">Site Plan / Parcel Plan</button>
                    </div>
                </div>

                {{-- Search row --}}
                <div class="flex flex-col lg:flex-row gap-3">
                    <div class="relative flex-1">
                        <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                        <input type="text" id="ipv_reference" autocomplete="off"
                               placeholder="e.g. KN/CofO/2024/00812, RES-2024-1906, MLS/KN/RES/2019/1420 …"
                               class="w-full border border-slate-300 rounded-lg pl-9 pr-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 focus:outline-none">
                    </div>
                    <div class="flex items-center gap-2">
                        <button id="ipv_btn_verify"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-5 py-2.5 rounded-lg flex items-center gap-2 transition">
                            <i data-lucide="shield-check" class="w-4 h-4"></i> Verify
                        </button>
                        <button id="ipv_btn_scan"
                                class="bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold px-4 py-2.5 rounded-lg flex items-center gap-2 transition">
                            <i data-lucide="qr-code" class="w-4 h-4"></i> Scan QR
                        </button>
                        <button id="ipv_btn_clear"
                                class="bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold px-4 py-2.5 rounded-lg flex items-center gap-2 transition">
                            <i data-lucide="x" class="w-4 h-4"></i> Clear
                        </button>
                    </div>
                </div>

                {{-- Advanced filters --}}
                <div>
                    <button type="button" id="ipv_toggle_advanced" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700 inline-flex items-center gap-1">
                        <i data-lucide="sliders-horizontal" class="w-3.5 h-3.5"></i>
                        <span>Additional matching criteria (optional)</span>
                        <i data-lucide="chevron-down" class="w-3.5 h-3.5 transition-transform" id="ipv_adv_chevron"></i>
                    </button>
                    <div id="ipv_advanced" class="hidden mt-3 grid grid-cols-1 md:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Holder / Grantee Name</label>
                            <input type="text" id="ipv_holder" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Plot No.</label>
                            <input type="text" id="ipv_plot" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Issued From</label>
                            <input type="date" id="ipv_from" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Issued To</label>
                            <input type="date" id="ipv_to" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                        </div>
                    </div>
                </div>

                {{-- Demo hint: drop this block once the backend endpoint is wired --}}
                <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-2.5 text-[11px] text-amber-800 flex items-start gap-2">
                    <i data-lucide="info" class="w-3.5 h-3.5 mt-0.5 shrink-0"></i>
                    <span>
                        <strong>Front-end preview.</strong> Results below come from a local sample set. Try
                        <code class="ipv-code">KN/CofO/2024/00812</code> (authentic),
                        <code class="ipv-code">RES-2024-1906</code> (review),
                        <code class="ipv-code">KN/OP/2021/00455</code> (revoked),
                        <code class="ipv-code">SP/KN/2023/7781</code> (authentic) — anything else returns "not found".
                    </span>
                </div>
            </div>
        </div>

        {{-- ============================= STATS ============================= --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-start gap-3 shadow-sm">
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-slate-100 text-slate-600 shrink-0"><i data-lucide="scan-line" class="w-5 h-5"></i></span>
                <div>
                    <div class="text-[11px] text-slate-400 uppercase tracking-wider font-semibold">Checks Today</div>
                    <div class="text-2xl font-extrabold text-slate-800 mt-0.5" id="ipv_stat_total">—</div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-start gap-3 shadow-sm">
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-emerald-100 text-emerald-600 shrink-0"><i data-lucide="badge-check" class="w-5 h-5"></i></span>
                <div>
                    <div class="text-[11px] text-slate-400 uppercase tracking-wider font-semibold">Authentic</div>
                    <div class="text-2xl font-extrabold text-slate-800 mt-0.5" id="ipv_stat_authentic">—</div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-start gap-3 shadow-sm">
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-amber-100 text-amber-600 shrink-0"><i data-lucide="alert-triangle" class="w-5 h-5"></i></span>
                <div>
                    <div class="text-[11px] text-slate-400 uppercase tracking-wider font-semibold">Flagged / Revoked</div>
                    <div class="text-2xl font-extrabold text-slate-800 mt-0.5" id="ipv_stat_flagged">—</div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-start gap-3 shadow-sm">
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-rose-100 text-rose-600 shrink-0"><i data-lucide="file-x" class="w-5 h-5"></i></span>
                <div>
                    <div class="text-[11px] text-slate-400 uppercase tracking-wider font-semibold">Not in Register</div>
                    <div class="text-2xl font-extrabold text-slate-800 mt-0.5" id="ipv_stat_notfound">—</div>
                </div>
            </div>
        </div>

        {{-- ============================= EMPTY STATE ============================= --}}
        <div id="ipv_empty" class="bg-white rounded-xl border border-dashed border-slate-300 py-16 text-center">
            <span class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-slate-100 text-slate-400 mb-3">
                <i data-lucide="shield-question" class="w-7 h-7"></i>
            </span>
            <div class="text-sm font-semibold text-slate-600">No document verified yet</div>
            <div class="text-xs text-slate-400 mt-1">Enter a reference above or scan a document QR code to begin.</div>
        </div>

        {{-- ============================= RESULT ============================= --}}
        <div id="ipv_result" class="hidden space-y-6">

            {{-- Verdict banner --}}
            <div id="ipv_verdict" class="rounded-xl border p-5 flex flex-col lg:flex-row lg:items-center gap-4">
                <span id="ipv_verdict_icon" class="inline-flex items-center justify-center w-14 h-14 rounded-full shrink-0">
                    <i data-lucide="badge-check" class="w-7 h-7"></i>
                </span>
                <div class="flex-1">
                    <div id="ipv_verdict_title" class="text-lg font-extrabold"></div>
                    <div id="ipv_verdict_text" class="text-sm mt-0.5"></div>
                </div>
                <div class="flex items-center gap-2 flex-wrap ipv-noprint">
                    <button id="ipv_btn_print" class="bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-semibold px-4 py-2 rounded-lg flex items-center gap-2 transition">
                        <i data-lucide="printer" class="w-4 h-4"></i> Print Report
                    </button>
                    <button id="ipv_btn_flag" class="bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-semibold px-4 py-2 rounded-lg flex items-center gap-2 transition">
                        <i data-lucide="flag" class="w-4 h-4"></i> Flag
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

                {{-- Document + property details --}}
                <div class="xl:col-span-2 space-y-6">
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
                            <div class="text-sm font-bold text-slate-800">Document Details</div>
                            <span id="ipv_doc_badge" class="ipv-badge"></span>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4" id="ipv_doc_fields"></div>
                    </div>

                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                        <div class="px-5 py-3 border-b border-slate-100 text-sm font-bold text-slate-800">Holder &amp; Property</div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4" id="ipv_prop_fields"></div>
                    </div>

                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                        <div class="px-5 py-3 border-b border-slate-100 text-sm font-bold text-slate-800">Document Lifecycle</div>
                        <div class="p-5">
                            <ol class="ipv-timeline" id="ipv_timeline"></ol>
                        </div>
                    </div>
                </div>

                {{-- Security checks --}}
                <div class="space-y-6">
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                        <div class="px-5 py-3 border-b border-slate-100 text-sm font-bold text-slate-800">Security Feature Checks</div>
                        <div class="p-4 space-y-1" id="ipv_checks"></div>
                        <div class="px-5 py-3 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-[11px] uppercase tracking-wider font-semibold text-slate-400">Confidence</span>
                            <span id="ipv_confidence" class="text-sm font-extrabold text-slate-800">—</span>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                        <div class="px-5 py-3 border-b border-slate-100 text-sm font-bold text-slate-800">Register Fingerprint</div>
                        <div class="p-5 text-center">
                            <div id="ipv_qr" class="inline-block p-3 bg-white border border-slate-200 rounded-lg"></div>
                            <div class="mt-3 text-[11px] text-slate-400 uppercase tracking-wider font-semibold">Verification Code</div>
                            <div id="ipv_vcode" class="font-mono text-sm font-bold text-slate-700 mt-0.5 break-all">—</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================= RECENT LOG ============================= --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm ipv-noprint">
            <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
                <div class="text-sm font-bold text-slate-800">Recent Verifications</div>
                <button id="ipv_btn_export" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700 inline-flex items-center gap-1">
                    <i data-lucide="download" class="w-3.5 h-3.5"></i> Export
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] uppercase tracking-wider text-slate-500 bg-slate-50 border-b border-slate-200">
                            <th class="px-4 py-2.5 font-semibold">Time</th>
                            <th class="px-4 py-2.5 font-semibold">Reference</th>
                            <th class="px-4 py-2.5 font-semibold">Product</th>
                            <th class="px-4 py-2.5 font-semibold">Holder</th>
                            <th class="px-4 py-2.5 font-semibold">Channel</th>
                            <th class="px-4 py-2.5 font-semibold">Verified By</th>
                            <th class="px-4 py-2.5 font-semibold">Result</th>
                        </tr>
                    </thead>
                    <tbody id="ipv_log_body"></tbody>
                </table>
            </div>
        </div>

    </div>
    @include('admin.footer')
</div>

{{-- ============================= QR SCAN MODAL ============================= --}}
<div id="ipv_scan_modal" class="hidden fixed inset-0 z-[9999] items-center justify-center bg-slate-900/60 p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
            <div class="text-sm font-bold text-slate-800">Scan Document QR Code</div>
            <button class="ipv-modal-close text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>
        <div class="p-5 space-y-4">
            <div class="aspect-square rounded-lg bg-slate-900 relative overflow-hidden flex items-center justify-center">
                <div class="ipv-scan-frame"></div>
                <div class="ipv-scan-line"></div>
                <span class="text-slate-500 text-xs px-6 text-center">Camera preview — device/backend integration pending</span>
            </div>
            <div>
                <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Or paste the QR payload / reference</label>
                <input type="text" id="ipv_scan_manual" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none" placeholder="KN/CofO/2024/00812">
            </div>
        </div>
        <div class="px-5 py-3 bg-slate-50 border-t border-slate-100 flex justify-end gap-2">
            <button class="ipv-modal-close bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold px-4 py-2 rounded-lg">Cancel</button>
            <button id="ipv_scan_use" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">Use Reference</button>
        </div>
    </div>
</div>

{{-- ============================= FLAG MODAL ============================= --}}
<div id="ipv_flag_modal" class="hidden fixed inset-0 z-[9999] items-center justify-center bg-slate-900/60 p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
            <div class="text-sm font-bold text-slate-800">Flag Document for Review</div>
            <button class="ipv-modal-close text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>
        <div class="p-5 space-y-4">
            <div>
                <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Reference</label>
                <input type="text" id="ipv_flag_ref" readonly class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono">
            </div>
            <div>
                <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Reason</label>
                <select id="ipv_flag_reason" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                    <option>Serial number not found in register</option>
                    <option>Holder name does not match register</option>
                    <option>Suspected altered document</option>
                    <option>Seal / signature irregular</option>
                    <option>Document reported lost or revoked</option>
                    <option>Other</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Notes</label>
                <textarea id="ipv_flag_notes" rows="3" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none" placeholder="Describe what was observed on the physical document…"></textarea>
            </div>
        </div>
        <div class="px-5 py-3 bg-slate-50 border-t border-slate-100 flex justify-end gap-2">
            <button class="ipv-modal-close bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold px-4 py-2 rounded-lg">Cancel</button>
            <button id="ipv_flag_submit" class="bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold px-4 py-2 rounded-lg">Submit Flag</button>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .ipv-pill {
        border: 1px solid #e2e8f0; background: #fff; color: #475569;
        font-size: 12px; font-weight: 600; padding: 6px 14px; border-radius: 9999px;
        transition: all .15s ease; white-space: nowrap;
    }
    .ipv-pill:hover { background: #f8fafc; border-color: #cbd5e1; }
    .ipv-pill-active, .ipv-pill-active:hover { background: #4f46e5; border-color: #4f46e5; color: #fff; }

    .ipv-code {
        background: #fff; border: 1px solid #fcd34d; border-radius: 4px;
        padding: 0 4px; font-family: ui-monospace, monospace; font-size: 10px; white-space: nowrap;
    }

    .ipv-field-label {
        font-size: 10px; text-transform: uppercase; letter-spacing: .06em;
        font-weight: 700; color: #94a3b8; margin-bottom: 2px;
    }
    .ipv-field-value { font-size: 13px; font-weight: 600; color: #1e293b; word-break: break-word; }

    .ipv-badge {
        display: inline-flex; align-items: center; padding: 3px 12px; border-radius: 9999px;
        font-size: 11px; font-weight: 700; letter-spacing: .02em;
    }
    .ipv-badge-rofo     { background: #dbeafe; color: #1e40af; }
    .ipv-badge-op       { background: #fef3c7; color: #92400e; }
    .ipv-badge-cofo     { background: #ede9fe; color: #5b21b6; }
    .ipv-badge-siteplan { background: #d1fae5; color: #065f46; }

    /* verdict tones */
    .ipv-tone-pass { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
    .ipv-tone-pass #ipv_verdict_icon { background: #d1fae5; color: #059669; }
    .ipv-tone-warn { background: #fffbeb; border-color: #fde68a; color: #92400e; }
    .ipv-tone-warn #ipv_verdict_icon { background: #fef3c7; color: #d97706; }
    .ipv-tone-fail { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
    .ipv-tone-fail #ipv_verdict_icon { background: #fee2e2; color: #dc2626; }
    .ipv-tone-none { background: #f8fafc; border-color: #e2e8f0; color: #334155; }
    .ipv-tone-none #ipv_verdict_icon { background: #e2e8f0; color: #64748b; }

    /* security checks */
    .ipv-check { display: flex; align-items: flex-start; gap: 10px; padding: 8px 10px; border-radius: 8px; }
    .ipv-check:hover { background: #f8fafc; }
    .ipv-check-icon {
        width: 18px; height: 18px; border-radius: 9999px; display: inline-flex;
        align-items: center; justify-content: center; flex-shrink: 0; margin-top: 1px;
    }
    .ipv-check-pass .ipv-check-icon { background: #d1fae5; color: #059669; }
    .ipv-check-warn .ipv-check-icon { background: #fef3c7; color: #d97706; }
    .ipv-check-fail .ipv-check-icon { background: #fee2e2; color: #dc2626; }
    .ipv-check-title { font-size: 12.5px; font-weight: 600; color: #1e293b; }
    .ipv-check-note  { font-size: 11px; color: #94a3b8; }

    /* timeline */
    .ipv-timeline { position: relative; padding-left: 22px; margin: 0; list-style: none; }
    .ipv-timeline::before { content: ''; position: absolute; left: 5px; top: 4px; bottom: 4px; width: 2px; background: #e2e8f0; }
    .ipv-timeline li { position: relative; padding-bottom: 16px; }
    .ipv-timeline li:last-child { padding-bottom: 0; }
    .ipv-timeline li::before {
        content: ''; position: absolute; left: -22px; top: 4px; width: 12px; height: 12px;
        border-radius: 9999px; background: #fff; border: 2px solid #6366f1;
    }
    .ipv-timeline li.is-alert::before { border-color: #dc2626; }
    .ipv-tl-title { font-size: 13px; font-weight: 700; color: #1e293b; }
    .ipv-tl-meta  { font-size: 11px; color: #94a3b8; }

    /* log */
    #ipv_log_body td { padding: 10px 16px; border-bottom: 1px solid #f1f5f9; white-space: nowrap; }
    #ipv_log_body tr:hover td { background: #f8fafc; }
    .ipv-result-chip { display: inline-flex; align-items: center; gap: 4px; padding: 2px 10px; border-radius: 9999px; font-size: 11px; font-weight: 700; }
    .ipv-result-authentic { background: #d1fae5; color: #065f46; }
    .ipv-result-review    { background: #fef3c7; color: #92400e; }
    .ipv-result-revoked   { background: #fee2e2; color: #991b1b; }
    .ipv-result-notfound  { background: #e2e8f0; color: #475569; }

    /* scanner decoration */
    .ipv-scan-frame { position: absolute; inset: 18%; border: 2px solid rgba(99,102,241,.8); border-radius: 12px; }
    .ipv-scan-line  { position: absolute; left: 18%; right: 18%; height: 2px; background: #6366f1; box-shadow: 0 0 12px #6366f1; animation: ipvScan 2.2s ease-in-out infinite; }
    @keyframes ipvScan { 0%, 100% { top: 20%; } 50% { top: 78%; } }

    @media print {
        .ipv-noprint, #ipv_empty { display: none !important; }
    }
</style>
@endpush

@push('scripts')
<script>
$(function () {
    'use strict';

    /* =====================================================================
     * SAMPLE DATA — front-end only.
     * Replace lookup() with the real endpoint; keep this response shape and
     * nothing else on the page has to change.
     * ===================================================================== */
    var SAMPLES = {
        'KN/COFO/2024/00812': {
            reference: 'KN/CofO/2024/00812',
            verdict: 'authentic',
            product: 'cofo',
            productLabel: 'Certificate of Occupancy',
            confidence: '98%',
            vcode: 'KLAES-VC-8F42-9A17-CC03',
            document: {
                'Serial No.': 'KN/CofO/2024/00812',
                'Registration No.': '812/812/2024',
                'File No.': 'MLS/KN/RES/2019/1420',
                'KANGIS No.': 'KNML 04120',
                'Date of Issue': '14 Mar 2024',
                'Term of Years': '99 Years (from 01 Jan 2024)',
                'Issuing Authority': 'New KANGIS CofO',
                'Signed By': 'Hon. Commissioner, Ministry of Land & Physical Planning',
                'Status': 'Active'
            },
            property: {
                'Holder / Grantee': 'Aliyu Musa Abdullahi',
                'Plot No.': 'Plot 41',
                'Street': 'Ahmadu Bello Way',
                'District': 'Nassarawa GRA',
                'LGA': 'Nassarawa',
                'Land Use': 'Residential',
                'Plot Size': '1,200 sqm',
                'Ground Rent': 'Paid to 2026',
                'Encumbrance': 'None recorded'
            },
            checks: [
                ['pass', 'Serial number found in register',   'Matched CofO_staging entry #00812'],
                ['pass', 'QR payload signature valid',        'Digital signature verified against issuing key'],
                ['pass', 'Holder matches register',           'Aliyu Musa Abdullahi'],
                ['pass', 'Property details match',            'Plot 41, Nassarawa GRA'],
                ['pass', 'No revocation recorded',            'Title status: Active'],
                ['pass', 'Issuing officer signature on file', 'Signature registry ref. SIG/2024/118']
            ],
            timeline: [
                ['Application received',            '02 Nov 2023 · Land Administration'],
                ['RofO granted',                    '18 Jan 2024 · Letter of Grant KN/RofO/2024/0331'],
                ['Certificate of Occupancy issued', '14 Mar 2024 · Commissioner signed'],
                ['Collected by holder',             '29 Mar 2024 · Registry counter']
            ]
        },
        'RES-2024-1906': {
            reference: 'RES-2024-1906',
            verdict: 'review',
            product: 'rofo',
            productLabel: 'Letter of Grant / RofO',
            confidence: '61%',
            vcode: 'KLAES-VC-2B90-4417-DD71',
            document: {
                'Serial No.': 'RES-2024-1906',
                'Registration No.': '1906/1906/2024',
                'File No.': 'KN 8841',
                'KANGIS No.': '—',
                'Date of Issue': '07 Jun 2024',
                'Term of Years': '99 Years',
                'Issuing Authority': 'Ministry of Land & Physical Planning',
                'Signed By': 'Director, Land Administration',
                'Status': 'Under review'
            },
            property: {
                'Holder / Grantee': 'Hauwa Ibrahim Sani',
                'Plot No.': 'Plot 7B',
                'Street': 'Zaria Road',
                'District': 'Sharada',
                'LGA': 'Kano Municipal',
                'Land Use': 'Commercial',
                'Plot Size': '860 sqm',
                'Ground Rent': 'Outstanding (2023–2024)',
                'Encumbrance': 'Mortgage lodged 2024'
            },
            checks: [
                ['pass', 'Serial number found in register', 'Matched pra entry #1906'],
                ['warn', 'QR payload absent',               'Document predates QR issuance — manual check required'],
                ['warn', 'Holder name differs slightly',    'Register reads "Hauwa I. Sani"'],
                ['pass', 'Property details match',          'Plot 7B, Sharada'],
                ['pass', 'No revocation recorded',          '—'],
                ['warn', 'Ground rent outstanding',         '2 years outstanding as at today']
            ],
            timeline: [
                ['Application received',    '11 Feb 2024 · Land Administration'],
                ['Recommendation approved', '21 May 2024 · Director'],
                ['Letter of Grant issued',  '07 Jun 2024 · RES-2024-1906'],
                ['Mortgage lodged',         '30 Sep 2024 · Deeds Registry']
            ]
        },
        'KN/OP/2021/00455': {
            reference: 'KN/OP/2021/00455',
            verdict: 'revoked',
            product: 'op',
            productLabel: 'Occupancy Permit (OP)',
            confidence: '99%',
            vcode: 'KLAES-VC-7C15-0083-AA26',
            document: {
                'Serial No.': 'KN/OP/2021/00455',
                'Registration No.': '455/455/2021',
                'File No.': 'CON/RES/2021/0455',
                'KANGIS No.': 'KNML 00455',
                'Date of Issue': '19 Aug 2021',
                'Term of Years': '99 Years',
                'Issuing Authority': 'Ministry of Land & Physical Planning',
                'Signed By': 'Permanent Secretary',
                'Status': 'Revoked'
            },
            property: {
                'Holder / Grantee': 'Bello Yusuf Garba',
                'Plot No.': 'Plot 19',
                'Street': 'Court Road',
                'District': 'Bompai',
                'LGA': 'Nassarawa',
                'Land Use': 'Industrial',
                'Plot Size': '2,400 sqm',
                'Ground Rent': 'Not applicable',
                'Encumbrance': 'Revocation notice served'
            },
            checks: [
                ['pass', 'Serial number found in register',   'Matched pra Occupancy Permit row'],
                ['pass', 'QR payload signature valid',        'Signature verified'],
                ['pass', 'Holder matches register',           'Bello Yusuf Garba'],
                ['fail', 'Revocation recorded',               'Revoked 12 Feb 2025 — Certificate KN/REV/2025/0031'],
                ['fail', 'Document not surrendered',          'Original never returned to the registry'],
                ['pass', 'Issuing officer signature on file', 'Signature registry ref. SIG/2021/077']
            ],
            timeline: [
                ['Occupancy Permit issued', '19 Aug 2021 · Permanent Secretary'],
                ['Breach notice served',    '04 Oct 2024 · Non-development'],
                ['Title revoked',           '12 Feb 2025 · Certificate KN/REV/2025/0031', true],
                ['Parcel returned to pool', '01 Mar 2025 · Land Administration', true]
            ]
        },
        'SP/KN/2023/7781': {
            reference: 'SP/KN/2023/7781',
            verdict: 'authentic',
            product: 'siteplan',
            productLabel: 'Site Plan / Parcel Plan',
            confidence: '96%',
            vcode: 'KLAES-VC-5510-6642-BE09',
            document: {
                'Serial No.': 'SP/KN/2023/7781',
                'Registration No.': '—',
                'File No.': 'MLS/KN/COM/2022/0917',
                'KANGIS No.': 'KNML 09170',
                'Date of Issue': '05 Dec 2023',
                'Term of Years': 'Not applicable',
                'Issuing Authority': 'Survey & Cadastral Department',
                'Signed By': 'Surveyor-General, Kano State',
                'Status': 'Active'
            },
            property: {
                'Holder / Grantee': 'Northern Agro Ventures Ltd',
                'Plot No.': 'Plot 12–14',
                'Street': 'Hadejia Road',
                'District': 'Gwagwarwa',
                'LGA': 'Nassarawa',
                'Land Use': 'Commercial',
                'Plot Size': '4,050 sqm',
                'Ground Rent': 'Not applicable',
                'Encumbrance': 'None recorded'
            },
            checks: [
                ['pass', 'Plan number found in cadastral register', 'Matched survey record #7781'],
                ['pass', 'Coordinates match parcel geometry',       'Beacon set KN/BC/7781 verified'],
                ['pass', 'Surveyor-General seal present',           'Seal reference SG/2023/441'],
                ['pass', 'No overlap with adjoining parcels',       'Cadastral overlay clean'],
                ['pass', 'No revocation recorded',                  '—'],
                ['pass', 'Scale and datum standard',                '1:1000 · Minna Datum']
            ],
            timeline: [
                ['Survey instruction issued', '18 Sep 2023 · Survey & Cadastral'],
                ['Field survey completed',    '14 Oct 2023'],
                ['Plan checked and approved', '28 Nov 2023 · Chief Cartographer'],
                ['Site Plan released',        '05 Dec 2023 · Surveyor-General signed']
            ]
        }
    };

    var LOG = [
        ['09:41', 'KN/CofO/2024/00812', 'cofo',     'Aliyu Musa Abdullahi',       'QR Scan', 'A. Yakubu',  'authentic'],
        ['09:28', 'RES-2024-1906',      'rofo',     'Hauwa Ibrahim Sani',         'Manual',  'A. Yakubu',  'review'],
        ['08:57', 'KN/OP/2021/00455',   'op',       'Bello Yusuf Garba',          'QR Scan', 'M. Danladi', 'revoked'],
        ['08:44', 'SP/KN/2023/7781',    'siteplan', 'Northern Agro Ventures Ltd', 'Manual',  'M. Danladi', 'authentic'],
        ['08:12', 'KN/CofO/2019/00113', 'cofo',     '—',                          'Manual',  'S. Ibrahim', 'notfound']
    ];

    var VERDICTS = {
        authentic: {
            tone: 'ipv-tone-pass', icon: 'badge-check', title: 'Authentic Document',
            text: 'This document matches the Ministry register and every security check passed.'
        },
        review: {
            tone: 'ipv-tone-warn', icon: 'alert-triangle', title: 'Requires Manual Review',
            text: 'The record exists but one or more checks did not fully match. Refer to the registry officer before accepting.'
        },
        revoked: {
            tone: 'ipv-tone-fail', icon: 'shield-x', title: 'Title Revoked',
            text: 'This document was validly issued but the title has since been revoked. It is no longer valid evidence of interest.'
        },
        notfound: {
            tone: 'ipv-tone-none', icon: 'file-x', title: 'Not Found in Register',
            text: 'No information product matching this reference exists in the register. Treat the document as unverified.'
        }
    };

    var PRODUCT_BADGE = { rofo: 'ipv-badge-rofo', op: 'ipv-badge-op', cofo: 'ipv-badge-cofo', siteplan: 'ipv-badge-siteplan' };
    var PRODUCT_LABEL = { rofo: 'Letter of Grant / RofO', op: 'Occupancy Permit', cofo: 'Certificate of Occupancy', siteplan: 'Site Plan / Parcel Plan' };

    var activeType = 'all';

    function esc(v) { return $('<span>').text(v == null ? '' : v).html(); }
    function icons() { if (window.lucide && lucide.createIcons) { lucide.createIcons(); } }

    /* ---- BACKEND SEAM: swap for the real lookup --------------------------- */
    function lookup(reference) {
        var key = (reference || '').trim().toUpperCase();
        var hit = SAMPLES[key];
        if (!hit) { return { reference: reference, verdict: 'notfound' }; }
        if (activeType !== 'all' && hit.product !== activeType) { return { reference: reference, verdict: 'notfound' }; }
        return hit;
    }
    /* ----------------------------------------------------------------------- */

    function renderFields($target, obj) {
        $target.empty();
        $.each(obj || {}, function (label, value) {
            $target.append(
                '<div>' +
                    '<div class="ipv-field-label">' + esc(label) + '</div>' +
                    '<div class="ipv-field-value">' + esc(value || '—') + '</div>' +
                '</div>'
            );
        });
    }

    function renderChecks(checks) {
        var $c = $('#ipv_checks').empty();
        $.each(checks || [], function (_, row) {
            var state = row[0];
            var icon = state === 'pass' ? 'check' : (state === 'warn' ? 'alert-triangle' : 'x');
            $c.append(
                '<div class="ipv-check ipv-check-' + state + '">' +
                    '<span class="ipv-check-icon"><i data-lucide="' + icon + '" class="w-3 h-3"></i></span>' +
                    '<div>' +
                        '<div class="ipv-check-title">' + esc(row[1]) + '</div>' +
                        '<div class="ipv-check-note">' + esc(row[2]) + '</div>' +
                    '</div>' +
                '</div>'
            );
        });
    }

    function renderTimeline(items) {
        var $t = $('#ipv_timeline').empty();
        $.each(items || [], function (_, row) {
            $t.append(
                '<li class="' + (row[2] ? 'is-alert' : '') + '">' +
                    '<div class="ipv-tl-title">' + esc(row[0]) + '</div>' +
                    '<div class="ipv-tl-meta">' + esc(row[1]) + '</div>' +
                '</li>'
            );
        });
    }

    // Decorative fingerprint block — swap for the register's real QR payload.
    function renderQr(seed) {
        var n = 11, h = 0, i;
        var html = '<div style="display:grid;grid-template-columns:repeat(' + n + ',8px);gap:1px">';
        for (i = 0; i < seed.length; i++) { h = (h * 31 + seed.charCodeAt(i)) >>> 0; }
        for (i = 0; i < n * n; i++) {
            h = (h * 1103515245 + 12345) >>> 0;
            var col = i % n, rowIdx = Math.floor(i / n);
            var corner = (col < 3 && rowIdx < 3) || (col > n - 4 && rowIdx < 3) || (col < 3 && rowIdx > n - 4);
            var on = corner ? (col % 2 === 0 || rowIdx % 2 === 0) : ((h >>> 16) & 1);
            html += '<span style="width:8px;height:8px;background:' + (on ? '#0f172a' : '#fff') + '"></span>';
        }
        return html + '</div>';
    }

    function renderResult(data) {
        var v = VERDICTS[data.verdict] || VERDICTS.notfound;

        $('#ipv_empty').addClass('hidden');
        $('#ipv_result').removeClass('hidden');

        $('#ipv_verdict')
            .removeClass('ipv-tone-pass ipv-tone-warn ipv-tone-fail ipv-tone-none')
            .addClass(v.tone);
        $('#ipv_verdict_icon').html('<i data-lucide="' + v.icon + '" class="w-7 h-7"></i>');
        $('#ipv_verdict_title').text(v.title);
        $('#ipv_verdict_text').html(esc(v.text) + ' <span class="font-mono font-bold">' + esc(data.reference || '') + '</span>');

        if (data.verdict === 'notfound') {
            $('#ipv_doc_badge').attr('class', 'ipv-badge').text('');
            renderFields($('#ipv_doc_fields'), {
                'Reference searched': data.reference,
                'Register match': 'None',
                'Searched at': new Date().toLocaleString()
            });
            renderFields($('#ipv_prop_fields'), { 'Holder / Grantee': '—', 'Property': '—', 'Land Use': '—' });
            renderChecks([
                ['fail', 'Serial number found in register', 'No matching record'],
                ['fail', 'QR payload signature valid',      'Nothing to verify'],
                ['warn', 'Manual registry search advised',  'Check archived / pre-digital registers']
            ]);
            renderTimeline([['No register history', 'This reference has never been issued as an information product', true]]);
            $('#ipv_confidence').text('0%');
            $('#ipv_qr').html('<div class="text-[11px] text-slate-400 py-6 px-4">No fingerprint</div>');
            $('#ipv_vcode').text('—');
        } else {
            $('#ipv_doc_badge')
                .attr('class', 'ipv-badge ' + (PRODUCT_BADGE[data.product] || ''))
                .text(data.productLabel || '');
            renderFields($('#ipv_doc_fields'), data.document);
            renderFields($('#ipv_prop_fields'), data.property);
            renderChecks(data.checks);
            renderTimeline(data.timeline);
            $('#ipv_confidence').text(data.confidence || '—');
            $('#ipv_qr').html(renderQr(data.vcode || data.reference || ''));
            $('#ipv_vcode').text(data.vcode || '—');
        }

        prependLog(data);
        icons();
        $('html, body').animate({ scrollTop: $('#ipv_result').offset().top - 90 }, 250);
    }

    function resultChip(kind) {
        var label = { authentic: 'Authentic', review: 'Review', revoked: 'Revoked', notfound: 'Not Found' }[kind] || kind;
        return '<span class="ipv-result-chip ipv-result-' + kind + '">' + label + '</span>';
    }

    function logRow(r) {
        return '<tr>' +
            '<td class="text-slate-500">' + esc(r[0]) + '</td>' +
            '<td class="font-mono font-semibold text-slate-800">' + esc(r[1]) + '</td>' +
            '<td><span class="ipv-badge ' + (PRODUCT_BADGE[r[2]] || '') + '">' + esc(PRODUCT_LABEL[r[2]] || r[2]) + '</span></td>' +
            '<td class="text-slate-600">' + esc(r[3]) + '</td>' +
            '<td class="text-slate-500">' + esc(r[4]) + '</td>' +
            '<td class="text-slate-500">' + esc(r[5]) + '</td>' +
            '<td>' + resultChip(r[6]) + '</td>' +
        '</tr>';
    }

    function renderLog() {
        var $b = $('#ipv_log_body').empty();
        $.each(LOG, function (_, r) { $b.append(logRow(r)); });
    }

    function prependLog(data) {
        var now = new Date();
        var hh = ('0' + now.getHours()).slice(-2), mm = ('0' + now.getMinutes()).slice(-2);
        var holder = (data.property && data.property['Holder / Grantee']) || '—';
        LOG.unshift([hh + ':' + mm, data.reference || '—', data.product || 'cofo', holder, 'Manual', 'You', data.verdict]);
        renderLog();
        renderStats();
    }

    function renderStats() {
        var count = function (k) { return LOG.filter(function (r) { return r[6] === k; }).length; };
        $('#ipv_stat_total').text(LOG.length);
        $('#ipv_stat_authentic').text(count('authentic'));
        $('#ipv_stat_flagged').text(count('review') + count('revoked'));
        $('#ipv_stat_notfound').text(count('notfound'));
    }

    /* ------------------------------ events ------------------------------ */
    $('#ipv-type-pills').on('click', 'button', function () {
        $('#ipv-type-pills button').removeClass('ipv-pill-active');
        $(this).addClass('ipv-pill-active');
        activeType = $(this).data('type');
    });

    $('#ipv_toggle_advanced').on('click', function () {
        $('#ipv_advanced').toggleClass('hidden');
        $('#ipv_adv_chevron').toggleClass('rotate-180');
    });

    function doVerify() {
        var ref = $.trim($('#ipv_reference').val());
        if (!ref) {
            $('#ipv_reference').addClass('border-rose-400').focus();
            setTimeout(function () { $('#ipv_reference').removeClass('border-rose-400'); }, 1200);
            return;
        }
        var $btn = $('#ipv_btn_verify')
            .prop('disabled', true)
            .html('<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Verifying…');
        icons();
        setTimeout(function () {          // stands in for the backend round-trip
            renderResult(lookup(ref));
            $btn.prop('disabled', false).html('<i data-lucide="shield-check" class="w-4 h-4"></i> Verify');
            icons();
        }, 550);
    }

    $('#ipv_btn_verify').on('click', doVerify);
    $('#ipv_reference').on('keydown', function (e) { if (e.key === 'Enter') { doVerify(); } });

    $('#ipv_btn_clear').on('click', function () {
        $('#ipv_reference, #ipv_holder, #ipv_plot, #ipv_from, #ipv_to').val('');
        $('#ipv_result').addClass('hidden');
        $('#ipv_empty').removeClass('hidden');
        $('#ipv_reference').focus();
    });

    /* modals */
    function openModal(id) { $('#' + id).removeClass('hidden').addClass('flex'); icons(); }
    function closeModal($m) { $m.addClass('hidden').removeClass('flex'); }

    $('.ipv-modal-close').on('click', function () {
        closeModal($(this).closest('#ipv_scan_modal, #ipv_flag_modal'));
    });
    $('#ipv_scan_modal, #ipv_flag_modal').on('click', function (e) {
        if (e.target === this) { closeModal($(this)); }
    });

    $('#ipv_btn_scan').on('click', function () { $('#ipv_scan_manual').val(''); openModal('ipv_scan_modal'); });
    $('#ipv_scan_use').on('click', function () {
        var v = $.trim($('#ipv_scan_manual').val());
        closeModal($('#ipv_scan_modal'));
        if (v) { $('#ipv_reference').val(v); doVerify(); }
    });

    $('#ipv_btn_flag').on('click', function () {
        $('#ipv_flag_ref').val($.trim($('#ipv_reference').val()));
        openModal('ipv_flag_modal');
    });
    $('#ipv_flag_submit').on('click', function () {
        closeModal($('#ipv_flag_modal'));
        alert('Flag captured — front-end only, no request was sent.');
    });

    $('#ipv_btn_print').on('click', function () { window.print(); });
    $('#ipv_btn_export').on('click', function () { alert('Export pending the backend endpoint.'); });

    /* ------------------------------- boot ------------------------------- */
    renderLog();
    renderStats();
    icons();
});
</script>
@endpush

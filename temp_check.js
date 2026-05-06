

document.addEventListener('DOMContentLoaded', function () {
        // ── File Number Commissioning button ──
        const commissioningBtn = document.getElementById('btn-file-commissioning');
        if (commissioningBtn) {
            commissioningBtn.addEventListener('click', function () {
                openCommissionModalForOP();
            });
        }

        // ── DataTable initialisation ──
        const table = $('#op-resettlement-table').DataTable({
            dom: '<"op-table-scroll"t><"px-4 py-3 flex items-center justify-between border-t border-slate-100"i>',
            pageLength: "",
            lengthChange: false,
            searching: false,
            ordering: true,
            info: true,
            paging: false,
            autoWidth: false,
            scrollX: false,
            order: [[13, 'desc']],
            columnDefs: [
                { orderable: false, targets: [14] }
            ],
            language: {
                info: 'Showing _START_ to _END_ of _TOTAL_ applications',
                infoEmpty: 'No applications available',
                emptyTable: 'No records found.',
            },
            drawCallback: function (settings) {
                var api = this.api();
                var offset = "";
                api.column(0, { page: 'current' }).nodes().each(function (cell, i) {
                    cell.textContent = offset + i + 1;
                });
                // Override info text to reflect server-side totals
                var infoEl = document.querySelector('.dataTables_info');
                if (infoEl) {
                    var start = offset + 1;
                    var end   = offset + api.rows({ page: 'current' }).count();
                    infoEl.textContent = 'Showing ' + start + ' to ' + end + ' of "" applications';
                }
            }
        });

        const searchInput = document.getElementById('op-search');
        const lengthSelect = document.getElementById('op-length');
        const dateFromInput = document.getElementById('op-filter-date-from');
        const dateToInput = document.getElementById('op-filter-date-to');

        const parseCommissionDate = function (raw) {
            const text = String(raw || '').trim();
            if (!text || text === '—') return null;

            const parts = text.toUpperCase().split(/\s+/); // e.g. MAR 17, 2026
            if (parts.length >= 3) {
                const monthMap = {
                    JAN: 0, FEB: 1, MAR: 2, APR: 3, MAY: 4, JUN: 5,
                    JUL: 6, AUG: 7, SEP: 8, OCT: 9, NOV: 10, DEC: 11
                };
                const month = monthMap[(parts[0] || '').replace(/[^A-Z]/g, '')];
                const day = parseInt((parts[1] || '').replace(/[^0-9]/g, ''), 10);
                const year = parseInt((parts[2] || '').replace(/[^0-9]/g, ''), 10);
                if (!Number.isNaN(month) && !Number.isNaN(day) && !Number.isNaN(year)) {
                    return new Date(year, month, day);
                }
            }

            const fallback = new Date(text);
            return Number.isNaN(fallback.getTime()) ? null : fallback;
        };

        // Date range filter is attached to DataTables global ext search, but restricted to this table only.
        $.fn.dataTable.ext.search.push(function (settings, data) {
            if (!settings || !settings.nTable || settings.nTable.id !== 'op-resettlement-table') {
                return true;
            }

            const fromVal = dateFromInput ? dateFromInput.value : '';
            const toVal = dateToInput ? dateToInput.value : '';

            if (!fromVal && !toVal) {
                return true;
            }

            const rowDate = parseCommissionDate(data[12]);
            if (!rowDate) {
                return false;
            }

            const rowOnly = new Date(rowDate.getFullYear(), rowDate.getMonth(), rowDate.getDate());
            const fromDate = fromVal ? new Date(fromVal + 'T00:00:00') : null;
            const toDate = toVal ? new Date(toVal + 'T23:59:59') : null;

            if (fromDate && rowOnly < fromDate) return false;
            if (toDate && rowOnly > toDate) return false;
            return true;
        });

        // ── Global search – server-side with debounce ──
        var _opSearchTimer = null;
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                clearTimeout(_opSearchTimer);
                var val = this.value;
                _opSearchTimer = setTimeout(function () {
                    var url = new URL(window.location.href);
                    if (val) {
                        url.searchParams.set('search', val);
                    } else {
                        url.searchParams.delete('search');
                    }
                    url.searchParams.delete('page');
                    window.location.href = url.toString();
                }, 600);
            });
        }

        // ── Page length – server-side reload (reset to page 1) ──
        if (lengthSelect) {
            lengthSelect.addEventListener('change', function () {
                var url = new URL(window.location.href);
                url.searchParams.set('limit', this.value);
                url.searchParams.delete('page');
                window.location.href = url.toString();
            });
        }

        // ── Edit modal: reload purposes when land use changes ──
        var opEditLandUseEl = document.getElementById('opEditLandUse');
        if (opEditLandUseEl) {
            opEditLandUseEl.addEventListener('change', function () {
                _opEditLoadPurposesAndSelect(this, '');
            });
        }

        // ── Toggle filter bar ──
        const toggleBtn = document.getElementById('btn-toggle-filters');
        const filterBar = document.getElementById('op-filter-bar');
        if (toggleBtn && filterBar) {
            toggleBtn.addEventListener('click', function () {
                filterBar.classList.toggle('hidden');
                this.classList.toggle('bg-blue-50');
                this.classList.toggle('border-blue-300');
                this.classList.toggle('text-blue-700');
                if (window.lucide) window.lucide.createIcons();
            });
        }

        // ── Column-level filters ──
        // Column indices: 2=Source, 3=MLS File No, 4=File Title, 8=LGA, 10=Commissioned By
        const columnFilters = [
            { id: 'op-filter-party2',      col: 4,  type: 'input' },
            { id: 'op-filter-lga',         col: 8,  type: 'select' },
            { id: 'op-filter-district',    col: 2,  type: 'input' },
            { id: 'op-filter-reg',         col: 3,  type: 'input' },
            { id: 'op-filter-particulars', col: 10, type: 'input' },
        ];

        columnFilters.forEach(function (f) {
            var el = document.getElementById(f.id);
            if (!el) return;
            var eventName = f.type === 'select' ? 'change' : 'input';
            el.addEventListener(eventName, function () {
                var val = this.value;
                table.column(f.col).search(val).draw();
            });
        });

        if (dateFromInput) {
            dateFromInput.addEventListener('change', function () {
                table.draw();
            });
        }

        if (dateToInput) {
            dateToInput.addEventListener('change', function () {
                table.draw();
            });
        }

        // ── Clear all filters ──
        var clearBtn = document.getElementById('btn-clear-filters');
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                columnFilters.forEach(function (f) {
                    var el = document.getElementById(f.id);
                    if (el) el.value = '';
                });
                if (dateFromInput) dateFromInput.value = '';
                if (dateToInput) dateToInput.value = '';
                if (searchInput) searchInput.value = '';
                table.search('').columns().search('').draw();
            });
        }

        if (window.lucide) {
            window.lucide.createIcons();
        }

        // Re-create icons and re-initialise Alpine after DataTable redraws (pagination / search)
        table.on('draw', function () {
            if (window.lucide) window.lucide.createIcons();
            if (typeof Alpine !== 'undefined') {
                document.querySelectorAll('#op-resettlement-table [x-data]').forEach(function (el) {
                    if (!el._x_dataStack) {
                        Alpine.initTree(el);
                    }
                });
            }
        });

        // Deep-link dispatcher: open exact action modal when redirected from Change-of-Name list.
        (function autoOpenRequestedAction() {
            const qs = new URLSearchParams(window.location.search || '');
            const action = (qs.get('action') || '').toLowerCase();
            if (!action) return;

            const fileQuery = (qs.get('search') || '').toString().trim().toUpperCase();
            const rows = document.querySelectorAll('#op-resettlement-table tbody tr[data-record]');
            let target = null;

            rows.forEach(function (tr) {
                if (target) return;
                try {
                    const rec = JSON.parse(tr.getAttribute('data-record') || '{}');
                    const keys = [rec.file_no, rec.mls_file_no, rec.source_temp_fileno].map(v => (v || '').toString().trim().toUpperCase());
                    if (!fileQuery || keys.includes(fileQuery)) {
                        target = rec;
                    }
                } catch (_) {}
            });

            if (!target && rows.length) {
                try { target = JSON.parse(rows[0].getAttribute('data-record') || '{}'); } catch (_) {}
            }
            if (!target) return;

            const openers = {
                'verification': openVerificationForOP,
                'acknowledgement': openAcknowledgementModal,
                'recommendation': openRecommendationModal,
                'printer-manager': openPrintManagerModal
            };

            const fn = openers[action];
            if (typeof fn !== 'function') return;

            setTimeout(function () { fn(target); }, 120);
        })();

        async function showTempFileDetailsByPropId(propId, sourceCaptureId, sourcePraId, tempFilenoLabel, mlsFilenoLabel, newPartyName) {
            if (!propId && !sourceCaptureId && !sourcePraId && !mlsFilenoLabel && !tempFilenoLabel) return;

            Swal.fire({
                title: 'Loading OP Details...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading(),
            });

            try {
                const esc = (v) => {
                    const s = String(v ?? '—');
                    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                };
                const normalizeText = (value, mode = 'general') => {
                    const raw = String(value ?? '').trim();
                    if (!raw) return '—';
                    const title = raw.toLowerCase().replace(/\b\w/g, ch => ch.toUpperCase());
                    if (mode === 'name' || mode === 'location') return title;
                    if (mode === 'land_use') {
                        const map = { RES:'Residential', RESIDENTIAL:'Residential', COM:'Commercial', COMMERCIAL:'Commercial', IND:'Industrial', INDUSTRIAL:'Industrial', AGR:'Agricultural', AGRICULTURAL:'Agricultural' };
                        return map[raw.toUpperCase()] || title;
                    }
                    return title.replace(/\bOp\b/g, 'OP').replace(/\bLga\b/g, 'LGA').replace(/\bTp\b/g, 'TP');
                };

                // Try to fetch all PRA transactions by prop_id
                let transactions = [];
                {
                    const qs = new URLSearchParams();
                    if (propId) qs.set('prop_id', String(propId));
                    if (mlsFilenoLabel && mlsFilenoLabel !== '—') qs.set('mls_fileno', String(mlsFilenoLabel));
                    if (tempFilenoLabel && tempFilenoLabel !== '—') qs.set('temp_fileno', String(tempFilenoLabel));
                    if (qs.toString()) {
                        const txRes = await fetch(`/lands-one-stop-shop/applications/op-resettlement/pra-transactions?${qs.toString()}`, {
                            headers: { 'Accept': 'application/json' }
                        });
                        const txBody = await txRes.json();
                        if (txRes.ok && txBody.success && Array.isArray(txBody.data)) {
                            transactions = txBody.data;
                        }
                    }
                }

                // Fallback: use old single-record endpoint if no PRA transactions found
                if (transactions.length === 0) {
                    const qs = new URLSearchParams();
                    if (propId) qs.set('prop_id', String(propId));
                    if (sourceCaptureId) qs.set('source_capture_id', String(sourceCaptureId));
                    if (sourcePraId) qs.set('pra_id', String(sourcePraId));
                    const res = await fetch(`/mls-fileno/temp-file-details?${qs.toString()}`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    const body = await res.json();
                    if (res.ok && body && body.success === true && body.data) {
                        transactions = [body.data];
                    }
                }

                if (transactions.length === 0) {
                    throw new Error('No transaction records found.');
                }

                // Sort: Occupancy Permit first, then Transfer of Title; within same type by date ascending
                transactions.sort((a, b) => {
                    const typeA = (a.instrument_type || '').toLowerCase();
                    const typeB = (b.instrument_type || '').toLowerCase();
                    const isTransferA = typeA.includes('transfer of title') ? 1 : 0;
                    const isTransferB = typeB.includes('transfer of title') ? 1 : 0;
                    if (isTransferA !== isTransferB) return isTransferA - isTransferB;
                    const dA = a.transaction_date || a.created_at || '';
                    const dB = b.transaction_date || b.created_at || '';
                    return String(dA).localeCompare(String(dB));
                });

                // Color palette for transaction cards
                const cardStyles = [
                    { border: 'border-sky-200', bg: 'bg-sky-50/60', accent: 'text-sky-700', accentBg: 'bg-sky-600', icon: 'text-sky-400' },
                    { border: 'border-emerald-200', bg: 'bg-emerald-50/60', accent: 'text-emerald-700', accentBg: 'bg-emerald-600', icon: 'text-emerald-400' },
                    { border: 'border-violet-200', bg: 'bg-violet-50/60', accent: 'text-violet-700', accentBg: 'bg-violet-600', icon: 'text-violet-400' },
                    { border: 'border-amber-200', bg: 'bg-amber-50/60', accent: 'text-amber-700', accentBg: 'bg-amber-600', icon: 'text-amber-400' },
                    { border: 'border-rose-200', bg: 'bg-rose-50/60', accent: 'text-rose-700', accentBg: 'bg-rose-600', icon: 'text-rose-400' },
                ];

                let cardsHtml = '';
                transactions.forEach((tx, idx) => {
                    const style = cardStyles[idx % cardStyles.length];
                    const num = idx + 1;
                    const instrType = esc(tx.instrument_type || tx.transaction_type || 'Transaction');
                    const txDate = tx.transaction_date ? new Date(tx.transaction_date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';

                    cardsHtml += `
                        <div class="rounded-lg border ${style.border} ${style.bg} p-3">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-md ${style.accentBg} text-white font-extrabold text-xs">${num}</span>
                                <span class="text-[11px] font-extrabold uppercase tracking-wide ${style.accent}">${instrType}</span>
                                <span class="ml-auto text-[9px] font-medium text-slate-400">${txDate}</span>
                            </div>
                            <div class="grid grid-cols-2 gap-x-3 gap-y-1.5">
                                <div class="flex items-start gap-1.5">
                                    <svg class="w-3 h-3 mt-0.5 ${style.icon} shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 9h6M9 13h4"/></svg>
                                    <div><div class="text-[9px] text-slate-400">File No</div><div class="font-mono font-bold text-slate-800 text-[11px]">${esc(tx.fileno || tx.mlsFNo || tx.temp_fileno)}</div></div>
                                </div>
                                <div class="flex items-start gap-1.5">
                                    <svg class="w-3 h-3 mt-0.5 ${style.icon} shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 9h6M9 13h4"/></svg>
                                    <div><div class="text-[9px] text-slate-400">Temp File No</div><div class="font-mono font-semibold text-slate-600 text-[11px]">${esc(tx.temp_fileno)}</div></div>
                                </div>
                                ${tx.op_type ? `
                                <div class="flex items-start gap-1.5">
                                    <svg class="w-3 h-3 mt-0.5 text-orange-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M7 7h10M7 12h10M7 17h5"/><rect x="2" y="2" width="20" height="20" rx="3"/></svg>
                                    <div><div class="text-[9px] text-slate-400">OP Type</div><div class="font-semibold text-orange-700 text-[11px]">${esc(normalizeText(tx.op_type))}</div></div>
                                </div>` : ''}
                                ${tx.op_serial_number ? `
                                <div class="flex items-start gap-1.5">
                                    <svg class="w-3 h-3 mt-0.5 ${style.icon} shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h8m-8 6h16"/></svg>
                                    <div><div class="text-[9px] text-slate-400">OP Serial No</div><div class="font-bold text-slate-800 text-[11px]">${esc(tx.op_serial_number)}</div></div>
                                </div>` : ''}
                                ${tx.registration_number ? `
                                <div class="flex items-start gap-1.5">
                                    <svg class="w-3 h-3 mt-0.5 ${style.icon} shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                    <div><div class="text-[9px] text-slate-400">Registration No</div><div class="font-semibold text-slate-800 text-[11px]">${esc(tx.registration_number)}</div></div>
                                </div>` : ''}
                                <div class="col-span-2 grid grid-cols-2 gap-x-3 gap-y-1.5">
                                    <div class="flex items-start gap-1.5">
                                        <svg class="w-3 h-3 mt-0.5 ${style.icon} shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                                        <div><div class="text-[9px] text-slate-400">Party 1</div><div class="font-semibold text-slate-800 text-[11px]">${esc(normalizeText(tx.party_1_name, 'name'))}</div></div>
                                    </div>
                                    <div class="flex items-start gap-1.5">
                                        <svg class="w-3 h-3 mt-0.5 ${style.icon} shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                                        <div><div class="text-[9px] text-slate-400">Party 2</div><div class="font-semibold text-slate-800 text-[11px]">${esc(normalizeText(tx.party_2_name, 'name'))}</div></div>
                                    </div>
                                </div>
                                ${tx.land_use ? `
                                <div class="flex items-start gap-1.5">
                                    <svg class="w-3 h-3 mt-0.5 ${style.icon} shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 7h4l3 9 4-16 3 7h4"/></svg>
                                    <div><div class="text-[9px] text-slate-400">Land Use</div><div class="font-semibold text-slate-800 text-[11px]">${esc(normalizeText(tx.land_use, 'land_use'))}</div></div>
                                </div>` : ''}
                                ${tx.tp_no ? `
                                <div class="flex items-start gap-1.5">
                                    <svg class="w-3 h-3 mt-0.5 ${style.icon} shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                                    <div><div class="text-[9px] text-slate-400">TP No</div><div class="font-semibold text-slate-800 text-[11px]">${esc(tx.tp_no)}</div></div>
                                </div>` : ''}
                                ${tx.plot_number ? `
                                <div class="flex items-start gap-1.5">
                                    <svg class="w-3 h-3 mt-0.5 ${style.icon} shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="15" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/></svg>
                                    <div><div class="text-[9px] text-slate-400">Plot No</div><div class="font-semibold text-slate-800 text-[11px]">${esc(tx.plot_number)}</div></div>
                                </div>` : ''}
                                ${tx.property_description || tx.location ? `
                                <div class="col-span-2 flex items-start gap-1.5">
                                    <svg class="w-3 h-3 mt-0.5 ${style.icon} shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0"/></svg>
                                    <div><div class="text-[9px] text-slate-400">Property</div><div class="font-semibold text-slate-800 text-[11px] leading-snug">${esc(normalizeText(tx.property_description || tx.location, 'location'))}</div></div>
                                </div>` : ''}
                            </div>
                        </div>
                    `;
                });

                const txCount = transactions.length;
                const propIdLabel = esc(propId || transactions[0]?.prop_id || '—');
                const titleTempFileNo = String(tempFilenoLabel || transactions[0]?.temp_fileno || '').trim();
                const titleMlsFileNo = String(mlsFilenoLabel || transactions[0]?.fileno || transactions[0]?.mlsFNo || '').trim();
                const titleFileNoParts = [titleTempFileNo, titleMlsFileNo].filter(Boolean);
                const modalTitle = titleFileNoParts.length
                    ? `Occupancy Permit (OP) Details (${titleFileNoParts.join('/')})`
                    : 'Occupancy Permit (OP) Details';
                const html = `
                    <div class="text-left text-xs font-sans" style="max-height:65vh; overflow-y:auto; padding-right:2px;">
                        <div class="mb-3 flex items-center gap-2">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-600">
                                Prop ID: ${propIdLabel}
                            </span>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-600">
                                ${txCount} Transaction${txCount !== 1 ? 's' : ''}
                            </span>
                        </div>
                        <div class="${txCount >= 3 ? 'grid grid-cols-3 gap-3' : txCount > 1 ? 'grid grid-cols-2 gap-3' : 'flex flex-col gap-3'}">
                            ${cardsHtml}
                        </div>
                    </div>
                `;

                Swal.fire({
                    icon: 'info',
                    title: modalTitle,
                    html,
                    width: txCount >= 3 ? 1060 : txCount > 1 ? 880 : 560,
                    confirmButtonText: 'Close',
                });
            } catch (err) {
                Swal.fire('Error', err.message || 'Unable to load OP details.', 'error');
            }
        }

        document.getElementById('op-resettlement-table')?.addEventListener('click', function (evt) {
            const trigger = evt.target.closest('.js-op-temp-file-link');
            if (!trigger) return;
            evt.preventDefault();
            evt.stopPropagation();
            const propId = trigger.getAttribute('data-prop-id') || '';
            const sourceCaptureId = trigger.getAttribute('data-source-capture-id') || '';
            const sourcePraId = trigger.getAttribute('data-source-pra-id') || '';
            const tempFileno = trigger.getAttribute('data-temp-fileno') || '';
            const mlsFileno = trigger.getAttribute('data-mls-fileno') || '';
            const newPartyName = trigger.getAttribute('data-new-party-name') || '';
            showTempFileDetailsByPropId(propId, sourceCaptureId, sourcePraId, tempFileno, mlsFileno, newPartyName);
        });
    });

    /**
     * Export the currently visible OSS table rows to a CSV file.
     * Covers the 14 data columns (excludes the Actions column).
     */
    function exportOssTableToCsv() {
        const dt = $('#op-resettlement-table').DataTable();
        const headers = ['S/N','Customer Type','Source','MLS File No','Temp FileNo','File Title','Land Use','TP No','Plot No','LGA','Location','Commissioned By','Time Commissioned','Date Commissioned','Full Commissioned Date'];
        const escape = (v) => '"' + String(v).replace(/"/g, '""') + '"';
        const norm = (v) => String(v == null ? '' : v).trim();

        const lines = [headers.map(escape).join(',')];
        dt.rows({ search: 'applied' }).every(function () {
            const tr = this.node();
            let rec = null;
            try { rec = JSON.parse(tr.getAttribute('data-record') || '{}'); } catch (_) { rec = {}; }

            const row = [
                norm(rec.sn),
                norm(rec.customer_type),
                norm(rec.source),
                norm(rec.mls_file_no),
                norm(rec.source_temp_fileno && rec.source_temp_fileno !== '—' ? rec.source_temp_fileno : ''),
                norm(rec.file_title),
                norm(rec.land_use),
                norm(rec.tp_no),
                norm(rec.plot_no),
                norm(rec.lga),
                norm(rec.location),
                norm(rec.commissioned_by),
                norm(rec.time_commissioned),
                norm(rec.date_commissioned),
                norm(rec.date_created),
            ].map(escape);
            lines.push(row.join(','));
        });

        const blob = new Blob([lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
        const url  = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href     = url;
        link.download = 'oss_applications_' + new Date().toISOString().slice(0, 10) + '.csv';
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    /**
     * Open a minimal printable version of the OSS table in a new window
     * so the user can save as PDF or print directly.
     */
    function printOssTable() {
        const dt = $('#op-resettlement-table').DataTable();
        const headers = ['S/N','Customer Type','Source','MLS File No','Temp FileNo','File Title','Land Use','TP No','Plot No','LGA','Location','Commissioned By','Time Commissioned','Date Commissioned','Full Commissioned Date'];
        const norm = (v) => String(v == null ? '' : v).trim();

        let tHead = '<tr>' + headers.map(h => `<th>${h}</th>`).join('') + '</tr>';
        let tBody = '';
        dt.rows({ search: 'applied' }).every(function () {
            const trNode = this.node();
            let rec = null;
            try { rec = JSON.parse(trNode.getAttribute('data-record') || '{}'); } catch (_) { rec = {}; }

            const cols = [
                norm(rec.sn),
                norm(rec.customer_type),
                norm(rec.source),
                norm(rec.mls_file_no),
                norm(rec.source_temp_fileno && rec.source_temp_fileno !== '—' ? rec.source_temp_fileno : ''),
                norm(rec.file_title),
                norm(rec.land_use),
                norm(rec.tp_no),
                norm(rec.plot_no),
                norm(rec.lga),
                norm(rec.location),
                norm(rec.commissioned_by),
                norm(rec.time_commissioned),
                norm(rec.date_commissioned),
                norm(rec.date_created)
            ];

            let tr = '<tr>';
            cols.forEach(function (c) { tr += `<td>${c}</td>`; });
            tr += '</tr>';
            tBody += tr;
        });

        const win = window.open('', '_blank');
        win.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8">
<title>OSS Applications Export</title>
<style>
    body{font-family:Arial,sans-serif;font-size:9px;margin:8px;}
  h2{font-size:14px;margin-bottom:6px;}
    table{border-collapse:collapse;width:100%;table-layout:fixed;}
    th,td{border:1px solid #ccc;padding:3px 4px;text-align:left;white-space:normal;word-break:break-word;vertical-align:top;}
  th{background:#f0f0f0;font-weight:bold;}
  tr:nth-child(even){background:#fafafa;}
    @page{size:A4 landscape;margin:8mm;}
</style></head><body>
<h2>OSS Applications (Occupancy Permit) &mdash; ${new Date().toLocaleDateString('en-GB')}</h2>
<p style="font-size:10px;color:#666;margin-bottom:8px;">Exported ${tBody.split('</tr>').length - 1} record(s)</p>
<table><thead>${tHead}</thead><tbody>${tBody}</tbody></table>
<script>window.onload=function(){window.print();}<\/script>
</body></html>`);
        win.document.close();
    }

    /**
     * Open the Commission New File Number modal for OP page flow.
        * Uses OP page defaults: Occupancy Permit (OP) flow.
     */
    function openCommissionModalForOP(config = {}) {
        var mode = (config.mode || 'existing').toString().toLowerCase();
        var preselectedFileNo = (config.preselectedFileNo || '').toString().trim();

        // Open the modal via the global function provided by commission-fileno-modal-include
        if (typeof window.openCommissionFileNoModal === 'function') {
            window.openCommissionFileNoModal();
        } else {
            // Fallback: show the modal directly
            var modal = document.getElementById('generateModal');
            if (modal) modal.classList.remove('hidden');
        }

        // Give Alpine a tick to initialise, then apply desired defaults.
        setTimeout(function () {
            // In Lands One Stop Shop (Applications - No Change of Name),
            // hide Conversion and Allocation List options in this modal.
            var conversionOption = document.querySelector('#generateModal .commission-conversion-option');
            if (conversionOption) {
                conversionOption.style.display = 'none';
            }

            var allocationListRadio = document.querySelector('#generateModal input[name="allocated_by_filter"][value="Allocation List"]');
            if (allocationListRadio) {
                var allocationListOption = allocationListRadio.closest('label');
                if (allocationListOption) {
                    allocationListOption.style.display = 'none';
                }
            }

            var applicationType = mode === 'new' ? 'existing' : 'new';
            var directRadio = document.querySelector('#generateModal input[name="application_type"][value="' + applicationType + '"]');
            if (directRadio) {
                directRadio.checked = true;
                directRadio.dispatchEvent(new Event('change', { bubbles: true }));
                directRadio.dispatchEvent(new Event('input', { bubbles: true }));
            }

            var defaultSourceRadio = document.querySelector('#generateModal input[name="allocated_by_filter"][value=""]');
            if (defaultSourceRadio) {
                // OP page request: do not pre-check this source option.
                defaultSourceRadio.checked = false;

                var defaultSourceLabel = defaultSourceRadio.closest('label');
                if (defaultSourceLabel) {
                    var sourceText = defaultSourceLabel.querySelector('span.ml-2');
                    if (sourceText) {
                        sourceText.textContent = 'Occupancy Permit (OP)';
                    }
                }
            }

            var modalContainer = document.querySelector('#generateModal [x-data="fileNumberGenerator()"]');
            if (modalContainer && modalContainer._x_dataStack && modalContainer._x_dataStack[0]) {
                var component = modalContainer._x_dataStack[0];
                component.applicationType = applicationType;
                component.allocatedByFilter = null;
                // Keep unselected so OP capture is triggered only by user click
                // on Direct Allocation or Resettlement radios.
                component.defaultAllocationType = '';
                component.requireOpSource = true;
                component.subSource = {};
                if (mode === 'new' && preselectedFileNo) {
                    component.existingFileNo = preselectedFileNo;
                }
                if (typeof component.updatePreview === 'function') {
                    component.updatePreview();
                }
            }

            // Reinitialize icons
            if (window.lucide) window.lucide.createIcons();
        }, 200);
    }

    function launchChangeOfNameMode(mode) {
        var normalizedMode = (mode || '').toString().toLowerCase();
        if (normalizedMode === 'existing') {
            openCommissionModalForOP({ mode: 'existing' });
            return;
        }

        if (normalizedMode !== 'new') {
            return;
        }

        if (typeof window.GlobalFileNoModal === 'undefined' || typeof window.GlobalFileNoModal.open !== 'function') {
            Swal.fire({
                icon: 'error',
                title: 'Capture Existing Unavailable',
                text: 'Global file selector is not loaded on this page.'
            });
            return;
        }

        window.GlobalFileNoModal.open({
            callback: function (fileData) {
                var selectedFileNo = (fileData && fileData.fileNumber ? fileData.fileNumber : '').toString().trim().replace(/-+$/, '');
                if (!selectedFileNo) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'File Number Required',
                        text: 'Please select a valid existing file number to continue.'
                    });
                    return;
                }

                openCommissionModalForOP({
                    mode: 'new',
                    preselectedFileNo: selectedFileNo
                });
            }
        });
    }

    /* ═══════════════════════════════════════════════════════════════════
       Acknowledgement Modal – open / close / print / save
       ═══════════════════════════════════════════════════════════════════ */
    function _getRecordFromButton(btn) {
        if (!btn) return null;
        // Accept a plain data object passed directly (e.g. from Print Manager)
        if (typeof btn === 'object' && typeof btn.closest !== 'function') {
            return btn;
        }
        var tr = btn.closest('tr');
        if (!tr) return null;
        try { return JSON.parse(tr.getAttribute('data-record')); } catch(e) { return null; }
    }

    function openPrintManagerModal(btn) {
        var data = _getRecordFromButton(btn);
        if (!data) {
            Swal.fire({ icon: 'warning', title: 'Missing Data', text: 'Could not read record details.' });
            return;
        }

        var ref = (data.file_no || data.mls_file_no || data.mlsfNo || '—').toString().trim();
        window.dispatchEvent(new CustomEvent('open-print-manager', {
            detail: {
                ref: ref,
                type: 'OSS OP',
                url: '',
                module: 'oss',
                record: data,
                hideCommissioningSheet: ""
            }
        }));
    }

    function openLand12ForOP(btn) {
        var data = _getRecordFromButton(btn) || {};
        var fileNo = (data.file_no || data.mls_file_no || data.mlsfNo || '').toString().trim();
        var fileTitle = (data.file_title || data.party_2_name || data.FileName || '').toString().trim();
        if (fileTitle === '—') fileTitle = '';
        var baseUrl = '""?url=lands';
        var targetUrl = fileNo ? (baseUrl + '&file_no=' + encodeURIComponent(fileNo)) : baseUrl;
        if (fileTitle) targetUrl += '&file_title=' + encodeURIComponent(fileTitle);
        if (window.openTailwindModal) {
            openTailwindModal(targetUrl, 'xl');
        } else {
            window.open(targetUrl, '_blank');
        }
    }

    // Fallback helper for address-builder based modals (Verification / Change of Name)
    // Some actions call this directly from this page script.
    function _ossClearSingleAddressBuilder(prefix, outputId) {
        ['_plot', '_street', '_street_other', '_district', '_district_other', '_lga', '_state'].forEach(function (suffix) {
            var el = document.getElementById(prefix + suffix);
            if (!el) return;
            if (el.tagName === 'SELECT') {
                if (suffix === '_state') {
                    var selectedKano = false;
                    for (var i = 0; i < el.options.length; i++) {
                        if (el.options[i].value === 'Kano') {
                            el.selectedIndex = i;
                            selectedKano = true;
                            break;
                        }
                    }
                    if (!selectedKano) el.selectedIndex = 0;
                } else {
                    el.selectedIndex = 0;
                }
            } else {
                el.value = '';
            }
        });

        var dashPrefix = prefix.replace(/_/g, '-');
        var streetWrapper = document.getElementById(dashPrefix + '-street-other-wrapper');
        var districtWrapper = document.getElementById(dashPrefix + '-district-other-wrapper');
        if (streetWrapper) streetWrapper.classList.add('hidden');
        if (districtWrapper) districtWrapper.classList.add('hidden');

        var outputEl = document.getElementById(outputId);
        if (outputEl) outputEl.value = '';
    }

    function _opSafeInputValue(value) {
        if (value === null || value === undefined || value === '—') return '';
        return String(value);
    }

    function _opNormalizeCustomerType(value) {
        var raw = _opSafeInputValue(value).toString().trim().toLowerCase();
        if (raw === 'corporate') return 'Corporate';
        if (raw === 'multiple' || raw === 'multiple owners') return 'Multiple';
        return 'Individual';
    }

    function _opSetLandUseSelect(value) {
        var select = document.getElementById('opEditLandUse');
        if (!select) return;

        var raw = _opSafeInputValue(value).toString().trim().toUpperCase();
        if (!raw) {
            select.value = '';
            return;
        }

        var map = {
            RESIDENTIAL: 'RES',
            COMMERCIAL: 'COM',
            INDUSTRIAL: 'IND',
            AGRICULTURAL: 'AGR'
        };
        var normalized = map[raw] || raw;

        var hasOption = Array.from(select.options).some(function (opt) {
            return String(opt.value || '').toUpperCase() === normalized;
        });

        if (!hasOption) {
            var opt = document.createElement('option');
            opt.value = normalized;
            opt.textContent = normalized;
            select.appendChild(opt);
        }

        select.value = normalized;
    }

    function _opSetSelectByText(selectId, value) {
        var select = document.getElementById(selectId);
        if (!select) return;
        var raw = _opSafeInputValue(value).toString().trim().toUpperCase();
        if (!raw) { select.value = ''; return; }
        for (var i = 0; i < select.options.length; i++) {
            if (select.options[i].value.toUpperCase() === raw || select.options[i].textContent.toUpperCase() === raw) {
                select.selectedIndex = i;
                return;
            }
        }
        select.value = '';
    }

    function _opSetSelectWithFallback(selectId, value) {
        var select = document.getElementById(selectId);
        if (!select) return;

        var normalized = _opSafeInputValue(value).toString().trim();
        if (!normalized) {
            select.value = '';
            return;
        }

        var existing = Array.from(select.options).find(function (opt) {
            return opt.value.toUpperCase() === normalized.toUpperCase() || opt.textContent.toUpperCase() === normalized.toUpperCase();
        });

        if (!existing) {
            var opt = document.createElement('option');
            opt.value = normalized.toUpperCase();
            opt.textContent = normalized.toUpperCase();
            select.appendChild(opt);
            existing = opt;
        }

        select.value = existing.value;
    }

    // Store fetched transactions so card-click can populate edit fields
    var _opEditTransactions = [];
    var _opEditSelectedPraId = null;

    function _opPickLatestFilled(txs, getter) {
        for (var i = txs.length - 1; i >= 0; i--) {
            var v = getter(txs[i]);
            if (v !== null && v !== undefined && String(v).trim() !== '') {
                return String(v).trim();
            }
        }
        return '';
    }

    /**
     * Render a single transaction card (matches OP Details card style).
     */
    function _opRenderTxCard(tx, idx, style) {
        var esc = function (v) {
            var s = String(v == null ? '—' : v);
            return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        };
        var norm = function (v, mode) {
            var raw = String(v == null ? '' : v).trim();
            if (!raw) return '—';
            var title = raw.toLowerCase().replace(/\b\w/g, function (ch) { return ch.toUpperCase(); });
            if (mode === 'land_use') {
                var map = { RES:'Residential', RESIDENTIAL:'Residential', COM:'Commercial', COMMERCIAL:'Commercial', IND:'Industrial', INDUSTRIAL:'Industrial', AGR:'Agricultural', AGRICULTURAL:'Agricultural' };
                return map[raw.toUpperCase()] || title;
            }
            if (mode === 'name' || mode === 'location') return title;
            return title.replace(/\bOp\b/g,'OP').replace(/\bLga\b/g,'LGA').replace(/\bTp\b/g,'TP');
        };
        var num = idx + 1;
        var instrType = esc(tx.instrument_type || tx.transaction_type || 'Transaction');
        var txDate = tx.transaction_date ? new Date(tx.transaction_date).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}) : '';

        var html = '<div class="rounded-lg border ' + style.border + ' ' + style.bg + ' p-3 cursor-pointer hover:shadow-md transition-shadow op-edit-tx-card" data-tx-idx="' + idx + '">';
        html += '<div class="flex items-center gap-2 mb-2">';
        html += '<span class="inline-flex items-center justify-center w-6 h-6 rounded-md ' + style.accentBg + ' text-white font-extrabold text-xs">' + num + '</span>';
        html += '<span class="text-[11px] font-extrabold uppercase tracking-wide ' + style.accent + '">' + instrType + '</span>';
        if (txDate) html += '<span class="ml-auto text-[9px] font-medium text-slate-400">' + txDate + '</span>';
        html += '</div>';
        html += '<div class="grid grid-cols-2 gap-x-3 gap-y-1.5">';

        // File No
        html += '<div class="flex items-start gap-1.5"><svg class="w-3 h-3 mt-0.5 ' + style.icon + ' shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 9h6M9 13h4"/></svg><div><div class="text-[9px] text-slate-400">File No</div><div class="font-mono font-bold text-slate-800 text-[11px]">' + esc(tx.fileno || tx.mlsFNo || tx.temp_fileno) + '</div></div></div>';

        // Temp File No
        html += '<div class="flex items-start gap-1.5"><svg class="w-3 h-3 mt-0.5 ' + style.icon + ' shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 9h6M9 13h4"/></svg><div><div class="text-[9px] text-slate-400">Temp File No</div><div class="font-mono font-semibold text-slate-600 text-[11px]">' + esc(tx.temp_fileno) + '</div></div></div>';

        // OP Type
        if (tx.op_type) {
            html += '<div class="flex items-start gap-1.5"><svg class="w-3 h-3 mt-0.5 text-orange-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M7 7h10M7 12h10M7 17h5"/><rect x="2" y="2" width="20" height="20" rx="3"/></svg><div><div class="text-[9px] text-slate-400">OP Type</div><div class="font-semibold text-orange-700 text-[11px]">' + esc(norm(tx.op_type)) + '</div></div></div>';
        }

        // OP Serial No
        if (tx.op_serial_number) {
            html += '<div class="flex items-start gap-1.5"><svg class="w-3 h-3 mt-0.5 ' + style.icon + ' shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h8m-8 6h16"/></svg><div><div class="text-[9px] text-slate-400">OP Serial No</div><div class="font-bold text-slate-800 text-[11px]">' + esc(tx.op_serial_number) + '</div></div></div>';
        }

        // Registration No
        if (tx.registration_number) {
            html += '<div class="flex items-start gap-1.5"><svg class="w-3 h-3 mt-0.5 ' + style.icon + ' shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg><div><div class="text-[9px] text-slate-400">Registration No</div><div class="font-semibold text-slate-800 text-[11px]">' + esc(tx.registration_number) + '</div></div></div>';
        }

        // Party 1 & Party 2
        html += '<div class="col-span-2 grid grid-cols-2 gap-x-3 gap-y-1.5">';
        html += '<div class="flex items-start gap-1.5"><svg class="w-3 h-3 mt-0.5 ' + style.icon + ' shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg><div><div class="text-[9px] text-slate-400">Party 1</div><div class="font-semibold text-slate-800 text-[11px]">' + esc(norm(tx.party_1_name,'name')) + '</div></div></div>';
        html += '<div class="flex items-start gap-1.5"><svg class="w-3 h-3 mt-0.5 ' + style.icon + ' shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg><div><div class="text-[9px] text-slate-400">Party 2</div><div class="font-semibold text-slate-800 text-[11px]">' + esc(norm(tx.party_2_name,'name')) + '</div></div></div>';
        html += '</div>';

        // Land Use
        if (tx.land_use) {
            html += '<div class="flex items-start gap-1.5"><svg class="w-3 h-3 mt-0.5 ' + style.icon + ' shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 7h4l3 9 4-16 3 7h4"/></svg><div><div class="text-[9px] text-slate-400">Land Use</div><div class="font-semibold text-slate-800 text-[11px]">' + esc(norm(tx.land_use,'land_use')) + '</div></div></div>';
        }

        // TP No
        if (tx.tp_no) {
            html += '<div class="flex items-start gap-1.5"><svg class="w-3 h-3 mt-0.5 ' + style.icon + ' shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg><div><div class="text-[9px] text-slate-400">TP No</div><div class="font-semibold text-slate-800 text-[11px]">' + esc(tx.tp_no) + '</div></div></div>';
        }

        // Plot No
        if (tx.plot_number) {
            html += '<div class="flex items-start gap-1.5"><svg class="w-3 h-3 mt-0.5 ' + style.icon + ' shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="15" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/></svg><div><div class="text-[9px] text-slate-400">Plot No</div><div class="font-semibold text-slate-800 text-[11px]">' + esc(tx.plot_number) + '</div></div></div>';
        }

        // Property / Location
        var propDesc = tx.property_description || tx.location;
        if (propDesc) {
            html += '<div class="col-span-2 flex items-start gap-1.5"><svg class="w-3 h-3 mt-0.5 ' + style.icon + ' shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0"/></svg><div><div class="text-[9px] text-slate-400">Property</div><div class="font-semibold text-slate-800 text-[11px] leading-snug">' + esc(norm(propDesc,'location')) + '</div></div></div>';
        }

        html += '</div></div>';
        return html;
    }

    /**
     * Populate the edit fields from a given transaction object.
     */
    function _opLoadTxIntoEditFields(tx, idx) {
        if (!tx) return;
        // Track which PRA row is selected so save targets this exact row
        _opEditSelectedPraId = tx.id || null;
        var badge = document.getElementById('opEditSelectedTxBadge');
        if (badge) {
            badge.textContent = 'From Transaction ' + (idx + 1);
            badge.classList.remove('hidden');
        }
        var instrType = (tx.instrument_type || tx.transaction_type || '').toString().trim();
        if (instrType) _opSetSelectWithFallback('opEditInstrumentType', instrType);
        var p1 = (tx.party_1_name || '').toString().trim();
        var p2 = (tx.party_2_name || '').toString().trim();
        if (p1) document.getElementById('opEditParty1Name').value = p1.toUpperCase();
        if (p2) document.getElementById('opEditParty2Name').value = p2.toUpperCase();
        var plot = (tx.plot_number || '').toString().trim();
        var tp = (tx.tp_no || '').toString().trim();
        var loc = (tx.location || tx.property_description || '').toString().trim();
        var lga = (tx.lga || '').toString().trim();
        if (plot) document.getElementById('opEditPlotNo').value = plot.toUpperCase();
        if (tp) document.getElementById('opEditTpNo').value = tp.toUpperCase();
        if (loc) document.getElementById('opEditLocation').value = loc.toUpperCase();
        if (lga) _opSetSelectByText('opEditLga', lga);
    }

    function _opApplyPraTransactionDetailsToEdit(data) {
        if (!data) return;
        var container = document.getElementById('opEditTxCardsContainer');

        var params = new URLSearchParams();
        var propId = _opSafeInputValue(data.source_prop_id);
        var mlsFileno = _opSafeInputValue(data.mls_file_no || data.file_no);
        var tempFileno = _opSafeInputValue(data.source_temp_fileno);

        if (propId) params.set('prop_id', propId);
        if (mlsFileno) params.set('mls_fileno', mlsFileno);
        if (tempFileno && tempFileno !== '—') params.set('temp_fileno', tempFileno);

        if (!params.toString()) {
            if (container) container.innerHTML = '<div class="text-center text-xs text-slate-400 py-2">No transaction identifiers available.</div>';
            return;
        }

        if (container) container.innerHTML = '<div class="flex items-center justify-center py-3"><span class="text-xs text-slate-400">Loading transactions...</span></div>';

        fetch('/lands-one-stop-shop/applications/op-resettlement/pra-transactions?' + params.toString(), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function (response) {
            return response.json().then(function (body) {
                return { ok: response.ok, body: body };
            });
        })
        .then(function (result) {
            if (!result.ok || !result.body || !result.body.success || !Array.isArray(result.body.data)) {
                _opEditTransactions = [];
                if (container) container.innerHTML = '<div class="text-center text-xs text-slate-400 py-2">No transactions found.</div>';
                return;
            }

            var txs = result.body.data;
            _opEditTransactions = txs;
            if (!txs.length) {
                if (container) container.innerHTML = '<div class="text-center text-xs text-slate-400 py-2">No transactions found.</div>';
                return;
            }

            // Card colour palette (same as OP Details modal)
            var cardStyles = [
                { border:'border-sky-200', bg:'bg-sky-50/60', accent:'text-sky-700', accentBg:'bg-sky-600', icon:'text-sky-400' },
                { border:'border-emerald-200', bg:'bg-emerald-50/60', accent:'text-emerald-700', accentBg:'bg-emerald-600', icon:'text-emerald-400' },
                { border:'border-violet-200', bg:'bg-violet-50/60', accent:'text-violet-700', accentBg:'bg-violet-600', icon:'text-violet-400' },
                { border:'border-amber-200', bg:'bg-amber-50/60', accent:'text-amber-700', accentBg:'bg-amber-600', icon:'text-amber-400' },
                { border:'border-rose-200', bg:'bg-rose-50/60', accent:'text-rose-700', accentBg:'bg-rose-600', icon:'text-rose-400' }
            ];

            // Render transaction cards
            var cardsHtml = '<div class="mb-2 flex items-center gap-2">';
            cardsHtml += '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-600">' + txs.length + ' Transaction' + (txs.length !== 1 ? 's' : '') + '</span>';
            cardsHtml += '<span class="text-[9px] text-slate-400">Click a card to load into edit fields</span>';
            cardsHtml += '</div>';
            cardsHtml += '<div class="' + (txs.length >= 3 ? 'grid grid-cols-3 gap-2' : txs.length > 1 ? 'grid grid-cols-2 gap-2' : 'flex flex-col gap-2') + '">';
            txs.forEach(function (tx, idx) {
                cardsHtml += _opRenderTxCard(tx, idx, cardStyles[idx % cardStyles.length]);
            });
            cardsHtml += '</div>';

            if (container) {
                container.innerHTML = cardsHtml;
                // Attach click handlers to cards
                var cards = container.querySelectorAll('.op-edit-tx-card');
                cards.forEach(function (card) {
                    card.addEventListener('click', function () {
                        var txIdx = parseInt(card.getAttribute('data-tx-idx'), 10);
                        if (!isNaN(txIdx) && _opEditTransactions[txIdx]) {
                            _opLoadTxIntoEditFields(_opEditTransactions[txIdx], txIdx);
                            // Highlight selected card
                            cards.forEach(function (c) { c.classList.remove('ring-2','ring-blue-400'); });
                            card.classList.add('ring-2','ring-blue-400');
                        }
                    });
                });
            }

            // Auto-expand party section so cards are visible
            toggleOpEditPartySection(true);

            // Auto-populate edit fields from the latest transaction
            var lastIdx = txs.length - 1;
            _opLoadTxIntoEditFields(txs[lastIdx], lastIdx);
            // Highlight the latest card
            if (container) {
                var lastCard = container.querySelector('.op-edit-tx-card[data-tx-idx="' + lastIdx + '"]');
                if (lastCard) lastCard.classList.add('ring-2','ring-blue-400');
            }
        })
        .catch(function () {
            _opEditTransactions = [];
            if (container) container.innerHTML = '<div class="text-center text-xs text-slate-400 py-2">Could not load transactions.</div>';
        });
    }

    function toggleOpEditPartySection(forceOpen) {
        var section = document.getElementById('opEditPartySection');
        var chevron = document.getElementById('opEditPartyChevron');
        if (!section) return;

        var shouldOpen = typeof forceOpen === 'boolean' ? forceOpen : section.classList.contains('hidden');
        if (shouldOpen) {
            section.classList.remove('hidden');
            if (chevron) chevron.style.transform = 'rotate(180deg)';
        } else {
            section.classList.add('hidden');
            if (chevron) chevron.style.transform = 'rotate(0deg)';
        }
    }

    function openOpEditModal(btn) {
        var data = _getRecordFromButton(btn);
        if (!data) {
            Swal.fire('Error', 'Could not read record details.', 'error');
            return;
        }

        document.getElementById('opEditRecordId').value = data.id || '';
        document.getElementById('opEditMlsfNo').value = _opSafeInputValue(data.mls_file_no || data.file_no);
        document.getElementById('opEditCustomerType').value = _opNormalizeCustomerType(data.customer_type);
        _opSetLandUseSelect(data.land_use);
        // Purpose is a dynamic select; load options then select the saved value
        var _opEditLuSel = document.getElementById('opEditLandUse');
        _opEditLoadPurposesAndSelect(_opEditLuSel, _opSafeInputValue(data.purpose));
        document.getElementById('opEditFileName').value = _opSafeInputValue(data.file_title || data.party_2);
        document.getElementById('opEditPlotNo').value = _opSafeInputValue(data.plot_no);
        document.getElementById('opEditTpNo').value = _opSafeInputValue(data.tp_no || data.plan_no);
        document.getElementById('opEditLocation').value = _opSafeInputValue(data.location || data.property_description);
        _opSetSelectByText('opEditLga', data.lga);
        _opSetSelectByText('opEditDistrict', data.district);
        document.getElementById('opEditApplicantPhone').value = _opSafeInputValue(data.applicant_phone);
        document.getElementById('opEditApplicantAddress').value = _opSafeInputValue(data.applicant_address);
        document.getElementById('opEditParty1Name').value = _opSafeInputValue(data.party_1_name);
        document.getElementById('opEditParty2Name').value = _opSafeInputValue(data.party_2_name || data.file_title || data.party_2);
        document.getElementById('opEditOpSerialNumber').value = _opSafeInputValue(data.op_serial_number);
        _opSetSelectWithFallback('opEditInstrumentType', data.source);
        document.getElementById('opEditParty1Phone').value = _opSafeInputValue(data.party_1_phone);
        document.getElementById('opEditParty2Phone').value = _opSafeInputValue(data.party_2_phone || data.applicant_phone);
        document.getElementById('opEditParty1Address').value = _opSafeInputValue(data.party_1_address);
        document.getElementById('opEditParty2Address').value = _opSafeInputValue(data.party_2_address || data.applicant_address);

        toggleOpEditPartySection(false);

        document.getElementById('opEditModal').classList.remove('hidden');
        if (window.lucide) window.lucide.createIcons();

        // Reset PRA selection before loading transactions
        _opEditSelectedPraId = null;
        _opEditTransactions = [];

        // Pull full transaction history mapping (same source used by OP Details modal)
        // so parties/instrument values reflect the latest valid transaction data.
        _opApplyPraTransactionDetailsToEdit(data);
    }

    function closeOpEditModal() {
        var modal = document.getElementById('opEditModal');
        if (modal) modal.classList.add('hidden');

        // Clear all user-entered data so re-opening starts fresh
        var fieldsToClear = [
            'opEditRecordId', 'opEditMlsfNo', 'opEditFileName',
            'opEditPlotNo', 'opEditTpNo', 'opEditLocation',
            'opEditApplicantPhone', 'opEditApplicantAddress',
            'opEditParty1Name', 'opEditParty2Name',
            'opEditParty1Phone', 'opEditParty2Phone',
            'opEditParty1Address', 'opEditParty2Address',
            'opEditOpSerialNumber'
        ];
        fieldsToClear.forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.value = '';
        });
        // Reset selects to first option
        ['opEditCustomerType', 'opEditLandUse', 'opEditPurpose',
         'opEditLga', 'opEditDistrict', 'opEditInstrumentType'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.selectedIndex = 0;
        });
        // Collapse party section
        var partySection = document.getElementById('opEditPartySection');
        if (partySection) partySection.classList.add('hidden');
        // Clear transaction cards
        var txCards = document.getElementById('opEditTxCardsContainer');
        if (txCards) txCards.innerHTML = '<div class="flex items-center justify-center py-3"><span class="text-xs text-slate-400">Loading transactions...</span></div>';
        // Clear selected tx badge
        var txBadge = document.getElementById('opEditSelectedTxBadge');
        if (txBadge) { txBadge.classList.add('hidden'); txBadge.textContent = ''; }
    }

    /**
     * Load purpose options for the edit modal based on the selected land use,
     * then optionally pre-select a saved purpose value.
     */
    function _opEditLoadPurposesAndSelect(selectEl, savedPurpose) {
        var purposeEl = document.getElementById('opEditPurpose');
        if (!purposeEl || !selectEl) return;
        var selectedOpt = selectEl.options[selectEl.selectedIndex];
        var landUseId = selectedOpt ? selectedOpt.getAttribute('data-id') : '';
        purposeEl.innerHTML = '<option value="">Select purpose</option>';
        if (!landUseId) return;
        fetch('/mls-fileno/get-dependent-data?land_use_id=' + encodeURIComponent(landUseId))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                (data.purposes || []).forEach(function (p) {
                    var opt = document.createElement('option');
                    opt.value = p.name;
                    opt.textContent = p.name;
                    purposeEl.appendChild(opt);
                });
                if (savedPurpose) {
                    for (var i = 0; i < purposeEl.options.length; i++) {
                        if (purposeEl.options[i].value.toUpperCase() === savedPurpose.toUpperCase() ||
                            purposeEl.options[i].textContent.toUpperCase() === savedPurpose.toUpperCase()) {
                            purposeEl.selectedIndex = i;
                            break;
                        }
                    }
                }
            })
            .catch(function () { /* silent fail - user can pick purpose manually */ });
    }

    function saveOpEditModal() {
        var recordId = (document.getElementById('opEditRecordId').value || '').toString().trim();
        var mlsfNo = (document.getElementById('opEditMlsfNo').value || '').toString().trim();
        var fileName = (document.getElementById('opEditFileName').value || '').toString().trim();
        var partySectionEl = document.getElementById('opEditPartySection');
        var isPartySectionHidden = partySectionEl ? partySectionEl.classList.contains('hidden') : true;
        var party2Name = (document.getElementById('opEditParty2Name').value || '').toString().trim();

        if (!recordId) {
            Swal.fire('Error', 'Missing record id.', 'error');
            return;
        }

        if (!mlsfNo) {
            Swal.fire('Required', 'MLSF Number is required.', 'warning');
            return;
        }

        if (!fileName) {
            Swal.fire('Required', 'File Name is required.', 'warning');
            return;
        }

        var customerType = _opNormalizeCustomerType(document.getElementById('opEditCustomerType').value);

        // Keep Grantee aligned with File Name only when party 2 is empty.
        // This avoids overwriting correctly mapped transaction party details.
        if (isPartySectionHidden && !party2Name) {
            party2Name = fileName;
        }

        var saveBtn = document.getElementById('opEditSaveBtn');
        var originalLabel = saveBtn ? saveBtn.innerHTML : '';
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';
        }

        var payload = {
            customer_type: customerType,
            land_use: (document.getElementById('opEditLandUse').value || '').toString().trim() || null,
            purpose: (document.getElementById('opEditPurpose').value || '').toString().trim() || null,
            file_name: fileName,
            plot_number: (document.getElementById('opEditPlotNo').value || '').toString().trim() || null,
            tp_number: (document.getElementById('opEditTpNo').value || '').toString().trim() || null,
            location: (document.getElementById('opEditLocation').value || '').toString().trim() || null,
            lga: (document.getElementById('opEditLga').value || '').toString().trim() || null,
            district: (document.getElementById('opEditDistrict').value || '').toString().trim() || null,
            instrument_type: (document.getElementById('opEditInstrumentType').value || '').toString().trim() || null,
            party_1_name: (document.getElementById('opEditParty1Name').value || '').toString().trim() || null,
            party_1_phone: (document.getElementById('opEditParty1Phone').value || '').toString().trim() || null,
            party_1_address: (document.getElementById('opEditParty1Address').value || '').toString().trim() || null,
            party_2_name: party2Name || null,
            party_2_phone: (document.getElementById('opEditParty2Phone').value || '').toString().trim() || null,
            party_2_address: (document.getElementById('opEditParty2Address').value || '').toString().trim() || null,
            applicant_phone: (document.getElementById('opEditApplicantPhone').value || '').toString().trim() || null,
            applicant_address: (document.getElementById('opEditApplicantAddress').value || '').toString().trim() || null,
            op_serial_number: (document.getElementById('opEditOpSerialNumber').value || '').toString().trim() || null,
            pra_id: _opEditSelectedPraId || null,
        };

        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        fetch('/lands-one-stop-shop/applications/op-resettlement/' + encodeURIComponent(recordId) + '/update-details', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(payload)
        })
        .then(function (response) {
            return response.json().then(function (body) {
                return { ok: response.ok, body: body };
            });
        })
        .then(function (result) {
            if (!result.ok || !result.body || !result.body.success) {
                var errMsg = (result.body && result.body.message) ? result.body.message : 'Unable to update record.';
                if (result.body && result.body.error) errMsg += '\n\n' + result.body.error;
                throw new Error(errMsg);
            }

            Swal.fire({
                icon: 'success',
                title: 'Updated',
                text: result.body.message || 'Record updated successfully.',
                timer: 1800,
                showConfirmButton: false
            });

            closeOpEditModal();
            setTimeout(function () {
                window.location.reload();
            }, 700);
        })
        .catch(function (error) {
            Swal.fire('Error', error.message || 'Failed to update record.', 'error');
        })
        .finally(function () {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = originalLabel;
                if (window.lucide) window.lucide.createIcons();
            }
        });
    }


    var ffrState = {
        sourceFileNo: '',
        sourceRecord: null,
        opRecord: null,
        captureExistingFromNotFound: false,
        existingOpType: 'OP Resettlement'
    };
    var ffrLookupRequestId = 0;

    function ffrToast(icon, title) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: icon,
            title: title,
            showConfirmButton: false,
            timer: 2600,
            timerProgressBar: true
        });
    }

    function ffrShowModes(showNew, showExisting, showDirectOp) {
        var modeBlock = document.getElementById('ffrModeBlock');
        var newBtn = document.getElementById('ffrBtnModeNew');
        var existingCard = document.getElementById('ffrExistingCard');
        var directOpCard = document.getElementById('ffrDirectOpCard');

        if (!modeBlock || !newBtn || !existingCard) return;

        // Direct OP card lives outside modeBlock, toggle independently
        if (directOpCard) {
            if (showDirectOp) {
                directOpCard.classList.remove('hidden');
            } else {
                directOpCard.classList.add('hidden');
            }
        }

        if (!showNew && !showExisting) {
            modeBlock.classList.add('hidden');
            newBtn.classList.add('hidden');
            existingCard.classList.add('hidden');
            return;
        }

        modeBlock.classList.remove('hidden');
        if (showNew) {
            newBtn.classList.remove('hidden');
        } else {
            newBtn.classList.add('hidden');
        }

        if (showExisting) {
            existingCard.classList.remove('hidden');
        } else {
            existingCard.classList.add('hidden');
        }
    }

    function openFfrModal() {
        document.getElementById('ffrModal').classList.remove('hidden');
        if (window.lucide) window.lucide.createIcons();
    }

    function closeFfrModal() {
        document.getElementById('ffrModal').classList.add('hidden');
        ffrResetState();
    }

    // ──────────────── Match OP ────────────────

    function openMatchOpModal(prefilledFileNo) {
        document.getElementById('matchOpModal').classList.remove('hidden');
        var fileNoInput = document.getElementById('matchOpFileNo');
        fileNoInput.value = prefilledFileNo || '';
        fileNoInput.disabled = true;
        document.getElementById('matchOpStatusMsg').textContent = prefilledFileNo ? 'Loading preview…' : 'Enter a file number and click Preview.';
        document.getElementById('matchOpStatusMsg').className = 'mt-1.5 text-xs text-slate-500';
        document.getElementById('matchOpPreviewPanel').classList.add('hidden');
        document.getElementById('matchOpMultipleWarning').classList.add('hidden');
        document.getElementById('matchOpConfirmBtn').disabled = true;
        document.getElementById('matchOpSelectedPraId').value = '';
        document.getElementById('matchOpOverrideNotice').classList.add('hidden');
        document.getElementById('matchOpOverrideBtn').classList.remove('hidden');
        var granteeInput = document.getElementById('matchOpGranteeInput');
        granteeInput.value = '';
        granteeInput.disabled = true;
        granteeInput.className = 'mt-0.5 w-full rounded-md border border-transparent bg-transparent px-0 py-0.5 text-sm font-semibold text-slate-800 cursor-not-allowed focus:outline-none';
        var holderEl = document.getElementById('matchOpCurrentHolder');
        holderEl.disabled = true;
        holderEl.className = 'w-full rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-sm text-slate-500 cursor-not-allowed';
        if (window.lucide) window.lucide.createIcons();
        if (prefilledFileNo) { matchOpPreview(); }
    }

    function ffrOpenMatchOp() {
        var fileNo = (ffrState.sourceFileNo || document.getElementById('ffrSourceFileNo').value || '').trim();
        openMatchOpModal(fileNo);
    }

    function closeMatchOpModal() {
        document.getElementById('matchOpModal').classList.add('hidden');
    }

    function matchOpPreview() {
        var fileNo = (document.getElementById('matchOpFileNo').value || '').trim();
        if (!fileNo) {
            document.getElementById('matchOpStatusMsg').textContent = 'Please enter a file number.';
            document.getElementById('matchOpStatusMsg').className = 'mt-1.5 text-xs text-red-500';
            return;
        }

        var statusEl = document.getElementById('matchOpStatusMsg');
        statusEl.textContent = 'Searching…';
        statusEl.className = 'mt-1.5 text-xs text-slate-500';
        document.getElementById('matchOpPreviewPanel').classList.add('hidden');
        document.getElementById('matchOpMultipleWarning').classList.add('hidden');
        document.getElementById('matchOpConfirmBtn').disabled = true;

        fetch('""?file_no=' + encodeURIComponent(fileNo), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) {
                statusEl.textContent = data.message || 'No unmatched OP found.';
                statusEl.className = 'mt-1.5 text-xs text-red-500';
                return;
            }

            var rows = data.op_rows || [];
            var first = rows[0] || {};

            document.getElementById('matchOpSelectedPraId').value = first.id || '';
            document.getElementById('matchOpPraId').textContent = first.id || '—';
            document.getElementById('matchOpOpSerial').textContent = first.op_serial_number || '—';
            document.getElementById('matchOpGrantor').textContent = first.Grantor || first.party_1 || '—';
            var granteeInput = document.getElementById('matchOpGranteeInput');
            granteeInput.value = first.Grantee || first.party_2 || '';
            granteeInput.disabled = true;
            granteeInput.className = 'mt-0.5 w-full rounded-md border border-transparent bg-transparent px-0 py-0.5 text-sm font-semibold text-slate-800 cursor-not-allowed focus:outline-none';
            document.getElementById('matchOpLocation').textContent = first.location || '—';
            document.getElementById('matchOpLandUse').textContent = first.land_use || '—';
            document.getElementById('matchOpCurrentHolder').value = data.current_holder || '';
            // Reset override state on each new preview
            document.getElementById('matchOpOverrideBtn').classList.remove('hidden');
            document.getElementById('matchOpOverrideNotice').classList.add('hidden');
            var holderEl = document.getElementById('matchOpCurrentHolder');
            holderEl.disabled = true;
            holderEl.className = 'w-full rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-sm text-slate-500 cursor-not-allowed';

            document.getElementById('matchOpPreviewPanel').classList.remove('hidden');
            document.getElementById('matchOpConfirmBtn').disabled = false;

            statusEl.textContent = rows.length + ' unmatched OP row(s) found.';
            statusEl.className = 'mt-1.5 text-xs text-teal-600 font-semibold';

            if (rows.length > 1) {
                var listEl = document.getElementById('matchOpAllRowsList');
                listEl.innerHTML = rows.map(function(r) {
                    return '<div class="flex gap-2 items-center"><span class="font-mono">ID ' + r.id + '</span><span class="text-slate-500">— Serial: ' + (r.op_serial_number || '—') + ' — Grantee: ' + (r.Grantee || r.party_2 || '—') + '</span></div>';
                }).join('');
                document.getElementById('matchOpMultipleWarning').classList.remove('hidden');
            }
        })
        .catch(function() {
            statusEl.textContent = 'Error contacting server.';
            statusEl.className = 'mt-1.5 text-xs text-red-500';
        });
    }

    function matchOpEnableOverride() {
        // Make Grantee editable
        var granteeInput = document.getElementById('matchOpGranteeInput');
        granteeInput.disabled = false;
        granteeInput.className = 'mt-0.5 w-full rounded-md border border-teal-400 bg-white px-2 py-0.5 text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-teal-500';

        // Make Current Holder editable
        var holderEl = document.getElementById('matchOpCurrentHolder');
        holderEl.disabled = false;
        holderEl.className = 'w-full rounded-lg border border-teal-400 bg-white px-3 py-2 text-sm text-slate-800';

        // Show override notice, hide override button
        document.getElementById('matchOpOverrideNotice').classList.remove('hidden');
        document.getElementById('matchOpOverrideBtn').classList.add('hidden');

        if (window.lucide) window.lucide.createIcons();
    }

    function matchOpConfirm() {
        var praId = (document.getElementById('matchOpSelectedPraId').value || '').trim();
        var currentHolder = (document.getElementById('matchOpCurrentHolder').value || '').trim();
        var allottee = (document.getElementById('matchOpGranteeInput').value || '').trim();
        var overrideHolder = !document.getElementById('matchOpCurrentHolder').disabled;

        if (!praId) {
            Swal.fire({ icon: 'warning', title: 'No PRA selected', text: 'Run Preview first.', confirmButtonColor: '#0d9488' });
            return;
        }

        document.getElementById('matchOpConfirmBtn').disabled = true;
        document.getElementById('matchOpConfirmBtn').textContent = 'Matching…';

        var csrfToken = document.querySelector('meta[name="csrf-token"]');
        fetch('""', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken ? csrfToken.getAttribute('content') : ''
            },
            body: JSON.stringify({ pra_id: parseInt(praId), current_holder: currentHolder, allottee: allottee, override_holder: overrideHolder })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            document.getElementById('matchOpConfirmBtn').disabled = false;
            document.getElementById('matchOpConfirmBtn').textContent = 'Yes';

            if (data.success) {
                closeMatchOpModal();
                Swal.fire({
                    icon: 'success',
                    title: 'OP Matched!',
                    html: 'TEMP: <strong>' + (data.data.temp_fileno || '') + '</strong><br>Transfer of Title row created.<br><br>Do you want to proceed with another transaction (Update Holder Name)?',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, proceed',
                    cancelButtonText: 'No, close',
                    confirmButtonColor: '#0d9488',
                    cancelButtonColor: '#94a3b8'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        // Reveal the NEW mode in FEFR without reloading
                        var matchOpBtnEl = document.getElementById('ffrMatchOpBtn');
                        if (matchOpBtnEl) matchOpBtnEl.classList.add('hidden');
                        ffrShowModes(true, false, false);
                        var statusEl = document.getElementById('ffrStatus');
                        if (statusEl) statusEl.textContent = 'OP & Transfer of Title matched. Use New mode to update holder name.';
                    } else {
                        location.reload();
                    }
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Match Failed', text: data.message || 'Unknown error.', confirmButtonColor: '#0d9488' });
            }
        })
        .catch(function() {
            document.getElementById('matchOpConfirmBtn').disabled = false;
            document.getElementById('matchOpConfirmBtn').textContent = 'Yes';
            Swal.fire({ icon: 'error', title: 'Network Error', text: 'Could not connect to server.', confirmButtonColor: '#0d9488' });
        });
    }

    function ffrResetState() {
        ffrState = {
            sourceFileNo: '',
            sourceRecord: null,
            opRecord: null,
            captureExistingFromNotFound: false,
            existingOpType: 'OP Resettlement',
            indexedFile: null,
            selectedFileMeta: null
        };
        window.ffrExistingManualRegistration = false;
        window.ffrDirectOpMode = false;
        window.ffrIndexedFileData = null;
        document.getElementById('ffrSourceFileNo').value = '';
        var matchOpBtn = document.getElementById('ffrMatchOpBtn');
        if (matchOpBtn) matchOpBtn.classList.add('hidden');
        document.getElementById('ffrStatus').textContent = 'Awaiting file selection.';
        ffrShowModes(false, false, false);
        document.getElementById('ffrNewCard').classList.add('hidden');
        document.getElementById('ffrPartyFromOp').value = '';
        document.getElementById('ffrNewParty2').value = '';
        var ffrAddressInput = document.getElementById('ffrNewParty2Address');
        if (ffrAddressInput) ffrAddressInput.value = '';
        var ffrPhoneInput = document.getElementById('ffrNewParty2Phone');
        if (ffrPhoneInput) ffrPhoneInput.value = '';
        document.getElementById('ffrOpRecordPanel').innerHTML = '<p class="text-emerald-800/80">No OP record loaded yet.</p>';
        var badgesContainer = document.getElementById('ffrBtnModeNewBadges');
        if (badgesContainer) badgesContainer.innerHTML = '';

        var opTypeRadios = document.querySelectorAll('input[name="ffr_existing_op_type"]');
        opTypeRadios.forEach(function (radio) {
            radio.checked = radio.value === 'OP Resettlement';
        });

        // Reset Direct OP Capture card
        var directOpTypeRadios = document.querySelectorAll('input[name="ffr_direct_op_type"]');
        directOpTypeRadios.forEach(function (radio) {
            radio.checked = false;
        });
        var directFileNo = document.getElementById('ffrDirectFileNo');
        if (directFileNo) directFileNo.textContent = '—';
        var directHolder = document.getElementById('ffrDirectHolder');
        if (directHolder) directHolder.textContent = '—';
    }

    function ffrPickSourceFile() {
        if (typeof window.GlobalFileNoModal === 'undefined' || typeof window.GlobalFileNoModal.open !== 'function') {
            Swal.fire('Unavailable', 'Global file number selector is not loaded.', 'error');
            return;
        }

        window.GlobalFileNoModal.open({
            excludePrefixes: ['CON'],
            callback: function (fileData) {
                var fileNo = (fileData && fileData.fileNumber ? fileData.fileNumber : '').toString().trim().replace(/-+$/, '');
                if (!fileNo) return;

                // Keep metadata from picker as a fallback signal that the file
                // already exists in the indexing/file number subsystem.
                ffrState.selectedFileMeta = {
                    file_number: fileNo,
                    file_name: (fileData && (fileData.fileName || fileData.title || fileData.applicantName) ? (fileData.fileName || fileData.title || fileData.applicantName) : '').toString().trim(),
                    plot_no: (fileData && (fileData.plotNo || fileData.plot_no) ? (fileData.plotNo || fileData.plot_no) : '').toString().trim(),
                    tp_no: (fileData && (fileData.tpNo || fileData.tp_no || fileData.planNo) ? (fileData.tpNo || fileData.tp_no || fileData.planNo) : '').toString().trim(),
                    location: (fileData && fileData.location ? fileData.location : '').toString().trim(),
                    lga: (fileData && fileData.lga ? fileData.lga : '').toString().trim(),
                    land_use: (fileData && fileData.landUse ? fileData.landUse : '').toString().trim(),
                    tracking_id: (fileData && (fileData.trackingId || fileData.tracking_id) ? (fileData.trackingId || fileData.tracking_id) : '').toString().trim(),
                };

                document.getElementById('ffrSourceFileNo').value = fileNo;
                ffrState.sourceFileNo = fileNo;
                ffrLoadSourceFromPra(fileNo);
            }
        });

        setTimeout(function () {
            var globalModal = document.getElementById('global-fileno-modal');
            if (globalModal) {
                globalModal.style.zIndex = '1000100';
            }
        }, 40);
    }

    function ffrLooksLikeOpRecord(row) {
        if (!row || typeof row !== 'object') return false;

        var opSerial = ((row.op_serial_number || row.opSerialNumber || '') + '').trim();
        if (opSerial) return true;

        var transactionText = [
            row.transaction_type,
            row.instrument_type,
            row.source,
            row.purpose
        ]
            .filter(function (val) { return val !== null && val !== undefined && String(val).trim() !== ''; })
            .map(function (val) { return String(val).toLowerCase(); })
            .join(' | ');

        if (!transactionText) return false;

        if (transactionText.indexOf('occupancy permit') !== -1) return true;
        if (transactionText.indexOf('(op)') !== -1) return true;

        // Match standalone OP token while avoiding unrelated words.
        if (/(^|[^a-z0-9])op([^a-z0-9]|$)/i.test(transactionText)) return true;

        return false;
    }

    function ffrLooksLikeTransferOfTitle(row) {
        if (!row || typeof row !== 'object') return false;
        var transactionText = [
            row.transaction_type,
            row.instrument_type
        ]
            .filter(function (val) { return val !== null && val !== undefined && String(val).trim() !== ''; })
            .map(function (val) { return String(val).toLowerCase(); })
            .join(' | ');
        if (transactionText.indexOf('transfer of title') !== -1) return true;
        if (transactionText.indexOf('transfer') !== -1 && transactionText.indexOf('title') !== -1) return true;
        return false;
    }

    function ffrBuildTransactionStatusHtml(hasOp, hasTransfer) {
        var opIcon = hasOp
            ? '<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:9999px;font-size:10px;font-weight:700;background:#dcfce7;color:#15803d;border:1px solid #86efac;"><i data-lucide="check-circle" class="h-3 w-3"></i> OP</span>'
            : '<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:9999px;font-size:10px;font-weight:700;background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;"><i data-lucide="x-circle" class="h-3 w-3"></i> OP</span>';
        var totIcon = hasTransfer
            ? '<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:9999px;font-size:10px;font-weight:700;background:#dcfce7;color:#15803d;border:1px solid #86efac;"><i data-lucide="check-circle" class="h-3 w-3"></i> Transfer of Title</span>'
            : '<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:9999px;font-size:10px;font-weight:700;background:#fef9c3;color:#854d0e;border:1px solid #fde68a;"><i data-lucide="minus-circle" class="h-3 w-3"></i> Transfer of Title</span>';
        return '<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px;">' + opIcon + totIcon + '</div>';
    }

    function ffrRedirectToCreateIndexing(fileNo) {
        var cleanFileNo = (fileNo || '').toString().trim();
        var baseUrl = '""';
        var params = new URLSearchParams();
        params.set('url', 'lands');
        params.set('source', 'fefr');
        if (cleanFileNo) {
            params.set('file_no', cleanFileNo);
        }

        var targetUrl = baseUrl + (baseUrl.indexOf('?') === -1 ? '?' : '&') + params.toString();

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'File Not Indexed',
                text: 'This file is not indexed yet. Redirecting to Create File Index page.',
                timer: 1900,
                timerProgressBar: true,
                showConfirmButton: false,
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then(function () {
                window.location.href = targetUrl;
            });
            return;
        }

        window.location.href = targetUrl;
    }

    async function ffrLoadSourceFromPra(fileNo) {
        var targetFileNo = (fileNo || '').toString().trim();
        if (!targetFileNo) {
            ffrResetState();
            return;
        }

        ffrLookupRequestId += 1;
        var activeLookupId = ffrLookupRequestId;

        var statusEl = document.getElementById('ffrStatus');
        statusEl.textContent = 'Checking PRA records...';
        ffrShowModes(false, false, false);
        document.getElementById('ffrNewCard').classList.add('hidden');
        var matchOpBtn = document.getElementById('ffrMatchOpBtn');
        if (matchOpBtn) matchOpBtn.classList.add('hidden');
        ffrState.sourceFileNo = targetFileNo;
        ffrState.sourceRecord = null;
        ffrState.opRecord = null;
        ffrState.captureExistingFromNotFound = false;
        ffrState.indexedFile = null;

        try {
            var response = await fetch('/api/pra/v1/records/all-by-file/' + encodeURIComponent(targetFileNo), {
                headers: { 'Accept': 'application/json' }
            });
            var result = await response.json();

            // Ignore stale responses when user picks another file quickly.
            if (activeLookupId !== ffrLookupRequestId) {
                return;
            }

            var records = Array.isArray(result && result.data) ? result.data : [];

            if (!response.ok || records.length === 0) {
                // No PRA records — check if file is indexed
                var indexed = await ffrCheckFileIndexed(targetFileNo);
                if (activeLookupId !== ffrLookupRequestId) return;

                if (indexed) {
                    ffrState.indexedFile = indexed;
                    ffrPopulateDirectOpCard(indexed);
                    statusEl.textContent = 'File is indexed but has no PRA record. Capture OP details directly.';
                    document.getElementById('ffrOpRecordPanel').innerHTML =
                        '<p class="text-violet-700">File indexed as <strong>' + ffrEsc(indexed.file_name || '—') + '</strong>. No PRA record — use Direct OP Capture below.</p>'
                        + ffrBuildTransactionStatusHtml(false, false);
                    if (window.lucide) window.lucide.createIcons();
                    ffrShowModes(false, false, true);
                    ffrToast('info', 'File is indexed. Fill OP details directly.');
                } else {
                    statusEl.textContent = 'No PRA record found for selected file.';
                    document.getElementById('ffrOpRecordPanel').innerHTML = '<p class="text-amber-700">No indexed record found. Redirecting to Create File Index page.</p>'
                        + ffrBuildTransactionStatusHtml(false, false);
                    if (window.lucide) window.lucide.createIcons();
                    ffrState.captureExistingFromNotFound = true;
                    ffrShowModes(false, true, false);
                    ffrRedirectToCreateIndexing(targetFileNo);
                }
                return;
            }

            ffrState.sourceRecord = records[0];
            var opRecord = records.find(function (row) {
                return ffrLooksLikeOpRecord(row);
            }) || null;

            if (!opRecord) {
                // PRA exists but no OP — check if file is indexed
                var indexed2 = await ffrCheckFileIndexed(targetFileNo);
                if (activeLookupId !== ffrLookupRequestId) return;

                if (indexed2) {
                    var hasTransfer2 = records.some(function (row) { return ffrLooksLikeTransferOfTitle(row); });
                    ffrState.indexedFile = indexed2;
                    ffrPopulateDirectOpCard(indexed2);
                    statusEl.textContent = 'PRA record found, but no OP. File is indexed — capture OP directly.';
                    document.getElementById('ffrOpRecordPanel').innerHTML =
                        '<p class="text-violet-700">File indexed as <strong>' + ffrEsc(indexed2.file_name || '—') + '</strong>. No OP — use Direct OP Capture below.</p>'
                        + ffrBuildTransactionStatusHtml(false, hasTransfer2);
                    if (window.lucide) window.lucide.createIcons();
                    ffrShowModes(false, false, true);
                    ffrToast('info', 'PRA history loaded but no OP. Use Direct OP Capture.');
                } else {
                    var hasTransfer3 = records.some(function (row) { return ffrLooksLikeTransferOfTitle(row); });
                    statusEl.textContent = 'PRA record found, but OP transaction was not clearly detected.';
                    document.getElementById('ffrOpRecordPanel').innerHTML = '<p class="text-amber-700">No indexed OP source found for this file. Redirecting to Create File Index page.</p>'
                        + ffrBuildTransactionStatusHtml(false, hasTransfer3);
                    if (window.lucide) window.lucide.createIcons();
                    ffrState.captureExistingFromNotFound = true;
                    ffrShowModes(false, true, false);
                    ffrRedirectToCreateIndexing(targetFileNo);
                }
                return;
            }

            ffrState.opRecord = opRecord;
            var partyFromOp = ((opRecord.parties && opRecord.parties.party_2) || '').toString().trim();
            document.getElementById('ffrPartyFromOp').value = partyFromOp;
            document.getElementById('ffrNewParty2').value = '';

            var hasTransfer = records.some(function (row) { return ffrLooksLikeTransferOfTitle(row); });

            document.getElementById('ffrOpRecordPanel').innerHTML = [
                '<div class="grid grid-cols-2 gap-x-3 gap-y-2">',
                '<div><span class="text-slate-500">Transaction</span><p class="font-semibold">' + ffrEsc(opRecord.transaction_type || 'Occupancy Permit') + '</p></div>',
                '<div><span class="text-slate-500">OP Serial</span><p class="font-semibold">' + ffrEsc(opRecord.op_serial_number || '—') + '</p></div>',
                '<div><span class="text-slate-500">Party 2 (OP)</span><p class="font-semibold">' + ffrEsc(partyFromOp || '—') + '</p></div>',
                '<div><span class="text-slate-500">Property</span><p class="font-semibold">' + ffrEsc(opRecord.property_description || opRecord.location || '—') + '</p></div>',
                '</div>',
                ffrBuildTransactionStatusHtml(true, hasTransfer)
            ].join('');
            if (window.lucide) window.lucide.createIcons();

            var matchOpBtnEl = document.getElementById('ffrMatchOpBtn');
            var opTempFileno = ((opRecord.temp_fileno || '') + '').trim();

            if (!opTempFileno) {
                // Unmatched OP (no TEMP file number) — show Match OP, hide NEW mode
                statusEl.textContent = 'OP found but not yet matched. Click Match OP to link this record.';
                ffrShowModes(false, false, false);
                if (matchOpBtnEl) matchOpBtnEl.classList.remove('hidden');
            } else {
                // OP is already matched (has TEMP-XXXXX) — hide Match OP, show NEW mode if Transfer of Title exists
                if (matchOpBtnEl) matchOpBtnEl.classList.add('hidden');
                if (hasTransfer) {
                    statusEl.textContent = 'OP & Transfer of Title found. Use New mode to update holder name.';
                    ffrShowModes(true, false, false);
                } else {
                    statusEl.textContent = 'OP matched but Transfer of Title not yet found.';
                    ffrShowModes(false, false, false);
                }
            }

            var badgesContainer = document.getElementById('ffrBtnModeNewBadges');
            if (badgesContainer) {
                badgesContainer.innerHTML = ffrBuildTransactionStatusHtml(true, hasTransfer);
                if (window.lucide) window.lucide.createIcons();
            }
        } catch (error) {
            if (activeLookupId !== ffrLookupRequestId) {
                return;
            }
            statusEl.textContent = 'Failed to load PRA record.';
            ffrToast('error', 'Failed to check PRA for selected file number.');
        }
    }

    /**
     * Check if the file number is already indexed (exists in fileNumber/file_indexings).
     * Returns the indexed data object or null.
     */
    async function ffrCheckFileIndexed(fileNo) {
        try {
            var resp = await fetch('""?file_no=' + encodeURIComponent(fileNo), {
                headers: { 'Accept': 'application/json' }
            });
            var data = await resp.json();
            if (resp.ok && data && data.success && data.data) {
                return data.data;
            }
            return null;
        } catch (e) {
            return null;
        }
    }

    /**
     * Populate the Direct OP Capture card with indexed file data.
     */
    function ffrPopulateDirectOpCard(indexed) {
        console.log('[FFR DirectOp] populateCard data:', JSON.stringify(indexed));
        var el = function (id) { return document.getElementById(id); };
        var resolvedHolder = (indexed.file_name || indexed.current_holder || indexed.file_title || '').toString().trim();
        if (el('ffrIdxFileNo')) el('ffrIdxFileNo').textContent = indexed.file_number || '—';
        if (el('ffrIdxFileName')) el('ffrIdxFileName').textContent = resolvedHolder || '—';

        // Uncheck OP type radios so user must explicitly select
        var opTypeRadios = document.querySelectorAll('input[name="ffr_direct_op_type"]');
        opTypeRadios.forEach(function (r) { r.checked = false; });

        if (window.lucide) window.lucide.createIcons();
    }

    /**
     * When user selects OP type on the Direct OP card (indexed file),
     * hide FEFR modal and open the Registration Dialog (OP modal) directly.
     * The OP modal handles all data entry. On submit, instruments-capture.js
     * creates both PRA rows (OP + Transfer of Title) using indexed file data.
     */
    function ffrDirectOpTypeSelected(opTypeValue) {
        if (!ffrState.sourceFileNo) {
            ffrToast('warning', 'Source file number is missing.');
            return;
        }

        if (typeof window.openRegistrationDialog !== 'function') {
            ffrToast('error', 'Capture modal is not available on this page.');
            return;
        }

        var normalized = (opTypeValue || 'OP Resettlement').toString();
        ffrState.existingOpType = normalized;

        // Store indexed file data so instruments-capture.js can use it for the second PRA row
        window.ffrIndexedFileData = ffrState.indexedFile || null;
        window.ffrDirectOpMode = true;
        window.ffrExistingManualRegistration = true;
        window.ossOpContext = false;
        window.requireOpLookupForCommission = false;
        window.ffrExistingSourceFileNo = ffrState.sourceFileNo;
        window._directOpCaptureUrl = """";

        // Hide FEFR modal so OP modal is visible
        var ffrModalEl = document.getElementById('ffrModal');
        if (ffrModalEl) ffrModalEl.classList.add('hidden');

        // Open Registration Dialog for Occupancy Permit
        window.openRegistrationDialog('occupancy-permit');

        // Pre-select the OP type radio in the OP modal
        setTimeout(function () {
            var targetId = normalized.toLowerCase().indexOf('resettlement') !== -1
                ? 'op_type_resettlement'
                : 'op_type_direct_allocation';
            var targetRadio = document.getElementById(targetId);
            if (targetRadio) {
                targetRadio.checked = true;
                targetRadio.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }, 80);
    }

    function ffrSwitchMode(mode) {
        if (mode !== 'new') return;
        document.getElementById('ffrNewCard').classList.remove('hidden');
        document.getElementById('ffrBtnModeNew').classList.add('ring-2', 'ring-blue-300');
        var existingBtn = document.getElementById('ffrBtnModeExisting');
        if (existingBtn) existingBtn.classList.remove('ring-2', 'ring-amber-300');
    }

    function ffrExistingOpTypeSelected() {
        var btn = document.getElementById('ffrBtnModeExisting');
        if (btn) btn.classList.remove('hidden');
        // Auto-open the capture existing modal when OP type is selected
        ffrOpenExistingCapture();
    }

    /**
     * Open Capture Existing File modal directly for Transfer of Title.
     * Called after Direct OP capture succeeds — skips FEFR entirely.
     */
    function ffrOpenCaptureExistingForTransfer(sourceFileNo, opDetails) {
        if (!sourceFileNo) {
            ffrToast('warning', 'Source file number is missing.');
            return;
        }

        if (typeof openCaptureModal !== 'function') {
            ffrToast('error', 'Capture Existing component is not available on this page.');
            window.location.reload();
            return;
        }

        var details = opDetails || {};

        // Ensure ffrState is populated so the Capture Existing modal works correctly
        ffrState.sourceFileNo = sourceFileNo;
        ffrState.captureExistingFromNotFound = true;
        ffrState.existingOpType = 'OP Resettlement';

        // Hide the FFR modal
        var ffrModalEl = document.getElementById('ffrModal');
        if (ffrModalEl) ffrModalEl.classList.add('hidden');

        openCaptureModal();

        var fileOptionSelect = document.getElementById('fileOption');
        if (fileOptionSelect) {
            fileOptionSelect.value = 'normal';
            updateCaptureForm('normal');
            fileOptionSelect.disabled = true;
        }

        var prefixField = document.getElementById('prefix');
        var yearField = document.getElementById('year');
        var serialField = document.getElementById('serialNo');

        // Parse source file number (e.g., RES-1981-64)
        var fileParts = sourceFileNo.split('-');
        if (fileParts.length >= 3) {
            if (prefixField) prefixField.value = fileParts[0];
            if (yearField) yearField.value = fileParts[1];
            if (serialField) serialField.value = fileParts.slice(2).join('-');
        }

        if (prefixField) prefixField.disabled = true;
        if (yearField) yearField.disabled = true;
        if (serialField) {
            serialField.disabled = true;
            serialField.removeAttribute('required');
        }

        var fileNameInput = document.getElementById('captureFileName');
        if (fileNameInput) fileNameInput.value = '';

        setCaptureFormValue('source_file_no', sourceFileNo);
        setCaptureFormValue('ffr_capture_mode', 'existing-not-found');

        // ── Linkage fields from the OP row ──
        if (details.temp_fileno) {
            setCaptureFormValue('ffr_temp_fileno', details.temp_fileno);
        }
        if (details.prop_id) {
            setCaptureFormValue('ffr_first_pra_prop_id', details.prop_id);
        }
        // Set party_1 = original allottee from OP for Transfer of Title
        if (details.allottee) {
            setCaptureFormValue('party_1', details.allottee);
        }
        // Flag that this is a Transfer of Title submission from OP flow
        setCaptureFormValue('from_op_flow', '1');

        var allocSourceSection = document.getElementById('captureAllocationSourceSection');
        if (allocSourceSection) allocSourceSection.classList.add('hidden');

        var customerTypeSelect = document.getElementById('customerType');
        if (customerTypeSelect && !customerTypeSelect.value) customerTypeSelect.value = 'Individual';

        var preview = document.getElementById('capturePreview');
        if (preview) {
            preview.textContent = sourceFileNo;
            preview.classList.remove('text-gray-400');
            preview.classList.add('text-green-600');
        }

        updateCapturePreview();

        // ── Backfill location details from the OP card ──
        if (details.location) {
            var locationInput = document.getElementById('captureLocation');
            if (locationInput) locationInput.value = details.location;
        }
        if (details.plot_no) {
            var plotInput = document.getElementById('capturePlotNo');
            if (plotInput) plotInput.value = details.plot_no;
        }
        if (details.tp_no) {
            var tpInput = document.getElementById('captureTpNo');
            if (tpInput) tpInput.value = details.tp_no;
        }
        if (details.lga) {
            var lgaSelect = document.getElementById('captureLga');
            if (lgaSelect) lgaSelect.value = details.lga;
        }
        if (details.land_use && typeof window.setCaptureLandUseAndLoadPurposes === 'function') {
            window.setCaptureLandUseAndLoadPurposes(details.land_use);
        } else if (details.land_use) {
            var landUseSelect = document.getElementById('captureLandUse');
            if (landUseSelect) landUseSelect.value = details.land_use;
        }

        window.ossOpSubmitLabel = 'Capture Existing OP';
        window.ffrExistingSourceFileNo = sourceFileNo;
    }

    function ffrOpenExistingCapture() {
        if (!ffrState.sourceFileNo) {
            ffrToast('warning', 'Please select the source file number first.');
            return;
        }

        if (typeof openCaptureModal !== 'function') {
            ffrToast('error', 'Capture Existing component is not available on this page.');
            return;
        }

        if (!ffrState.captureExistingFromNotFound) {
            ffrToast('info', 'Existing capture is available when OP record is not available.');
            return;
        }

        var selectedOpType = document.querySelector('input[name="ffr_existing_op_type"]:checked');
        ffrState.existingOpType = selectedOpType ? selectedOpType.value : 'OP Resettlement';

        // Hide the FFR modal so the capture modal and registration dialog are
        // visible (their z-indices are lower than ffrModal's z-[1000040]).
        var ffrModalEl = document.getElementById('ffrModal');
        if (ffrModalEl) ffrModalEl.classList.add('hidden');

        openCaptureModal();

        var fileOptionSelect = document.getElementById('fileOption');
        if (fileOptionSelect) {
            fileOptionSelect.value = 'normal';
            updateCaptureForm('normal');
            fileOptionSelect.disabled = true;
        }

        var prefixField = document.getElementById('prefix');
        var yearField = document.getElementById('year');
        var serialField = document.getElementById('serialNo');

        // Parse source file number (e.g., RES-1981-64) and populate prefix, year, serial BEFORE disabling
        var sourceFileNo = ffrState.sourceFileNo || '';
        var fileParts = sourceFileNo.split('-');
        if (fileParts.length >= 3) {
            var extractedPrefix = fileParts[0];
            var extractedYear = fileParts[1];
            var extractedSerial = fileParts.slice(2).join('-');

            if (prefixField) prefixField.value = extractedPrefix;
            if (yearField) yearField.value = extractedYear;
            if (serialField) serialField.value = extractedSerial;
        }

        // Disable editing after values are set
        if (prefixField) prefixField.disabled = true;
        if (yearField) yearField.disabled = true;
        if (serialField) {
            serialField.disabled = true;
            serialField.removeAttribute('required');
        }

        // Keep File Name manual in Capture Existing flow.
        var fileNameInput = document.getElementById('captureFileName');
        if (fileNameInput) {
            fileNameInput.value = '';
        }

        var locationInput = document.getElementById('captureLocation');
        if (locationInput) {
            locationInput.value = (
                ffrState.opRecord?.property_description ||
                ffrState.opRecord?.location ||
                ffrState.sourceRecord?.location ||
                ''
            ).toString().trim();
        }

        setCaptureFormValue('lga', (ffrState.sourceRecord?.lga || ffrState.opRecord?.lga || 'Unknown').toString().trim() || 'Unknown');
        setCaptureFormValue('source_file_no', ffrState.sourceFileNo);
        setCaptureFormValue('ffr_existing_op_type', ffrState.existingOpType);
        setCaptureFormValue('ffr_capture_mode', 'existing-not-found');

        // Hide Allocation Source section since OP type was selected on FEFR card
        var allocSourceSection = document.getElementById('captureAllocationSourceSection');
        if (allocSourceSection) {
            allocSourceSection.classList.add('hidden');
        }

        var lgaSelect = document.getElementById('captureLga');
        if (lgaSelect) {
            lgaSelect.value = (ffrState.sourceRecord?.lga || ffrState.opRecord?.lga || 'Unknown').toString().trim() || '';
        }

        var customerTypeSelect = document.getElementById('customerType');
        if (customerTypeSelect && !customerTypeSelect.value) {
            customerTypeSelect.value = 'Individual';
        }

        var preview = document.getElementById('capturePreview');
        if (preview) {
            preview.textContent = ffrState.sourceFileNo;
            preview.classList.remove('text-gray-400');
            preview.classList.add('text-green-600');
        }

        updateCapturePreview();

        var modal = document.getElementById('ffrModal');
        if (modal) {
            modal.classList.add('hidden');
        }

        // Set submit button label and source file no for OP modal
        window.ossOpSubmitLabel = 'Capture Existing OP';
        window.ffrExistingSourceFileNo = ffrState.sourceFileNo;

        // The first PRA (OP) record uses the system temp file number from the OP modal.
        // It is read from #display_fileno at submit time in instruments-capture.js.
        // Step 3 (FEFR New mode) later records the Transfer of Title transaction.
        window.ffrExistingTempFileNo = '';

        // Get tracking_id from the grouping table (simple SELECT by awaiting_fileno)
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrfTokenVal = csrfMeta ? csrfMeta.getAttribute('content') : '';
        fetch('""?file_no=' + encodeURIComponent(ffrState.sourceFileNo), {
            headers: { 'Accept': 'application/json' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.success && data.tracking_id) {
                var trackingId = (data.tracking_id || '').trim();
                var trackingInput = document.querySelector('#captureModal #trackingId');
                if (trackingInput) trackingInput.value = trackingId;
                var trackingDisplay = document.querySelector('#captureModal #trackingIdDisplay');
                if (trackingDisplay) {
                    trackingDisplay.textContent = trackingId;
                    trackingDisplay.classList.remove('text-slate-600');
                    trackingDisplay.classList.add('text-green-600');
                }
                var trackingStatus = document.querySelector('#captureModal #trackingIdStatus');
                if (trackingStatus) trackingStatus.textContent = 'Tracking ID matched from grouping table.';
            } else {
                var trackingDisplay2 = document.querySelector('#captureModal #trackingIdDisplay');
                if (trackingDisplay2) {
                    trackingDisplay2.textContent = 'Not Found';
                    trackingDisplay2.classList.add('text-red-500');
                }
                var trackingStatus2 = document.querySelector('#captureModal #trackingIdStatus');
                if (trackingStatus2) trackingStatus2.textContent = 'No grouping record found for this file number.';
            }
        })
        .catch(function () {
            var trackingDisplay3 = document.querySelector('#captureModal #trackingIdDisplay');
            if (trackingDisplay3) trackingDisplay3.textContent = 'Lookup failed';
        });

        if (typeof window.openRegistrationDialog === 'function') {
            setTimeout(function () {
                window.ffrExistingManualRegistration = true;
                window.openRegistrationDialog('occupancy-permit');

                setTimeout(function () {
                    var targetId = ffrState.existingOpType.toLowerCase().indexOf('resettlement') !== -1
                        ? 'op_type_resettlement'
                        : 'op_type_direct_allocation';
                    var targetRadio = document.getElementById(targetId);
                    if (targetRadio) {
                        targetRadio.checked = true;
                        targetRadio.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }, 80);
            }, 120);
        }
    }

    async function ffrSaveToPra() {
        if (!ffrState.opRecord || !ffrState.sourceRecord) {
            Swal.fire('Required', 'Select a source file with OP record first.', 'warning');
            return;
        }

        var newParty2 = (document.getElementById('ffrNewParty2').value || '').toString().trim();
        var residenceAddress = (document.getElementById('ffrNewParty2Address').value || '').toString().trim();
        var phone = (document.getElementById('ffrNewParty2Phone').value || '').toString().trim();
        var partyFromOp = (document.getElementById('ffrPartyFromOp').value || '').toString().trim();

        if (!newParty2) {
            Swal.fire('Required', 'Please enter Party 2 for the new record.', 'warning');
            return;
        }

        var payload = {
            source_file_no: (ffrState.sourceFileNo || '').toString().trim(),
            prop_id: ffrState.sourceRecord.prop_id || null,
            op_serial_number: ffrState.opRecord.op_serial_number || null,
            land_use: ffrState.opRecord.land_use || ffrState.sourceRecord.land_use || null,
            location: ffrState.opRecord.location || ffrState.sourceRecord.location || ffrState.opRecord.property_description || null,
            party_1: partyFromOp || null,
            party_2: newParty2,
            residence_address: residenceAddress || null,
            phone: phone || null
        };

        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        try {
            var response = await fetch('""', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });
            var result = await response.json();

            if (!response.ok || !result || !result.success) {
                var backendMessage = (result && result.message) ? result.message : 'Unable to save PRA record.';
                var backendError = (result && result.error) ? String(result.error) : '';
                throw new Error(backendError ? (backendMessage + ' (' + backendError + ')') : backendMessage);
            }

            Swal.fire({
                icon: 'success',
                title: 'Saved',
                text: (result && result.message) ? result.message : 'FEFR saved and synchronized successfully.',
                timer: 2200,
                showConfirmButton: false
            });

            // Keep FEFR open and refresh loaded history after the transfer transaction is saved.
            if (ffrState.sourceFileNo) {
                ffrLoadSourceFromPra(ffrState.sourceFileNo);
            }
        } catch (error) {
            Swal.fire('Error', error.message || 'Failed to save FEFR record to PRA.', 'error');
        }
    }

    function ffrEsc(value) {
        return String(value || '—')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    document.addEventListener('DOMContentLoaded', function () {
        var ffrBtn = document.getElementById('btn-ffr');
        if (ffrBtn) {
            ffrBtn.addEventListener('click', openFfrModal);
        }
    });

    function setCaptureFormValue(name, value) {
        var form = document.getElementById('captureForm');
        if (!form) return;

        var input = form.querySelector('[name="' + name + '"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            form.appendChild(input);
        }
        input.value = value || '';
    }

    var trackingLookupTimeout = null;
    var trackingLookupRequestId = 0;

    function setTrackingIdDisplay(text, tone) {
        var display = document.querySelector('#captureModal #trackingIdDisplay');
        if (!display) return;

        display.textContent = text || 'Awaiting grouping match';
        display.classList.remove('text-slate-500', 'text-slate-600', 'text-green-600', 'text-red-600');

        if (tone === 'success') {
            display.classList.add('text-green-600');
        } else if (tone === 'error') {
            display.classList.add('text-red-600');
        } else {
            display.classList.add('text-slate-600');
        }
    }

    function setTrackingStatus(message) {
        var statusEl = document.querySelector('#captureModal #trackingIdStatus');
        if (!statusEl) return;
        statusEl.textContent = message || '';
    }

    function resetTrackingIdState() {
        var trackingInput = document.querySelector('#captureModal #trackingId');
        if (trackingInput) trackingInput.value = '';
        setTrackingIdDisplay('Awaiting grouping match');
        setTrackingStatus('');
    }

    function normalizeGroupingLookupTarget(value) {
        return (value || '')
            .toString()
            .trim()
            .replace(/\s+AND\s+EXTENSION$/i, '')
            .replace(/\(T\)\s*$/i, '')
            .replace(/-+$/, '');
    }

    function scheduleGroupingLookup(previewValue) {
        if (trackingLookupTimeout) {
            clearTimeout(trackingLookupTimeout);
        }

        var normalizedValue = normalizeGroupingLookupTarget(previewValue);
        if (!normalizedValue || normalizedValue === '-') {
            resetTrackingIdState();
            return;
        }

        trackingLookupTimeout = setTimeout(function () {
            lookupGroupingTrackingId(normalizedValue);
        }, 350);
    }

    async function lookupGroupingTrackingId(previewValue) {
        trackingLookupTimeout = null;
        trackingLookupRequestId += 1;
        var requestId = trackingLookupRequestId;
        var lookupTarget = normalizeGroupingLookupTarget(previewValue);
        var trackingInput = document.querySelector('#captureModal #trackingId');

        setTrackingIdDisplay('Searching...');
        setTrackingStatus('Checking grouping records...');

        try {
            var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

            var linkResponse = await fetch('/api/grouping/link-mls', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ mls_file_number: lookupTarget })
            });
            var linkPayload = await linkResponse.json().catch(function () { return {}; });

            if (requestId !== trackingLookupRequestId) return;

            if (linkResponse.ok && linkPayload.success) {
                var linkedRecord = linkPayload.data || {};
                var linkedTrackingId = (linkedRecord.tracking_id || '').toString().trim();

                if (trackingInput) trackingInput.value = linkedTrackingId;

                if (linkedTrackingId) {
                    setTrackingIdDisplay(linkedTrackingId, 'success');
                    setTrackingStatus('Linked to grouping record.');
                } else {
                    setTrackingIdDisplay('Grouping record has no tracking ID');
                    setTrackingStatus('Assign tracking ID manually before saving.');
                }
                return;
            }

            var response = await fetch('/api/grouping/awaiting/' + encodeURIComponent(lookupTarget));
            var payload = await response.json().catch(function () { return {}; });

            if (requestId !== trackingLookupRequestId) return;

            if (!response.ok || !payload.success) {
                throw new Error((payload && payload.message) ? payload.message : 'Grouping record not found');
            }

            var record = payload.data || payload;
            var trackingId = (record && record.tracking_id ? record.tracking_id : '').toString().trim();

            if (trackingInput) trackingInput.value = trackingId;

            if (trackingId) {
                setTrackingIdDisplay(trackingId, 'success');
                setTrackingStatus('Linked to grouping record.');
            } else {
                setTrackingIdDisplay('Grouping record has no tracking ID');
                setTrackingStatus('Assign tracking ID manually before saving.');
            }
        } catch (error) {
            if (requestId !== trackingLookupRequestId) return;
            if (trackingInput) trackingInput.value = '';
            setTrackingIdDisplay('Grouping tracking ID not found', 'error');
            setTrackingStatus('A new tracking ID will be generated on save.');
            console.error('Error fetching grouping tracking ID:', error);
        }
    }

    function openCaptureModal() {
        var modal = document.getElementById('captureModal');
        var form = document.getElementById('captureForm');
        if (!modal || !form) return;

        modal.classList.remove('hidden');
        form.reset();

        var yearInput = document.getElementById('year');
        if (yearInput) yearInput.value = new Date().getFullYear();

        var fileOption = document.getElementById('fileOption');
        if (fileOption) fileOption.value = 'normal';

        if (trackingLookupTimeout) {
            clearTimeout(trackingLookupTimeout);
            trackingLookupTimeout = null;
        }
        trackingLookupRequestId += 1;
        resetTrackingIdState();

        ['fileOption', 'prefix', 'year', 'serialNo'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.disabled = false;
        });

        setCaptureFormValue('lga', 'Unknown');
        setCaptureFormValue('ffr_capture_mode', '');
        setCaptureFormValue('source_file_no', '');
        setCaptureFormValue('ffr_existing_op_type', '');

        // Ensure Allocation Source section is visible when opening normally
        var allocSourceSection = document.getElementById('captureAllocationSourceSection');
        if (allocSourceSection) {
            allocSourceSection.classList.remove('hidden');
        }

        updateCaptureForm('normal');
        loadExistingFileNumbers();
        updateCapturePreview();

        if (window.lucide) window.lucide.createIcons();
    }

    function closeCaptureModal() {
        var modal = document.getElementById('captureModal');
        if (modal) modal.classList.add('hidden');
    }

    function syncCaptureFileOption(value) {
        var fileOption = document.getElementById('fileOption');
        if (!fileOption || !value) return;
        fileOption.value = value;
        updateCaptureForm(value);
    }

    function syncQuickFileOptionUI(value) {
        var quickOptions = document.querySelectorAll('input[name="quick_file_option_capture"]');
        if (!quickOptions.length) return;

        quickOptions.forEach(function (option) {
            option.checked = option.value === value;
        });
    }

    function updateCaptureForm(type) {
        var prefixSection = document.getElementById('prefixSection');
        var middlePrefixSection = document.getElementById('middlePrefixSection');
        var yearSection = document.getElementById('yearSection');
        var extensionFileSection = document.getElementById('extensionFileSection');
        var temporaryFileSection = document.getElementById('temporaryFileSection');
        var serialNoField = document.getElementById('serialNo');

        syncQuickFileOptionUI(type);

        [prefixSection, middlePrefixSection, yearSection, extensionFileSection, temporaryFileSection].forEach(function (section) {
            if (section) section.classList.add('hidden');
        });

        if (serialNoField) {
            serialNoField.type = 'text';
            serialNoField.disabled = false;
            serialNoField.value = serialNoField.value || '';
            serialNoField.placeholder = 'Enter serial number';
            ['min', 'max', 'step', 'maxlength', 'pattern', 'inputmode'].forEach(function (attr) {
                serialNoField.removeAttribute(attr);
            });
        }

        if (type === 'normal') {
            if (prefixSection) prefixSection.classList.remove('hidden');
            if (yearSection) yearSection.classList.remove('hidden');
            if (serialNoField) {
                serialNoField.type = 'number';
                serialNoField.setAttribute('min', '1');
                serialNoField.setAttribute('max', '9999');
                serialNoField.placeholder = 'Enter serial number (1-9999)';
            }
        } else if (type === 'temporary') {
            if (temporaryFileSection) temporaryFileSection.classList.remove('hidden');
            if (yearSection) yearSection.classList.remove('hidden');
            if (serialNoField) {
                serialNoField.value = '';
                serialNoField.disabled = true;
                serialNoField.placeholder = 'Not required for temporary files';
            }
            setupTemporaryToggle();
            loadExistingFileNumbers();
        } else if (type === 'extension') {
            if (extensionFileSection) extensionFileSection.classList.remove('hidden');
            if (yearSection) yearSection.classList.remove('hidden');
            if (serialNoField) {
                serialNoField.value = '';
                serialNoField.disabled = true;
                serialNoField.placeholder = 'Not required for extensions';
            }
            setupExtensionToggle();
            loadExistingFileNumbers();
        } else if (type === 'miscellaneous') {
            if (middlePrefixSection) middlePrefixSection.classList.remove('hidden');
            if (yearSection) yearSection.classList.remove('hidden');
            if (serialNoField) serialNoField.placeholder = 'Enter custom serial';
        } else if (type === 'sit') {
            if (yearSection) yearSection.classList.remove('hidden');
        }

        updateCapturePreview();
    }

    function setupExtensionToggle() {
        var dropdownRadio = document.getElementById('extensionDropdown');
        var manualRadio = document.getElementById('extensionManual');
        var dropdownSection = document.getElementById('extensionDropdownSection');
        var manualInputSection = document.getElementById('extensionManualInput');
        var manualInput = document.getElementById('extensionManualFile');
        var dropdown = document.getElementById('existingFileNo');

        if (!dropdownRadio || !manualRadio || !manualInput || !dropdown) return;

        dropdownRadio.onchange = function () {
            if (!this.checked) return;
            if (dropdownSection) dropdownSection.classList.remove('hidden');
            if (manualInputSection) manualInputSection.classList.add('hidden');
            manualInput.value = '';
            manualInput.disabled = true;
            dropdown.disabled = false;
            updateCapturePreview();
        };

        manualRadio.onchange = function () {
            if (!this.checked) return;
            if (dropdownSection) dropdownSection.classList.add('hidden');
            if (manualInputSection) manualInputSection.classList.remove('hidden');
            dropdown.value = '';
            dropdown.disabled = true;
            manualInput.disabled = false;
            updateCapturePreview();
        };
    }

    function setupTemporaryToggle() {
        var dropdownRadio = document.getElementById('temporaryDropdown');
        var manualRadio = document.getElementById('temporaryManual');
        var dropdownSection = document.getElementById('temporaryDropdownSection');
        var manualInputSection = document.getElementById('temporaryManualInput');
        var manualInput = document.getElementById('temporaryManualFile');
        var dropdown = document.getElementById('temporaryFileNo');

        if (!dropdownRadio || !manualRadio || !manualInput || !dropdown) return;

        dropdownRadio.onchange = function () {
            if (!this.checked) return;
            if (dropdownSection) dropdownSection.classList.remove('hidden');
            if (manualInputSection) manualInputSection.classList.add('hidden');
            manualInput.value = '';
            manualInput.disabled = true;
            dropdown.disabled = false;
            updateCapturePreview();
        };

        manualRadio.onchange = function () {
            if (!this.checked) return;
            if (dropdownSection) dropdownSection.classList.add('hidden');
            if (manualInputSection) manualInputSection.classList.remove('hidden');
            dropdown.value = '';
            dropdown.disabled = true;
            manualInput.disabled = false;
            updateCapturePreview();
        };
    }

    function updateCapturePreview() {
        var captureMode = ((document.querySelector('#captureForm [name="ffr_capture_mode"]') || {}).value || '').toString();
        var sourceFileNo = ((document.querySelector('#captureForm [name="source_file_no"]') || {}).value || '').toString();
        var fileOption = (document.getElementById('fileOption') || {}).value || 'normal';
        var prefix = (document.getElementById('prefix') || {}).value || '';
        var middlePrefix = (document.getElementById('middlePrefix') || {}).value || '';
        var year = (document.getElementById('year') || {}).value || '';
        var serialNo = (document.getElementById('serialNo') || {}).value || '';
        var preview = document.getElementById('capturePreview');
        if (!preview) return;

        var extensionFileValue = '';
        var temporaryFileValue = '';

        if (fileOption === 'extension') {
            var extensionDropdownRadio = document.getElementById('extensionDropdown');
            if (extensionDropdownRadio && extensionDropdownRadio.checked) {
                extensionFileValue = ((document.getElementById('existingFileNo') || {}).value || '').trim();
            } else {
                extensionFileValue = ((document.getElementById('extensionManualFile') || {}).value || '').trim();
            }
        }

        if (fileOption === 'temporary') {
            var temporaryDropdownRadio = document.getElementById('temporaryDropdown');
            if (temporaryDropdownRadio && temporaryDropdownRadio.checked) {
                temporaryFileValue = ((document.getElementById('temporaryFileNo') || {}).value || '').trim();
            } else {
                temporaryFileValue = ((document.getElementById('temporaryManualFile') || {}).value || '').trim();
            }
        }

        var previewText = '-';
        if (captureMode === 'existing-not-found' && sourceFileNo) {
            previewText = sourceFileNo;
        } else if (fileOption === 'extension' && extensionFileValue) {
            previewText = extensionFileValue + ' AND EXTENSION';
        } else if (fileOption === 'temporary' && temporaryFileValue) {
            previewText = temporaryFileValue + '(T)';
        } else if (fileOption === 'miscellaneous' && middlePrefix && year && serialNo) {
            previewText = 'MISC-' + middlePrefix + '-' + year + '-' + serialNo;
        } else if (fileOption === 'old_mls' && serialNo) {
            previewText = 'KN ' + serialNo;
        } else if (fileOption === 'sltr' && serialNo) {
            previewText = 'SLTR-' + serialNo;
        } else if (fileOption === 'sit' && year && serialNo) {
            previewText = 'SIT-' + year + '-' + serialNo;
        } else if (fileOption === 'normal' && prefix && year && serialNo) {
            previewText = prefix + '-' + year + '-' + serialNo;
        }

        preview.textContent = previewText;

        var groupingLookupTarget = null;
        if (captureMode === 'existing-not-found' && sourceFileNo) {
            groupingLookupTarget = sourceFileNo;
        } else if (fileOption === 'normal' && previewText !== '-') {
            groupingLookupTarget = previewText;
        } else if (fileOption === 'extension' && extensionFileValue) {
            groupingLookupTarget = extensionFileValue;
        } else if (fileOption === 'temporary' && temporaryFileValue) {
            groupingLookupTarget = temporaryFileValue;
        }

        if (groupingLookupTarget) {
            scheduleGroupingLookup(groupingLookupTarget);
        } else if (fileOption === 'normal' || captureMode === 'existing-not-found') {
            resetTrackingIdState();
        } else {
            resetTrackingIdState();
            setTrackingIdDisplay('Will be generated on save');
        }
    }

    function loadExistingFileNumbers() {
        var extensionSelect = document.getElementById('existingFileNo');
        var temporarySelect = document.getElementById('temporaryFileNo');
        if (!extensionSelect || !temporarySelect) return;

        fetch('""')
            .then(function (response) { return response.json(); })
            .then(function (data) {
                extensionSelect.innerHTML = '<option value="">Select existing file number...</option>';
                temporarySelect.innerHTML = '<option value="">Select existing file number...</option>';

                (Array.isArray(data) ? data : []).forEach(function (fileNo) {
                    var value = fileNo && fileNo.mlsfNo ? fileNo.mlsfNo : '';
                    if (!value) return;

                    var extensionOption = document.createElement('option');
                    extensionOption.value = value;
                    extensionOption.textContent = value;
                    extensionSelect.appendChild(extensionOption);

                    var temporaryOption = document.createElement('option');
                    temporaryOption.value = value;
                    temporaryOption.textContent = value;
                    temporarySelect.appendChild(temporaryOption);
                });
            })
            .catch(function (error) {
                console.error('Error loading existing file numbers:', error);
            });
    }

    function promptForAnotherTransaction(onYes) {
        return Swal.fire({
            icon: 'question',
            title: 'Another Transaction?',
            text: 'Does this file have another transaction?',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'No',
            reverseButtons: true,
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then(function (choice) {
            if (choice.isConfirmed) {
                if (typeof onYes === 'function') onYes();
                return;
            }

            window.location.reload();
        });
    }

    function submitCaptureForm(event) {
        event.preventDefault();

        var form = document.getElementById('captureForm');
        if (!form) return;

        var captureMode = ((form.querySelector('[name="ffr_capture_mode"]') || {}).value || '').toString();

        var submitBtn = form.querySelector('button[type="submit"]');
        var originalHtml = submitBtn ? submitBtn.innerHTML : '';

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span>Submitting...</span>';
        }

        setCaptureFormValue('lga', (form.querySelector('[name="lga"]') || {}).value || 'Unknown');

        // LGA is required when Application Type is Conversion
        var captureAppType = ((form.querySelector('input[name="capture_application_type"]:checked') || {}).value || 'direct').toString();
        if (captureAppType === 'conversion') {
            var lgaVal = ((document.getElementById('captureLga') || {}).value || '').toString().trim();
            if (!lgaVal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'LGA Required',
                    text: 'Please select an LGA for Conversion applications.',
                    confirmButtonColor: '#f59e0b'
                });
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalHtml;
                    if (window.lucide) window.lucide.createIcons();
                }
                return;
            }
        }

        var formData = new FormData(form);
        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        if (captureMode === 'existing-not-found') {
            var payload = {
                source_file_no: ((form.querySelector('[name="source_file_no"]') || {}).value || ffrState.sourceFileNo || '').toString().trim(),
                file_name: ((document.getElementById('captureFileName') || {}).value || '').toString().trim(),
                location: ((document.getElementById('captureLocation') || {}).value || '').toString().trim(),
                lga: ((form.querySelector('[name="lga"]') || {}).value || 'Unknown').toString().trim() || 'Unknown',
                customer_type: ((form.querySelector('#customerType') || form.querySelector('[name="customer_type"]') || {}).value || 'Individual').toString().trim() || 'Individual',
                land_use: ((document.getElementById('captureLandUse') || {}).value || '').toString().trim() || null,
                purpose: ((document.getElementById('capturePurpose') || {}).value || '').toString().trim() || null,
                purpose_id: ((document.getElementById('capturePurposeId') || {}).value || '').toString().trim() || null,
                phone_no: ((document.getElementById('capturePhoneNo') || {}).value || '').toString().trim() || null,
                address: ((document.getElementById('captureAddress') || {}).value || '').toString().trim() || null,
                plot_no: ((document.getElementById('capturePlotNo') || {}).value || '').toString().trim() || null,
                tp_no: ((document.getElementById('captureTpNo') || {}).value || '').toString().trim() || null,
                op_type: ((form.querySelector('[name="ffr_existing_op_type"]') || {}).value || ffrState.existingOpType || 'OP Resettlement').toString(),
                op_serial_number: ((form.querySelector('[name="op_serial_number"]') || {}).value || '').toString().trim() || null,
                registration_number: ((form.querySelector('[name="registration_number"]') || {}).value || '').toString().trim() || null,
                serial_no: ((form.querySelector('[name="serial_no"]') || {}).value || '').toString().trim() || null,
                page_no: ((form.querySelector('[name="page_no"]') || {}).value || '').toString().trim() || null,
                volume_no: ((form.querySelector('[name="volume_no"]') || {}).value || '').toString().trim() || null,
                party_1: ((form.querySelector('[name="party_1"]') || {}).value || '').toString().trim(),
                party_2: ((form.querySelector('[name="party_2"]') || {}).value || (document.getElementById('captureFileName') || {}).value || '').toString().trim() || null,
                transaction_date: ((form.querySelector('[name="transaction_date"]') || form.querySelector('[name="commission_date"]') || {}).value || '').toString().trim() || null,
                tracking_id: ((document.querySelector('#captureModal #trackingId') || {}).value || '').toString().trim() || null,
                ffr_first_pra_prop_id: ((form.querySelector('[name="ffr_first_pra_prop_id"]') || {}).value || '').toString().trim() || null,
                ffr_temp_fileno: ((form.querySelector('[name="ffr_temp_fileno"]') || {}).value || '').toString().trim() || null,
                from_op_flow: ((form.querySelector('[name="from_op_flow"]') || {}).value || '').toString().trim() || null,
            };

            if (payload.purpose_id !== null) {
                var parsedPurposeId = parseInt(payload.purpose_id, 10);
                payload.purpose_id = Number.isNaN(parsedPurposeId) ? null : parsedPurposeId;
            }

            if (!payload.source_file_no) {
                Swal.fire('Required', 'Source file number is required.', 'warning');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalHtml;
                    if (window.lucide) window.lucide.createIcons();
                }
                return;
            }

            if (!payload.transaction_date && payload.from_op_flow !== '1') {
                Swal.fire('Required', 'Transaction Date is required.', 'warning');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalHtml;
                    if (window.lucide) window.lucide.createIcons();
                }
                return;
            }

            fetch('""', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (!result.ok || !result.data || !result.data.success) {
                    throw new Error((result.data && result.data.message) ? result.data.message : 'Unable to capture existing OP file number.');
                }

                ffrToast('success', result.data.message || 'Existing OP capture synchronized successfully.');
                closeCaptureModal();

                return promptForAnotherTransaction(function () {
                    // Step 2 -> Step 3 handoff: keep FEFR flow open for additional transactions.
                    openFfrModal();
                    var sourceFileInput = document.getElementById('ffrSourceFileNo');
                    if (sourceFileInput) sourceFileInput.value = payload.source_file_no;
                    ffrState.sourceFileNo = payload.source_file_no;
                    ffrLoadSourceFromPra(payload.source_file_no);
                });
            })
            .catch(function (error) {
                Swal.fire('Error', error.message || 'Failed to capture existing OP file.', 'error');
            })
            .finally(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalHtml;
                    if (window.lucide) window.lucide.createIcons();
                }
            });

            return;
        }

        fetch('""', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        })
        .then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, data: data };
            });
        })
        .then(function (result) {
            if (!result.ok || !result.data || !result.data.success) {
                throw new Error((result.data && result.data.message) ? result.data.message : 'Unable to capture existing file number.');
            }

            return Swal.fire({
                icon: 'success',
                title: 'Captured',
                text: result.data.message || 'Existing file captured successfully.',
                timer: 1500,
                showConfirmButton: false
            }).then(function () {
                closeCaptureModal();

                return promptForAnotherTransaction(function () {
                    openFfrModal();
                });
            });
        })
        .catch(function (error) {
            Swal.fire('Error', error.message || 'Failed to capture existing file number.', 'error');
        })
        .finally(function () {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHtml;
                if (window.lucide) window.lucide.createIcons();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var serialNo = document.getElementById('serialNo');
        var year = document.getElementById('year');
        var prefix = document.getElementById('prefix');
        var middlePrefix = document.getElementById('middlePrefix');
        var existingFileNo = document.getElementById('existingFileNo');
        var extensionManualFile = document.getElementById('extensionManualFile');
        var temporaryFileNo = document.getElementById('temporaryFileNo');
        var temporaryManualFile = document.getElementById('temporaryManualFile');
        var fileOption = document.getElementById('fileOption');

        if (serialNo) serialNo.addEventListener('input', updateCapturePreview);
        if (year) year.addEventListener('input', updateCapturePreview);
        if (prefix) prefix.addEventListener('change', updateCapturePreview);
        if (middlePrefix) middlePrefix.addEventListener('input', updateCapturePreview);
        if (existingFileNo) existingFileNo.addEventListener('change', updateCapturePreview);
        if (extensionManualFile) extensionManualFile.addEventListener('input', updateCapturePreview);
        if (temporaryFileNo) temporaryFileNo.addEventListener('change', updateCapturePreview);
        if (temporaryManualFile) temporaryManualFile.addEventListener('input', updateCapturePreview);
        if (fileOption) {
            fileOption.addEventListener('change', function () {
                updateCaptureForm(this.value || 'normal');
            });
        }

        window.addEventListener('ffr-existing-op-captured', function (evt) {
            var details = evt && evt.detail ? evt.detail : {};
            var captureForm = document.getElementById('captureForm');
            if (!captureForm) return;

            // ── Merger prompt: only for indexed-file Direct OP captures ──────────────
            if (!details.was_ffrDirectOpMode) {
                // Non-direct-OP flow: original behaviour — populate Capture Existing and proceed
                var captureModalFallback = document.getElementById('captureModal');
                if (captureModalFallback && captureModalFallback.classList.contains('hidden') && details.source_file_no) {
                    if (typeof ffrOpenCaptureExistingForTransfer === 'function') {
                        ffrOpenCaptureExistingForTransfer(details.source_file_no, details);
                    }
                }
                var fileNameInputFallback = document.getElementById('captureFileName');
                if (fileNameInputFallback) { fileNameInputFallback.focus(); }
                if (typeof window.setCaptureLandUseAndLoadPurposes === 'function' && details.land_use) {
                    window.setCaptureLandUseAndLoadPurposes((details.land_use || '').toString().trim(), (details.purpose || '').toString().trim());
                }
                setCaptureFormValue('party_1', (details.allottee || details.second_party_name || '').toString().trim());
                setCaptureFormValue('party_2', '');
                setCaptureFormValue('from_op_flow', '1');
                if (details.ffr_first_pra_prop_id) { setCaptureFormValue('ffr_first_pra_prop_id', details.ffr_first_pra_prop_id.toString()); }
                if (details.ffr_temp_fileno) { setCaptureFormValue('ffr_temp_fileno', details.ffr_temp_fileno.toString()); }
                updateCapturePreview();
                ffrToast('success', 'OP data captured and sent to PRA. Enter File Name and submit.');
                return;
            }

            // ── Merger prompt: ask whether there is another OP for this file ─────────
            // Small delay lets closeRegistrationDialog() finish clearing its backdrop
            // so the Swal buttons are not obscured by a residual overlay.
            setTimeout(function () {
            Swal.fire({
                icon: 'question',
                title: 'OP Saved!',
                html: '<p>Is there <strong>another OP</strong> to add to this transfer? (Merger)</p>',
                showCancelButton: true,
                confirmButtonText: '<i class="fa fa-plus"></i> Yes, add another OP',
                cancelButtonText: '<i class="fa fa-arrow-right"></i> No, proceed to Transfer of Title',
                confirmButtonColor: '#7c3aed',
                cancelButtonColor: '#2563eb',
                reverseButtons: false,
            }).then(function (result) {

                // Accumulate this grantor
                var thisGrantor = (details.first_party_name || '').toString().trim();
                if (thisGrantor) {
                    window._mergerGrantors = window._mergerGrantors || [];
                    window._mergerGrantors.push(thisGrantor);
                }

                if (result.isConfirmed) {
                    // ── YES: another OP in the merger ─────────────────────────────────
                    if (!window._mergerGroupId) {
                        window._mergerGroupId = _ffrGenerateMergerUUID();
                    }
                    window._isOngoingMerger = true;

                    // Stamp merger_group_id on the just-saved OP row if we have its prop_id
                    var opPropId = details.ffr_first_pra_prop_id;
                    if (opPropId && window._mergerGroupId) {
                        var csrfStamp = document.querySelector('input[name="_token"]')?.value ||
                                        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                        fetch('/api/pra/v1/records/' + encodeURIComponent(opPropId), {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': csrfStamp,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ merger_group_id: window._mergerGroupId, is_merger_op: 1 }),
                        }).catch(function (e) { console.warn('Merger stamp failed:', e); });
                    }

                    // Reset OP form fields for next seller
                    _ffrResetOpFormForNextMergerOp();

                    // Re-open OP dialog in Direct OP mode
                    window.ffrDirectOpMode = true;
                    window.ffrExistingManualRegistration = true;
                    window.ffrExistingSourceFileNo = details.source_file_no || window.ffrExistingSourceFileNo || '';
                    if (typeof openRegistrationDialog === 'function') {
                        openRegistrationDialog('occupancy-permit');
                    }
                } else {
                    // ── NO: proceed to Transfer of Title ──────────────────────────────
                    // Build combined grantor string: "A, B, & C"
                    var grantors = window._mergerGrantors || [];
                    var combinedGrantor;
                    if (grantors.length > 1) {
                        combinedGrantor = grantors.slice(0, -1).join(', ') + ', & ' + grantors[grantors.length - 1];
                    } else {
                        combinedGrantor = grantors[0] || thisGrantor || (details.allottee || details.second_party_name || '').toString().trim();
                    }

                    // Reset merger state
                    window._mergerGrantors = [];
                    window._mergerGroupId = null;
                    window._isOngoingMerger = false;

                    // Open Capture Existing modal if needed
                    var captureModal = document.getElementById('captureModal');
                    if (captureModal && captureModal.classList.contains('hidden') && details.source_file_no) {
                        if (typeof ffrOpenCaptureExistingForTransfer === 'function') {
                            ffrOpenCaptureExistingForTransfer(details.source_file_no, details);
                        }
                    }

                    // Keep File Name manual in Capture Existing flow.
                    var fileNameInput = document.getElementById('captureFileName');
                    if (fileNameInput) {
                        fileNameInput.focus();
                    }

                    // Set land use & purpose via dropdown-aware helper (loads purposes via AJAX)
                    if (typeof window.setCaptureLandUseAndLoadPurposes === 'function' && details.land_use) {
                        window.setCaptureLandUseAndLoadPurposes(
                            (details.land_use || '').toString().trim(),
                            (details.purpose || '').toString().trim()
                        );
                    }

                    var plotInput = document.getElementById('capturePlotNo');
                    if (plotInput) {
                        plotInput.value = (details.plot_no || '').toString().trim();
                    }

                    var tpInput = document.getElementById('captureTpNo');
                    if (tpInput) {
                        tpInput.value = (details.tp_no || '').toString().trim();
                    }

                    var locationInput = document.getElementById('captureLocation');
                    if (locationInput) {
                        locationInput.value = (details.location || locationInput.value || '').toString().trim();
                    }

                    var lgaSelect = document.getElementById('captureLga');
                    if (lgaSelect && details.lga) {
                        lgaSelect.value = details.lga;
                    }

                    setCaptureFormValue('op_serial_number', (details.op_serial_number || '').toString().trim());
                    setCaptureFormValue('registration_number', (details.registration_number || '').toString().trim());
                    setCaptureFormValue('serial_no', (details.serial_no || '').toString().trim());
                    setCaptureFormValue('page_no', (details.page_no || '').toString().trim());
                    setCaptureFormValue('volume_no', (details.volume_no || '').toString().trim());
                    setCaptureFormValue('transaction_date', (details.transaction_date || '').toString().trim());
                    // For Transfer of Title: Party 1 = combined grantor (single or merged sellers)
                    // Party 2 is left empty for user to fill in the new holder name
                    setCaptureFormValue('party_1', combinedGrantor);
                    setCaptureFormValue('party_2', '');
                    // Flag that this submission originates from an OP capture flow (Transfer of Title)
                    setCaptureFormValue('from_op_flow', '1');

                    // Pass prop_id and temp_fileno from first PRA record for the second record
                    if (details.ffr_first_pra_prop_id) {
                        setCaptureFormValue('ffr_first_pra_prop_id', details.ffr_first_pra_prop_id.toString());
                    }
                    if (details.ffr_temp_fileno) {
                        setCaptureFormValue('ffr_temp_fileno', details.ffr_temp_fileno.toString());
                    }

                    updateCapturePreview();

                    // Show toast: OP modal closed, Capture Existing form is populated
                    ffrToast('success', 'OP data captured and sent to PRA. Enter File Name and submit.');
                }
            });
            }, 150); // end setTimeout — allow registration dialog backdrop to clear
        });

        // ── Merger helpers ────────────────────────────────────────────────────────
        function _ffrGenerateMergerUUID() {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                var r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        }

        function _ffrResetOpFormForNextMergerOp() {
            // Clear seller (Party 1 / Grantor) — each OP has a different seller
            var firstParty = document.getElementById('firstPartyName');
            if (firstParty) { firstParty.value = ''; firstParty.dispatchEvent(new Event('input')); }

            // Clear OP registration details
            var clearIds = ['op_serial_number', 'serial_no', 'reg_page_no', 'reg_page_no_display',
                            'volume_no', 'registration_number', 'registrationDate', 'registrationTime'];
            clearIds.forEach(function (id) {
                var el = document.getElementById(id);
                if (el) { el.value = ''; el.dispatchEvent(new Event('input')); }
            });

            // Uncheck all op_type radios
            document.querySelectorAll('input[name="op_type"]').forEach(function (r) { r.checked = false; });
        }

        // Initialise merger globals (reset on page load)
        window._mergerGroupId  = null;
        window._mergerGrantors = [];
        window._isOngoingMerger = false;
    });

    function openAcknowledgementModal(btn) {
        var data = _getRecordFromButton(btn);
        if (!data) { alert('Could not read record data.'); return; }

        var ackRecordId = data.source_instrument_capture_id || data.id || '';

        // Auto-fill readonly fields
        document.getElementById('ack_date').value         = data.date_captured || '';
        document.getElementById('ack_time').value         = data.time_captured || '';
        document.getElementById('ack_applicant_name').value = [data.party_2].filter(Boolean).join(' / ') || '';
        document.getElementById('ack_plot_no').value      = data.plot_no || '';
        document.getElementById('ack_plan_no').value      = data.plan_no || '';
        document.getElementById('ack_location').value     = data.property_description || '';
        document.getElementById('ack_record_id').value    = ackRecordId;

        // Clear editable fields
        document.getElementById('ack_sig_applicant_name').value = '';
        document.getElementById('ack_sig_applicant').value      = '';
        document.getElementById('ack_sig_chairman_name').value  = '';
        document.getElementById('ack_sig_chairman').value       = '';

        // Show modal
        document.getElementById('acknowledgementModal').classList.remove('hidden');

        // Reset print button to disabled
        var printBtn = document.getElementById('ackPrintBtn');
        if (printBtn) printBtn.disabled = true;

        // Re-create lucide icons inside the modal
        if (window.lucide) window.lucide.createIcons();
    }

    function closeAcknowledgementModal() {
        document.getElementById('acknowledgementModal').classList.add('hidden');
    }

    function printAcknowledgement() {
        var recordId = document.getElementById('ack_record_id').value;
        if (!recordId) { alert('No record selected.'); return; }
        window.open('""/' + recordId + '/print-acknowledgement', '_blank');
    }

    function saveAcknowledgement() {
        // Collect form data – endpoint can be wired when ready
        var payload = {
            record_id:      document.getElementById('ack_record_id').value,
            date:           document.getElementById('ack_date').value,
            time:           document.getElementById('ack_time').value,
            applicant_name: document.getElementById('ack_applicant_name').value,
            plot_no:        document.getElementById('ack_plot_no').value,
            plan_no:        document.getElementById('ack_plan_no').value,
            location:       document.getElementById('ack_location').value,
            sig_applicant_name: document.getElementById('ack_sig_applicant_name').value,
            sig_applicant:      document.getElementById('ack_sig_applicant').value,
            sig_chairman_name:  document.getElementById('ack_sig_chairman_name').value,
            sig_chairman:       document.getElementById('ack_sig_chairman').value
        };

        if (!payload.sig_applicant_name) {
            Swal.fire({ icon: 'warning', title: 'Required', text: 'Please enter the Name of the Applicant for signature.' });
            return;
        }

        // TODO: POST to backend endpoint when available
        console.log('Acknowledgement payload:', payload);
        if (payload.record_id) {
            localStorage.setItem('oss_ack_generated_' + payload.record_id, '1');
        }
        Swal.fire({ icon: 'success', title: 'Saved', text: 'Acknowledgement data captured successfully.', timer: 2000, showConfirmButton: false });
        var printBtn = document.getElementById('ackPrintBtn');
        if (printBtn) printBtn.disabled = false;
    }

    /* ═══════════════════════════════════════════════════════════════════
       Recommendation Modal – open / close / print / save
       ═══════════════════════════════════════════════════════════════════ */
    function setRecommendationFieldsLocked(locked) {
        var editableIds = [
            'rec_term',
            'rec_dev_value',
            'rec_completion_time',
            'rec_ground_rent',
            'rec_dev_charge',
            'rec_survey_charges',
            'rec_director_reasons',
            'rec_director_sign',
            'rec_director_date',
            'rec_ps_sign',
            'rec_ps_date',
            'rec_commissioner_name',
            'rec_commissioner_date'
        ];

        editableIds.forEach(function(id) {
            var el = document.getElementById(id);
            if (!el) return;
            el.disabled = !!locked;
        });

        var radios = document.querySelectorAll('input[name="rec_approval_status"]');
        radios.forEach(function(r) {
            r.disabled = !!locked;
        });
    }

    function openRecommendationModal(btn) {
        var data = _getRecordFromButton(btn);
        if (!data) { alert('Could not read record data.'); return; }

        // Auto-fill readonly fields
        document.getElementById('rec_applicant_name').value = data.party_2 || data.party_1 || '';
        document.getElementById('rec_file_ref').value       = data.file_no || '';
        document.getElementById('rec_purpose').value        = data.land_use || '';
        document.getElementById('rec_location').value       = data.property_description || '';
        document.getElementById('rec_plot_no').value        = data.plot_no || '';
        document.getElementById('rec_plan_no').value        = data.plan_no || '';
        document.getElementById('rec_record_id').value      = data.id || '';

        // Application date & address for land recommendation record
        var appDate = (data.created_at || '').toString().substring(0, 10);
        document.getElementById('rec_application_date').value   = appDate || '';
        document.getElementById('rec_applicant_address').value  = data.address || data.residential_address || data.correspondence_address || '';

        // Perm Sec section auto-fill (mirrors the main fields)
        document.getElementById('rec_ps_plot').value        = data.plot_no || '';
        document.getElementById('rec_ps_location').value    = data.property_description || '';

        // Clear editable fields
        document.getElementById('rec_term').value            = '';
        document.getElementById('rec_dev_value').value       = '';
        document.getElementById('rec_completion_time').value = '';
        document.getElementById('rec_ground_rent').value     = '';
        document.getElementById('rec_dev_charge').value      = 'To Follow';
        document.getElementById('rec_survey_charges').value  = '';
        document.getElementById('rec_director_reasons').value = '';
        document.getElementById('rec_director_sign').value   = '';
        document.getElementById('rec_director_date').value   = '';
        document.getElementById('rec_ps_sign').value         = '';
        document.getElementById('rec_ps_date').value         = '';
        document.getElementById('rec_commissioner_name').value = '';
        document.getElementById('rec_commissioner_date').value = '';
        // Reset radio
        var radios = document.querySelectorAll('input[name="rec_approval_status"]');
        radios.forEach(function(r){ r.checked = false; });

        // Show modal
        document.getElementById('recommendationModal').classList.remove('hidden');

        // Default action state before lookup
        var saveBtn = document.getElementById('recSaveBtn');
        var printBtn = document.getElementById('recPrintBtn');
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.classList.remove('opacity-40', 'cursor-not-allowed');
        }
        if (printBtn) printBtn.disabled = true;
        setRecommendationFieldsLocked(false);

        var fileRef = (data.file_no || '').toString().trim();
        if (fileRef) {
            fetch('/lands-one-stop-shop/applications/recommendation-status?file_ref=' + encodeURIComponent(fileRef), {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            })
            .then(function (res) { return res.json(); })
            .then(function (result) {
                if (!result || !result.success || !result.data || !result.data.exists) {
                    return;
                }

                var rec = result.data;
                document.getElementById('rec_term').value = rec.term || '';
                document.getElementById('rec_dev_value').value = rec.dev_value || '';
                document.getElementById('rec_completion_time').value = rec.completion_time || '';
                document.getElementById('rec_ground_rent').value = rec.ground_rent || '';
                document.getElementById('rec_dev_charge').value = rec.dev_charge || 'To Follow';
                document.getElementById('rec_survey_charges').value = rec.survey_charges || '';
                document.getElementById('rec_director_reasons').value = rec.director_reasons || '';

                if (saveBtn) {
                    saveBtn.disabled = true;
                    saveBtn.classList.add('opacity-40', 'cursor-not-allowed');
                }
                if (printBtn) printBtn.disabled = false;
                setRecommendationFieldsLocked(true);
            })
            .catch(function () {
                // Keep default new-record behavior on lookup failure
                setRecommendationFieldsLocked(false);
            });
        }

        // Re-create lucide icons inside the modal
        if (window.lucide) window.lucide.createIcons();
    }

    function closeRecommendationModal() {
        document.getElementById('recommendationModal').classList.add('hidden');
    }

    function printRecommendation() {
        var fields = {
            applicant_name:    document.getElementById('rec_applicant_name').value,
            file_ref:          document.getElementById('rec_file_ref').value,
            purpose:           document.getElementById('rec_purpose').value,
            location:          document.getElementById('rec_location').value,
            plot_no:           document.getElementById('rec_plot_no').value,
            plan_no:           document.getElementById('rec_plan_no').value,
            term:              document.getElementById('rec_term').value,
            dev_value:         document.getElementById('rec_dev_value').value,
            completion_time:   document.getElementById('rec_completion_time').value,
            ground_rent:       document.getElementById('rec_ground_rent').value,
            dev_charge:        document.getElementById('rec_dev_charge').value,
            survey_charges:    document.getElementById('rec_survey_charges').value,
            director_reasons:  document.getElementById('rec_director_reasons').value,
            director_sign:     document.getElementById('rec_director_sign').value,
            director_date:     document.getElementById('rec_director_date').value,
            ps_plot:           document.getElementById('rec_ps_plot') ? document.getElementById('rec_ps_plot').value : '',
            ps_location:       document.getElementById('rec_ps_location') ? document.getElementById('rec_ps_location').value : '',
            ps_sign:           document.getElementById('rec_ps_sign').value,
            ps_date:           document.getElementById('rec_ps_date').value,
            commissioner_name: document.getElementById('rec_commissioner_name').value,
            commissioner_date: document.getElementById('rec_commissioner_date').value,
            approval_status:   (document.querySelector('input[name="rec_approval_status"]:checked') || {}).value || ''
        };

        // Submit via hidden form POST to open in new tab
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '""';
        form.target = '_blank';

        // CSRF token
        var csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = '""';
        form.appendChild(csrf);

        // Append all fields
        for (var key in fields) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = fields[key];
            form.appendChild(input);
        }

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }

    /* ═══════════════════════════════════════════════════════════════════
       Bill Modal – open / close / save  (OP Resettlement page)
       ═══════════════════════════════════════════════════════════════════ */
    var _opBillRates = {
        residential:  { Change_of_Name: 25000, Processing_Fee: 3000,  survey_fee: 10000, Application_Form: 2000  },
        commercial:   { Change_of_Name: 25000, Processing_Fee: 20000, survey_fee: 20000, Application_Form: 10000 },
        industrial:   { Change_of_Name: 25000, Processing_Fee: 30000, survey_fee: 30000, Application_Form: 20000 },
        agricultural: { Change_of_Name: 25000, Processing_Fee: 3000,  survey_fee: 10000, Application_Form: 2000  }
    };

    function _opFormatMoney(n) {
        return '₦' + Number(n).toLocaleString('en-NG');
    }

    /** Title-case a name: "GREGG ANDERSON" → "Gregg Anderson" */
    function _opTitleCase(str) {
        if (!str || str === '—') return str;
        return str.replace(/\S+/g, function (w) {
            return w.charAt(0).toUpperCase() + w.slice(1).toLowerCase();
        });
    }

    /** Check if a land_use value is valid/known */
    function _opIsValidLandUse(val) {
        return val && val !== '—' && _opBillRates.hasOwnProperty(val);
    }

    /** Recalculate total from current DOM input values */
    function _ossRecalcTotal() {
        function _parseAmt(id) {
            var v = (document.getElementById(id).value || '0').replace(/[^0-9]/g, '');
            return parseInt(v, 10) || 0;
        }
        var total = _parseAmt('ossBill_change_of_name') + _parseAmt('ossBill_processing_fee')
                  + _parseAmt('ossBill_survey_deposit') + _parseAmt('ossBill_application_form');
        document.getElementById('ossBill_total').textContent = _opFormatMoney(total);
    }

    /** Called when the density dropdown changes (Residential only) */
    function ossOnDensityChange(val) {
        document.getElementById('ossBill_processing_fee').value = val ? Number(val).toLocaleString('en-NG') : '';
        _ossRecalcTotal();
    }

    /** Fill bill amounts for a given land use type */
    function _opFillBillAmounts(landUse) {
        var rates = _opBillRates[landUse] || _opBillRates['residential'];
        document.getElementById('ossBill_application_type').value = landUse;
        document.getElementById('ossBill_change_of_name').value  = Number(rates.Change_of_Name).toLocaleString('en-NG');
        document.getElementById('ossBill_survey_deposit').value   = Number(rates.survey_fee).toLocaleString('en-NG');
        document.getElementById('ossBill_application_form').value = Number(rates.Application_Form).toLocaleString('en-NG');

        // Density row: show for residential (user picks density → sets processing fee)
        var densityRow    = document.getElementById('ossBill_density_row');
        var densitySelect = document.getElementById('ossBill_density_select');
        if (landUse === 'residential') {
            document.getElementById('ossBill_processing_fee').value = '';
            if (densityRow)    densityRow.classList.remove('hidden');
            if (densitySelect) densitySelect.value = '';
        } else {
            document.getElementById('ossBill_processing_fee').value = Number(rates.Processing_Fee).toLocaleString('en-NG');
            if (densityRow)    densityRow.classList.add('hidden');
        }
        _ossRecalcTotal();
    }

    /** Called when the land_use dropdown changes */
    function ossBillLandUseChanged(val) {
        if (!val) return;
        _opFillBillAmounts(val);

        // Update subtitle land use label
        var sub = document.getElementById('ossBillSubtitle');
        if (sub) {
            sub.textContent = sub.textContent.replace(/\([^)]*\)$/, '(' + val.charAt(0).toUpperCase() + val.slice(1) + ')');
        }

        // Update header colour
        var headerColours = {
            residential: 'from-green-600 to-green-700', commercial: 'from-blue-600 to-blue-700',
            industrial: 'from-red-600 to-red-700', agricultural: 'from-emerald-600 to-emerald-700'
        };
        var hdr = document.getElementById('ossBillModalHeader');
        if (hdr) hdr.className = 'flex items-center justify-between px-6 py-4 border-b border-slate-200 bg-gradient-to-r ' + (headerColours[val] || headerColours['residential']);
    }

    // Store  the current OP record id for land_use update
    var _opBillCurrentRecordId = null;

    function openBillModalForOP(btn) {
        var data = _getRecordFromButton(btn);
        if (!data) { alert('Could not read record data.'); return; }

        _opBillCurrentRecordId = data.id;
        var sourceCaptureId = Number(data.source_instrument_capture_id || 0);
        _opBillCurrentRecordId = sourceCaptureId > 0 ? sourceCaptureId : data.id;
        var rawLandUse = (data.land_use || '').trim();
        var landUse    = rawLandUse.toLowerCase();
        var hasLandUse = _opIsValidLandUse(landUse);

        document.getElementById('ossBill_application_id').value = data.id;

        // Land use dropdown: show if missing, hide if present
        var dropdownRow = document.getElementById('ossBill_landuse_row');
        var ddSelect    = document.getElementById('ossBill_landuse_select');
        if (hasLandUse) {
            dropdownRow.classList.add('hidden');
            _opFillBillAmounts(landUse);
        } else {
            dropdownRow.classList.remove('hidden');
            ddSelect.value = '';
            // Clear amounts until user selects
            document.getElementById('ossBill_application_type').value = '';
            document.getElementById('ossBill_change_of_name').value  = '';
            document.getElementById('ossBill_processing_fee').value  = '';
            document.getElementById('ossBill_survey_deposit').value   = '';
            document.getElementById('ossBill_application_form').value = '';
            document.getElementById('ossBill_total').textContent      = '₦0';
            // Also hide density row until land use is chosen
            var densityRowClear = document.getElementById('ossBill_density_row');
            if (densityRowClear) densityRowClear.classList.add('hidden');
        }

        // Title-case names
        var name = [data.party_2]
            .filter(function(v){ return v && v !== '—' && v.toUpperCase() !== ''; })
            .map(_opTitleCase)
            .join(' / ') || '';
        var typeLabel = hasLandUse ? landUse.charAt(0).toUpperCase() + landUse.slice(1) : 'Unknown';
        document.getElementById('ossBillSubtitle').textContent = '#' + data.id + ' — ' + name + ' (' + typeLabel + ')';

        var headerColours = {
            residential: 'from-green-600 to-green-700', commercial: 'from-blue-600 to-blue-700',
            industrial: 'from-red-600 to-red-700', agricultural: 'from-emerald-600 to-emerald-700'
        };
        var hdr = document.getElementById('ossBillModalHeader');
        if (hdr) hdr.className = 'flex items-center justify-between px-6 py-4 border-b border-slate-200 bg-gradient-to-r ' + (headerColours[landUse] || 'from-amber-600 to-amber-700');

        document.getElementById('ossBillModal').classList.remove('hidden');

        // Resolve bill status and set action button mode
        var billBtn = document.getElementById('ossBillSaveBtn');
        if (billBtn) {
            billBtn.dataset.mode = 'save';
            billBtn.innerHTML = '<i data-lucide="save" class="w-4 h-4 inline-block mr-1 -mt-0.5"></i> Save Bill';
        }

        fetch('/lands-one-stop-shop/applications/' + data.id + '/bill-status', {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(function(res) { return res.json(); })
        .then(function(result) {
            if (!billBtn) return;
            if (result && result.success && result.data && result.data.exists) {
                billBtn.dataset.mode = 'print';
                billBtn.innerHTML = '<i data-lucide="printer" class="w-4 h-4 inline-block mr-1 -mt-0.5"></i> Print Bill';
            } else {
                billBtn.dataset.mode = 'save';
                billBtn.innerHTML = '<i data-lucide="save" class="w-4 h-4 inline-block mr-1 -mt-0.5"></i> Save Bill';
            }
            if (window.lucide) window.lucide.createIcons();
        })
        .catch(function() {
            // Keep default save mode on lookup failure
        });

        if (window.lucide) window.lucide.createIcons();
    }

    function ossCloseBillModal() {
        document.getElementById('ossBillModal').classList.add('hidden');
    }

    function ossSaveBill() {
        var appId   = document.getElementById('ossBill_application_id').value;
        var landUse = document.getElementById('ossBill_application_type').value;
        var saveBtn = document.getElementById('ossBillSaveBtn');
        var mode = (saveBtn && saveBtn.dataset && saveBtn.dataset.mode) ? saveBtn.dataset.mode : 'save';

        if (!appId) return;

        // Existing bill -> print directly
        if (mode === 'print') {
            ossCloseBillModal();
            window.open('/lands-one-stop-shop/bill/' + appId + '/print', '_blank');
            return;
        }

        if (!landUse) {
            Swal.fire({ icon: 'warning', title: 'Land Use Required', text: 'Please select a land use / purpose before printing the bill.' });
            return;
        }

        if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Preparing…'; }

        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        // If land_use was selected from dropdown, update instrument_capture first
        var dropdownRow = document.getElementById('ossBill_landuse_row');
        var needsLandUseUpdate = !dropdownRow.classList.contains('hidden');

        var saveBillFn = function() {
            fetch('/lands-one-stop-shop/applications/' + appId + '/bill', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ land_use: landUse })
            })
            .then(function (res) { return res.json().then(function(body) { return { ok: res.ok, body: body }; }); })
            .then(function (result) {
                if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = '<i data-lucide="printer" class="w-4 h-4 inline-block mr-1 -mt-0.5"></i> Print Bill'; saveBtn.dataset.mode = 'print'; }
                if (result.body.success) {
                    ossCloseBillModal();
                    Swal.fire({ icon: 'success', title: 'Bill Ready', text: result.body.message, timer: 1200, showConfirmButton: false });
                    window.open('/lands-one-stop-shop/bill/' + appId + '/print', '_blank');
                    setTimeout(function(){ location.reload(); }, 1500);
                } else {
                    // If bill already exists, switch to print behavior instead of hard error
                    if ((result.body.message || '').toLowerCase().indexOf('already been generated') !== -1) {
                        saveBtn.dataset.mode = 'print';
                        ossCloseBillModal();
                        window.open('/lands-one-stop-shop/bill/' + appId + '/print', '_blank');
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: result.body.message || 'Could not prepare bill.' });
                    }
                }
                if (window.lucide) window.lucide.createIcons();
            })
            .catch(function (err) {
                console.error('OP bill error:', err);
                if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = '<i data-lucide="save" class="w-4 h-4 inline-block mr-1 -mt-0.5"></i> Save Bill'; saveBtn.dataset.mode = 'save'; }
                Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
            });
        };

        if (needsLandUseUpdate) {
            var updateTargetId = Number(_opBillCurrentRecordId || 0);
            if (!updateTargetId) {
                if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = '<i data-lucide="save" class="w-4 h-4 inline-block mr-1 -mt-0.5"></i> Save Bill'; saveBtn.dataset.mode = 'save'; }
                Swal.fire({ icon: 'error', title: 'Error', text: 'Could not resolve source OP record for land use update.' });
                return;
            }

            // Update instrument_capture land_use first, then save bill
            fetch('/lands-one-stop-shop/applications/op-resettlement/' + updateTargetId + '/update-land-use', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ land_use: landUse })
            })
            .then(function (res) { return res.json().then(function(body) { return { ok: res.ok, body: body }; }); })
            .then(function (result) {
                if (result.body.success) {
                    saveBillFn();
                } else {
                    if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = '<i data-lucide="save" class="w-4 h-4 inline-block mr-1 -mt-0.5"></i> Save Bill'; saveBtn.dataset.mode = 'save'; }
                    Swal.fire({ icon: 'error', title: 'Error', text: result.body.message || 'Could not update land use.' });
                }
            })
            .catch(function (err) {
                console.error('Update land_use error:', err);
                if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = '<i data-lucide="save" class="w-4 h-4 inline-block mr-1 -mt-0.5"></i> Save Bill'; saveBtn.dataset.mode = 'save'; }
                Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
            });
        } else {
            saveBillFn();
        }
    }

    function saveRecommendation() {
        var saveBtn = document.getElementById('recSaveBtn');
        if (saveBtn && saveBtn.disabled) {
            return;
        }

        // Validate the 6 required editable fields (3–8)
        var validationFields = [
            { id: 'rec_term',            label: '3. Term (years)' },
            { id: 'rec_dev_value',       label: '4. Value for Proposed Development' },
            { id: 'rec_completion_time', label: '5. Time for Completion' },
            { id: 'rec_ground_rent',     label: '6. Annual Ground Rent' },
            { id: 'rec_dev_charge',      label: '7. Development Charge' },
            { id: 'rec_survey_charges',  label: '8. Survey & Processing Charges' }
        ];
        var missing = [];
        validationFields.forEach(function(f) {
            var el = document.getElementById(f.id);
            if (!el || !el.value.trim()) {
                missing.push(f.label);
                if (el) el.classList.add('border-red-400', 'ring-1', 'ring-red-300');
            } else {
                if (el) el.classList.remove('border-red-400', 'ring-1', 'ring-red-300');
            }
        });
        if (missing.length) {
            Swal.fire({
                icon: 'warning',
                title: 'Required Fields Missing',
                html: '<div class="text-left text-sm">Please fill in:<br><ul class="mt-2 space-y-1 list-disc pl-5">' +
                      missing.map(function(l){ return '<li>' + l + '</li>'; }).join('') +
                      '</ul></div>',
                confirmButtonColor: '#0f172a'
            });
            return;
        }

        var payload = {
            record_id:      document.getElementById('rec_record_id').value,
            applicant_name: document.getElementById('rec_applicant_name').value,
            file_ref:       document.getElementById('rec_file_ref').value,
            purpose:        document.getElementById('rec_purpose').value,
            location:       document.getElementById('rec_location').value,
            plot_no:        document.getElementById('rec_plot_no').value,
            plan_no:        document.getElementById('rec_plan_no').value,
            term:           document.getElementById('rec_term').value,
            dev_value:      document.getElementById('rec_dev_value').value,
            completion_time:document.getElementById('rec_completion_time').value,
            ground_rent:    document.getElementById('rec_ground_rent').value,
            dev_charge:     document.getElementById('rec_dev_charge').value,
            survey_charges: document.getElementById('rec_survey_charges').value,
            director_reasons: document.getElementById('rec_director_reasons').value,
            director_sign:  document.getElementById('rec_director_sign').value,
            director_date:  document.getElementById('rec_director_date').value,
            ps_sign:        document.getElementById('rec_ps_sign').value,
            ps_date:        document.getElementById('rec_ps_date').value,
            commissioner_name: document.getElementById('rec_commissioner_name').value,
            commissioner_date: document.getElementById('rec_commissioner_date').value,
            approval_status: (document.querySelector('input[name="rec_approval_status"]:checked') || {}).value || ''
        };

        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.classList.add('opacity-40', 'cursor-not-allowed');
        }

        fetch('/lands-one-stop-shop/applications/save-recommendation', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(function (res) {
            return res.json().then(function (body) {
                return { ok: res.ok, body: body };
            });
        })
        .then(function (result) {
            if (!result.ok || !result.body || !result.body.success) {
                throw new Error((result.body && result.body.message) ? result.body.message : 'Failed to save recommendation.');
            }

            Swal.fire({ icon: 'success', title: 'Saved', text: result.body.message || 'Recommendation saved successfully.', timer: 1800, showConfirmButton: false });
            var printBtn = document.getElementById('recPrintBtn');
            if (printBtn) printBtn.disabled = false;

            // Once it exists in table, Save remains disabled for this file reference.
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.classList.add('opacity-40', 'cursor-not-allowed');
            }
            setRecommendationFieldsLocked(true);
        })
        .catch(function (err) {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.classList.remove('opacity-40', 'cursor-not-allowed');
            }
            setRecommendationFieldsLocked(false);
            Swal.fire({ icon: 'error', title: 'Error', text: err.message || 'Could not save recommendation.' });
        });
    }

    /* ═══════════════════════════════════════════════════════════════════
       Print Bill / Verification / Change of Name actions
       ═══════════════════════════════════════════════════════════════════ */
    function printBillForOP(btn) {
        var data = _getRecordFromButton(btn);
        if (!data) { alert('Could not read record data.'); return; }

        fetch('/lands-one-stop-shop/applications/' + data.id + '/bill-status', {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(function(res) { return res.json(); })
        .then(function(result) {
            var hasBill = !!(result && result.success && result.data && result.data.exists);
            if (hasBill) {
                var printId = result.data.bill_id || data.id;
                window.open('""/' + printId + '/print', '_blank');
                return;
            }

            Swal.fire({
                icon: 'info',
                title: 'Bill Not Generated',
                text: 'Generate the bill first before printing.'
            });
            openBillModalForOP(btn);
        })
        .catch(function() {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Could not verify bill status. Please try again.' });
        });
    }

    function openCommissioningPrinterManagerForOP(btn) {
        // Kept as alias for backward compatibility; delegates to the new Print Manager modal.
        openPrintManagerModal(btn);
    }

    function openCommissioningSheetForOP(btn) {
        var data = _getRecordFromButton(btn);
        if (!data) {
            Swal.fire({ icon: 'warning', title: 'Missing Data', text: 'Could not read record details.' });
            return;
        }

        var fileNo = (data.file_no || data.mls_file_no || data.mlsfNo || '').toString().trim();
        if (!fileNo) {
            Swal.fire({ icon: 'warning', title: 'Missing File Number', text: 'This record has no file number.' });
            return;
        }

        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        var payload = {
            file_number: fileNo,
            file_name: String(data.party_2 || data.file_title || '').trim(),
            name_or_allottee: String(data.party_2 || data.file_title || '').trim(),
            plot_number: String(data.plot_no || '').trim(),
            tp_number: String(data.tp_no || data.plan_no || '').trim(),
            location: String(data.location || data.property_description || data.district || '').trim(),
            lga: String(data.lga || '').trim(),
            created_by: String(data.commissioned_by || '').trim(),
            commissioning_time: String(data.time_commissioned || '').trim(),
            date_created: String(data.date_commissioned || '').trim()
        };

        fetch('""', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(function(res) { return res.json(); })
        .then(function(result) {
            if (!result || !result.success || !result.data || !result.data.id) {
                Swal.fire({ icon: 'error', title: 'Error', text: (result && result.message) ? result.message : 'Failed to prepare commissioning sheet.' });
                return;
            }
            var printUrl = '""/' + result.data.id + '?source=oss';
            if (result.data.commissioning_time) {
                printUrl += '&commissioning_time=' + encodeURIComponent(result.data.commissioning_time);
            }
            window.open(printUrl, '_blank');
        })
        .catch(function(err) {
            console.error('Commissioning sheet error:', err);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
        });
    }

    /* ═══════════════════════════════════════════════════════════════════
       Verification Modal – open / close / save / print
       ═══════════════════════════════════════════════════════════════════ */
    function openVerificationForOP(btn) {
        var data = _getRecordFromButton(btn);
        if (!data) { alert('Could not read record data.'); return; }

        // Auto-fill readonly fields
        document.getElementById('ver_applicant_name').value  =  data.party_1 || '';
        document.getElementById('ver_original_allottee').value = data.party_1 || '';
        document.getElementById('ver_op_no').value           = data.op_serial_number || '';
        document.getElementById('ver_plot_plan').value       = (data.plot_no || '') + ' / ' + (data.plan_no || '');
        document.getElementById('ver_location').value        = data.property_description || data.district || '';
        document.getElementById('ver_record_id').value       = data.id || '';

        // Clear editable fields
        _ossClearSingleAddressBuilder('ver_addr', 'ver_address');
        document.getElementById('ver_phone').value         = '';
        document.getElementById('ver_email').value         = '';
        document.getElementById('ver_chairman_name').value = '';
        document.querySelectorAll('input[name="ver_id_type"]').forEach(function(r){ r.checked = false; });
        document.querySelectorAll('input[name="ver_recommendation"]').forEach(function(r){ r.checked = false; });

        // Reset passport photo
        verRemovePassport();

        document.getElementById('verificationModal').classList.remove('hidden');
        var printBtn = document.getElementById('verPrintBtn');
        if (printBtn) printBtn.disabled = true;
        if (window.lucide) window.lucide.createIcons();
    }

    function closeVerificationModal() {
        document.getElementById('verificationModal').classList.add('hidden');
    }

    /* ── Passport photo preview helpers ── */
    function verPreviewPassport(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                var img = document.getElementById('ver_passport_img');
                img.src = e.target.result;
                img.classList.remove('hidden');
                document.getElementById('ver_passport_placeholder').classList.add('hidden');
                document.getElementById('ver_passport_remove').classList.remove('hidden');
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    function verRemovePassport() {
        var input = document.getElementById('ver_passport_input');
        if (input) input.value = '';
        var img = document.getElementById('ver_passport_img');
        if (img) { img.src = ''; img.classList.add('hidden'); }
        var ph = document.getElementById('ver_passport_placeholder');
        if (ph) ph.classList.remove('hidden');
        var rm = document.getElementById('ver_passport_remove');
        if (rm) rm.classList.add('hidden');
    }

    /* ── Residential passport photo preview helpers ── */
    function ossResPreviewPassport(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                var img = document.getElementById('oss_res_passport_img');
                img.src = e.target.result;
                img.classList.remove('hidden');
                document.getElementById('oss_res_passport_placeholder').classList.add('hidden');
                document.getElementById('oss_res_passport_remove').classList.remove('hidden');
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    function ossResRemovePassport() {
        var input = document.getElementById('oss_res_passport_input');
        if (input) input.value = '';
        var img = document.getElementById('oss_res_passport_img');
        if (img) { img.src = ''; img.classList.add('hidden'); }
        var ph = document.getElementById('oss_res_passport_placeholder');
        if (ph) ph.classList.remove('hidden');
        var rm = document.getElementById('oss_res_passport_remove');
        if (rm) rm.classList.add('hidden');
    }

    function saveVerification() {
        var applicantName = document.getElementById('ver_applicant_name').value;
        if (!applicantName) {
            Swal.fire({ icon: 'warning', title: 'Required', text: 'Applicant name is required.' });
            return;
        }

        var formData = new FormData();
        formData.append('record_id',        document.getElementById('ver_record_id').value);
        formData.append('applicant_name',   applicantName);
        formData.append('address',          document.getElementById('ver_address').value);
        formData.append('phone',            document.getElementById('ver_phone').value);
        formData.append('email',            document.getElementById('ver_email').value);
        formData.append('id_type',          (document.querySelector('input[name="ver_id_type"]:checked') || {}).value || '');
        formData.append('original_allottee',document.getElementById('ver_original_allottee').value);
        formData.append('op_number',        document.getElementById('ver_op_no').value);
        formData.append('plot_plan',        document.getElementById('ver_plot_plan').value);
        formData.append('location',         document.getElementById('ver_location').value);
        formData.append('recommendation',   (document.querySelector('input[name="ver_recommendation"]:checked') || {}).value || '');
        formData.append('chairman_name',    document.getElementById('ver_chairman_name').value);

        var passportInput = document.getElementById('ver_passport_input');
        if (passportInput.files && passportInput.files[0]) {
            formData.append('passport_photo', passportInput.files[0]);
        }

        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        fetch('""', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: formData
        })
        .then(function(res) { return res.json(); })
        .then(function(result) {
            if (result.success) {
                Swal.fire({ icon: 'success', title: 'Saved', text: result.message || 'Verification data saved.', timer: 2000, showConfirmButton: false });
                var printBtn = document.getElementById('verPrintBtn');
                if (printBtn) printBtn.disabled = false;
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: result.message || 'Could not save verification.' });
            }
        })
        .catch(function(err) {
            console.error('Save verification error:', err);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
        });
    }

    function printVerification() {
        var fields = {
            applicant_name:   document.getElementById('ver_applicant_name').value,
            applicant_address:document.getElementById('ver_address').value,
            applicant_phone:  document.getElementById('ver_phone').value,
            applicant_email:  document.getElementById('ver_email').value,
            id_type:          (document.querySelector('input[name="ver_id_type"]:checked') || {}).value || '',
            original_allottee:document.getElementById('ver_original_allottee').value,
            op_number:        document.getElementById('ver_op_no').value,
            plot_no:          document.getElementById('ver_plot_plan').value.split('/')[0]?.trim() || '',
            plan_no:          document.getElementById('ver_plot_plan').value.split('/')[1]?.trim() || '',
            location:         document.getElementById('ver_location').value,
            recommendation:   (document.querySelector('input[name="ver_recommendation"]:checked') || {}).value || '',
            chairman_name:    document.getElementById('ver_chairman_name').value,
            record_id:        document.getElementById('ver_record_id').value
        };

        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '""';
        form.target = '_blank';
        var csrf = document.createElement('input');
        csrf.type = 'hidden'; csrf.name = '_token'; csrf.value = '""';
        form.appendChild(csrf);
        for (var key in fields) {
            var input = document.createElement('input');
            input.type = 'hidden'; input.name = key; input.value = fields[key];
            form.appendChild(input);
        }
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }

    /* ═══════════════════════════════════════════════════════════════════
       Change of Ownership Modal – open / close / save / print
       ═══════════════════════════════════════════════════════════════════ */
    function openChangeOfNameForOP(btn) {
        var data = _getRecordFromButton(btn);
        if (!data) { alert('Could not read record data.'); return; }

        // Auto-fill readonly fields
        document.getElementById('coo_op_number').value = data.op_serial_number || '';
        document.getElementById('coo_location').value  = data.property_description || data.district || '';
        document.getElementById('coo_plot_no').value   = data.plot_no || '';
        document.getElementById('coo_plan_no').value   = data.plan_no || '';
        document.getElementById('coo_record_id').value = data.id || '';

        // Pre-fill original allottee from record
        document.getElementById('coo_original_name').value = data.party_2 || '';

        // Clear editable fields
        document.getElementById('coo_date_of_issuance').value = '';
        _ossClearSingleAddressBuilder('coo_orig_addr', 'coo_original_address');
        document.getElementById('coo_original_phone').value   = '';
        document.getElementById('coo_current_name').value     = '';
        _ossClearSingleAddressBuilder('coo_curr_addr', 'coo_current_address');
        document.getElementById('coo_current_phone').value    = '';
        document.querySelectorAll('input[name="coo_ownership_method"]').forEach(function(r){ r.checked = false; });

        document.getElementById('changeOfOwnershipModal').classList.remove('hidden');
        var printBtn = document.getElementById('cooPrintBtn');
        if (printBtn) printBtn.disabled = true;
        if (window.lucide) window.lucide.createIcons();
    }

    function closeChangeOfOwnershipModal() {
        document.getElementById('changeOfOwnershipModal').classList.add('hidden');
    }

    function saveChangeOfOwnership() {
        var payload = {
            record_id:        document.getElementById('coo_record_id').value,
            op_number:        document.getElementById('coo_op_number').value,
            location:         document.getElementById('coo_location').value,
            plot_no:          document.getElementById('coo_plot_no').value,
            plan_no:          document.getElementById('coo_plan_no').value,
            date_of_issuance: document.getElementById('coo_date_of_issuance').value,
            original_name:    document.getElementById('coo_original_name').value,
            original_address: document.getElementById('coo_original_address').value,
            original_phone:   document.getElementById('coo_original_phone').value,
            current_name:     document.getElementById('coo_current_name').value,
            current_address:  document.getElementById('coo_current_address').value,
            current_phone:    document.getElementById('coo_current_phone').value,
            ownership_method: (document.querySelector('input[name="coo_ownership_method"]:checked') || {}).value || ''
        };

        if (!payload.current_name) {
            Swal.fire({ icon: 'warning', title: 'Required', text: 'Please enter the current owner name.' });
            return;
        }

        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        fetch('""', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function(res) { return res.json(); })
        .then(function(result) {
            if (result.success) {
                Swal.fire({ icon: 'success', title: 'Saved', text: result.message || 'Change of ownership saved.', timer: 2000, showConfirmButton: false });
                var printBtn = document.getElementById('cooPrintBtn');
                if (printBtn) printBtn.disabled = false;
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: result.message || 'Could not save change of ownership.' });
            }
        })
        .catch(function(err) {
            console.error('Save change of ownership error:', err);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
        });
    }

    function printChangeOfOwnership() {
        var fields = {
            file_no:          document.getElementById('coo_record_id').value,
            op_number:        document.getElementById('coo_op_number').value,
            location:         document.getElementById('coo_location').value,
            plot_no:          document.getElementById('coo_plot_no').value,
            plan_no:          document.getElementById('coo_plan_no').value,
            date_of_issuance: document.getElementById('coo_date_of_issuance').value,
            original_name:    document.getElementById('coo_original_name').value,
            original_address: document.getElementById('coo_original_address').value,
            original_phone:   document.getElementById('coo_original_phone').value,
            current_name:     document.getElementById('coo_current_name').value,
            current_address:  document.getElementById('coo_current_address').value,
            current_phone:    document.getElementById('coo_current_phone').value,
            ownership_method: (document.querySelector('input[name="coo_ownership_method"]:checked') || {}).value || ''
        };

        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '""';
        form.target = '_blank';
        var csrf = document.createElement('input');
        csrf.type = 'hidden'; csrf.name = '_token'; csrf.value = '""';
        form.appendChild(csrf);
        for (var key in fields) {
            var input = document.createElement('input');
            input.type = 'hidden'; input.name = key; input.value = fields[key];
            form.appendChild(input);
        }
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }
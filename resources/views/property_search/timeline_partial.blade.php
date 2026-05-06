{{--
  timeline_partial.blade.php
  Renders the timeline content WITHOUT the full-page layout.
  Loaded via AJAX by property-timeline-modal.js when ?mode=partial is passed.
--}}
<style>
    .tl-source-tag {
        display: inline-flex;
        align-items: center;
        padding: 0.15rem 0.5rem;
        border-radius: 9999px;
        font-size: 0.6rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        white-space: nowrap;
        border: 1px solid transparent;
    }
    .tl-source-tag-file_history_staging { background:#dbeafe;color:#1e40af;border-color:#bfdbfe; }
    .tl-source-tag-CofO_staging         { background:#d1fae5;color:#065f46;border-color:#a7f3d0; }
    .tl-source-tag-pra                  { background:#fef3c7;color:#92400e;border-color:#fde68a; }
    .tl-source-tag-deed_registrations   { background:#ede9fe;color:#5b21b6;border-color:#ddd6fe; }

    .ptl-summary-grid { display:grid; grid-template-columns: repeat(2,1fr); gap:1rem; margin-bottom:1.25rem; }
    @media(min-width:640px){ .ptl-summary-grid { grid-template-columns: repeat(4,1fr); } }

    .ptl-timeline-line { position:absolute; left:1.25rem; top:0; bottom:0; width:2px; background:#e5e7eb; z-index:0; }
    .ptl-timeline-wrap { position:relative; padding-left:3rem; }
    .ptl-dot { position:absolute; left:0.6rem; width:1.25rem; height:1.25rem; border-radius:50%; border:2px solid; display:flex; align-items:center; justify-content:center; z-index:1; background:#fff; }
    .ptl-card { background:#fff; border:1px solid #e5e7eb; border-radius:0.5rem; padding:1rem 1.25rem; box-shadow:0 1px 3px rgba(0,0,0,.07); }
</style>

{{-- Modal header with title and close button --}}
<div class="flex items-center justify-between px-5 py-3 border-b border-gray-200 bg-white sticky top-0 z-10">
    <div class="flex items-center gap-2">
        <i class="fas fa-history text-indigo-500"></i>
        <h3 class="text-base font-semibold text-gray-800">Property Timeline</h3>
    </div>
    <button type="button" data-dismiss="modal"
        class="text-gray-400 hover:text-gray-600 transition-colors rounded-full p-1 hover:bg-gray-100"
        title="Close">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>
</div>

<div class="p-4 space-y-5" id="ptl-container">
    {{-- Summary strip --}}
    <div class="ptl-summary-grid">
        <div class="bg-gray-50 rounded-lg p-3 text-center border border-gray-100">
            <p class="text-xs text-gray-500 mb-1">File Number</p>
            <p class="font-semibold text-gray-800 text-sm truncate" id="ptl-file-number">—</p>
        </div>
        <div class="bg-gray-50 rounded-lg p-3 text-center border border-gray-100">
            <p class="text-xs text-gray-500 mb-1">Prop ID</p>
            <p class="font-semibold text-gray-800 text-sm" id="ptl-prop-id">—</p>
        </div>
        <div class="bg-gray-50 rounded-lg p-3 text-center border border-gray-100">
            <p class="text-xs text-gray-500 mb-1">Total Transactions</p>
            <p class="font-semibold text-gray-800 text-sm" id="ptl-total">0</p>
        </div>
        <div class="bg-gray-50 rounded-lg p-3 text-center border border-gray-100">
            <p class="text-xs text-gray-500 mb-1">Location</p>
            <p class="font-semibold text-gray-800 text-sm truncate" id="ptl-location">—</p>
        </div>
    </div>

    {{-- Source breakdown badges --}}
    <div class="flex flex-wrap gap-2 pb-1" id="ptl-source-badges"></div>

    {{-- Timeline items split: Weighted vs Non-Weighted --}}
    <div id="ptl-empty" class="text-center py-10 text-gray-400 hidden">
        <i class="fas fa-history fa-2x mb-3 opacity-40"></i>
        <p>No transactions found for this property.</p>
    </div>

    <div id="ptl-weighted-section" class="space-y-3">
        <div class="flex items-center justify-between">
            <h4 class="text-sm font-semibold text-gray-800"></h4>
            <span id="ptl-weighted-count" class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700">0</span>
        </div>
        <div class="relative ptl-timeline-wrap">
            <div class="ptl-timeline-line"></div>
            <div class="space-y-5" id="ptl-items-weighted"></div>
        </div>
    </div>

    <div id="ptl-nonweighted-section" class="space-y-3 pt-2 border-t border-gray-100">
        <div class="flex items-center justify-between">
            <h4 class="text-sm font-semibold text-gray-800">Suporting Records</h4>
            <span id="ptl-nonweighted-count" class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-700">0</span>
        </div>
        <div class="relative ptl-timeline-wrap">
            <div class="ptl-timeline-line"></div>
            <div class="space-y-5" id="ptl-items-nonweighted"></div>
        </div>
    </div>

    {{-- Link to full-page timeline --}}
    <div class="text-right pt-2 border-t border-gray-100">
        <a id="ptl-fullpage-link" href="#" target="_blank"
           class="text-sm text-indigo-600 hover:text-indigo-800 inline-flex items-center gap-1">
            <i class="fas fa-external-link-alt text-xs"></i> Open full timeline
        </a>
    </div>
</div>

<script>
(function () {
    const payload = @json($historyPayload);

    const e = (v) => {
        if (v === null || v === undefined || String(v).trim() === '') return '<span class="text-gray-300">—</span>';
        return String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    };

    const sourceLabel = {
        'file_history_staging': 'File History',
        'CofO_staging': 'CofO',
        'pra': 'PRA',
        'deed_registrations': 'Deed Reg.',
    };

    const dotColors = {
        'file_history_staging': '#3b82f6',
        'CofO_staging': '#10b981',
        'pra': '#f59e0b',
        'deed_registrations': '#8b5cf6',
    };

    // Populate summary
    document.getElementById('ptl-file-number').textContent = payload.fileNumber || '—';
    document.getElementById('ptl-prop-id').textContent    = payload.propId     || '—';
    document.getElementById('ptl-total').textContent      = payload.totalTransactions || 0;
    document.getElementById('ptl-location').textContent   = payload.location   || '—';

    // Full-page link
    const params = new URLSearchParams();
    if (payload.propId)      params.set('prop_id',     payload.propId);
    if (payload.fileNumber)  params.set('file_number', payload.fileNumber);
    const fullLink = document.getElementById('ptl-fullpage-link');
    if (fullLink) fullLink.href = '/property-search/timeline?' + params.toString();

    const txns = payload.transactions || [];
    const badgesEl = document.getElementById('ptl-source-badges');
    const totalEl = document.getElementById('ptl-total');

    const renderSourceBadges = (rows) => {
        if (!badgesEl) return;
        badgesEl.innerHTML = '';

        if (!rows.length) return;

        const counts = {};
        rows.forEach((t) => {
            const src = t?.source_table || '';
            counts[src] = (counts[src] || 0) + 1;
        });

        Object.entries(counts).forEach(([src, cnt]) => {
            const span = document.createElement('span');
            span.className = `tl-source-tag tl-source-tag-${src}`;
            span.textContent = `${sourceLabel[src] || src}: ${cnt}`;
            badgesEl.appendChild(span);
        });
    };

    const normalize = (value) => String(value || '')
        .trim()
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .replace(/[.,]/g, '');

    const cleanNumericValue = (value) => {
        const s = String(value || '').trim();
        if (!s || s === '-' || s.toLowerCase() === 'null' || s.toLowerCase() === 'n/a') return '';
        return s.replace(/\s+/g, '');
    };

    // Same canonicalization used in the Legal Search weighting method.
    const canonicalInstrumentType = (value) => {
        const raw = normalize(value);
        if (!raw || raw === '-') return raw;

        if (raw.includes('right of occupancy') || raw.includes('right of occupanc')) return 'right of occupancy';
        if (/^r\s*of\s*o$/.test(raw)) return 'right of occupancy';
        const compact = raw.replace(/[^a-z0-9]/g, '');
        if (/^r[o0]f[o0]$/.test(compact) || /^r[o0]f[o0]occupanc/.test(compact)) return 'right of occupancy';
        if (raw === 'customary right of occupancy' || raw === 'statutory right of occupancy') return 'right of occupancy';

        if (raw.includes('certificate of occupancy') || raw.includes('cert of occupancy')) return 'certificate of occupancy';
        if (/^c\s*of\s*o$/.test(raw) || /^c[o0]f[o0]$/.test(compact)) return 'certificate of occupancy';

        if (raw.includes('occupancy permit')) return 'occupancy permit';
        if (/^o\s*p$/.test(raw) || compact === 'op') return 'occupancy permit';

        if (raw.includes('transfer of title')) return 'transfer of title';

        // Mortgage aliases — collapse file-history shorthand ("mortgage") and
        // PRA variants ("tripartite/legal/equitable mortgage", "deed of mortgage")
        // to a single canonical so reg-particulars dedup can pair them up.
        if (raw === 'mortgage' || raw === 'deed of mortgage' ||
            raw === 'tripartite mortgage' || raw === 'legal mortgage' || raw === 'equitable mortgage') {
            return 'deed of mortgage';
        }

        // Assignment aliases — "assignment", "deed assignment", "deed of
        // assignment" (and any trivial spacing / missing "of") all describe the
        // same instrument. Matching on the word "assignment" safely groups
        // PRA and file-history entries that share reg particulars.
        if (/\bassignment\b/.test(raw) &&
            !raw.includes('sub-assignment') &&
            !raw.includes('re-assignment') &&
            !raw.includes('reassignment')) {
            return 'deed of assignment';
        }

        if (raw === 'deed of surrender' || raw === 'deed of release' || raw === 'deed of surrender & release') {
            return 'deed of surrender and release';
        }

        // Power of Attorney variants → power of attorney
        // Collapses "Power Of Attorney", "Irrevocable Power Of Attorney",
        // "Deed Of Power Of Attorney", "POA", etc. to one canonical key so
        // copies of the same instrument across PRA/FH dedupe correctly.
        if (raw.includes('power of attorney')) return 'power of attorney';
        if (compact === 'poa' || compact === 'ipoa') return 'power of attorney';

        return raw;
    };

    const recordRichnessScore = (item) => {
        if (!item) return 0;
        const hasText = (v) => v !== null && v !== undefined && String(v).trim() !== '' && String(v).trim() !== '-';
        const hasReg  = (v) => {
            if (v === null || v === undefined) return false;
            const s = String(v).trim();
            if (s === '' || s === '-' || s === '0') return false;
            return true;
        };
        let score = 0;
        if (hasText(item.party_1 || item.primary_party) || hasText(item.party_2 || item.secondary_party) || hasText(item.party_3)) score += 2;
        const serial = item.serial_no ?? item.serialNo ?? '';
        const page   = item.page_no   ?? item.pageNo   ?? '';
        const volume = item.volume_no ?? item.volumeNo ?? '';
        if (hasReg(serial) || hasReg(page) || hasReg(volume)) score += 2;
        const txDate = item.sort_date ?? item.transaction_date ?? item.display_date ?? '';
        if (hasText(txDate)) score += 2;
        const regTime = item.reg_time ?? item.deeds_time ?? item.transaction_time ?? '';
        if (hasText(regTime)) score += 2;
        const regDate = item.reg_date ?? item.deeds_date ?? '';
        if (hasText(regDate)) score += 2;
        return score;
    };

    const sourceBaseScore = (row) => {
        const source = String(row?.source_table || '').trim();
        const transType = canonicalInstrumentType(row?.transaction_type || '');

        // Requested precedence
        if (transType === 'occupancy permit') return 10;
        if (transType === 'transfer of title') return 9.5;
        if (transType === 'right of occupancy') return 9;
        if (source === 'CofO_staging' || transType === 'certificate of occupancy') return 8;

        // Existing baseline for other records
        if (source === 'pra') return 5;
        if (source === 'file_history_staging') return 2.5;
        return 1;
    };

    // Same key strategy as Legal Search:
    // - PRA, File History, CofO and Deed Registration participate in dedup
    // - reg particulars first
    // - fallback party/date key, with ROFO date ignored
    const recordKey = (row) => {
        const source = String(row?.source_table || '').trim();
        const dedupableSources = ['file_history_staging', 'pra', 'CofO_staging', 'deed_registrations'];
        if (!dedupableSources.includes(source)) return null;

        const transType = canonicalInstrumentType(row?.transaction_type || '');
        if (!transType) return null;

        const serialNo = cleanNumericValue(row?.serial_no) || '0';
        const pageNo   = cleanNumericValue(row?.page_no) || '0';
        const volumeNo = cleanNumericValue(row?.volume_no) || '0';
        const hasRealReg = (serialNo !== '0' && serialNo !== '-') ||
                           (pageNo !== '0' && pageNo !== '-') ||
                           (volumeNo !== '0' && volumeNo !== '-');

        if (hasRealReg) {
            return 'reg|' + transType + '|' + serialNo + '/' + pageNo + '/' + volumeNo;
        }

        const party1 = normalize(row?.party_1 || row?.primary_party || '');
        const party2 = normalize(row?.party_2 || row?.secondary_party || '');
        const party3 = normalize(row?.party_3 || '');
        const party4 = normalize(row?.party_4 || '');
        const date = transType === 'right of occupancy'
            ? ''
            : normalize(row?.sort_date || row?.transaction_date || row?.display_date || '');

        const hasSignal = [transType, party1, party2, date].some(Boolean);
        if (!hasSignal) return null;

        return [transType, party1, party2, party3, party4, date].join('|');
    };

    const classifyWeighting = (rows) => {
        const deduped = [];
        const keyToIndex = new Map();
        const keyToAllRows = new Map();

        rows.forEach((row, idx) => {
            row._ptl_idx = idx;
            // Match Legal Search behavior: rows outside PRA/FH dedup groups are unique/weighted.
            row._ptl_weighting_status = 'unique';
            row._ptl_weighting_score = sourceBaseScore(row);

            const key = recordKey(row);
            if (!key) {
                deduped.push(row);
                return;
            }

            if (!keyToAllRows.has(key)) keyToAllRows.set(key, []);
            keyToAllRows.get(key).push(row);

            const existingIndex = keyToIndex.get(key);
            if (existingIndex === undefined) {
                keyToIndex.set(key, deduped.length);
                deduped.push(row);
                return;
            }

            const existing = deduped[existingIndex];
            const rowRichness = recordRichnessScore(row);
            const existingRichness = recordRichnessScore(existing);

            if (rowRichness > existingRichness) {
                deduped[existingIndex] = row;
            } else if (rowRichness === existingRichness && sourceBaseScore(row) > sourceBaseScore(existing)) {
                deduped[existingIndex] = row;
            }
        });

        const winnerRows = new Set(deduped);
        keyToAllRows.forEach((groupRows) => {
            const isDuplicated = groupRows.length > 1;
            groupRows.forEach((row) => {
                const isWinner = winnerRows.has(row);
                row._ptl_weighting_status = isDuplicated
                    ? (isWinner ? 'preferred' : 'duplicate')
                    : 'unique';
            });
        });
    };

    // Timeline items
    const weightedItemsEl = document.getElementById('ptl-items-weighted');
    const nonWeightedItemsEl = document.getElementById('ptl-items-nonweighted');
    const weightedSectionEl = document.getElementById('ptl-weighted-section');
    const nonWeightedSectionEl = document.getElementById('ptl-nonweighted-section');
    const weightedCountEl = document.getElementById('ptl-weighted-count');
    const nonWeightedCountEl = document.getElementById('ptl-nonweighted-count');
    const emptyEl  = document.getElementById('ptl-empty');

    if (!txns.length) {
        emptyEl.classList.remove('hidden');
        if (weightedSectionEl) weightedSectionEl.classList.add('hidden');
        if (nonWeightedSectionEl) nonWeightedSectionEl.classList.add('hidden');
        renderSourceBadges([]);
        if (totalEl) totalEl.textContent = '0';
        return;
    }

    classifyWeighting(txns);

    // Weighted = preferred/unique surviving rows.
    // Supporting = duplicate rows that lost to a preferred winner (still shown
    // in their own section so users can see the full history).
    const weightedTxns = txns.filter(t => t._ptl_weighting_status === 'preferred' || t._ptl_weighting_status === 'unique');
    const nonWeightedTxns = txns.filter(t => t._ptl_weighting_status === 'duplicate');

    const parseTxnTime = (t) => {
        const candidate = t?.sort_date || t?.transaction_date || t?.display_date || '';
        const d = new Date(candidate);
        return Number.isNaN(d.getTime()) ? null : d.getTime();
    };

    const sortByPriorityThenDate = (rows) => {
        return [...rows].sort((a, b) => {
            const ra = recordRichnessScore(a);
            const rb = recordRichnessScore(b);
            if (ra !== rb) return rb - ra;

            const wa = sourceBaseScore(a);
            const wb = sourceBaseScore(b);
            if (wa !== wb) return wb - wa;

            const ta = parseTxnTime(a);
            const tb = parseTxnTime(b);
            if (ta === null && tb === null) return (Number(a.id) || 0) - (Number(b.id) || 0);
            if (ta === null) return 1;
            if (tb === null) return -1;
            if (ta !== tb) return ta - tb;

            return (Number(a.id) || 0) - (Number(b.id) || 0);
        });
    };

    const weightedTxnsSorted = sortByPriorityThenDate(weightedTxns);
    const nonWeightedTxnsSorted = sortByPriorityThenDate(nonWeightedTxns);
    const displayedTxns = weightedTxnsSorted.concat(nonWeightedTxnsSorted);

    // Keep summary cards consistent with what the modal actually renders.
    renderSourceBadges(displayedTxns);
    if (totalEl) totalEl.textContent = String(displayedTxns.length);

    if (weightedCountEl) weightedCountEl.textContent = String(weightedTxnsSorted.length);
    if (nonWeightedCountEl) nonWeightedCountEl.textContent = String(nonWeightedTxnsSorted.length);

    if (weightedSectionEl) weightedSectionEl.classList.toggle('hidden', weightedTxnsSorted.length === 0);
    if (nonWeightedSectionEl) nonWeightedSectionEl.classList.toggle('hidden', nonWeightedTxnsSorted.length === 0);

    const renderTxnCard = (t) => {
        const color  = dotColors[t.source_table] || '#6b7280';
        const label  = sourceLabel[t.source_table] || t.source_table;
        const srcCls = `tl-source-tag-${t.source_table}`;

        const parties = [
            t.primary_party   || t.party_1 || t.grantor || '',
            t.secondary_party || t.party_2 || t.grantee || '',
            t.party_3 || '',
        ].filter(Boolean).join(' → ');

        const regPart = t.reg_no || t.regNo
            ? `<span class="text-xs text-gray-400 ml-2">Reg: ${e(t.reg_no || t.regNo)}</span>`
            : '';

        return `
        <div class="relative">
            <div class="ptl-dot" style="border-color:${color}; top: 0.75rem;">
                <span style="width:6px;height:6px;border-radius:50%;background:${color};display:block;"></span>
            </div>
            <div class="ptl-card">
                <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="tl-source-tag ${srcCls}">${e(label)}</span>
                        <span class="text-sm font-semibold text-gray-800">${e(t.transaction_type)}</span>
                        ${regPart}
                    </div>
                    <span class="text-xs text-gray-500 whitespace-nowrap">${e(t.display_date || t.transaction_date)}</span>
                </div>
                ${parties ? `<p class="text-sm text-gray-600">${e(parties)}</p>` : ''}
                ${t.location ? `<p class="text-xs text-gray-400 mt-1"><i class="fas fa-map-pin mr-1"></i>${e(t.location)}</p>` : ''}
            </div>
        </div>`;
    };

    weightedTxnsSorted.forEach((t) => {
        if (weightedItemsEl) weightedItemsEl.insertAdjacentHTML('beforeend', renderTxnCard(t));
    });

    nonWeightedTxnsSorted.forEach((t) => {
        if (nonWeightedItemsEl) nonWeightedItemsEl.insertAdjacentHTML('beforeend', renderTxnCard(t));
    });
})();
</script>

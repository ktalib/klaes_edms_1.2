@extends('layouts.app')

@section('page-title') {{ __($pageTitle ?? 'Related File Numbers') }} @endsection

@section('content')
<div class="flex-1 overflow-auto bg-slate-50 text-slate-900">
    @include('admin.header')

    <div class="max-w-7xl mx-auto px-6 lg:px-10 pt-5 pb-10 space-y-4">

        <!-- Header -->
        <div class="bg-gradient-to-r from-white via-orange-50 to-orange-100/60 border border-orange-100 rounded-2xl px-5 py-3 shadow-sm flex items-center justify-between gap-4">
            <div>
                <h1 class="text-lg font-semibold text-orange-900 leading-tight">Related File Numbers</h1>
            </div>
            <div class="text-right shrink-0">
                <p class="text-[0.6rem] uppercase tracking-widest text-orange-500">Total</p>
                <p class="text-lg font-semibold text-slate-800 leading-none">{{ number_format($totals->total_rows ?? 0) }}</p>
            </div>
        </div>

        <!-- Filters + table card -->
        <div class="bg-white rounded-3xl shadow-xl p-6 space-y-6">
            <!-- Filters -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <div class="md:col-span-3">
                    <label class="block text-xs font-medium text-slate-600 mb-1">Search</label>
                    <input id="rfn-search" type="text"
                        class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-300 focus:border-orange-400"
                        placeholder="related_fileno, file_number, title, party, comment" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Comment</label>
                    <select id="rfn-comment-type" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-300">
                        <option value="">All</option>
                        <option value="kangis">KANGIS Recertification</option>
                        <option value="mlpp">MLPP Recertification</option>
                        <option value="none">No comment</option>
                    </select>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto border border-slate-200 rounded-2xl">
                <table class="w-full text-sm" id="rfn-table">
                    <thead class="bg-slate-50 text-slate-700 text-xs uppercase tracking-wide">
                        <tr>
                            <th class="px-3 py-2 text-left">ID</th>
                            <th class="px-3 py-2 text-left whitespace-nowrap">Related File No</th>
                            <th class="px-3 py-2 text-left whitespace-nowrap">Parent File</th>
                            <th class="px-3 py-2 text-left">File Title</th>
                            <th class="px-3 py-2 text-left">Location</th>
                            <th class="px-3 py-2 text-left">Comment</th>
                        </tr>
                    </thead>
                    <tbody id="rfn-tbody" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-sm text-slate-600">
                <div id="rfn-summary">Loading…</div>
                <div class="flex items-center gap-2">
                    <button id="rfn-prev" class="px-3 py-1.5 rounded-lg border border-slate-300 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed">Prev</button>
                    <span id="rfn-page" class="font-medium text-slate-700">1 / 1</span>
                    <button id="rfn-next" class="px-3 py-1.5 rounded-lg border border-slate-300 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
                    <select id="rfn-per-page" class="border border-slate-300 rounded-lg px-2 py-1 text-sm">
                        <option value="25">25</option>
                        <option value="50" selected>50</option>
                        <option value="100">100</option>
                        <option value="200">200</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const tbody   = document.getElementById('rfn-tbody');
    const summary = document.getElementById('rfn-summary');
    const pageLbl = document.getElementById('rfn-page');
    const prevBtn = document.getElementById('rfn-prev');
    const nextBtn = document.getElementById('rfn-next');
    const search  = document.getElementById('rfn-search');
    const cType   = document.getElementById('rfn-comment-type');
    const perSel  = document.getElementById('rfn-per-page');

    let page = 1;
    let lastPage = 1;
    const endpoint = "{{ route('related-file-number.records') }}";

    const e = (s) => String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

    const commentClass = (c) => {
        if (!c) return '';
        if (c.startsWith('KANGIS')) return 'text-orange-700 font-medium';
        if (c.startsWith('MINISTRY')) return 'text-amber-700 font-medium';
        return '';
    };

    const fileNumberCell = (val) => {
        const v = e(val || '-');
        // Visually mark KANGIS-style related file numbers in orange
        if (val && /^(KNML|MLKN|KNGP|KN\s)/i.test(val)) {
            return `<span class="px-1.5 py-0.5 rounded bg-orange-50 text-orange-700 border border-orange-200 text-xs font-semibold">${v}</span>`;
        }
        return v;
    };

    const render = (rows) => {
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="px-3 py-8 text-center text-slate-400">No results.</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(r => `
            <tr class="hover:bg-orange-50/40">
                <td class="px-3 py-2 text-xs text-slate-400 align-top">${r.id}</td>
                <td class="px-3 py-2 align-top whitespace-nowrap">${fileNumberCell(r.related_fileno)}</td>
                <td class="px-3 py-2 align-top text-slate-700 whitespace-nowrap">${e(r.file_number || '-')}</td>
                <td class="px-3 py-2 align-top text-slate-700">${e(r.file_title || r.party_2 || '-')}</td>
                <td class="px-3 py-2 align-top text-slate-500 max-w-xs truncate" title="${e(r.location || '')}">${e(r.location || '-')}</td>
                <td class="px-3 py-2 align-top ${commentClass(r.comment)}">${e(r.comment || '-')}</td>
            </tr>
        `).join('');
    };

    const load = async () => {
        tbody.innerHTML = '<tr><td colspan="6" class="px-3 py-8 text-center text-slate-400">Loading…</td></tr>';
        const url = new URL(endpoint, window.location.origin);
        url.searchParams.set('page', page);
        url.searchParams.set('per_page', perSel.value);
        if (search.value.trim()) url.searchParams.set('search', search.value.trim());
        if (cType.value) url.searchParams.set('comment_type', cType.value);

        try {
            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            const json = await res.json();
            render(json.data || []);
            const meta = json.meta || {};
            lastPage = meta.last_page || 1;
            pageLbl.textContent = `${meta.page || 1} / ${lastPage}`;
            const from = ((meta.page - 1) * meta.per_page) + 1;
            const to = Math.min(meta.page * meta.per_page, meta.total);
            summary.textContent = meta.total ? `Showing ${from.toLocaleString()}–${to.toLocaleString()} of ${meta.total.toLocaleString()}` : 'No results.';
            prevBtn.disabled = (meta.page || 1) <= 1;
            nextBtn.disabled = (meta.page || 1) >= lastPage;
        } catch (err) {
            tbody.innerHTML = '<tr><td colspan="6" class="px-3 py-8 text-center text-red-500">Failed to load results.</td></tr>';
        }
    };

    // Debounce search
    let t;
    search.addEventListener('input', () => {
        clearTimeout(t);
        t = setTimeout(() => { page = 1; load(); }, 300);
    });
    cType.addEventListener('change', () => { page = 1; load(); });
    perSel.addEventListener('change', () => { page = 1; load(); });
    prevBtn.addEventListener('click', () => { if (page > 1) { page--; load(); } });
    nextBtn.addEventListener('click', () => { if (page < lastPage) { page++; load(); } });

    load();
})();
</script>
@endsection

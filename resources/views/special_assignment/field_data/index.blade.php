@extends('layouts.app')
@section('page-title'){{ $PageTitle ?? 'Special Assignment – Field Data' }}@endsection

@section('content')
<div class="flex-1 overflow-auto">
    @include('admin.header')
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Field Data</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $PageDescription ?? '' }}</p>
        </div>

        {{-- Tabs --}}
        <div class="flex gap-1 bg-gray-100 rounded-lg p-1 mb-6 w-fit">
            <button data-tab="field-map" class="tab-btn active px-5 py-2 text-sm font-medium rounded-md transition-all duration-200">
                <span class="flex items-center gap-2"><i data-lucide="map" class="h-4 w-4"></i> Field Map</span>
            </button>
            <button data-tab="records" class="tab-btn px-5 py-2 text-sm font-medium rounded-md text-gray-500 transition-all duration-200">
                <span class="flex items-center gap-2"><i data-lucide="file-text" class="h-4 w-4"></i> Records</span>
            </button>
        </div>

        {{-- Field Map Tab --}}
        <div id="tab-field-map" class="tab-content">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4" style="min-height:560px;">

                {{-- Sidebar --}}
                <div class="md:col-span-1">
                    <div class="bg-white border border-gray-200 rounded-lg shadow-sm h-full">
                        <div class="p-4 border-b">
                            <h2 class="text-base font-semibold flex items-center gap-2">
                                <i data-lucide="layers" class="h-5 w-5 text-[rgb(186,191,12)]"></i> GIS Tools
                            </h2>
                        </div>
                        <div class="p-4 space-y-5 text-sm">

                            {{-- Base Map --}}
                            <div>
                                <h3 class="font-medium text-gray-700 mb-2">Base Map</h3>
                                <select id="baseMapSelect" class="w-full p-2 border border-gray-200 rounded-md text-sm">
                                    <option value="streets">Streets</option>
                                    <option value="satellite" selected>Satellite</option>
                                    <option value="terrain">Terrain</option>
                                </select>
                            </div>

                            <hr class="border-gray-100">

                            {{-- Land Use Filter --}}
                            <div>
                                <h3 class="font-medium text-gray-700 mb-2">Land Use Filter</h3>
                                <select id="landUseFilter" class="w-full p-2 border border-gray-200 rounded-md text-sm">
                                    <option value="all">All Land Uses</option>
                                    <option value="RESIDENTIAL">Residential</option>
                                    <option value="COMMERCIAL">Commercial</option>
                                    <option value="AGRICULTURAL">Agricultural</option>
                                    <option value="INDUSTRIAL">Industrial</option>
                                </select>
                            </div>

                            <hr class="border-gray-100">

                            {{-- Legend --}}
                            <div>
                                <h3 class="font-medium text-gray-700 mb-2">Legend</h3>
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-sm flex-shrink-0" style="background:#0f766e"></div><span class="text-xs text-gray-600">Residential</span></div>
                                    <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-sm flex-shrink-0" style="background:#0e7490"></div><span class="text-xs text-gray-600">Commercial</span></div>
                                    <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-sm flex-shrink-0" style="background:#b45309"></div><span class="text-xs text-gray-600">Agricultural</span></div>
                                    <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-sm flex-shrink-0" style="background:#7e22ce"></div><span class="text-xs text-gray-600">Industrial</span></div>
                                    <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-sm flex-shrink-0" style="background:#4b5563"></div><span class="text-xs text-gray-600">Other</span></div>
                                </div>
                            </div>

                            <hr class="border-gray-100">

                            <div class="text-xs text-gray-400">
                                <span id="map-point-count">{{ count($mapPoints) }}</span> inspection point(s) plotted
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Map Area --}}
                <div class="md:col-span-3 flex flex-col">
                    <div class="bg-white border border-gray-200 rounded-lg shadow-sm flex-1 flex flex-col overflow-hidden">
                        {{-- Map Header --}}
                        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                            <h2 class="text-sm font-semibold flex items-center gap-2">
                                <i data-lucide="map" class="h-4 w-4 text-[rgb(186,191,12)]"></i>
                                Special Assignment – Field Inspection Map
                            </h2>
                            <div class="flex items-center gap-1">
                                <button id="map-zoom-in" title="Zoom In" class="p-1.5 border border-gray-200 rounded hover:bg-gray-50"><i data-lucide="plus" class="h-4 w-4"></i></button>
                                <button id="map-zoom-out" title="Zoom Out" class="p-1.5 border border-gray-200 rounded hover:bg-gray-50"><i data-lucide="minus" class="h-4 w-4"></i></button>
                                <button id="map-fullscreen" title="Fullscreen" class="p-1.5 border border-gray-200 rounded hover:bg-gray-50"><i data-lucide="maximize" class="h-4 w-4"></i></button>
                                <button id="map-reset" title="Reset View" class="p-1.5 border border-gray-200 rounded hover:bg-gray-50"><i data-lucide="rotate-cw" class="h-4 w-4"></i></button>
                            </div>
                        </div>
                        {{-- Map --}}
                        <div id="field-map" class="flex-1 relative" style="min-height:480px;">
                            <div id="map-loading" class="absolute inset-0 flex items-center justify-center bg-gray-50 z-10">
                                <div class="text-center">
                                    <div class="animate-spin rounded-full h-10 w-10 border-b-2 mx-auto mb-3" style="border-color:rgb(186,191,12)"></div>
                                    <p class="text-sm text-gray-500">Loading map…</p>
                                </div>
                            </div>
                        </div>
                        {{-- Zoom indicator --}}
                        <div class="px-3 py-1.5 border-t border-gray-100 text-xs text-gray-400 flex justify-between">
                            <span>Zoom: <span id="map-zoom-level">11</span></span>
                            <span>Kano State, Nigeria</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Records Tab --}}
        <div id="tab-records" class="tab-content hidden">
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-700">Field Records</h2>
                </div>
                <div class="p-4">
                    <table id="field-records-table" class="w-full text-sm" style="width:100%">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 uppercase">
                                <th class="px-3 py-2">#</th>
                                <th class="px-3 py-2">File No</th>
                                <th class="px-3 py-2">Owner</th>
                                <th class="px-3 py-2">Location</th>
                                <th class="px-3 py-2">Approved Use</th>
                                <th class="px-3 py-2">Prevailing Use</th>
                                <th class="px-3 py-2">Contravention</th>
                                <th class="px-3 py-2">Inspection</th>
                                <th class="px-3 py-2">Findings</th>
                                <th class="px-3 py-2 w-10"></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Log Inspection Modal --}}
<div id="modal-log-inspection" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-xl mx-4 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-800">Log Field Inspection</h3>
            <button class="modal-close text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>

        {{-- Land record details (read-only) --}}
        <div id="li-details" class="px-6 pt-4 pb-3 bg-gray-50 border-b border-gray-100 space-y-2">
            <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-sm">
                <div><span class="text-xs text-gray-500 block">File Number</span><span id="li-file" class="font-semibold text-gray-800">—</span></div>
                <div><span class="text-xs text-gray-500 block">Owner</span><span id="li-owner" class="font-semibold text-gray-800">—</span></div>
                <div class="col-span-2"><span class="text-xs text-gray-500 block">Location</span><span id="li-location" class="text-gray-700">—</span></div>
                <div>
                    <span class="text-xs text-gray-500 block">Approved Land Use</span>
                    <span id="li-applied" class="font-medium text-gray-800">—</span>
                </div>
                <div>
                    <span class="text-xs text-gray-500 block">Prevailing Land Use</span>
                    <span id="li-prevailing" class="font-medium text-gray-800">—</span>
                </div>
                <div class="col-span-2 pt-1">
                    <span id="li-contravening-badge"></span>
                </div>
            </div>
            {{-- Property photos --}}
            <div id="li-photos-wrap" class="hidden pt-1">
                <p class="text-xs text-gray-500 mb-1.5">Property Photos</p>
                <div id="li-photos" class="flex flex-wrap gap-2"></div>
            </div>
        </div>

        <form id="form-log-inspection" enctype="multipart/form-data" class="p-6 space-y-4">
            @csrf
            <input type="hidden" name="spa_application_id" id="li-app-id">
            <input type="hidden" name="file_number"        id="li-file-no">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Inspection Date <span class="text-red-500">*</span></label>
                    <input type="date" name="inspection_date" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Coordinates (lat, lng) <span class="text-red-500">*</span></label>
                    <input type="text" name="coordinates" id="f-coords" required placeholder="e.g. 12.0022, 8.5919"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Findings <span class="text-red-500">*</span></label>
                <textarea name="findings" rows="3" required
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none resize-none focus:border-[rgb(186,191,12)]"
                    placeholder="Describe on-site observations…"></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" class="modal-close px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" id="btn-save-inspection" class="px-5 py-2 text-sm text-white bg-[rgb(186,191,12)] rounded-lg hover:opacity-90">Save Inspection</button>
            </div>
        </form>
    </div>
</div>

{{-- View Field Inspection Modal --}}
<div id="modal-view-field" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-xl mx-4 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-800">Field Inspection Details</h3>
            <button id="btn-close-view" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>
        <div class="p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="text-xs text-gray-500 mb-0.5">File Number</p>
                    <p id="vf-file" class="font-medium text-gray-800">—</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 mb-0.5">Inspection Date</p>
                    <p id="vf-date" class="font-medium text-gray-800">—</p>
                </div>
                <div class="col-span-2">
                    <p class="text-xs text-gray-500 mb-0.5">Inspector</p>
                    <p id="vf-inspector" class="font-medium text-gray-800">—</p>
                </div>
                <div class="col-span-2">
                    <p class="text-xs text-gray-500 mb-0.5">Findings</p>
                    <p id="vf-findings" class="text-gray-700 leading-relaxed whitespace-pre-wrap">—</p>
                </div>
            </div>
            <div id="vf-photos-wrap" class="hidden">
                <p class="text-xs text-gray-500 mb-2">Photos</p>
                <div id="vf-photos" class="flex flex-wrap gap-2"></div>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-100 flex justify-end">
            <button id="btn-close-view2" class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Close</button>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
    .tab-btn.active { background:white; color:#1f2937; box-shadow:0 1px 3px rgba(0,0,0,.1); }
    table.dataTable thead th { background:#f9fafb; font-weight:600; }
    .dataTables_wrapper .dataTables_filter input { border:1px solid #e5e7eb; border-radius:.5rem; padding:.35rem .75rem; font-size:.85rem; }
    .dataTables_wrapper .dataTables_length select { border:1px solid #e5e7eb; border-radius:.5rem; padding:.25rem .5rem; }
</style>

<script>
const MAP_POINTS    = @json($mapPoints);
const CSRF          = '{{ csrf_token() }}';
const STORE         = '{{ route("special-assignment.field-data.store") }}';
const APP_URL       = '{{ route("special-assignment.land-records") }}';
const DELETE_FIELD  = '{{ url("special-assignment/field-data") }}';

$(document).ready(function () {
    // ── Tab switching ──────────────────────────────────────────────────────
    const HASH_TAB = { '#field-map': 'field-map', '#records': 'records' };

    function activateTab(tabKey) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(p => p.classList.add('hidden'));
        const btn = document.querySelector(`.tab-btn[data-tab="${tabKey}"]`);
        if (btn) btn.classList.add('active');
        const content = document.getElementById('tab-' + tabKey);
        if (content) content.classList.remove('hidden');
        if (tabKey === 'records') setTimeout(() => $.fn.DataTable.tables({ visible:true, api:true }).columns.adjust(), 50);
        if (tabKey === 'field-map') setTimeout(() => { if (typeof map !== 'undefined') map.invalidateSize(); }, 100);
    }

    // Activate from hash on load
    activateTab(HASH_TAB[window.location.hash] || 'field-map');

    // Respond to sidebar link clicks (hash change)
    window.addEventListener('hashchange', () => {
        const tab = HASH_TAB[window.location.hash];
        if (tab) activateTab(tab);
    });

    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const tab = btn.dataset.tab;
            history.replaceState(null, '', '#' + tab);
            activateTab(tab);
        });
    });

    // ── Leaflet map ────────────────────────────────────────────────────────
    const LAND_USE_COLORS = {
        RESIDENTIAL:  '#0f766e',
        COMMERCIAL:   '#0e7490',
        AGRICULTURAL: '#b45309',
        INDUSTRIAL:   '#7e22ce',
    };
    const DEFAULT_COLOR = '#4b5563';
    const KANO_CENTER   = [12.0, 8.52];
    const KANO_ZOOM     = 11;

    function landUseColor(landUse) {
        if (!landUse) return DEFAULT_COLOR;
        const key = landUse.toString().trim().toUpperCase();
        for (const [k, v] of Object.entries(LAND_USE_COLORS)) {
            if (key.includes(k)) return v;
        }
        return DEFAULT_COLOR;
    }

    function makeIcon(color) {
        return L.divIcon({
            className: '',
            html: `<div style="width:14px;height:14px;border-radius:50%;background:${color};border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.4);"></div>`,
            iconSize:   [14, 14],
            iconAnchor: [7, 7],
            popupAnchor:[0, -10],
        });
    }

    // Base tile layers
    const tileLayers = {
        streets:   L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',         { attribution:'© OpenStreetMap', maxZoom:19 }),
        satellite: L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { attribution:'© Esri', maxZoom:19 }),
        terrain:   L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',           { attribution:'© OpenTopoMap', maxZoom:17 }),
    };

    const map = L.map('field-map', { zoomControl: false }).setView(KANO_CENTER, KANO_ZOOM);
    tileLayers.satellite.addTo(map);

    // Build markers
    const markersAll = [];
    MAP_POINTS.forEach(p => {
        if (!p.coords || !p.coords.lat || !p.coords.lng) return;
        const color  = landUseColor(p.applied_use || p.approved_use);
        const marker = L.marker([p.coords.lat, p.coords.lng], { icon: makeIcon(color) });
        marker._landUse = (p.applied_use || '').toString().trim().toUpperCase();
        const contraveneBadge = p.contravening
            ? `<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:99px;background:#fee2e2;border:1.5px solid #fca5a5;color:#dc2626;font-size:10px;font-weight:900;letter-spacing:.05em;">⚠ CONTRAVENTION</span>`
            : `<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:99px;background:#dcfce7;border:1.5px solid #86efac;color:#16a34a;font-size:10px;font-weight:900;letter-spacing:.05em;">✓ COMPLIANT</span>`;
        const photoHtml = p.photo
            ? `<img src="${p.photo}" style="width:100%;height:110px;object-fit:cover;border-radius:6px;margin-bottom:8px;display:block;">`
            : '';
        function luChip(val) {
            const cols = { RESIDENTIAL:'#0e7490', COMMERCIAL:'#ea580c', INDUSTRIAL:'#7c3aed', AGRICULTURAL:'#92400e' };
            const bg = cols[(val||'').toUpperCase().split(' ')[0]] || '#4b5563';
            return val ? `<span style="display:inline-block;padding:1px 7px;border-radius:99px;background:${bg};color:#fff;font-size:10px;">${val}</span>` : '<span style="color:#9ca3af;font-size:10px;">—</span>';
        }
        const popup = `
            <div style="min-width:240px;font-family:inherit;font-size:12px;">
                ${photoHtml}
                <div style="margin-bottom:7px;">
                    <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;margin-bottom:1px;">File Number</div>
                    <div style="font-weight:700;font-size:13px;color:#1f2937;">${p.file_number || '—'}</div>
                </div>
                <div style="margin-bottom:7px;">
                    <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;margin-bottom:1px;">File Title / Owner</div>
                    <div style="color:#374151;font-size:12px;">${p.owner || '—'}</div>
                </div>
                <div style="margin-bottom:8px;">
                    <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;margin-bottom:2px;">Location</div>
                    <div style="color:#374151;font-size:11px;padding:5px 7px;background:#f9fafb;border-radius:5px;border-left:3px solid rgb(186,191,12);">
                        ${p.location || '—'}
                    </div>
                </div>
                <div style="border-top:1px solid #f3f4f6;padding-top:7px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px;">
                        <span style="font-size:10px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Applied Land Use</span>
                        ${luChip(p.applied_use)}
                    </div>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px;">
                        <span style="font-size:10px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Approved Land Use</span>
                        ${luChip(p.approved_use)}
                    </div>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:7px;padding-bottom:7px;border-bottom:1px solid #f3f4f6;">
                        <span style="font-size:10px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Prevailing Land Use</span>
                        ${luChip(p.prevailing)}
                    </div>
                    <div style="display:flex;align-items:center;justify-content:space-between;">
                        <span style="font-size:10px;color:#9ca3af;">Date Inspected</span>
                        <span style="font-size:11px;color:#374151;font-weight:500;">${p.date || '—'}</span>
                    </div>
                    <div style="margin-top:7px;">${contraveneBadge}</div>
                </div>
            </div>`;
        marker.bindPopup(popup);
        marker.addTo(map);
        markersAll.push(marker);
    });

    // Hide loading overlay once map is ready
    map.whenReady(() => {
        const overlay = document.getElementById('map-loading');
        if (overlay) overlay.style.display = 'none';
    });

    // Zoom level indicator
    function updateZoomLabel() {
        const el = document.getElementById('map-zoom-level');
        if (el) el.textContent = map.getZoom();
    }
    map.on('zoomend', updateZoomLabel);
    updateZoomLabel();

    // Custom zoom / fullscreen / reset controls
    document.getElementById('map-zoom-in').addEventListener('click', () => map.zoomIn());
    document.getElementById('map-zoom-out').addEventListener('click', () => map.zoomOut());
    document.getElementById('map-reset').addEventListener('click', () => map.setView(KANO_CENTER, KANO_ZOOM));
    document.getElementById('map-fullscreen').addEventListener('click', () => {
        const el = document.getElementById('field-map');
        if (document.fullscreenElement) { document.exitFullscreen(); }
        else { el.requestFullscreen && el.requestFullscreen(); }
    });

    // Base map switcher
    document.getElementById('baseMapSelect').addEventListener('change', function () {
        Object.values(tileLayers).forEach(l => map.removeLayer(l));
        (tileLayers[this.value] || tileLayers.streets).addTo(map);
    });

    // Land use filter
    document.getElementById('landUseFilter').addEventListener('change', function () {
        const val = this.value.toUpperCase();
        markersAll.forEach(m => {
            if (val === 'ALL' || m._landUse.includes(val)) { m.addTo(map); }
            else { map.removeLayer(m); }
        });
        const visible = val === 'ALL' ? markersAll.length : markersAll.filter(m => m._landUse.includes(val)).length;
        const el = document.getElementById('map-point-count');
        if (el) el.textContent = visible;
    });

    // Allow clicking map to capture coordinates for form
    map.on('click', e => {
        document.getElementById('f-coords').value = `${e.latlng.lat.toFixed(6)},${e.latlng.lng.toFixed(6)}`;
    });

    // ── Records DataTable ──────────────────────────────────────────────────
    const LU_CLS = { Residential:'bg-blue-100 text-blue-700', Commercial:'bg-orange-100 text-orange-700', Industrial:'bg-purple-100 text-purple-700', Agricultural:'bg-green-100 text-green-700' };
    function luBadge(d) {
        const cls = LU_CLS[d] || 'bg-gray-100 text-gray-600';
        return d ? `<span class="px-2 py-0.5 rounded-full text-xs font-medium ${cls}">${d}</span>` : '—';
    }

    const fieldTable = $('#field-records-table').DataTable({
        processing : true, serverSide: true, scrollX: true,
        ajax: { url: window.location.href, headers:{'X-Requested-With':'XMLHttpRequest'}, data: d => ({ ...d, ajax: 1 }) },
        columns: [
            { data: 'DT_RowIndex', orderable:false, searchable:false },
            { data: 'file_number', render: d => `<span style="white-space:nowrap">${d||'—'}</span>` },
            { data: 'owner_name' },
            { data: 'location', render: d => d ? `<span title="${d}">${d.length>35?d.slice(0,35)+'…':d}</span>` : '—' },
            { data: 'land_use_type', render: d => luBadge(d) },
            { data: 'existing_use',  render: d => luBadge(d) },
            { data: 'contravening', render: d => d
                ? `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700 border border-red-200">⚠ Yes</span>`
                : `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700 border border-green-200">✓ No</span>`
            },
            { data: 'inspection_status', render: d => d === 'inspected'
                ? `<span class="px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Inspected</span>`
                : `<span class="px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Pending</span>`
            },
            { data: 'findings', render: d => d||'—' },
            { data: 'action', orderable:false, searchable:false },
        ],
    });

    // ── View field inspection ─────────────────────────────────────────────
    const viewModal = document.getElementById('modal-view-field');
    function closeViewModal() { viewModal.classList.add('hidden'); viewModal.classList.remove('flex'); }
    document.getElementById('btn-close-view').addEventListener('click', closeViewModal);
    document.getElementById('btn-close-view2').addEventListener('click', closeViewModal);

    $(document).on('click', '.btn-view-field', function () {
        $('.action-dropdown').addClass('hidden');
        const info = JSON.parse($(this).attr('data-info') || '{}');
        document.getElementById('vf-file').textContent      = info.file      || '—';
        document.getElementById('vf-date').textContent      = info.date      || '—';
        document.getElementById('vf-inspector').textContent = info.inspector || '—';
        document.getElementById('vf-findings').textContent  = info.findings  || '—';

        const photos = info.photos || [];
        const photosDiv  = document.getElementById('vf-photos');
        const photosWrap = document.getElementById('vf-photos-wrap');
        photosDiv.innerHTML = '';
        if (photos.length) {
            photos.forEach(url => {
                photosDiv.innerHTML += `<a href="${url}" target="_blank"><img src="${url}" class="h-20 w-20 object-cover rounded border border-gray-200"></a>`;
            });
            photosWrap.classList.remove('hidden');
        } else {
            photosWrap.classList.add('hidden');
        }

        viewModal.classList.remove('hidden');
        viewModal.classList.add('flex');
        lucide.createIcons();
    });

    // ── Dropdown action menu ───────────────────────────────────────────────
    $(document).on('click', '.btn-action-toggle', function(e) {
        e.stopPropagation();
        const $dd = $(this).siblings('.action-dropdown');
        $('.action-dropdown').not($dd).addClass('hidden');
        $dd.toggleClass('hidden');
    });
    $(document).on('click', function() { $('.action-dropdown').addClass('hidden'); });

    // ── Delete field record ────────────────────────────────────────────────
    $(document).on('click', '.btn-delete-field', function() {
        $('.action-dropdown').addClass('hidden');
        const id = $(this).data('id');
        Swal.fire({
            title: 'Delete Record?',
            text: 'This will permanently remove the field inspection record.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, delete',
        }).then(result => {
            if (!result.isConfirmed) return;
            fetch(`${DELETE_FIELD}/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    $.fn.DataTable.tables({ visible:true, api:true }).ajax.reload();
                    Swal.fire({ icon:'success', title:'Deleted', text: d.message, timer:2000, showConfirmButton:false });
                } else {
                    Swal.fire({ icon:'error', title:'Error', text: d.message });
                }
            });
        });
    });

    // ── Modal helpers ──────────────────────────────────────────────────────
    const modal = document.getElementById('modal-log-inspection');
    let _currentApp = null;

    function openInspectionModal(app) {
        _currentApp = app;
        document.getElementById('li-app-id').value  = app.id;
        document.getElementById('li-file-no').value = app.file_number;
        document.getElementById('li-file').textContent       = app.file_number    || '—';
        document.getElementById('li-owner').textContent      = app.owner_name     || '—';
        document.getElementById('li-location').textContent   = app.location       || '—';
        document.getElementById('li-applied').textContent    = app.land_use_type  || '—';
        document.getElementById('li-prevailing').textContent = app.existing_use   || '—';
        const applied    = (app.land_use_type || '').trim().toUpperCase();
        const prevailing = (app.existing_use  || '').trim().toUpperCase();
        const isContravention = applied && prevailing && applied !== prevailing;
        const cbEl = document.getElementById('li-contravening-badge');
        if (isContravention) {
            cbEl.innerHTML = `<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:99px;background:#fee2e2;border:1.5px solid #fca5a5;color:#dc2626;font-size:11px;font-weight:900;letter-spacing:.05em;">⚠ CONTRAVENTION</span>`;
        } else if (applied && prevailing) {
            cbEl.innerHTML = `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700 border border-green-200"><svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>Compliant</span>`;
        } else {
            cbEl.innerHTML = '';
        }
        const photosDiv  = document.getElementById('li-photos');
        const photosWrap = document.getElementById('li-photos-wrap');
        photosDiv.innerHTML = '';
        if (app.photos && app.photos.length) {
            app.photos.forEach(url => {
                photosDiv.innerHTML += `<a href="${url}" target="_blank"><img src="${url}" class="h-20 w-20 object-cover rounded border border-gray-200"></a>`;
            });
            photosWrap.classList.remove('hidden');
        } else {
            photosWrap.classList.add('hidden');
        }
        modal.classList.remove('hidden'); modal.classList.add('flex');
        lucide.createIcons();
    }
    function closeModal() { modal.classList.add('hidden'); modal.classList.remove('flex'); }
    document.querySelectorAll('.modal-close').forEach(btn => btn.addEventListener('click', closeModal));


    // ── Row action: Log Inspection ─────────────────────────────────────────
    $(document).on('click', '.btn-log-inspection', function () {
        $('.action-dropdown').addClass('hidden');
        const app = JSON.parse($(this).attr('data-app'));
        document.getElementById('form-log-inspection').reset();
        openInspectionModal(app);
    });

    // ── Form submit ────────────────────────────────────────────────────────
    document.getElementById('form-log-inspection').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('btn-save-inspection');
        btn.disabled = true; btn.textContent = 'Saving…';
        const fd = new FormData(this);
        const coordStr = fd.get('coordinates');
        if (coordStr) {
            const parts = coordStr.split(',');
            if (parts.length === 2) fd.set('coordinates', JSON.stringify({ lat: parseFloat(parts[0].trim()), lng: parseFloat(parts[1].trim()) }));
        }
        try {
            const res  = await fetch(STORE, { method:'POST', headers:{'X-CSRF-TOKEN':CSRF}, body:fd });
            const data = await res.json();
            if (data.success) {
                closeModal(); this.reset();
                fieldTable.ajax.reload();
                // Add marker to live map
                if (data.mapPoint) {
                    const p = data.mapPoint;
                    if (p.coords && p.coords.lat && p.coords.lng) {
                        const color  = landUseColor(p.applied_use || p.approved_use);
                        const marker = L.marker([p.coords.lat, p.coords.lng], { icon: makeIcon(color) });
                        marker._landUse = (p.applied_use || '').toUpperCase();
                        function lc2(val) { const cols={RESIDENTIAL:'#0e7490',COMMERCIAL:'#ea580c',INDUSTRIAL:'#7c3aed',AGRICULTURAL:'#92400e'}; const bg=cols[(val||'').toUpperCase().split(' ')[0]]||'#4b5563'; return val?`<span style="display:inline-block;padding:1px 7px;border-radius:99px;background:${bg};color:#fff;font-size:10px;">${val}</span>`:'<span style="color:#9ca3af;font-size:10px;">—</span>'; }
                        const cb = p.contravening
                            ? `<span style="padding:3px 10px;border-radius:99px;background:#fee2e2;border:1.5px solid #fca5a5;color:#dc2626;font-size:10px;font-weight:900;letter-spacing:.05em;">⚠ CONTRAVENTION</span>`
                            : `<span style="padding:3px 10px;border-radius:99px;background:#dcfce7;border:1.5px solid #86efac;color:#16a34a;font-size:10px;font-weight:900;letter-spacing:.05em;">✓ COMPLIANT</span>`;
                        const ph = p.photo ? `<img src="${p.photo}" style="width:100%;height:110px;object-fit:cover;border-radius:6px;margin-bottom:8px;display:block;">` : '';
                        marker.bindPopup(`<div style="min-width:240px;font-family:inherit;font-size:12px;">${ph}<div style="font-weight:700;font-size:13px;margin-bottom:2px;color:#1f2937;">${p.file_number||'—'}</div><div style="color:#6b7280;font-size:11px;margin-bottom:2px;">${p.owner||'—'}</div><div style="color:#374151;font-size:11px;margin-bottom:8px;padding:5px 7px;background:#f9fafb;border-radius:5px;border-left:3px solid rgb(186,191,12);">${p.location||'—'}</div><div style="border-top:1px solid #f3f4f6;padding-top:7px;"><div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px;"><span style="font-size:10px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Applied Land Use</span>${lc2(p.applied_use)}</div><div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px;"><span style="font-size:10px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Approved Land Use</span>${lc2(p.approved_use)}</div><div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:7px;padding-bottom:7px;border-bottom:1px solid #f3f4f6;"><span style="font-size:10px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Prevailing Land Use</span>${lc2(p.prevailing)}</div><div style="margin-top:5px;">${cb}</div></div></div>`);
                        marker.addTo(map);
                        markersAll.push(marker);
                        const countEl = document.getElementById('map-point-count');
                        if (countEl) countEl.textContent = markersAll.length;
                        map.panTo([p.coords.lat, p.coords.lng]);
                    }
                }
                Swal.fire({ icon:'success', title:'Saved', text:data.message, timer:2000, showConfirmButton:false });
            } else {
                Swal.fire({ icon:'error', title:'Error', text:data.message||'Save failed.' });
            }
        } catch(err) { Swal.fire({ icon:'error', title:'Error', text:'Unexpected error.' }); }
        btn.disabled=false; btn.textContent='Save Inspection';
    });
});
</script>
@endsection

<div class="sticky bottom-0 bg-white/95 backdrop-blur-sm px-8 py-6 -mx-8 mt-12 border-t border-gray-100 z-20 shadow-[0_-10px_15px_-3px_rgba(0,0,0,0.05)]">
    <div class="flex justify-between items-center gap-4">
        <button type="button" 
            class="inline-flex items-center px-8 py-3 border border-gray-300 text-sm font-medium rounded-lg shadow-sm text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200" 
            id="cancel-btn">
            <i data-lucide="x-circle" class="h-4 w-4 mr-2"></i>
            Cancel
        </button>

        <button type="button"
            class="inline-flex items-center px-8 py-3 border border-amber-300 text-sm font-medium rounded-lg shadow-sm text-amber-700 bg-amber-50 hover:bg-amber-100 hover:border-amber-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 transition-all duration-200"
            id="refresh-form-btn">
            <i data-lucide="refresh-cw" class="h-4 w-4 mr-2"></i>
            Refresh
        </button>
        <button
            type="button"
            class="inline-flex items-center px-8 py-3 border border-transparent text-sm font-medium rounded-lg shadow-lg text-white bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200 transform hover:scale-105"
            id="create-file-btn"
            data-default-label="Create File Index"
            data-edit-label="Update File Index"
            data-mode="create"
        >
            <i data-lucide="save" class="h-4 w-4 mr-2"></i>
            <span data-state="create">Create File Index</span>
            <span data-state="edit" class="hidden">Update File Index</span>
        </button>
    </div>
</div>

<script>
    // Opens the Property Transaction Details modal straight from the indexing form,
    // WITHOUT submitting the form (i.e. without creating/updating the file index).
    (function () {
        if (window.__addPropertyTransactionBtnInit) return;
        window.__addPropertyTransactionBtnInit = true;

        // Locate the Alpine component that holds the property tabs (fileParams).
        function getIndexingComponent() {
            if (typeof Alpine === 'undefined') return null;
            const nodes = document.querySelectorAll('[x-data]');
            for (const el of nodes) {
                try {
                    const data = Alpine.$data(el);
                    if (data && Array.isArray(data.fileParams)) return data;
                } catch (e) { /* not an initialized component */ }
            }
            return null;
        }

        // Resolve the effective file number from whichever input holds it for the
        // current indexing mode (standard Select, KANGIS placeholder, KN-series, MLS…).
        function resolveFileNumber() {
            const candidateIds = [
                'fileno',
                'file-number-display',
                'new_kangis_file_no_hidden',
                'new_kangis_file_no_input',
                'kangis_file_no_hidden',
                'kangis_file_no_input',
                'mls_file_no_hidden',
                'mls_file_no_input',
                'kangis-fileno-placeholder',
            ];
            for (const id of candidateIds) {
                const val = (document.getElementById(id)?.value || '').trim();
                if (val) return val;
            }
            return '';
        }

        // Build the base payload from what's currently on the form.
        function buildPayload() {
            const fileNumber = resolveFileNumber();

            // Pull property details from the currently active property tab.
            let param = {};
            const comp = getIndexingComponent();
            if (comp) {
                const idx = comp.activeTab || 0;
                param = comp.fileParams[idx] || comp.fileParams[0] || {};
            }

            const resolveOther = (val, custom) =>
                (String(val || '').toLowerCase() === 'other') ? (custom || '') : (val || '');

            const district = resolveOther(param.district, param.custom_district);

            return {
                file_number: fileNumber,
                file_title: param.title || '',
                plot_no: param.plot_number || '',
                plot_number: param.plot_number || '',
                tp_no: param.tp_number || '',
                lpkn_no: param.lpkn_no || '',
                lga: param.lga || '',
                district: district,
                location: param.location || '',
                land_use_type: param.land_use_type || '',
                state: 'KANO',
                existing_records: []
            };
        }

        // Keep a form value when it's meaningful, otherwise fall back to the server value.
        function preferForm(formVal, serverVal) {
            const v = String(formVal || '').trim();
            return (v && v !== 'N/A') ? formVal : (serverVal || '');
        }

        // Backfill the payload from the saved record for this file number (property
        // details only), then open the modal with a fresh, blank transaction card.
        // We deliberately do NOT pre-load existing transactions here: triggering the
        // button directly is an "Add" action, so the card starts empty and ready for
        // a new selection rather than opening in "Update" mode.
        async function openWithBackfill(payload) {
            const registry =
                document.getElementById('registry')?.value ||
                document.getElementById('general-registry')?.value ||
                '';

            try {
                const url = new URL(@json(route('fileindex.check-indexed')), window.location.origin);
                url.searchParams.set('fileno', payload.file_number);
                if (registry) url.searchParams.set('registry', registry);

                const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                const data = await res.json();

                if (data && data.exists && data.record) {
                    const r = data.record;
                    payload.file_title    = preferForm(payload.file_title, r.file_title);
                    payload.plot_no       = preferForm(payload.plot_no, r.plot_number);
                    payload.plot_number   = payload.plot_no;
                    payload.tp_no         = preferForm(payload.tp_no, r.tp_no);
                    payload.lpkn_no       = preferForm(payload.lpkn_no, r.lpkn_no);
                    payload.lga           = preferForm(payload.lga, r.lga);
                    payload.district      = preferForm(payload.district, r.district);
                    payload.location      = preferForm(payload.location, r.location);
                    payload.land_use_type = preferForm(payload.land_use_type, r.land_use_type);
                    payload.property_description = payload.location || r.location || '';
                } else if (data && data.grouping_found && data.grouping_record) {
                    const g = data.grouping_record;
                    payload.plot_no     = preferForm(payload.plot_no, g.plot_no || g.plot_number);
                    payload.plot_number = payload.plot_no;
                    payload.tp_no       = preferForm(payload.tp_no, g.tp_no);
                    payload.lga         = preferForm(payload.lga, g.lga || g.lgsaOrCity);
                    payload.district    = preferForm(payload.district, g.district || g.districtName);
                    payload.location    = preferForm(payload.location, g.location);
                    payload.property_description = payload.location || '';
                }

                // Keep the transaction card blank (Add mode) — do not pre-load existing
                // transactions when the modal is opened directly from the button.
                payload.existing_records = [];
            } catch (err) {
                console.warn('Property transaction backfill lookup failed; opening with form data only.', err);
            }

            if (typeof window.openPropertyTransactionModal === 'function') {
                window.openPropertyTransactionModal(payload);
            } else {
                console.error('openPropertyTransactionModal function not found');
            }
        }

        document.addEventListener('click', function (e) {
            const btn = e.target.closest('#add-property-transaction-btn');
            if (!btn) return;

            e.preventDefault();

            const payload = buildPayload();

            // A file number is required to SAVE (the modal's submit + backend enforce it),
            // but we no longer block opening the modal. If one is present, backfill the
            // property details from the saved record; otherwise just open a blank modal.
            if (payload.file_number) {
                openWithBackfill(payload);
            } else if (typeof window.openPropertyTransactionModal === 'function') {
                window.openPropertyTransactionModal(payload);
            } else {
                console.error('openPropertyTransactionModal function not found');
            }
        });
    })();
</script>

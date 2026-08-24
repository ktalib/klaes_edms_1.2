{{-- Duplex Parcel Update — wizard, register actions and the stage runner. --}}
<script>
(function () {
    'use strict';

    const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const TYPES = @json($types);
    const LGAS = @json(collect($lgas)->pluck('name')->values());
    const DISTRICTS = @json(collect($districts)->pluck('name')->values());

    // Icon + one-line description per update type. Lives here because the list is
    // rendered from the plan now rather than pre-rendered in the blade.
    const TYPE_META = {
        merger:            ['combine', 'Several files become one parcel'],
        subdivision:       ['split-square-horizontal', 'One parcel becomes several plots'],
        change_of_purpose: ['repeat', 'Same parcel, new land use'],
        extension:         ['expand', 'Boundary adjusted, one for one'],
        separation:        ['scissors', 'Plots separated out of one file'],
    };

    // ---------------------------------------------------------------- state
    // `plan` is the ordered list the officer built by ticking. Its order IS the
    // execution order — never re-sort it by type anywhere downstream.
    const state = {
        step: 1,
        sources: [],       // {fileNumber, title}
        plan: [],          // [{type, rank, count}] in tick order
        duplex: null,      // {id, duplex_id} once created
        stages: [],        // server stage rows
        stageIndex: 0,
        carry: [],         // holding numbers produced by the stage just saved
    };

    // ---------------------------------------------------------------- helpers
    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': CSRF,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(body || {}),
        }).then(r => r.json());
    }

    function toast(icon, title, text) {
        if (window.Swal) return Swal.fire({ icon, title, text, timer: icon === 'success' ? 2200 : undefined });
        alert(title + (text ? '\n' + text : ''));
    }

    function icons() { if (window.lucide) window.lucide.createIcons(); }

    // ---------------------------------------------------------------- step 1
    window.openDuplexWizard = function () {
        state.step = 1;
        state.sources = [];
        state.plan = [];
        state.duplex = null;
        state.stages = [];
        state.stageIndex = 0;
        state.carry = [];

        document.getElementById('dx-applicant').value = '';
        document.getElementById('dx-plot-no-main').value = '';
        document.getElementById('dx-district').value = '';
        document.getElementById('dx-lga').value = '';
        updateAddressPreview();
        document.getElementById('dx-duplex-badge').classList.add('hidden');
        renderRanks();
        renderSources();

        const modal = document.getElementById('duplex-wizard');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        showStep(1);
        icons();
    };

    window.closeDuplexWizard = function () {
        const modal = document.getElementById('duplex-wizard');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    };

    window.pickSourceFile = function () {
        // The plan is fixed once the duplex row exists; adding a file here would not
        // reach the server.
        if (state.duplex) {
            return toast('info', 'Plan locked', 'The duplex has been created, so its source files cannot change.');
        }
        if (!window.GlobalFileNoModal) return toast('error', 'File selector unavailable');

        window.GlobalFileNoModal.open({
            callback: function (picked) {
                const fileNumber = (picked.fileNumber || '').trim();
                if (!fileNumber) return;
                if (state.sources.some(s => s.fileNumber === fileNumber)) {
                    return toast('info', 'Already added', fileNumber + ' is already on this duplex.');
                }
                state.sources.push({ fileNumber, title: picked.file_title || '' });

                // The first file picked names the applicant, so the officer rarely has
                // to type it. Anything already typed is left alone.
                const applicantBox = document.getElementById('dx-applicant');
                if (!applicantBox.value.trim() && picked.file_title) {
                    applicantBox.value = picked.file_title;
                }

                renderSources();
                syncMergerFromSources();
            }
        });
    };

    /** Kano is the only state this registry serves, so it is a constant, not a field. */
    const STATE_NAME = 'KANO';

    /**
     * One location, assembled the same way every time: district, LGA, state.
     *
     * The plot number is NOT part of it. A subdivision gives each child its own plot
     * number, so folding the parcel's number into the location would stamp the mother's
     * plot onto every child.
     */
    window.updateAddressPreview = function () {
        const parts = [
            document.getElementById('dx-district').value.trim(),
            document.getElementById('dx-lga').value.trim(),
            STATE_NAME,
        ];

        const location = parts.filter(Boolean).join(', ').toUpperCase();
        document.getElementById('dx-address').value = location;

        const box = document.getElementById('dx-address-preview');
        box.innerHTML = (parts[0] || parts[1])
            ? location
            : '<span class="text-slate-400 font-normal italic">Fills in from the fields above.</span>';
    };

    function renderSources() {
        const box = document.getElementById('dx-sources');

        // Empty state is itself the picker button — an empty dashed box with no
        // affordance was the least useful thing on the card.
        if (!state.sources.length) {
            box.innerHTML = `
                <button type="button" onclick="pickSourceFile()"
                        class="w-full flex flex-col items-center justify-center gap-1.5 py-2 text-slate-400 hover:text-blue-600 transition">
                    <i data-lucide="file-search" class="w-5 h-5"></i>
                    <span class="text-xs font-semibold">Choose a file from the registry</span>
                </button>`;
            if (window.lucide) lucide.createIcons();
            return;
        }

        box.innerHTML = state.sources.map((s, i) => `
            <span class="inline-flex items-center gap-2.5 pl-3 pr-2 py-2 rounded-xl bg-white border border-slate-200 shadow-sm">
                <span class="w-6 h-6 rounded-lg bg-slate-100 text-slate-500 flex items-center justify-center text-[10px] font-black">${i + 1}</span>
                <span class="min-w-0">
                    <span class="block holding-no text-xs font-black text-slate-700 leading-tight">${s.fileNumber}</span>
                    ${s.title ? `<span class="block text-[10px] text-slate-400 truncate max-w-[180px]">${s.title}</span>` : ''}
                </span>
                <button type="button" onclick="removeSource(${i})" title="Remove"
                        class="w-6 h-6 rounded-lg flex items-center justify-center text-slate-300 hover:text-red-600 hover:bg-red-50 transition">
                    <i data-lucide="x" class="w-3.5 h-3.5"></i>
                </button>
            </span>`).join('');

        if (window.lucide) lucide.createIcons();
    }

    window.removeSource = function (i) {
        if (state.duplex) return;
        state.sources.splice(i, 1);
        renderSources();
        syncMergerFromSources();
    };

    /**
     * More than one source file can only mean one thing: they are being merged. So the
     * Merger is added automatically as the first leg, locked, and its quantity set to
     * the number of files picked.
     *
     * Nothing else can sensibly consume several files at once — a Subdivision or a
     * Change of Purpose starts from one parcel — so leaving the officer to remember it
     * only invites a duplex that cannot run.
     */
    function syncMergerFromSources() {
        if (state.duplex) return;               // the plan is fixed once it exists

        const n = state.sources.length;
        const at = state.plan.findIndex(p => p.type === 'merger');

        if (n > 1) {
            if (at === -1) {
                state.plan.unshift({ type: 'merger', rank: 0, count: n, auto: true });
            } else {
                const entry = state.plan.splice(at, 1)[0];
                entry.count = n;
                entry.auto = true;
                state.plan.unshift(entry);      // a merger is always the first leg
            }
        } else if (at !== -1 && state.plan[at].auto) {
            // Down to a single file: the merger it added is no longer meaningful.
            state.plan.splice(at, 1);
        }

        renderRanks();
    }

    /**
     * Selection order = execution order. Each pick from the dropdown APPENDS to the
     * plan; removing one shuffles the rest up, so the ranks always read 1..N.
     */
    window.addTypeFromPicker = function (select) {
        const type = select.value;
        select.value = '';
        if (!type || state.plan.some(p => p.type === type)) return;

        state.plan.push({ type, rank: 0, count: null });
        renderRanks();
    };

    window.removeTypeFromPlan = function (type) {
        if (state.duplex) return;          // the plan is fixed once the duplex exists

        const entry = state.plan.find(p => p.type === type);
        if (entry && entry.auto) {
            return toast('info', 'Merger is required',
                'More than one source file is selected, so they have to be merged first. '
                + 'Remove a source file to drop the merger.');
        }
        state.plan = state.plan.filter(p => p.type !== type);
        renderRanks();
    };

    /**
     * The chosen updates, in the order they were chosen. Rank 1 is the first row, so
     * the list reads top to bottom as the sequence it will actually run in.
     */
    function renderSelectedTypes() {
        const list = document.getElementById('dx-type-list');
        if (!list) return;

        if (!state.plan.length) {
            list.innerHTML = `
                <div class="flex flex-col items-center justify-center gap-2 py-8 rounded-2xl border border-dashed border-slate-200 text-slate-400">
                    <i data-lucide="list-plus" class="w-5 h-5"></i>
                    <p class="text-xs font-semibold">No updates added yet</p>
                    <p class="text-[11px]">Choose one from the dropdown above to start.</p>
                </div>`;
            if (window.lucide) lucide.createIcons();
            return;
        }

        const locked = !!state.duplex;

        list.innerHTML = state.plan.map(entry => {
            const meta = TYPE_META[entry.type] || ['circle', ''];
            return `
                <div class="type-row picked flex items-center gap-3.5 px-4 py-3.5 rounded-2xl border border-blue-300 bg-blue-50/40"
                     data-type="${entry.type}">
                    <span class="rank-badge rank-${Math.min(entry.rank, 5)} w-8 h-8 rounded-xl border flex items-center justify-center text-xs font-black shrink-0">
                        ${entry.rank}</span>

                    <span class="w-9 h-9 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center shrink-0">
                        <i data-lucide="${meta[0]}" class="w-4 h-4"></i>
                    </span>

                    <span class="flex-1 min-w-0">
                        <span class="block text-sm font-bold text-slate-700 leading-tight">${TYPES[entry.type]}</span>
                        <span class="block text-[11px] text-slate-500 mt-0.5">
                            Runs ${ordinal(entry.rank)} &middot; ${entry.auto
                                ? `<b class="text-blue-700">${state.sources.length} source files</b> merged into one parcel`
                                : meta[1]}</span>
                    </span>

                    ${(locked || entry.auto)
                        ? `<span class="text-slate-400" title="${entry.auto
                              ? 'Added automatically because more than one source file is selected'
                              : 'The plan is locked'}"><i data-lucide="lock" class="w-3.5 h-3.5"></i></span>`
                        : `<button type="button" onclick="removeTypeFromPlan('${entry.type}')" title="Remove"
                                  class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-300 hover:text-red-600 hover:bg-red-50 transition shrink-0">
                              <i data-lucide="x" class="w-3.5 h-3.5"></i>
                          </button>`}
                </div>`;
        }).join('');

        if (window.lucide) lucide.createIcons();
    }

    /** Only what has NOT been chosen yet — a picked update leaves the dropdown. */
    function renderTypePicker() {
        const select = document.getElementById('dx-type-picker');
        if (!select) return;

        const taken = state.plan.map(p => p.type);
        const left = Object.keys(TYPES).filter(t => !taken.includes(t));

        select.innerHTML = left.length
            ? '<option value="">— Select an update to add —</option>'
              + left.map(t => '<option value="' + t + '">' + TYPES[t] + '</option>').join('')
            : '<option value="">All five updates added</option>';

        select.disabled = !left.length || !!state.duplex;
    }

    const ordinal = n => ['', 'first', 'second', 'third', 'fourth', 'fifth'][n] || (n + 'th');

    function renderRanks() {
        state.plan.forEach((p, i) => { p.rank = i + 1; });


        renderTypePicker();
        renderSelectedTypes();

        document.getElementById('dx-single-note').classList.toggle('hidden', state.plan.length !== 1);
        document.getElementById('dx-picked-count').textContent = state.plan.length + ' selected';

        renderOrderRail();
    }

    /**
     * Live read-back of the plan in execution order. The order IS the instruction —
     * it drives the memo and the commit — so it gets shown as a list rather than
     * being left for the officer to reconstruct from scattered badges.
     */
    function renderOrderRail() {
        const rail = document.getElementById('dx-order-rail');
        if (!rail) return;

        if (!state.plan.length) {
            rail.innerHTML = '<p class="text-xs text-slate-400 italic">Nothing ticked yet.</p>';
            return;
        }

        rail.innerHTML = state.plan.map(p => `
            <div class="flex items-center gap-2.5">
                <span class="rank-badge rank-${Math.min(p.rank, 5)} w-6 h-6 rounded-lg border flex items-center justify-center text-[11px] font-black shrink-0">${p.rank}</span>
                <span class="text-xs font-bold text-slate-700">${TYPES[p.type]}</span>
            </div>`).join('');
    }

    // ---------------------------------------------------------------- step 2
    /**
     * How many parcels a stage records a size for.
     *
     * A merger sizes what goes IN (each source file has its own area); everything else
     * sizes what comes OUT. A Change of Purpose sizes only the files it renames.
     */
    function sizeSlots(p, incoming) {
        if (p.type === 'merger') return incoming;
        if (p.type === 'extension') return 1;
        return p.count || 2;
    }

    /** The size inputs under one stage row, preserving anything already typed. */
    function sizeGrid(p, slots) {
        p.sizes = p.sizes || [];

        const cells = Array.from({ length: slots }).map((_, i) => `
            <div>
                <label class="block text-[11px] font-black uppercase tracking-wider text-slate-500 mb-1.5">
                    ${p.type === 'merger' ? 'File' : 'Plot'} ${i + 1}</label>
                <input type="number" step="0.01" min="0" placeholder="0.00"
                       value="${p.sizes[i] ?? ''}"
                       data-type="${p.type}" data-slot="${i}"
                       oninput="onSizeInput(this)"
                       class="dx-size w-full px-2.5 py-2 rounded-lg border border-slate-200 bg-white text-sm text-center">
            </div>`).join('');

        {{-- Where the running-area line sits depends on which way the arithmetic runs.
             A subdivision ALLOCATES out of a parcel whose size is already known, so the
             figure has to be above the boxes being filled in. A merger DERIVES its total
             from the sizes typed in, so its line can only follow them. --}}
        const allocating = p.type === 'subdivision' || p.type === 'separation';
        const noteEl = '<p class="dx-size-note text-xs"></p>';

        return `
            <div class="dx-size-grid mt-3 pt-3 border-t border-slate-100">
                ${allocating ? '<div class="mb-2.5">' + noteEl + '</div>' : ''}
                <p class="text-[11px] font-black uppercase tracking-wider text-slate-500 mb-2">
                    Plot size${slots === 1 ? '' : 's'} (Ha)</p>
                <div class="grid grid-cols-3 md:grid-cols-6 gap-2">${cells}</div>
                ${allocating ? '' : '<div class="mt-2.5">' + noteEl + '</div>'}
            </div>`;
    }

    window.onSizeInput = function (input) {
        const entry = state.plan.find(p => p.type === input.dataset.type);
        if (!entry) return;
        entry.sizes = entry.sizes || [];
        entry.sizes[Number(input.dataset.slot)] = input.value;
        refreshSizeNotes();
    };

    const sumSizes = p => (p.sizes || []).reduce((t, v) => t + (parseFloat(v) || 0), 0);
    const ha = n => (Math.round(n * 100) / 100) + ' Ha';

    /**
     * The parcel area walked through the chain.
     *
     * A merger's four files add up to the parcel the subdivision then divides — that
     * total was nowhere on screen, so there was no way to tell whether the plot sizes
     * entered below actually accounted for the land. Each stage now states what it
     * receives and what it passes on, and a subdivision says when the two disagree.
     */
    function refreshSizeNotes() {
        let carried = null;   // area of the parcel entering the next stage, or null if unknown

        document.querySelectorAll('#dx-quantities > div').forEach((row, i) => {
            const p = state.plan[i];
            const note = row.querySelector('.dx-size-note');
            if (!p || !note) return;

            const entered = sumSizes(p);

            // The line carries a state, so it is coloured as one rather than sitting grey
            // with a coloured word buried in it: blue while it is simply reporting an
            // area, green when a subdivision accounts for its parent, amber when it does
            // not. `strong` keeps the figures a shade darker than the sentence.
            let text = '', tone = 'text-slate-400', strong = 'text-slate-700';

            const b = v => '<b class="' + strong + '">' + v + '</b>';

            if (p.type === 'merger') {
                carried = entered || null;
                if (entered) {
                    tone = 'text-blue-600';
                    strong = 'text-blue-800';
                    text = 'Merged parcel: ' + b(ha(entered));
                }
            } else if (p.type === 'subdivision' || p.type === 'separation') {
                const balanced = carried && entered
                    && Math.abs(Math.round((carried - entered) * 100) / 100) <= 0.009;

                if (carried && entered) {
                    tone = balanced ? 'text-emerald-600' : 'text-amber-600';
                    strong = balanced ? 'text-emerald-800' : 'text-amber-800';
                } else if (carried || entered) {
                    tone = 'text-blue-600';
                    strong = 'text-blue-800';
                }

                const parts = [];
                if (carried) parts.push('Parcel being divided: ' + b(ha(carried)));
                if (entered) parts.push('plots entered: ' + b(ha(entered)));

                if (carried && entered) {
                    const diff = Math.round((carried - entered) * 100) / 100;
                    if (balanced) {
                        parts.push(b('accounted for'));
                    } else {
                        parts.push(b(diff > 0 ? ha(diff) + ' remaining' : ha(-diff) + ' over'));
                    }
                }

                text = parts.join(' &middot; ');
                carried = entered || carried;
            } else if (p.type === 'extension') {
                if (carried || entered) { tone = 'text-blue-600'; strong = 'text-blue-800'; }
                if (carried) text = 'Parcel before extension: ' + b(ha(carried));
                if (entered) {
                    text += (text ? ' &middot; ' : '') + 'after: ' + b(ha(entered));
                    carried = entered;
                }
            } else if (p.type === 'change_of_purpose') {
                // A rename does not change any area; it just renames some of the parcels.
                if (entered) {
                    tone = 'text-blue-600';
                    strong = 'text-blue-800';
                    text = 'Parcels changing purpose: ' + b(ha(entered));
                }
            }

            note.className = 'dx-size-note text-xs font-semibold ' + tone;
            note.innerHTML = text;
        });
    }

    function renderQuantities() {
        const box = document.getElementById('dx-quantities');

        // What each stage receives, walked forward through the plan. A Merger shows "1"
        // because it produces one parcel — but on its own that reads as "one file", when
        // the point of a merger is how MANY files it swallows. So the input count is
        // stated beside it.
        let incoming = state.sources.length;

        box.innerHTML = state.plan.map(p => {
            // Merger collapses many into one and Extension is a 1-to-1 adjustment,
            // so neither has a count to ask for.
            const fixed = (p.type === 'merger' || p.type === 'extension');
            const outputs = fixed ? 1 : (p.count || 2);

            // For a merger the useful number is how many files go IN — that is what the
            // officer picked, and "1" on its own reads as "one file".
            const shown = p.type === 'merger' ? incoming : outputs;

            const hint = p.type === 'merger'
                ? `<b class="text-slate-600">${incoming}</b> file${incoming === 1 ? '' : 's'} merged into one parcel`
                : (p.type === 'extension'
                    ? 'One adjusted parcel, replacing the incoming file'
                    : 'How many plots');

            // A visible many-to-one (or one-to-many) badge, so the arithmetic of the
            // stage is readable without opening it.
            const flow = `
                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg bg-slate-50 border border-slate-200 text-[11px] font-black text-slate-500">
                    <span class="dx-flow-in">${incoming}</span>
                    <i data-lucide="arrow-right" class="w-3 h-3 text-slate-300"></i>
                    <span class="dx-flow-out ${outputs === incoming ? 'text-slate-500' : 'text-blue-600'}">${outputs}</span>
                </span>`;

            const slots = sizeSlots(p, incoming);

            const row = `
                <div class="px-4 py-3 rounded-xl border border-slate-200">
                  <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="rank-badge rank-${Math.min(p.rank, 5)} text-center px-2 py-0.5 rounded-lg border text-xs font-black">${p.rank}</span>
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-slate-700">${TYPES[p.type]}</p>
                            <p class="dx-qty-hint text-[11px] text-slate-400">${hint}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        ${flow}
                        <input type="number" min="1" max="200" ${fixed ? 'disabled' : ''}
                            value="${shown}"
                            data-type="${p.type}"
                            oninput="renderQuantities()"
                            class="dx-qty w-24 px-3 py-2 rounded-xl border border-slate-200 text-sm text-center ${fixed ? 'bg-slate-50 text-slate-400' : ''}">
                    </div>
                  </div>
                  ${sizeGrid(p, slots)}
                </div>`;

            // A Change of Purpose renames some of what it receives and passes the rest
            // through, so the count going on to the next stage does not shrink.
            incoming = (p.type === 'change_of_purpose') ? incoming : outputs;

            return row;
        }).join('');

        refreshSizeNotes();
        if (window.lucide) lucide.createIcons();
    }

    /**
     * Carry revised quantities from the plan onto the stage rows already on screen, so
     * a corrected count re-renders the right number of plot cards. The new value is
     * persisted when that stage is saved — saveStage takes plot_count with the payload.
     */
    function applyPlanCountsToStages() {
        state.stages.forEach(stage => {
            const entry = state.plan.find(p => p.type === stage.type);
            if (!entry || !entry.count) return;

            if (Number(stage.plot_count) !== Number(entry.count)) {
                stage.plot_count = entry.count;

                // The stage's saved plots no longer match the new count; drop the stale
                // ones so the panel rebuilds from the sizes just entered.
                if (stage.payload && stage.payload.plots) {
                    stage.payload.plots = stage.payload.plots.slice(0, entry.count);
                }
            }
        });
    }

    function collectQuantities() {
        document.querySelectorAll('.dx-qty').forEach(input => {
            const entry = state.plan.find(p => p.type === input.dataset.type);
            if (entry) entry.count = parseInt(input.value || '1', 10) || 1;
        });
    }

    // Re-rendering on every keystroke would steal focus, so the counts are read back
    // into the plan first and only the flow badges are refreshed.
    window.renderQuantities = function () {
        collectQuantities();
        document.querySelectorAll('.dx-qty').forEach(input => {
            const entry = state.plan.find(p => p.type === input.dataset.type);
            if (entry) entry.count = parseInt(input.value || '1', 10) || 1;
        });
        refreshQuantityFlows();
    };

    /** Update just the "N -> M" badges in place. */
    function refreshQuantityFlows() {
        let incoming = state.sources.length;

        document.querySelectorAll('#dx-quantities > div').forEach((row, i) => {
            const p = state.plan[i];
            if (!p) return;

            const fixed = (p.type === 'merger' || p.type === 'extension');
            const outputs = fixed ? 1 : (p.count || 2);
            const badge = row.querySelector('.dx-flow-in');
            const outEl = row.querySelector('.dx-flow-out');
            const qty = row.querySelector('.dx-qty');

            if (qty && p.type === 'merger') qty.value = incoming;

            if (badge) badge.textContent = incoming;
            if (outEl) {
                outEl.textContent = outputs;
                outEl.classList.toggle('text-blue-600', outputs !== incoming);
                outEl.classList.toggle('text-slate-500', outputs === incoming);
            }

            // Grow or shrink the size boxes with the count, without disturbing the
            // quantity field the officer is typing in.
            const grid = row.querySelector('.dx-size-grid .grid');
            if (grid) {
                const want = sizeSlots(p, incoming);
                p.sizes = p.sizes || [];

                while (grid.children.length > want) grid.lastElementChild.remove();

                for (let i = grid.children.length; i < want; i++) {
                    const cell = document.createElement('div');
                    cell.innerHTML =
                        '<label class="block text-[11px] font-black uppercase tracking-wider text-slate-500 mb-1.5">'
                        + (p.type === 'merger' ? 'File ' : 'Plot ') + (i + 1) + '</label>'
                        + '<input type="number" step="0.01" min="0" placeholder="0.00" value="' + (p.sizes[i] ?? '')
                        + '" data-type="' + p.type + '" data-slot="' + i + '" oninput="onSizeInput(this)"'
                        + ' class="dx-size w-full px-2.5 py-2 rounded-lg border border-slate-200 bg-white text-sm text-center">';
                    grid.appendChild(cell);
                }

                const label = row.querySelector('.dx-size-grid p');
                if (label) label.textContent = 'Plot size' + (want === 1 ? '' : 's') + ' (Ha)';
            }

            const hint = row.querySelector('.dx-qty-hint');
            if (hint && p.type === 'merger') {
                hint.innerHTML = '<b class="text-slate-600">' + incoming + '</b> file'
                    + (incoming === 1 ? '' : 's') + ' merged into one parcel';
            }

            incoming = (p.type === 'change_of_purpose') ? incoming : outputs;
        });

        refreshSizeNotes();
    }

    // ---------------------------------------------------------------- step 3
    /**
     * What a stage consumes, derived from what is SAVED rather than from the last
     * save in this session. `state.carry` only knows about the stage just submitted,
     * so on resume - or on jumping back to an earlier stage - it is empty, which left
     * the input line blank and the Change of Purpose "Apply to" list with nothing in
     * it. The stage rows carry their holding numbers, so read them from there.
     */
    function carryFor(index) {
        if (index <= 0) return state.sources.map(s => s.fileNumber);

        const previous = state.stages[index - 1];
        const holdings = (previous?.files || [])
            .slice()
            .sort((a, b) => (a.sequence ?? 0) - (b.sequence ?? 0))
            .map(f => f.holding_no)
            .filter(Boolean);

        // Fall back to the in-session value for a stage saved a moment ago whose rows
        // have not been re-read yet.
        return holdings.length ? holdings : state.carry;
    }

    /** Re-read the duplex so stage rows (and their holding numbers) are current. */
    async function refreshStages() {
        if (!state.duplex) return;
        const res = await fetch('{{ url('duplex-parcel-update') }}/' + state.duplex.id, {
            headers: { 'Accept': 'application/json' }
        }).then(r => r.json());
        if (res.success) {
            state.stages = (res.data?.stage_rows || []).slice().sort((a, b) => a.rank - b.rank);
        }
    }

    /**
     * Jump straight to a stage from the track. Any stage already captured is
     * reachable, plus the one being worked on; stages further ahead stay locked
     * because their input does not exist yet.
     */
    window.goToStage = function (index) {
        if (index > state.stageIndex && state.stages[index]?.status !== 'done') return;
        state.stageIndex = index;
        renderStageTrack();
        renderStagePanel();
        showStep(3);
    };

    function renderStageTrack() {
        const track = document.getElementById('dx-stage-track');

        track.innerHTML = state.stages.map((s, i) => {
            const cls = i < state.stageIndex ? 'done' : (i === state.stageIndex ? 'current' : 'locked');
            const arrow = i < state.stages.length - 1
                ? '<i data-lucide="chevron-right" class="w-4 h-4 text-slate-300"></i>' : '';
            const tick = (i < state.stageIndex || s.status === 'done')
                ? '<i data-lucide="check" class="w-3.5 h-3.5"></i>'
                : `<span class="text-[10px] font-black">${s.rank}</span>`;

            // Reachable = already captured, or the stage in hand. Anything beyond that
            // has no input yet, so it stays inert rather than pretending to be a link.
            const reachable = i <= state.stageIndex || s.status === 'done';
            const tag = reachable ? 'button' : 'span';
            const attrs = reachable
                ? `type="button" onclick="goToStage(${i})" title="Go to ${TYPES[s.type]}" class="stage-pill ${cls} clickable`
                : `class="stage-pill ${cls}`;

            return `
                <${tag} ${attrs} inline-flex items-center gap-2 pl-2 pr-3.5 py-2 rounded-xl text-xs font-bold">
                    <span class="w-5 h-5 rounded-lg bg-white/70 flex items-center justify-center shrink-0">${tick}</span>
                    ${TYPES[s.type]}
                </${tag}>${arrow}`;
        }).join('');

        icons();
    }

    function renderStagePanel() {
        const stage = state.stages[state.stageIndex];
        const panel = document.getElementById('dx-stage-panel');

        if (!stage) { panel.innerHTML = ''; return; }

        const incoming = carryFor(state.stageIndex);
        const inputLine = incoming.join(', ');

        const inputLabel = state.stageIndex === 0 ? 'source file(s)' : 'holding, from the previous stage';
        const count = stage.plot_count || 1;

        // Whatever this stage was saved with, so going back to edit shows the real
        // entries instead of an empty form.
        const saved = stage.payload || {};

        // Sizes captured on step 2 seed the stage panel, so the officer is not asked
        // for the same number twice. Anything saved on the stage itself wins.
        const planned = (state.plan.find(p => p.type === stage.type) || {}).sizes || [];
        const savedPlots = (saved.plots && saved.plots.length)
            ? saved.plots
            : planned.map(size => ({ size }));
        const savedApplies = saved.applies_to || [];
        const esc = v => (v === null || v === undefined) ? '' : String(v).replace(/"/g, '&quot;');

        // A file's registered holder, taken from the record picked on step 1. Falls back
        // to the applicant for a holding number, which has no registry row of its own.
        const applicantName = document.getElementById('dx-applicant')?.value || '';
        const holderOf = fileNo => {
            const src = state.sources.find(x => x.fileNumber === fileNo);
            return (src && src.title) ? src.title : applicantName;
        };


        // How many holding numbers this stage will mint. Needed before the template,
        // because it decides whether the Tracking ID is optional.
        const outputs = (stage.type === 'merger' || stage.type === 'extension') ? 1 : count;

        let body = '';

        if (stage.type === 'change_of_purpose') {
            const options = incoming;

            // The purpose being changed FROM is the one the duplex started with, so it
            // is read off the source file rather than off this stage's input.
            const currentLandUse = landUseLabel(state.sources[0]?.fileNumber || '');
            body = `
                <div class="mb-5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Apply to</label>
                    <div class="flex flex-wrap gap-2">
                        ${options.map((o, i) => `
                            <label class="applies-chip inline-flex items-center gap-2 px-3 py-2 rounded-xl border text-xs font-semibold cursor-pointer">
                                <input type="checkbox" class="dx-applies" value="${o}" onchange="onAppliesChange()"
                                       ${savedApplies.length ? (savedApplies.includes(o) ? 'checked' : '') : (i < count ? 'checked' : '')}>
                                <span class="holding-no">${o}</span>
                            </label>`).join('')}
                    </div>

                    {{-- Says what will happen to THESE files, by name. The generic version
                         ("files you leave unticked pass through untouched") meant nothing to
                         anyone who had not written it. --}}
                    <p id="dx-applies-explain" class="text-[11px] text-slate-500 mt-2 leading-relaxed"></p>

                    {{-- Size and holder for the files being changed. Every other stage
                         records these, and a renamed parcel has a size like any other. --}}
                    <div id="dx-cop-plots" class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4"></div>
                </div>
                <div class="grid md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Current land use</label>
                        <input type="text" id="dx-current-landuse" readonly value="${currentLandUse}"
                            class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-sm font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">New land use *</label>
                        <select id="dx-new-landuse" onchange="onAppliesChange()"
                            class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">
                            <option value="">— Select —</option>
                            <option value="RES">Residential (RES)</option>
                            <option value="COM">Commercial (COM)</option>
                            <option value="IND">Industrial (IND)</option>
                            <option value="AGR">Agricultural (AGR)</option>
                            <option value="MIX">Mixed Use (MIX)</option>
                        </select>
                    </div>
                </div>`;
        } else if (stage.type === 'merger') {
            body = `
                <p class="text-sm text-slate-600 mb-4">
                    These files merge into one parcel. Give each its size as it stands today.
                </p>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    ${incoming.map((f, i) => `
                        <div class="p-4 rounded-2xl border border-slate-200 bg-slate-50/50">
                            <p class="text-[11px] font-black text-slate-600 holding-no mb-3 truncate" title="${f}">${f}</p>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Size (Ha)</label>
                            <input type="number" step="0.01" class="dx-plot-size w-full px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm"
                                   placeholder="0.00" value="${esc(savedPlots[i]?.size)}">
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1 mt-3">Holder</label>
                            <input type="text" class="dx-plot-holder w-full px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm"
                                   placeholder="Name" value="${esc(savedPlots[i]?.holder ?? holderOf(f))}">
                        </div>`).join('')}
                </div>`;
        } else {
            const label = stage.type === 'extension' ? 'Adjusted parcel' : 'Plot';
            body = `
                <p class="text-sm text-slate-600 mb-4">
                    ${stage.type === 'extension'
                        ? 'The extended parcel replaces the incoming file.'
                        : 'Size each plot, and name its holder. The holder defaults to the applicant — change it where a plot goes to someone else.'}
                </p>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    ${Array.from({ length: count }).map((_, i) => `
                        <div class="p-4 rounded-2xl border border-slate-200 bg-slate-50/50">
                            <p class="text-[11px] font-black text-slate-600 mb-3">${label} ${i + 1}</p>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1 mt-3">Size (Ha)</label>
                            <input type="number" step="0.01" class="dx-plot-size w-full px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm"
                                   placeholder="0.00" value="${esc(savedPlots[i]?.size)}">
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1 mt-3">Holder</label>
                            <input type="text" class="dx-plot-holder w-full px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm"
                                   placeholder="Name" value="${esc(savedPlots[i]?.holder ?? document.getElementById('dx-applicant').value ?? '')}">
                        </div>`).join('')}
                </div>`;
        }

        panel.innerHTML = `
            <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/60 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="rank-badge rank-${Math.min(stage.rank, 5)} w-8 h-8 rounded-xl border flex items-center justify-center text-xs font-black shrink-0">${stage.rank}</span>
                        <div class="min-w-0">
                            <p class="text-sm font-black text-slate-800 leading-tight">${TYPES[stage.type]}</p>
                            <p class="text-[11px] text-slate-400 mt-0.5">
                                Stage ${state.stageIndex + 1} of ${state.stages.length}
                            </p>
                        </div>
                    </div>
                    <div class="text-right min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Input · ${inputLabel}</p>
                        <p class="holding-no text-xs font-black text-slate-700 truncate max-w-xs">${inputLine || '—'}</p>
                    </div>
                </div>

                <div class="p-6">${body}</div>

                <div class="px-6 py-3 border-t border-slate-100 bg-slate-50/60 flex items-center gap-2 text-[11px] text-slate-500">
                    <i data-lucide="corner-down-right" class="w-3.5 h-3.5 text-slate-400"></i>
                    <span id="dx-stage-produces">Produces <strong class="text-slate-700">${outputs}</strong> holding number${outputs === 1 ? '' : 's'} for the next stage.</span>
                </div>
            </div>`;

        if (stage.type === 'change_of_purpose') onAppliesChange();

        icons();
    }

    /** Land-use codes as they appear in file numbers, mapped to what officers call them. */
    const LAND_USE_LABELS = {
        RES: 'Residential', COM: 'Commercial', IND: 'Industrial',
        AGR: 'Agricultural', AG: 'Agricultural', AGRIC: 'Agricultural',
        MIX: 'Mixed Use', MIXED: 'Mixed Use',
    };

    // Segments that sit BEFORE the land use in a file number rather than being one.
    const FILE_NO_PREFIXES = ['CON', 'ST', 'SLTR', 'KN'];

    /**
     * The land-use code carried by a file number.
     *
     *   CON-AG-1995-15    -> AG      (CON is a prefix, not a land use)
     *   ST-RES-2025-0001  -> RES
     *   RES-1994-762      -> RES
     *
     * Returns '' for anything that is not a registry file number - a holding number
     * like DPX-2026-0006-H01 has no land use, and reading "DPX" off it was exactly the
     * bug this replaces.
     */
    function landUseCode(fileNo) {
        const first = String(fileNo || '').split(',')[0].trim().toUpperCase();
        if (!first || first.startsWith('DPX-')) return '';

        const parts = first.split('-').filter(Boolean);
        if (!parts.length) return '';

        if (FILE_NO_PREFIXES.includes(parts[0]) && parts[1] && isNaN(parts[1])) {
            return parts[1];
        }

        return isNaN(parts[0]) ? parts[0] : '';
    }

    /** "Agricultural (AG)" - the code alone means nothing to most readers. */
    function landUseLabel(fileNo) {
        const code = landUseCode(fileNo);
        if (!code) return '';
        const name = LAND_USE_LABELS[code];
        return name ? name + ' (' + code + ')' : code;
    }

    /**
     * Restyle the chips and rewrite the explanation whenever the selection or the new
     * land use changes. Naming the actual files is the whole point: "H01 and H02 become
     * Commercial, H03 stays Industrial" is understood immediately, and no amount of
     * rewording the abstract version got close.
     */
    window.onAppliesChange = function () {
        const panel = document.getElementById('dx-stage-panel');
        if (!panel) return;

        const boxes = [...panel.querySelectorAll('.dx-applies')];
        if (!boxes.length) return;

        // Unticked chips read as inactive, so the selection is legible at a glance.
        boxes.forEach(box => {
            const chip = box.closest('.applies-chip');
            if (chip) chip.classList.toggle('is-off', !box.checked);
        });

        const on  = boxes.filter(b => b.checked).map(b => b.value);
        const off = boxes.filter(b => !b.checked).map(b => b.value);

        const shortName = v => String(v).split('-').pop();          // ...-H01 -> H01
        const list = arr => arr.length <= 1
            ? (arr[0] || '')
            : arr.slice(0, -1).join(', ') + ' and ' + arr[arr.length - 1];

        const select  = document.getElementById('dx-new-landuse');
        const newUse  = select && select.value
            ? select.options[select.selectedIndex].textContent.trim()
            : 'the new land use';

        const currentUse = document.getElementById('dx-current-landuse')?.value.trim() || 'their current land use';

        // The stage that produced these files, so the sentence can say where the file
        // numbers they keep came from.
        const previous = state.stages[state.stageIndex - 1];
        const from = previous ? 'the ' + TYPES[previous.type] : 'the previous stage';

        const out = document.getElementById('dx-applies-explain');
        if (!out) return;

        if (!on.length) {
            out.innerHTML = '<span class="text-amber-600 font-bold">Nothing ticked.</span> '
                + 'Tick the files whose land use is changing — at least one is needed.';
            return;
        }

        let text = '<strong>' + list(on.map(shortName)) + '</strong> become ' + newUse + '.';

        text += ' Their current numbers are decommissioned and replaced.';

        if (off.length) {
            text += ' <strong>' + list(off.map(shortName)) + '</strong> stay ' + currentUse
                 + ' and keep the file numbers ' + from + ' gave them.';
        }

        text += ' All ' + boxes.length + ' still get real file numbers at the Land step.';
        out.innerHTML = text;

        renderCopPlots(on);

        // A Change of Purpose hands on EVERY file it received, not just the ones it
        // changed - the untouched files still have to reach the Land step. Saying
        // "produces 2" when 5 leave the stage is what made the Done step unreadable.
        const produces = document.getElementById('dx-stage-produces');
        if (produces) {
            produces.innerHTML = 'Mints <strong class="text-slate-700">' + on.length + '</strong> new file number'
                + (on.length === 1 ? '' : 's') + '. The other <strong class="text-slate-700">' + off.length
                + '</strong> keep theirs, so <strong class="text-slate-700">' + boxes.length
                + '</strong> file' + (boxes.length === 1 ? '' : 's') + ' go on to the next step.';
        }
    };

    /**
     * Size and holder for each file a Change of Purpose renames.
     *
     * Rebuilt from the ticked chips so it always matches the selection, and existing
     * values are carried across a re-tick rather than being wiped.
     */
    function renderCopPlots(selected) {
        const box = document.getElementById('dx-cop-plots');
        if (!box) return;

        // Keep whatever has already been typed, keyed by the file it belongs to.
        const kept = {};
        box.querySelectorAll('[data-holding]').forEach(card => {
            kept[card.dataset.holding] = {
                size: card.querySelector('.dx-plot-size')?.value || '',
                holder: card.querySelector('.dx-plot-holder')?.value || '',
            };
        });

        const stage = state.stages[state.stageIndex];
        const saved = (stage?.payload?.plots) || [];
        const applicant = document.getElementById('dx-applicant')?.value || '';

        box.innerHTML = selected.map((h, i) => {
            const prior = kept[h] || {};
            const size = prior.size !== undefined && prior.size !== ''
                ? prior.size
                : (saved[i]?.size ?? '');
            const holder = prior.holder || saved[i]?.holder || applicant;

            return `
                <div data-holding="${h}" class="p-4 rounded-2xl border border-slate-200 bg-slate-50/50">
                    <p class="text-[11px] font-black text-slate-600 holding-no mb-3 truncate" title="${h}">${h}</p>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Size (Ha)</label>
                    <input type="number" step="0.01" class="dx-plot-size w-full px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm"
                           placeholder="0.00" value="${String(size).replace(/"/g, '&quot;')}">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1 mt-3">Holder</label>
                    <input type="text" class="dx-plot-holder w-full px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm"
                           placeholder="Name" value="${String(holder).replace(/"/g, '&quot;')}">
                </div>`;
        }).join('');
    }

    function collectStagePayload() {
        const stage = state.stages[state.stageIndex];
        const panel = document.getElementById('dx-stage-panel');

        const sizes = [...panel.querySelectorAll('.dx-plot-size')].map(e => e.value);
        const holders = [...panel.querySelectorAll('.dx-plot-holder')].map(e => e.value);

        const plots = sizes.map((size, i) => ({
            size: size === '' ? null : parseFloat(size),
            holder: holders[i] || null,
        }));

        const payload = {
            plot_count: stage.plot_count || plots.length || 1,
            plots: plots,
        };

        if (stage.type === 'change_of_purpose') {
            payload.new_land_use = document.getElementById('dx-new-landuse')?.value || '';
            payload.applies_to = [...panel.querySelectorAll('.dx-applies:checked')].map(e => e.value);
            if (!payload.new_land_use) {
                toast('warning', 'New land use required', 'Pick the land use this file is changing to.');
                return null;
            }
            if (!payload.applies_to.length) {
                toast('warning', 'Nothing selected', 'Tick at least one file for the Change of Purpose.');
                return null;
            }
        }

        return payload;
    }

    // ---------------------------------------------------------------- nav
    /**
     * Which wizard steps the officer may click to.
     *
     * Steps 1 and 2 stay reachable for reference once the duplex exists, but the plan
     * behind them is fixed by then - the stage rows and holding numbers were built
     * from it - so they open read-only rather than pretending an edit would stick.
     * Step 4 is the summary and only means anything once every stage is captured.
     */
    function canGoToStep(step) {
        if (step === state.step) return true;
        if (step <= 2) return true;
        if (step === 3) return !!state.duplex;
        if (step === 4) return !!state.duplex && state.stages.length > 0
            && state.stages.every(s => s.status === 'done');
        return false;
    }

    window.goToWizardStep = function (step) {
        if (!canGoToStep(step)) {
            const why = step === 4
                ? 'Every stage has to be captured before the summary.'
                : 'Finish selecting the updates first.';
            return toast('info', 'Not there yet', why);
        }

        if (step === 3) { renderStageTrack(); renderStagePanel(); }
        if (step === 4) renderDone();
        showStep(step);
    };

    function showStep(step) {
        state.step = step;
        document.querySelectorAll('.wizard-step').forEach(el => {
            el.classList.toggle('active', Number(el.dataset.step) === step);
        });

        // The button label lives in its own span so the trailing arrow icon survives.
        const next = document.getElementById('dx-next-text');
        const back = document.getElementById('dx-back');
        const label = document.getElementById('dx-step-label');
        const subtitle = document.getElementById('wizard-subtitle');

        back.style.visibility = step === 1 || step === 4 ? 'hidden' : 'visible';

        // Stepper: everything before the current step reads as done, and a tab you
        // cannot reach yet must not look or behave like a link.
        document.querySelectorAll('.dx-step-tab').forEach(tab => {
            const n = Number(tab.dataset.tab);
            tab.classList.toggle('is-active', n === step);
            tab.classList.toggle('is-done', n < step);

            const reachable = canGoToStep(n);
            tab.classList.toggle('is-locked', !reachable);
            const btn = tab.querySelector('button');
            if (btn) {
                btn.disabled = !reachable;
                btn.title = reachable ? 'Go to this step' : 'Not available yet';
            }
        });

        // Steps 1 and 2 are read-only once the duplex row exists.
        const locked = !!state.duplex;
        document.getElementById('dx-plan-locked').classList.toggle('hidden', !locked);
        document.getElementById('dx-applicant').disabled = locked;
        // Quantities and plot sizes stay editable. What is fixed once the duplex row
        // exists is the PLAN — which updates, in what order, on which files — because
        // the stage rows and holding numbers were built from it. How many plots a stage
        // makes, and how big they are, is ordinary data the officer must be able to
        // correct without deleting the duplex and starting again.
        ['dx-plot-no-main', 'dx-district', 'dx-lga'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.disabled = locked;
        });
        document.querySelectorAll('[onclick^="pickSourceFile"], [onclick^="removeSource"]')
            .forEach(el => { el.disabled = locked; el.classList.toggle('hidden', locked); });

        if (step === 1) {
            next.textContent = 'Start Process';
            label.textContent = 'Step 1 of 3';
            subtitle.textContent = 'Pick the file, then add the updates in the order you want them carried out.';
        } else if (step === 2) {
            next.textContent = 'Continue Process';
            label.textContent = 'Step 2 of 3';
            subtitle.textContent = 'How many of each.';
        } else if (step === 3) {
            const last = state.stageIndex === state.stages.length - 1;
            next.textContent = last ? 'Finish' : 'Save & Next Stage';
            label.textContent = 'Step 3 of 3';
            subtitle.textContent = 'Each stage runs on holding numbers — nothing is commissioned yet.';
        } else {
            next.textContent = 'Submit';
            label.textContent = '';
            subtitle.textContent = 'Captured.';
        }

        icons();
    }

    window.wizardBack = function () {
        if (state.step === 3 && state.stageIndex > 0) {
            // Stages already saved keep their holding numbers; stepping back is just
            // a view change, and re-saving a stage replaces its numbers cleanly.
            state.stageIndex -= 1;
            renderStageTrack();
            renderStagePanel();
            showStep(3);
            return;
        }
        if (state.step > 1) showStep(state.step - 1);
    };

    window.wizardNext = async function () {
        // The duplex already exists, so step 1/2 must not create it again — but an edit
        // made on the way back through still has to reach the stages. Without this the
        // corrected quantity was silently thrown away.
        if (state.duplex && state.step < 3) {
            if (state.step === 2) {
                collectQuantities();
                applyPlanCountsToStages();
            }
            return goToWizardStep(3);
        }

        if (state.step === 1) {
            // Checked in the order the fields now appear on the card.
            if (!state.sources.length) return toast('warning', 'Pick at least one source file');
            const applicant = document.getElementById('dx-applicant').value.trim();
            if (!applicant) return toast('warning', 'Applicant required');
            if (!state.plan.length) return toast('warning', 'Add at least one update');

            const merger = state.plan.find(p => p.type === 'merger');
            if (merger && merger.rank === 1 && state.sources.length < 2) {
                return toast('warning', 'A Merger needs two or more source files');
            }

            renderQuantities();
            return showStep(2);
        }

        if (state.step === 2) {
            collectQuantities();

            const res = await post('{{ route('duplex-parcel-update.store') }}', {
                // One field feeds both columns - they are the same thing here.
                applicant_name: document.getElementById('dx-applicant').value.trim(),
                file_title: document.getElementById('dx-applicant').value.trim(),
                plot_no: document.getElementById('dx-plot-no-main').value.trim() || null,
                district: document.getElementById('dx-district').value || null,
                lga: document.getElementById('dx-lga').value || null,
                state: STATE_NAME,
                address: document.getElementById('dx-address').value || null,
                source_file_nos: state.sources.map(s => s.fileNumber),
                stages: state.plan.map(p => ({ type: p.type, rank: p.rank, count: p.count })),
            });

            if (!res.success) {
                return toast('error', 'Could not create duplex', res.message || 'Check the form and try again.');
            }

            state.duplex = { id: res.id, duplex_id: res.duplex_id };

            // From here on the officer is working inside a numbered duplex, so the
            // reference belongs in the header where it stays visible.
            const badge = document.getElementById('dx-duplex-badge');
            badge.textContent = res.duplex_id;
            badge.classList.remove('hidden');

            const detail = await fetch('{{ url('duplex-parcel-update') }}/' + res.id, {
                headers: { 'Accept': 'application/json' }
            }).then(r => r.json());

            // stage_rows, not stages: `stages` on the payload is the JSON plan column
            // (type + rank + count). The rows are what carry the ids we post back to.
            state.stages = (detail.data?.stage_rows || []).sort((a, b) => a.rank - b.rank);
            state.stageIndex = 0;
            state.carry = [];

            renderStageTrack();
            renderStagePanel();
            return showStep(3);
        }

        if (state.step === 3) {
            const payload = collectStagePayload();
            if (!payload) return;

            const stage = state.stages[state.stageIndex];
            const res = await post(
                '{{ url('duplex-parcel-update') }}/' + state.duplex.id + '/stages/' + stage.id,
                payload
            );

            if (!res.success) {
                return toast('error', 'Stage not saved', res.message || 'Check the entries and try again.');
            }

            state.carry = res.holding_numbers || [];

            // Re-read the rows so the stage just saved carries its holding numbers and
            // its payload. Everything downstream - the next stage's input line, the
            // Apply-to list, and the values shown when the officer clicks back to this
            // stage - is read from those rows, not from this response.
            await refreshStages();

            if (state.stageIndex < state.stages.length - 1) {
                state.stageIndex += 1;
                renderStageTrack();
                renderStagePanel();
                return showStep(3);
            }

            renderDone();
            return showStep(4);
        }

        // Submitting from the Done step: hand the officer the summary sheet rather than
        // dropping them back on the register with nothing to check. The reload waits
        // until the sheet is dismissed.
        closeDuplexWizard();

        if (state.duplex && typeof window.showDuplexSummary === 'function') {
            await window.showDuplexSummary('{{ url('duplex-parcel-update') }}', state.duplex.id);
        }

        window.location.reload();
    };

    function renderDone() {
        const one = state.stages.length === 1;
        document.getElementById('dx-done-text').textContent = one
            ? state.duplex.duplex_id + ' captured with a single update.'
            : state.duplex.duplex_id + ' captured with ' + state.stages.length + ' updates, in the order you added them.';

        document.getElementById('dx-done-chain').innerHTML = state.stages.map(s => {
            const holdings = (s.files || [])
                .slice()
                .sort((a, b) => (a.sequence ?? 0) - (b.sequence ?? 0))
                .map(f => f.holding_no)
                .filter(Boolean);

            // A carried file kept the number the previous stage gave it - no new one was
            // minted for it, because no new FILE number will be minted either. That is
            // what makes a 5-plot subdivision plus a 2-file CoP use 7 numbers, not 10.
            const rows = (s.files || []).slice().sort((a, b) => (a.sequence ?? 0) - (b.sequence ?? 0));
            const isCarried = i => (rows[i]?.role === 'carried');
            const changedCount = rows.filter(r => r.role !== 'carried').length;

            const chip = (h, carried) => `
                <span class="holding-no text-[10px] font-black px-2 py-1 rounded-lg border ${carried
                    ? 'bg-slate-50 text-slate-400 border-slate-200'
                    : 'bg-slate-100 text-slate-600 border-slate-200'}">${h}</span>`;

            const chips = holdings.map((h, i) => chip(h, isCarried(i))).join('');

            const carried = holdings.length - changedCount;
            const summary = carried > 0
                ? changedCount + ' new · ' + carried + ' unchanged'
                : holdings.length + (holdings.length === 1 ? ' file' : ' files');

            const note = carried > 0
                ? `<p class="text-[10px] text-slate-400 mt-1.5">The greyed numbers keep the file numbers the previous stage gave them — this stage does not renumber them.</p>`
                : '';

            return `
                <div class="flex items-start gap-3 px-4 py-3 rounded-2xl border border-slate-200 bg-white">
                    <span class="rank-badge rank-${Math.min(s.rank, 5)} w-7 h-7 rounded-lg border flex items-center justify-center text-xs font-black shrink-0">${s.rank}</span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="text-sm font-bold text-slate-700 leading-tight">${TYPES[s.type]}</p>
                            ${holdings.length ? `<span class="text-[11px] font-bold text-slate-400 shrink-0">${summary}</span>` : ''}
                        </div>
                        ${chips ? `<div class="flex flex-wrap gap-1.5 mt-2">${chips}</div>` : ''}
                        ${note}
                    </div>
                </div>`;
        }).join('');
    }

    // ---------------------------------------------------------------- register
    /**
     * The row menu is moved to <body> and positioned in viewport coordinates.
     *
     * Left inside the table it is clipped: the table sits in an `overflow-x-auto`
     * wrapper, and any ancestor with a non-visible overflow crops an absolutely
     * positioned child — which is why the menu appeared to drop *inside* the table.
     * Escaping to <body> with `position: fixed` is the only way round that without
     * removing the horizontal scroll the table needs.
     */
    window.toggleRowMenu = function (id, trigger) {
        const menu = document.getElementById('row-menu-' + id);
        const isOpen = !menu.classList.contains('hidden');

        closeRowMenus();
        if (isOpen) return;

        if (menu.parentElement !== document.body) document.body.appendChild(menu);

        menu.classList.remove('hidden');

        const btn = trigger.getBoundingClientRect();
        const box = menu.getBoundingClientRect();
        const gap = 6;

        // Flip above the trigger when there is not enough room below, and keep the
        // right edge on screen.
        const below = window.innerHeight - btn.bottom;
        const top = below < box.height + gap && btn.top > box.height + gap
            ? btn.top - box.height - gap
            : btn.bottom + gap;

        menu.style.top = Math.max(8, top) + 'px';
        menu.style.left = Math.max(8, Math.min(btn.right - box.width, window.innerWidth - box.width - 8)) + 'px';
    };

    function closeRowMenus() {
        document.querySelectorAll('[id^="row-menu-"]').forEach(m => m.classList.add('hidden'));
    }

    document.addEventListener('click', function (e) {
        if (!e.target.closest('[id^="row-menu-"]') && !e.target.closest('button[onclick^="toggleRowMenu"]')) {
            closeRowMenus();
        }
    });

    // A fixed menu does not travel with the page, so close it rather than let it
    // hang detached from its row.
    window.addEventListener('scroll', closeRowMenus, true);
    window.addEventListener('resize', closeRowMenus);

    /**
     * Reopen an unfinished duplex at the first stage still to be captured.
     *
     * Without this a draft is a dead end: the wizard only ever opened on a brand-new
     * duplex, so an officer who closed it mid-capture could not get back in.
     */
    window.resumeDuplex = async function (id) {
        closeRowMenus();

        const res = await fetch('{{ url('duplex-parcel-update') }}/' + id, {
            headers: { 'Accept': 'application/json' }
        }).then(r => r.json());

        if (!res.success) return toast('error', 'Could not open this duplex');

        const d = res.data;
        openDuplexWizard();

        state.duplex = { id: d.id, duplex_id: d.duplex_id };
        state.sources = (d.source_file_nos || []).map(fn => ({ fileNumber: fn, title: d.file_title || '' }));
        state.plan = (d.stages || []).map(p => ({ type: p.type, rank: p.rank, count: p.count ?? null }));
        state.stages = (d.stage_rows || []).slice().sort((a, b) => a.rank - b.rank);

        // Land on the first stage not yet done — a rejected stage counts as not done,
        // which is what makes "re-run that one stage" reachable.
        const next = state.stages.findIndex(s => s.status !== 'done');
        state.stageIndex = next === -1 ? state.stages.length - 1 : next;
        state.carry = [];

        document.getElementById('dx-applicant').value = d.applicant_name || '';
        document.getElementById('dx-plot-no-main').value = d.plot_no || '';
        document.getElementById('dx-district').value = d.district || '';
        document.getElementById('dx-lga').value = d.lga || '';
        updateAddressPreview();

        const badge = document.getElementById('dx-duplex-badge');
        badge.textContent = d.duplex_id;
        badge.classList.remove('hidden');

        renderSources();
        renderRanks();
        renderStageTrack();
        renderStagePanel();
        showStep(3);

        toast('info', 'Resuming ' + d.duplex_id,
            'Picking up at stage ' + state.stages[state.stageIndex].rank + '.');
    };

    /**
     * The whole account of a duplex - sources, every stage, what was issued and what
     * was retired - in the same card the File Indexing and MLS commissioning screens
     * use. Available at any status, not just after commissioning.
     */
    window.openDuplexSummary = function (id) {
        closeRowMenus();

        if (typeof window.showDuplexSummary !== 'function') {
            return toast('error', 'Summary unavailable', 'The summary card script did not load.');
        }

        return window.showDuplexSummary('{{ url('duplex-parcel-update') }}', id);
    };

    window.viewDuplex = async function (id) {
        const res = await fetch('{{ url('duplex-parcel-update') }}/' + id, {
            headers: { 'Accept': 'application/json' }
        }).then(r => r.json());

        if (!res.success) return toast('error', 'Could not load duplex');

        const d = res.data;
        document.getElementById('dx-view-title').textContent = d.duplex_id;
        document.getElementById('dx-view-sub').textContent =
            (d.applicant_name || '') + ' · ' + (d.status || '').replace('_', ' ');

        const stages = (d.stages || []).sort((a, b) => a.rank - b.rank);
        document.getElementById('dx-view-body').innerHTML = stages.map(s => {
            const files = (s.files || []);
            return `
                <div class="border border-slate-200 rounded-2xl p-4">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="rank-badge rank-${Math.min(s.rank, 5)} text-center px-2 py-0.5 rounded-lg border text-xs font-black">${s.rank}</span>
                        <p class="text-sm font-black text-slate-700">${TYPES[s.type] || s.type}</p>
                        <span class="ml-auto text-[11px] font-bold uppercase text-slate-400">${s.status}</span>
                    </div>
                    ${files.length ? `<div class="space-y-1">${files.map(f => `
                        <div class="flex items-center gap-2 text-xs">
                            <span class="holding-no text-slate-500">${f.holding_no || f.source_file_no || ''}</span>
                            ${f.final_file_no ? `<i data-lucide="arrow-right" class="w-3 h-3 text-slate-300"></i>
                                <span class="holding-no font-bold text-emerald-700">${f.final_file_no}</span>` : ''}
                        </div>`).join('')}</div>`
                        : '<p class="text-xs text-slate-400">No holding numbers yet.</p>'}
                </div>`;
        }).join('');

        const modal = document.getElementById('dx-view-modal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        icons();
    };

    window.closeViewModal = function () {
        const modal = document.getElementById('dx-view-modal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    };

    window.openKnupda = function (id) {
        document.getElementById('knupda-duplex-id').value = id;
        const modal = document.getElementById('dx-knupda-modal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        icons();
    };

    window.closeKnupda = function () {
        const modal = document.getElementById('dx-knupda-modal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    };

    window.saveKnupda = async function () {
        const id = document.getElementById('knupda-duplex-id').value;
        const res = await post('{{ url('duplex-parcel-update') }}/' + id + '/knupda', {
            land_value: document.getElementById('knupda-land-value').value,
            knupda_fee: document.getElementById('knupda-fee').value,
            knupda_status: document.getElementById('knupda-status').value,
            knupda_remarks: document.getElementById('knupda-remarks').value,
        });

        if (!res.success) return toast('error', 'Not saved', res.message);
        closeKnupda();
        toast('success', 'KNUPDA updated');
        setTimeout(() => window.location.reload(), 900);
    };

    window.generateDoc = async function (id, kind) {
        const res = await post('{{ url('duplex-parcel-update') }}/' + id + '/generate-' + kind, {});
        if (!res.success) return toast('error', 'Not generated', res.message);
        toast('success', res.message);
        setTimeout(() => window.location.reload(), 900);
    };

    window.approveDuplex = async function (id) {
        // Approval is what opens the rest of the pipeline and puts the duplex in front
        // of Land, so it asks first — the same courtesy Reject already had.
        if (window.Swal) {
            const r = await Swal.fire({
                icon: 'question',
                title: 'Approve this duplex?',
                html: 'Approving unlocks KNUPDA, the application, the memo and the conveyance, '
                    + 'and lists the duplex for commissioning.',
                showCancelButton: true,
                confirmButtonText: 'Yes, approve',
                confirmButtonColor: '#059669',
                allowOutsideClick: false,
            });
            if (!r.isConfirmed) return;
        }

        const res = await post('{{ url('duplex-parcel-update') }}/' + id + '/approve', {});
        if (!res.success) return toast('error', 'Not approved', res.message);
        toast('success', res.message);
        setTimeout(() => window.location.reload(), 900);
    };

    window.rejectDuplex = async function (id) {
        let reason = '';
        if (window.Swal) {
            const r = await Swal.fire({
                icon: 'warning',
                title: 'Reject this duplex?',
                html: 'A rejected duplex is closed: every action except viewing is disabled.',
                input: 'text',
                inputLabel: 'Reason',
                showCancelButton: true,
                confirmButtonText: 'Yes, reject',
                confirmButtonColor: '#dc2626',
                allowOutsideClick: false,
            });
            if (!r.isConfirmed) return;
            reason = r.value || '';
        }
        const res = await post('{{ url('duplex-parcel-update') }}/' + id + '/reject', { reason });
        if (!res.success) return toast('error', 'Not rejected', res.message);
        toast('success', res.message);
        setTimeout(() => window.location.reload(), 900);
    };

    window.sendToLand = async function (id) {
        const res = await post('{{ url('duplex-parcel-update') }}/' + id + '/send-to-land', {});
        if (!res.success) return toast('error', 'Not sent', res.message);
        toast('success', res.message);
        setTimeout(() => window.location.reload(), 900);
    };

    window.deleteDuplex = async function (id) {
        if (window.Swal) {
            const r = await Swal.fire({
                icon: 'warning', title: 'Delete this duplex?',
                showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#dc2626',
            });
            if (!r.isConfirmed) return;
        }
        const res = await fetch('{{ url('duplex-parcel-update') }}/' + id, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        }).then(r => r.json());

        if (!res.success) return toast('error', 'Not deleted', res.message);
        toast('success', res.message);
        setTimeout(() => window.location.reload(), 900);
    };

    document.addEventListener('DOMContentLoaded', icons);
})();
</script>

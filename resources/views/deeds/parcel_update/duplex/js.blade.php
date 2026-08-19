{{-- Duplex Parcel Update — wizard, register actions and the stage runner. --}}
<script>
(function () {
    'use strict';

    const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const TYPES = @json($types);
    const LGAS = @json(collect($lgas)->pluck('name')->values());
    const DISTRICTS = @json(collect($districts)->pluck('name')->values());

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
        document.getElementById('dx-file-title').value = '';
        document.querySelectorAll('.dx-type').forEach(cb => { cb.checked = false; });
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
        if (!window.GlobalFileNoModal) return toast('error', 'File selector unavailable');

        window.GlobalFileNoModal.open({
            callback: function (picked) {
                const fileNumber = (picked.fileNumber || '').trim();
                if (!fileNumber) return;
                if (state.sources.some(s => s.fileNumber === fileNumber)) {
                    return toast('info', 'Already added', fileNumber + ' is already on this duplex.');
                }
                state.sources.push({ fileNumber, title: picked.file_title || '' });
                renderSources();
            }
        });
    };

    function renderSources() {
        const box = document.getElementById('dx-sources');
        if (!state.sources.length) {
            box.innerHTML = '<span class="text-xs text-slate-400">No file selected yet.</span>';
            return;
        }
        box.innerHTML = state.sources.map((s, i) => `
            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-100 border border-slate-200 text-xs font-bold text-slate-700">
                <span class="holding-no">${s.fileNumber}</span>
                <button type="button" onclick="removeSource(${i})" class="text-slate-400 hover:text-red-600">&times;</button>
            </span>`).join('');
    }

    window.removeSource = function (i) {
        state.sources.splice(i, 1);
        renderSources();
    };

    /**
     * Tick order = execution order. Ticking appends to the plan; unticking removes
     * it and everything below shuffles up, so the badges always read 1..N.
     */
    window.onTypeToggle = function (cb) {
        const type = cb.value;
        if (cb.checked) {
            state.plan.push({ type, rank: 0, count: null });
        } else {
            state.plan = state.plan.filter(p => p.type !== type);
        }
        renderRanks();
    };

    function renderRanks() {
        state.plan.forEach((p, i) => { p.rank = i + 1; });

        document.querySelectorAll('#dx-type-list .type-row').forEach(row => {
            const type = row.dataset.type;
            const entry = state.plan.find(p => p.type === type);
            const badge = row.querySelector('.rank-badge');

            row.classList.toggle('picked', !!entry);
            badge.className = 'rank-badge text-center px-2 py-0.5 rounded-lg border text-xs font-black';
            if (entry) {
                badge.classList.add('rank-' + Math.min(entry.rank, 5));
                badge.textContent = entry.rank;
            } else {
                badge.classList.add('hidden');
                badge.textContent = '';
            }
        });

        document.getElementById('dx-single-note').classList.toggle('hidden', state.plan.length !== 1);
    }

    // ---------------------------------------------------------------- step 2
    function renderQuantities() {
        const box = document.getElementById('dx-quantities');

        box.innerHTML = state.plan.map(p => {
            // Merger collapses many into one and Extension is a 1-to-1 adjustment,
            // so neither has a count to ask for.
            const fixed = (p.type === 'merger' || p.type === 'extension');
            const hint = p.type === 'merger'
                ? 'Consumes the source files you picked'
                : (p.type === 'extension' ? 'One adjusted parcel' : 'How many plots');

            return `
                <div class="flex items-center justify-between gap-4 px-4 py-3 rounded-xl border border-slate-200">
                    <div class="flex items-center gap-3">
                        <span class="rank-badge rank-${Math.min(p.rank, 5)} text-center px-2 py-0.5 rounded-lg border text-xs font-black">${p.rank}</span>
                        <div>
                            <p class="text-sm font-bold text-slate-700">${TYPES[p.type]}</p>
                            <p class="text-[11px] text-slate-400">${hint}</p>
                        </div>
                    </div>
                    <input type="number" min="1" max="200" ${fixed ? 'disabled' : ''}
                        value="${fixed ? 1 : (p.count || 2)}"
                        data-type="${p.type}"
                        class="dx-qty w-24 px-3 py-2 rounded-xl border border-slate-200 text-sm text-center ${fixed ? 'bg-slate-50 text-slate-400' : ''}">
                </div>`;
        }).join('');
    }

    function collectQuantities() {
        document.querySelectorAll('.dx-qty').forEach(input => {
            const entry = state.plan.find(p => p.type === input.dataset.type);
            if (entry) entry.count = parseInt(input.value || '1', 10) || 1;
        });
    }

    // ---------------------------------------------------------------- step 3
    function renderStageTrack() {
        const track = document.getElementById('dx-stage-track');

        track.innerHTML = state.stages.map((s, i) => {
            const cls = i < state.stageIndex ? 'done' : (i === state.stageIndex ? 'current' : 'locked');
            const arrow = i < state.stages.length - 1
                ? '<i data-lucide="chevron-right" class="w-4 h-4 text-slate-300"></i>' : '';
            return `<span class="stage-pill ${cls} px-3 py-1.5 rounded-lg text-xs font-bold">(${s.rank}) ${TYPES[s.type]}</span>${arrow}`;
        }).join('');

        icons();
    }

    function renderStagePanel() {
        const stage = state.stages[state.stageIndex];
        const panel = document.getElementById('dx-stage-panel');

        if (!stage) { panel.innerHTML = ''; return; }

        const inputLine = state.stageIndex === 0
            ? state.sources.map(s => s.fileNumber).join(', ')
            : state.carry.join(', ');

        const inputLabel = state.stageIndex === 0 ? 'source file(s)' : 'holding, from the previous stage';
        const count = stage.plot_count || 1;

        let body = '';

        if (stage.type === 'change_of_purpose') {
            const options = state.stageIndex === 0 ? state.sources.map(s => s.fileNumber) : state.carry;
            body = `
                <div class="mb-5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Apply to</label>
                    <div class="flex flex-wrap gap-2">
                        ${options.map((o, i) => `
                            <label class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-200 text-xs font-semibold cursor-pointer">
                                <input type="checkbox" class="dx-applies" value="${o}" ${i < count ? 'checked' : ''}>
                                <span class="holding-no">${o}</span>
                            </label>`).join('')}
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1.5">
                        Files you leave unticked pass through untouched and keep their current purpose.
                    </p>
                </div>
                <div class="grid md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Current land use</label>
                        <input type="text" id="dx-current-landuse" readonly value="${guessLandUse(inputLine)}"
                            class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-sm font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">New land use *</label>
                        <select id="dx-new-landuse" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">
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
                    ${(state.stageIndex === 0 ? state.sources.map(s => s.fileNumber) : state.carry).map((f, i) => `
                        <div class="p-3 rounded-xl border border-slate-200">
                            <p class="text-[11px] font-bold text-slate-500 holding-no mb-1.5">${f}</p>
                            <input type="number" step="0.01" class="dx-plot-size w-full px-3 py-2 rounded-lg border border-slate-200 text-sm" placeholder="Size">
                            <input type="text" class="dx-plot-holder w-full px-3 py-2 mt-2 rounded-lg border border-slate-200 text-sm" placeholder="Holder">
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
                        <div class="p-3 rounded-xl border border-slate-200">
                            <p class="text-[11px] font-bold text-slate-500 mb-1.5">${label} ${i + 1}</p>
                            <input type="text" class="dx-plot-no w-full px-3 py-2 rounded-lg border border-slate-200 text-sm" placeholder="Plot no">
                            <input type="number" step="0.01" class="dx-plot-size w-full px-3 py-2 mt-2 rounded-lg border border-slate-200 text-sm" placeholder="Size">
                            <input type="text" class="dx-plot-holder w-full px-3 py-2 mt-2 rounded-lg border border-slate-200 text-sm"
                                placeholder="Holder" value="${document.getElementById('dx-applicant').value || ''}">
                        </div>`).join('')}
                </div>`;
        }

        panel.innerHTML = `
            <div class="border border-slate-200 rounded-2xl p-5">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-xs font-black uppercase tracking-wider text-slate-500">
                            Stage ${state.stageIndex + 1} of ${state.stages.length} — ${TYPES[stage.type]}
                        </p>
                        <p class="text-[11px] text-slate-400 mt-0.5">
                            Input: <span class="holding-no font-bold text-slate-600">${inputLine || '—'}</span>
                            <span class="text-slate-400">(${inputLabel})</span>
                        </p>
                    </div>
                </div>
                ${body}
            </div>`;

        icons();
    }

    function guessLandUse(fileNo) {
        const first = (fileNo || '').split(',')[0].trim();
        const parts = first.split('-');
        if (parts.length >= 3 && isNaN(parts[1])) return parts[0] + '-' + parts[1];
        return parts[0] || '';
    }

    function collectStagePayload() {
        const stage = state.stages[state.stageIndex];
        const panel = document.getElementById('dx-stage-panel');

        const sizes = [...panel.querySelectorAll('.dx-plot-size')].map(e => e.value);
        const holders = [...panel.querySelectorAll('.dx-plot-holder')].map(e => e.value);
        const plotNos = [...panel.querySelectorAll('.dx-plot-no')].map(e => e.value);

        const plots = sizes.map((size, i) => ({
            size: size === '' ? null : parseFloat(size),
            holder: holders[i] || null,
            plot_no: plotNos[i] || null,
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
    function showStep(step) {
        state.step = step;
        document.querySelectorAll('.wizard-step').forEach(el => {
            el.classList.toggle('active', Number(el.dataset.step) === step);
        });

        const next = document.getElementById('dx-next');
        const back = document.getElementById('dx-back');
        const label = document.getElementById('dx-step-label');
        const subtitle = document.getElementById('wizard-subtitle');

        back.style.visibility = step === 1 || step === 4 ? 'hidden' : 'visible';

        if (step === 1) {
            next.textContent = 'Start Process';
            label.textContent = 'Step 1 of 3';
            subtitle.textContent = 'Tick the updates in the order you want them carried out.';
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
            next.textContent = 'Close';
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
        if (state.step === 1) {
            const applicant = document.getElementById('dx-applicant').value.trim();
            if (!applicant) return toast('warning', 'Applicant required');
            if (!state.sources.length) return toast('warning', 'Pick at least one source file');
            if (!state.plan.length) return toast('warning', 'Tick at least one update');

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
                applicant_name: document.getElementById('dx-applicant').value.trim(),
                file_title: document.getElementById('dx-file-title').value.trim(),
                source_file_nos: state.sources.map(s => s.fileNumber),
                stages: state.plan.map(p => ({ type: p.type, rank: p.rank, count: p.count })),
            });

            if (!res.success) {
                return toast('error', 'Could not create duplex', res.message || 'Check the form and try again.');
            }

            state.duplex = { id: res.id, duplex_id: res.duplex_id };

            const detail = await fetch('{{ url('duplex-parcel-update') }}/' + res.id, {
                headers: { 'Accept': 'application/json' }
            }).then(r => r.json());

            state.stages = (detail.data?.stages || []).sort((a, b) => a.rank - b.rank);
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

            if (state.stageIndex < state.stages.length - 1) {
                state.stageIndex += 1;
                renderStageTrack();
                renderStagePanel();
                return showStep(3);
            }

            renderDone();
            return showStep(4);
        }

        closeDuplexWizard();
        window.location.reload();
    };

    function renderDone() {
        const one = state.stages.length === 1;
        document.getElementById('dx-done-text').textContent = one
            ? state.duplex.duplex_id + ' captured with a single update.'
            : state.duplex.duplex_id + ' captured with ' + state.stages.length + ' updates, in the order you ticked them.';

        document.getElementById('dx-done-chain').innerHTML = state.stages.map(s => `
            <div class="flex items-center gap-3 px-4 py-2.5 rounded-xl border border-slate-200">
                <span class="rank-badge rank-${Math.min(s.rank, 5)} text-center px-2 py-0.5 rounded-lg border text-xs font-black">${s.rank}</span>
                <span class="text-sm font-bold text-slate-700">${TYPES[s.type]}</span>
            </div>`).join('');
    }

    // ---------------------------------------------------------------- register
    window.toggleRowMenu = function (id) {
        const menu = document.getElementById('row-menu-' + id);
        document.querySelectorAll('[id^="row-menu-"]').forEach(m => {
            if (m !== menu) m.classList.add('hidden');
        });
        menu.classList.toggle('hidden');
    };

    document.addEventListener('click', function (e) {
        if (!e.target.closest('[id^="row-menu-"]') && !e.target.closest('button[onclick^="toggleRowMenu"]')) {
            document.querySelectorAll('[id^="row-menu-"]').forEach(m => m.classList.add('hidden'));
        }
    });

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
        const res = await post('{{ url('duplex-parcel-update') }}/' + id + '/approve', {});
        if (!res.success) return toast('error', 'Not approved', res.message);
        toast('success', res.message);
        setTimeout(() => window.location.reload(), 900);
    };

    window.rejectDuplex = async function (id) {
        let reason = '';
        if (window.Swal) {
            const r = await Swal.fire({
                title: 'Reject duplex', input: 'text', inputLabel: 'Reason',
                showCancelButton: true, confirmButtonText: 'Reject',
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

/**
 * OP-holder Match on the Recommendation capture form.
 *
 * When the officer picks a file whose Occupancy Permit was granted to one person
 * while File Indexing holds another — and no transfer on the file explains the
 * change — this draws the file's chain and offers Match. Match writes the missing
 * Transfer of Title server-side; the officer is never asked to key it, because it is
 * one row with two names already on the file and hand-keying it is how the wrong
 * parties, the wrong file, or nothing at all gets recorded.
 *
 * After a Match the capture continues in "existing recommendation" mode: this file
 * has been through recommendation before, that letter is approved and will not go
 * for approval again, so no new one is generated. The record is still saved — the
 * RofO is generated and printed from it — and the approved letter is uploaded on the
 * register, which is what unlocks Approve.
 *
 * Batch mode is out of scope: a batch is a subdivision mother's children, which do
 * not come from OSS.
 *
 * The card is fail-open. A file that cannot be checked (network, a 500, a file with
 * no indexing row) captures exactly as it always did — this must never be the reason
 * an ordinary recommendation cannot be saved.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('land-recommendation-form');
        var fileNoInput = document.getElementById('file_number');
        var host = document.getElementById('op-match-card');

        if (!form || !fileNoInput || !host) return;

        var checkUrl = form.dataset.opmatchCheckUrl || '';
        var matchUrl = form.dataset.opmatchUrl || '';
        var csrf = form.querySelector('input[name="_token"]');

        var flagInput = document.getElementById('is_existing_recommendation');
        var totInput = document.getElementById('op_match_tot_pra_id');
        var batchToggle = document.getElementById('batch-mode-toggle');

        var lastChecked = '';
        var inFlight = null;

        function esc(value) {
            return String(value === null || value === undefined ? '' : value)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        function batchOn() {
            return !!(batchToggle && batchToggle.checked);
        }

        /** Clear the card and every trace of the mode it may have switched on. */
        function reset() {
            host.innerHTML = '';
            host.classList.add('hidden');
            setExistingMode(false, null);
        }

        /**
         * "This file keeps its existing approved recommendation."
         *
         * Carried on hidden inputs so the server records the decision rather than
         * re-deriving it: Match writes the very row whose absence made the file
         * qualify, so the condition is gone by the time the form is submitted.
         */
        function setExistingMode(on, totId) {
            if (flagInput) flagInput.value = on ? '1' : '0';
            if (totInput) totInput.value = on && totId ? String(totId) : '';

            // The submit button is deliberately left alone. Saving is saving, whatever
            // mode the file is in, and uploading the approved letter is a separate act
            // that lives in the record's action menu on the register — not something
            // one button both promises and cannot actually do.
        }

        function timelineRows(rows) {
            if (!rows || !rows.length) {
                return '<p class="text-xs text-slate-500 italic">No transactions are recorded on this file.</p>';
            }

            return rows.map(function (r) {
                var tone = r.is_op ? 'border-amber-300 bg-amber-50'
                    : (r.is_tot ? 'border-emerald-300 bg-emerald-50' : 'border-slate-200 bg-white');

                return ''
                    + '<li class="relative pl-6 pb-3 last:pb-0">'
                    +   '<span class="absolute left-0 top-1.5 h-2.5 w-2.5 rounded-full ' + (r.is_op ? 'bg-amber-500' : (r.is_tot ? 'bg-emerald-500' : 'bg-slate-300')) + '"></span>'
                    +   '<div class="rounded-lg border ' + tone + ' px-3 py-2">'
                    +     '<div class="flex items-center justify-between gap-3">'
                    +       '<span class="text-xs font-bold text-slate-800">' + esc(r.type) + '</span>'
                    +       '<span class="text-[10px] text-slate-500 whitespace-nowrap">' + esc(r.date || '—') + '</span>'
                    +     '</div>'
                    +     '<div class="mt-1 text-[11px] text-slate-600">'
                    +       '<span class="font-medium">' + esc(r.party_1 || '—') + '</span>'
                    +       ' <span class="text-slate-400">&rarr;</span> '
                    +       '<span class="font-medium">' + esc(r.party_2 || '—') + '</span>'
                    +     '</div>'
                    +   '</div>'
                    + '</li>';
            }).join('');
        }

        /** The card: what the file says, why it is a problem, and the one action. */
        function render(state) {
            var applies = !!state.applies;

            var head = applies
                ? '<div class="flex items-start gap-3">'
                +   '<span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700"><i data-lucide="alert-triangle" class="h-4 w-4"></i></span>'
                +   '<div>'
                +     '<h3 class="text-sm font-bold text-amber-900">The Occupancy Permit names a different holder</h3>'
                +     '<p class="mt-0.5 text-xs text-amber-800">' + esc(state.reason) + '</p>'
                +   '</div>'
                + '</div>'
                : (state.name_spelling_only
                    ? '<div class="flex items-start gap-3">'
                    +   '<span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-100 text-sky-700"><i data-lucide="spell-check" class="h-4 w-4"></i></span>'
                    +   '<div>'
                    +     '<h3 class="text-sm font-bold text-sky-900">Same holder, spelt two ways</h3>'
                    +     '<p class="mt-0.5 text-xs text-sky-800">' + esc(state.reason) + '</p>'
                    +   '</div>'
                    + '</div>'
                    : '<div class="flex items-start gap-3">'
                    +   '<span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700"><i data-lucide="check" class="h-4 w-4"></i></span>'
                    +   '<div>'
                    +     '<h3 class="text-sm font-bold text-emerald-900">File history</h3>'
                    +     '<p class="mt-0.5 text-xs text-emerald-800">' + esc(state.reason) + '</p>'
                    +   '</div>'
                    + '</div>');

            // The two names are the whole point of the card, so they carry a colour
            // each and keep it in every state: amber is always the holder the chain
            // records (the OP), indigo is always the name File Indexing holds. The
            // arrow between them is the direction a Match would transfer in.
            //
            // Same two colours on a file that needs no action — a reader who has seen
            // one card can read the next without re-learning what the tints mean.
            var names = ''
                + '<div class="mt-3 flex flex-col sm:flex-row items-stretch sm:items-center gap-2">'
                +   '<div class="flex-1 rounded-lg border border-amber-300 bg-amber-100/70 px-3 py-2">'
                +     '<div class="text-[10px] font-bold uppercase tracking-wider text-amber-700">Root of title</div>'
                +     '<div class="text-xs font-bold text-amber-950">' + esc(state.root_of_title || '—') + '</div>'
                +   '</div>'
                +   '<div class="shrink-0 self-center text-slate-400">'
                +     '<i data-lucide="arrow-right" class="h-4 w-4 hidden sm:block"></i>'
                +     '<i data-lucide="arrow-down" class="h-4 w-4 sm:hidden"></i>'
                +   '</div>'
                +   '<div class="flex-1 rounded-lg border border-indigo-300 bg-indigo-100/70 px-3 py-2">'
                +     '<div class="text-[10px] font-bold uppercase tracking-wider text-indigo-700">File Indexing name</div>'
                +     '<div class="text-xs font-bold text-indigo-950">' + esc(state.indexing_name || '—') + '</div>'
                +   '</div>'
                + '</div>';

            var action = applies
                ? '<div class="mt-3 flex flex-wrap items-center gap-3">'
                +   '<button type="button" id="op-match-btn" class="inline-flex items-center gap-2 px-4 py-2 bg-amber-600 text-white text-xs font-bold rounded-lg hover:bg-amber-700 transition shadow-sm">'
                +     '<i data-lucide="git-merge" class="h-4 w-4"></i> Match'
                +   '</button>'
                +   '<span class="text-[11px] text-slate-600">Records the transfer from '
                +     '<b class="text-amber-800">' + esc(state.op ? state.op.holder : '') + '</b>'
                +     ' to <b class="text-indigo-800">' + esc(state.indexing_name) + '</b>, then continues the capture.</span>'
                + '</div>'
                : '';

            // Once the file is in "existing recommendation" mode the fact belongs to
            // this card, not to a panel of its own — the save button already says it
            // too, and a full-width box for one sentence pushed the form down.
            var mode = (flagInput && flagInput.value === '1')
                ? '<p class="mt-3 flex items-start gap-2 text-[11px] text-violet-800 border-t border-violet-200/70 pt-3">'
                +   '<i data-lucide="file-check-2" class="h-3.5 w-3.5 shrink-0 mt-px"></i>'
                +   '<span><b>No new recommendation is generated for this file.</b> Its letter was already approved — save the record, then upload that letter from the action menu on the register. The approval waits for it.</span>'
                + '</p>'
                : '';

            host.innerHTML = ''
                + '<div class="rounded-xl border ' + (applies ? 'border-amber-200 bg-amber-50/60'
                        : (state.name_spelling_only ? 'border-sky-200 bg-sky-50/60' : 'border-emerald-200 bg-emerald-50/50')) + ' p-5">'
                +   head
                +   names
                +   '<details class="mt-3" ' + (applies ? 'open' : '') + '>'
                +     '<summary class="cursor-pointer text-[11px] font-bold uppercase tracking-wider text-slate-500 hover:text-slate-700">File history (' + (state.timeline || []).length + ')</summary>'
                +     '<ul class="mt-2 border-l border-slate-200 ml-1">' + timelineRows(state.timeline) + '</ul>'
                +   '</details>'
                +   action
                +   mode
                + '</div>';

            host.classList.remove('hidden');
            if (window.lucide) window.lucide.createIcons();

            var btn = document.getElementById('op-match-btn');
            if (btn) btn.addEventListener('click', function () { runMatch(btn); });
        }

        function runMatch(btn) {
            var fileNo = fileNoInput.value.trim();
            if (!fileNo || !matchUrl) return;

            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader" class="h-4 w-4 animate-spin"></i> Matching…';
            if (window.lucide) window.lucide.createIcons();

            var body = new FormData();
            body.append('file_number', fileNo);
            if (csrf) body.append('_token', csrf.value);

            fetch(matchUrl, {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
                .then(function (res) {
                    if (!res.ok || !res.data.success) {
                        throw new Error(res.data.message || 'The transfer could not be recorded.');
                    }

                    // The write is what puts this file into "existing recommendation"
                    // mode — the recommendation it already has was approved on paper.
                    setExistingMode(true, res.data.pra_id);
                    render(res.data.data || {});

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Transfer recorded',
                            html: '<p class="text-sm text-slate-600">' + esc(res.data.message) + '</p>'
                                + '<p class="text-xs text-slate-500 mt-2">This file already has an approved recommendation, so a new one will not be generated. '
                                + 'Save the record, then upload that approved letter — it is what allows the approval.</p>',
                            confirmButtonColor: '#d97706'
                        });
                    }
                })
                .catch(function (err) {
                    btn.disabled = false;
                    btn.innerHTML = '<i data-lucide="git-merge" class="h-4 w-4"></i> Match';
                    if (window.lucide) window.lucide.createIcons();

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Not matched', text: err.message, confirmButtonColor: '#dc2626' });
                    }
                });
        }

        function check() {
            var fileNo = fileNoInput.value.trim();

            if (batchOn() || !fileNo || !checkUrl) {
                reset();
                lastChecked = '';
                return;
            }

            if (fileNo === lastChecked) return;
            lastChecked = fileNo;

            // A second selection while the first is still in the air must not paint
            // the earlier file's answer over the later one.
            if (inFlight) inFlight.abort();
            var controller = ('AbortController' in window) ? new AbortController() : null;
            inFlight = controller;

            fetch(checkUrl + '?file_number=' + encodeURIComponent(fileNo), {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller ? controller.signal : undefined
            })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (payload) {
                    if (!payload || !payload.success) { reset(); return; }

                    var state = payload.data || {};

                    // The card is only worth the space when there is something to say:
                    // an action to take, or a transfer already on file whose name does
                    // not match, which is the officer's cue that a correction — not a
                    // new transfer — is what this file needs.
                    if (state.applies || state.has_working_transfer || state.name_spelling_only) {
                        setExistingMode(false, null);
                        render(state);
                    } else {
                        reset();
                    }
                })
                .catch(function () { /* fail open — an ordinary capture is unaffected */ });
        }

        // Saving without pressing Match would write a recommendation on top of a
        // chain that still does not explain how the title reached the applicant. The
        // server refuses this too; this only saves the round trip and keeps the
        // officer on the card that has the button.
        form.addEventListener('submit', function (event) {
            var pending = document.getElementById('op-match-btn');
            if (!pending || (flagInput && flagInput.value === '1')) return;

            event.preventDefault();
            event.stopImmediatePropagation();

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Match this file first',
                    text: 'The Occupancy Permit names a different holder and no transfer on the file explains the change. '
                        + 'Press Match to record it, then save.',
                    confirmButtonColor: '#d97706'
                }).then(function () {
                    host.scrollIntoView({ behavior: 'smooth', block: 'center' });
                });
            } else {
                host.scrollIntoView();
            }
        // Capture phase: this has to run before the form's own submit handlers, or
        // the save is already on its way by the time it is refused.
        }, true);

        // The file-number selector writes the field with jQuery's .val().trigger('change'),
        // which never reaches a native listener — so bind through jQuery as well.
        fileNoInput.addEventListener('change', check);
        if (window.jQuery) window.jQuery(fileNoInput).on('change', check);
        if (batchToggle) batchToggle.addEventListener('change', check);

        // An edit page opens with a file number already in the field.
        if (fileNoInput.value.trim() !== '') check();
    });
})();

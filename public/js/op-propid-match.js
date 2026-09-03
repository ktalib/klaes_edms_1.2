/**
 * OP → File Property ID Matching.
 *
 * The FILE is the control record. The officer loads a confirmed file on the left and
 * its Property ID becomes the target; on the right they search the Occupancy Permits —
 * by serial number, because that is what is printed on the permit in their hand — tick
 * the ones that belong to the file, and Batch Match moves every ticked permit onto the
 * file's Property ID.
 *
 * WHY SELECTION SURVIVES A SEARCH
 * A file's permits rarely share one serial: the officer searches 989, ticks two,
 * searches 990, ticks another. So ticks are held in a Map keyed source_table:op_id and
 * are NOT cleared when a new search runs — only by Clear, by a change of file, or by a
 * completed match. Anything selected but no longer on screen is still counted and still
 * submitted, and the summary says how many are off-screen so that is never a surprise.
 *
 * WHY THE TARGET FILE CLEARS THE SELECTION
 * Ticks mean "these belong to THAT file". Changing the file changes what they mean, so
 * they are dropped rather than carried onto a target the officer never checked them
 * against.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.getElementById('op-propid-match');
        if (!root) return;

        var urls = {
            file: root.dataset.fileUrl,
            ops: root.dataset.opsUrl,
            batch: root.dataset.batchUrl,
            batches: root.dataset.batchesUrl,
            undo: root.dataset.undoUrl
        };
        var csrf = root.dataset.csrf || '';

        // ------------------------------------------------------------------ state
        var target = null;          // the chosen file: {file_number, prop_id, ...}
        var results = [];           // the OP rows currently on screen
        var selected = new Map();   // source_table:op_id -> the row, across searches
        var busy = false;

        // ------------------------------------------------------------------ elements
        var el = {
            banner: document.getElementById('opm-banner'),
            reset: document.getElementById('opm-reset'),

            fileDisplay: document.getElementById('opm-file-display'),
            filePickBtn: document.getElementById('opm-file-pick-btn'),
            fileSelected: document.getElementById('opm-file-selected'),
            fileExisting: document.getElementById('opm-file-existing'),

            advancedToggle: document.getElementById('opm-advanced-toggle'),
            advancedLabel: document.getElementById('opm-advanced-label'),
            advanced: document.getElementById('opm-advanced'),
            serial: document.getElementById('opm-serial'),
            serialMode: document.getElementById('opm-serial-mode'),
            opFile: document.getElementById('opm-op-file'),
            party: document.getElementById('opm-party'),
            propId: document.getElementById('opm-prop-id'),
            opType: document.getElementById('opm-op-type'),
            hideMatched: document.getElementById('opm-hide-matched'),
            unlinkedOnly: document.getElementById('opm-unlinked-only'),
            opSearchBtn: document.getElementById('opm-op-search-btn'),
            opClear: document.getElementById('opm-op-clear'),
            opCount: document.getElementById('opm-op-count'),
            opResults: document.getElementById('opm-op-results'),

            summary: document.getElementById('opm-summary'),
            companions: document.getElementById('opm-move-companions'),
            batchBtn: document.getElementById('opm-batch-btn'),
            batchLabel: document.getElementById('opm-batch-label'),

            batches: document.getElementById('opm-batches'),
            batchesRefresh: document.getElementById('opm-batches-refresh')
        };

        // ------------------------------------------------------------------ helpers
        function esc(value) {
            return String(value === null || value === undefined ? '' : value)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        function dash(value) {
            var text = String(value === null || value === undefined ? '' : value).trim();
            return text === '' ? '—' : esc(text);
        }

        function icons() {
            if (window.lucide && typeof window.lucide.createIcons === 'function') window.lucide.createIcons();
        }

        function key(row) {
            return row.source_table + ':' + row.op_id;
        }

        function shortDate(value) {
            if (!value) return '—';
            var parsed = new Date(String(value).replace(' ', 'T'));
            if (isNaN(parsed.getTime())) return esc(String(value).slice(0, 10));
            return parsed.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        }

        function banner(kind, message) {
            var palette = {
                ok: 'bg-emerald-50 border-emerald-200 text-emerald-800',
                warn: 'bg-amber-50 border-amber-200 text-amber-800',
                error: 'bg-rose-50 border-rose-200 text-rose-800',
                info: 'bg-sky-50 border-sky-200 text-sky-800'
            };
            var glyph = { ok: 'check-circle-2', warn: 'alert-triangle', error: 'alert-octagon', info: 'info' };

            el.banner.className = 'mb-5 rounded-xl border px-4 py-3 text-sm flex items-start gap-2.5 ' + (palette[kind] || palette.info);
            el.banner.innerHTML =
                '<i data-lucide="' + (glyph[kind] || 'info') + '" class="h-4 w-4 mt-0.5 flex-shrink-0"></i>' +
                '<div class="flex-1">' + esc(message) + '</div>' +
                '<button type="button" class="opm-banner-close text-current/60 hover:text-current">' +
                '<i data-lucide="x" class="h-4 w-4"></i></button>';
            el.banner.classList.remove('hidden');
            icons();
            el.banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function clearBanner() {
            el.banner.classList.add('hidden');
            el.banner.innerHTML = '';
        }

        /** GET a JSON endpoint. Errors come back as a rejected promise carrying the server's message. */
        function get(url, params) {
            var query = new URLSearchParams(params || {}).toString();
            return fetch(url + (query ? '?' + query : ''), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            }).then(readJson);
        }

        function post(url, body) {
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify(body)
            }).then(readJson);
        }

        function readJson(response) {
            return response.json().catch(function () {
                throw new Error('The server returned something that was not a response (HTTP ' + response.status + ').');
            }).then(function (payload) {
                if (!response.ok || payload.success === false) {
                    throw new Error(payload.message || firstValidationError(payload) || 'That request failed (HTTP ' + response.status + ').');
                }
                return payload;
            });
        }

        function firstValidationError(payload) {
            if (!payload || !payload.errors) return null;
            var keys = Object.keys(payload.errors);
            return keys.length ? payload.errors[keys[0]][0] : null;
        }

        // ------------------------------------------------------------------ left: file
        /**
         * Open the shared Global File Number Selector.
         *
         * The picker owns the searching, the registry tabs and the recent-selections
         * list; this page only takes the number it returns and loads it as the target.
         * Its callback hands back { fileNumber, tab, file_title, record } — only the
         * number is trusted here, because fileTarget() re-reads the file from the
         * registries anyway and its answer is the one the match is made against.
         */
        function openFilePicker() {
            if (!window.GlobalFileNoModal || typeof window.GlobalFileNoModal.open !== 'function') {
                banner('error', 'The File Number Selector did not load on this page. Refresh, and if it keeps happening report it — the file cannot be chosen without it.');
                return;
            }

            window.GlobalFileNoModal.open({
                // The picker fills any input it recognises as a file-number field by name.
                // This page has none to fill — the display box is set from the callback,
                // after the server has confirmed the file — so that is turned off rather
                // than left to guess.
                autoPopulateGenericFields: false,
                callback: function (picked) {
                    var fileNumber = ((picked && picked.fileNumber) || '').toString().trim().replace(/-+$/, '');
                    if (!fileNumber) return;
                    pickFile(fileNumber);
                }
            });
        }

        /**
         * Load a file as the target.
         *
         * `allocate` is passed only when the officer has pressed the button on the
         * unregistered-file panel. It is never set automatically: minting a parcel id for
         * a file that already has one under another of its numbers is how a batch ends up
         * on an id belonging to nothing.
         */
        function pickFile(fileNumber, allocate) {
            el.fileSelected.classList.remove('hidden');
            el.fileSelected.innerHTML = '<div class="rounded-lg border border-slate-200 px-4 py-6 text-center text-sm text-slate-400">Loading the file…</div>';

            var params = { file_no: fileNumber };
            if (allocate) params.allocate = 1;

            get(urls.file, params).then(function (payload) {
                target = payload.data;
                el.fileDisplay.value = target.file_number;

                // A change of target invalidates every tick: they meant "these belong to
                // the file I was looking at", and that file is no longer this one.
                selected.clear();

                renderTarget();
                renderExisting(target.existing || []);
                renderResults();
                updateSummary();

                if (target.prop_id_allocated) {
                    banner('ok', 'A new Property ID was allocated for ' + target.file_number + ': ' +
                        target.prop_id + '. It is now this file\'s permanent parcel id.');
                } else if (target.needs_allocation) {
                    banner('warn', target.file_number + ' has no Property ID registered against it. ' +
                        'Check the related numbers below before allocating a new one.');
                } else {
                    clearBanner();
                }
            }).catch(function (error) {
                target = null;
                el.fileDisplay.value = '';
                el.fileSelected.classList.add('hidden');
                el.fileExisting.classList.add('hidden');
                updateSummary();
                banner('error', error.message);
            });
        }

        function renderTarget() {
            if (!target) {
                el.fileSelected.classList.add('hidden');
                el.fileSelected.innerHTML = '';
                return;
            }

            var badges = [];
            if (target.commissioned) badges.push('<span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 text-[10px] font-semibold">Commissioned</span>');
            if (target.in_mls) badges.push('<span class="px-2 py-0.5 rounded bg-slate-100 text-slate-600 text-[10px] font-semibold">MLS File Numbers</span>');
            if (target.indexed) badges.push('<span class="px-2 py-0.5 rounded bg-slate-100 text-slate-600 text-[10px] font-semibold">Indexed</span>');

            el.fileSelected.classList.remove('hidden');
            el.fileSelected.innerHTML = '' +
                '<div class="rounded-xl border-2 border-violet-200 bg-violet-50/50 p-4">' +
                  '<div class="flex items-start justify-between gap-4">' +
                    '<div class="min-w-0">' +
                      '<div class="text-[11px] font-semibold uppercase tracking-wide text-violet-600">Target file</div>' +
                      '<div class="text-lg font-bold text-slate-900 mt-0.5">' + dash(target.file_number) + '</div>' +
                      // The picker's KANGIS tabs return a KANGIS number; the permits being
                      // moved will be carrying the land file number behind it, so show it.
                      (target.also_known_as
                        ? '<div class="text-xs text-violet-700 font-medium">also indexed as ' + esc(target.also_known_as) + '</div>'
                        : '') +
                      '<div class="text-sm text-slate-600">' + dash(target.file_title) + '</div>' +
                      '<div class="flex flex-wrap gap-1.5 mt-2">' + badges.join('') + '</div>' +
                    '</div>' +
                    '<div class="flex-shrink-0 text-right">' +
                      '<div class="text-[11px] font-semibold uppercase tracking-wide ' +
                        (target.needs_allocation ? 'text-amber-600' : 'text-violet-600') + '">Target Property ID</div>' +
                      (target.needs_allocation
                        ? '<div class="text-2xl font-black text-amber-600 leading-tight">none</div>'
                        : '<div class="text-3xl font-black text-violet-700 tabular-nums leading-tight">' + esc(target.prop_id) + '</div>') +
                    '</div>' +
                  '</div>' +
                  '<dl class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4 pt-3 border-t border-violet-200/70 text-xs">' +
                    field('Plot', target.plot_no) + field('Land use', target.land_use) +
                    field('LGA', target.lga) + field('Location', target.location) +
                  '</dl>' +
                  renderRelated() +
                '</div>';

            icons();
        }

        /**
         * The file's other numbers, and what each one's parcel id is.
         *
         * Always shown when there are any, not only on the unregistered path: a land file,
         * its Old KANGIS file and its New KANGIS file are three files over one parcel and
         * carry three different prop_ids on purpose. Seeing that the number in hand points
         * somewhere else is the difference between consolidating a file and splitting it.
         */
        function renderRelated() {
            var related = (target && target.related) || [];
            if (!related.length && !target.needs_allocation) return '';

            var rows = related.map(function (item) {
                return '<div class="flex items-center justify-between gap-2 py-1">' +
                    '<span class="font-medium text-slate-700">' + dash(item.file_number) + '</span>' +
                    (item.prop_id
                      ? '<button type="button" class="opm-use-related px-2 py-0.5 rounded bg-white border border-violet-200 text-violet-700 text-[11px] font-semibold hover:bg-violet-50 transition" ' +
                        'data-file="' + esc(item.file_number) + '">use PROP_ID ' + esc(item.prop_id) + '</button>'
                      : '<span class="text-slate-400 text-[11px]">not registered</span>') +
                  '</div>';
            }).join('');

            return '' +
                '<div class="mt-3 pt-3 border-t border-violet-200/70">' +
                  '<div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 mb-1">' +
                    'Other numbers on this file' +
                  '</div>' +
                  (rows || '<div class="text-xs text-slate-400 py-1">None recorded.</div>') +
                  (target.needs_allocation
                    ? '<div class="mt-3 rounded-lg bg-amber-50 border border-amber-200 p-3">' +
                        '<div class="text-xs text-amber-800">' +
                          '<span class="font-semibold">This number has no Property ID.</span> ' +
                          'If one of the numbers above is the same parcel, use its Property ID instead — ' +
                          'a new one would put these permits on a parcel of their own.' +
                        '</div>' +
                        '<button type="button" id="opm-allocate-btn" ' +
                          'class="mt-2 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold transition">' +
                          '<i data-lucide="plus-circle" class="h-3.5 w-3.5"></i> Allocate a new Property ID for ' + esc(target.file_number) +
                        '</button>' +
                      '</div>'
                    : '') +
                '</div>';
        }

        function field(label, value) {
            return '<div><dt class="text-slate-400 font-medium">' + esc(label) + '</dt>' +
                   '<dd class="text-slate-800 font-semibold truncate">' + dash(value) + '</dd></div>';
        }

        function renderExisting(rows) {
            if (!rows.length) {
                el.fileExisting.classList.remove('hidden');
                el.fileExisting.innerHTML =
                    '<div class="rounded-lg border border-dashed border-slate-300 px-4 py-3 text-xs text-slate-500">' +
                    'Nothing is recorded on this Property ID yet.</div>';
                return;
            }

            el.fileExisting.classList.remove('hidden');
            el.fileExisting.innerHTML = '' +
                '<details class="rounded-lg border border-slate-200 overflow-hidden" open>' +
                  '<summary class="px-4 py-2.5 bg-slate-50 cursor-pointer text-xs font-semibold text-slate-600 select-none">' +
                    'Already on Property ID ' + esc(target ? target.prop_id : '') + ' — ' + rows.length + ' record(s)' +
                  '</summary>' +
                  '<div class="max-h-56 overflow-auto divide-y divide-slate-100">' +
                    rows.map(function (row) {
                        return '<div class="px-4 py-2 text-xs">' +
                            '<div class="flex items-center justify-between gap-2">' +
                              '<span class="font-semibold text-slate-800">' + dash(row.instrument_type) + '</span>' +
                              '<span class="text-slate-400 tabular-nums">' + esc(row.source_table) + ' #' + esc(row.id) + '</span>' +
                            '</div>' +
                            '<div class="text-slate-500 mt-0.5">' +
                              dash(row.file_no) +
                              (row.op_serial_number ? ' · serial ' + esc(row.op_serial_number) : '') +
                              ' · ' + dash(row.party_2 || row.party_1) +
                            '</div>' +
                          '</div>';
                    }).join('') +
                  '</div>' +
                '</details>';
        }

        // ------------------------------------------------------------------ right: OPs
        function searchOps() {
            var params = {
                serial: el.serial.value.trim(),
                serial_mode: el.serialMode.value,
                file_no: el.opFile.value.trim(),
                party: el.party.value.trim(),
                prop_id: el.propId.value.trim(),
                op_type: el.opType.value
            };

            if (el.unlinkedOnly.checked) params.unlinked_only = 1;
            if (el.hideMatched.checked && target && target.prop_id) params.exclude_prop_id = target.prop_id;

            el.opResults.classList.remove('hidden');
            el.opResults.innerHTML = '<div class="rounded-lg border border-slate-200 px-4 py-8 text-center text-sm text-slate-400">Searching…</div>';
            el.opCount.textContent = '';

            get(urls.ops, params).then(function (payload) {
                results = payload.data || [];
                renderResults();

                if (payload.truncated) {
                    banner('warn', 'More permits match than can be shown. Narrow the search — add the file number, ' +
                        'the holder name, or fewer serials — so you can see everything you are about to move.');
                } else {
                    clearBanner();
                }
            }).catch(function (error) {
                results = [];
                el.opResults.classList.add('hidden');
                el.opCount.textContent = '';
                banner('error', error.message);
            });
        }

        function renderResults() {
            if (!results.length) {
                el.opResults.classList.remove('hidden');
                el.opResults.innerHTML =
                    '<div class="rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-400">' +
                    'No Occupancy Permit matches that search.</div>';
                el.opCount.textContent = '';
                updateSummary();
                return;
            }

            var onPageSelected = results.filter(function (row) { return selected.has(key(row)); }).length;

            el.opCount.textContent = results.length + ' found · ' + onPageSelected + ' ticked here';
            el.opResults.classList.remove('hidden');
            el.opResults.innerHTML = '' +
                '<div class="rounded-lg border border-slate-200 overflow-hidden">' +
                  '<div class="px-4 py-2 bg-slate-50 border-b border-slate-200 flex items-center gap-3">' +
                    '<label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600 cursor-pointer">' +
                      '<input type="checkbox" id="opm-select-all" ' + (onPageSelected === results.length ? 'checked' : '') + ' ' +
                        'class="h-3.5 w-3.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"> Select all on screen' +
                    '</label>' +
                  '</div>' +
                  '<div class="max-h-[28rem] overflow-auto divide-y divide-slate-100">' +
                    results.map(renderRow).join('') +
                  '</div>' +
                '</div>';

            icons();
            updateSummary();
        }

        function renderRow(row) {
            var isSelected = selected.has(key(row));
            var alreadyOnTarget = target && target.prop_id && String(row.prop_id) === String(target.prop_id);
            var companions = row.companions;

            return '' +
                '<label class="flex items-start gap-3 px-4 py-3 cursor-pointer transition ' +
                  (isSelected ? 'bg-emerald-50/70' : 'hover:bg-slate-50') + '">' +
                  '<input type="checkbox" class="opm-op-tick mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" ' +
                    'data-key="' + esc(key(row)) + '" ' + (isSelected ? 'checked' : '') + (alreadyOnTarget ? ' disabled' : '') + '>' +
                  '<div class="min-w-0 flex-1">' +
                    '<div class="flex items-center flex-wrap gap-2">' +
                      '<span class="px-2 py-0.5 rounded bg-slate-900 text-white text-[11px] font-bold tabular-nums">Serial ' + dash(row.op_serial_number) + '</span>' +
                      '<span class="font-semibold text-sm text-slate-900">' + dash(row.file_no) + '</span>' +
                      (row.temp_file_no && row.temp_file_no !== row.file_no
                        ? '<span class="px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 text-[10px] font-semibold">' + esc(row.temp_file_no) + '</span>'
                        : '') +
                      (row.op_type ? '<span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 text-[10px] font-semibold">' + esc(row.op_type) + '</span>' : '') +
                    '</div>' +
                    '<div class="text-xs text-slate-600 mt-1">' +
                      '<span class="text-slate-400">Holder:</span> <span class="font-medium">' + dash(row.holder) + '</span>' +
                      ' <span class="text-slate-300">|</span> <span class="text-slate-400">Granted:</span> ' + shortDate(row.transaction_date) +
                      ' <span class="text-slate-300">|</span> <span class="text-slate-400">Reg:</span> ' + dash(row.reg_particulars) +
                    '</div>' +
                    '<div class="text-[11px] text-slate-400 mt-0.5">' +
                      esc(row.source_table) + ' #' + esc(row.op_id) +
                      (row.plot_no ? ' · Plot ' + esc(row.plot_no) : '') +
                      (row.lga ? ' · ' + esc(row.lga) : '') +
                      (row.location ? ' · ' + esc(row.location) : '') +
                    '</div>' +
                  '</div>' +
                  '<div class="flex-shrink-0 text-right">' +
                    (alreadyOnTarget
                      ? '<span class="px-2 py-1 rounded-md bg-emerald-100 text-emerald-700 text-[11px] font-semibold">already matched</span>'
                      : '<span class="px-2 py-1 rounded-md bg-slate-100 text-slate-600 text-[11px] font-semibold tabular-nums">PROP_ID ' + dash(row.prop_id) + '</span>') +
                    (companions
                      ? '<div class="text-[10px] text-amber-600 mt-1">+' + companions + ' linked row(s)</div>'
                      : '') +
                  '</div>' +
                '</label>';
        }

        function findRow(rowKey) {
            for (var i = 0; i < results.length; i++) {
                if (key(results[i]) === rowKey) return results[i];
            }
            return null;
        }

        // ------------------------------------------------------------------ batch
        function updateSummary() {
            var count = selected.size;
            var onScreen = results.filter(function (row) { return selected.has(key(row)); }).length;
            var offScreen = count - onScreen;

            var companionTotal = 0;
            selected.forEach(function (row) { companionTotal += (row.companions || 0); });

            if (!target && count === 0) {
                el.summary.innerHTML = 'Select a confirmed file on the left and at least one OP on the right.';
            } else if (!target) {
                el.summary.innerHTML = '<span class="text-amber-600 font-medium">' + count +
                    ' OP(s) ticked — now select the confirmed file on the left.</span>';
            } else if (!target.prop_id) {
                // A target with no parcel id is not a target. There is nothing to move the
                // permits ONTO, so the button stays shut until the officer resolves it.
                el.summary.innerHTML = '<span class="text-amber-600 font-medium">' +
                    esc(target.file_number) + ' has no Property ID yet.</span> ' +
                    'Use one of the file\'s other numbers, or allocate a new Property ID, before matching.';
            } else if (count === 0) {
                el.summary.innerHTML = 'Target is <span class="font-semibold text-slate-800">' + esc(target.file_number) +
                    '</span> (Property ID <span class="font-semibold text-violet-700">' + esc(target.prop_id) +
                    '</span>). Now search and tick the OPs that belong to it.';
            } else {
                el.summary.innerHTML =
                    '<span class="font-semibold text-slate-800">' + count + ' OP record(s)</span>' +
                    (companionTotal && el.companions.checked
                        ? ' <span class="text-slate-500">plus ' + companionTotal + ' linked Transfer of Title row(s)</span>'
                        : '') +
                    ' will be moved to Property ID <span class="font-semibold text-violet-700">' + esc(target.prop_id) +
                    '</span> — file <span class="font-semibold text-slate-800">' + esc(target.file_number) + '</span>.' +
                    (offScreen > 0
                        ? '<div class="text-xs text-amber-600 mt-1">' + offScreen +
                          ' of these were ticked in an earlier search and are not on screen. They will still be matched.</div>'
                        : '');
            }

            var ready = !!target && !!target.prop_id && count > 0 && !busy;
            el.batchBtn.disabled = !ready;
            el.batchLabel.textContent = count > 0 ? 'Batch Match ' + count + ' OP(s)' : 'Batch Match';
        }

        function runBatch() {
            if (!target || !target.prop_id || selected.size === 0 || busy) return;

            var ops = [];
            selected.forEach(function (row) {
                ops.push({ source_table: row.source_table, op_id: row.op_id });
            });

            var confirmed = window.confirm(
                'Move ' + ops.length + ' Occupancy Permit record(s) onto Property ID ' + target.prop_id +
                ' (file ' + target.file_number + ')?\n\n' +
                'Their current Property IDs will be replaced. This can be undone from Recent matches.'
            );
            if (!confirmed) return;

            busy = true;
            el.batchBtn.disabled = true;
            el.batchLabel.textContent = 'Matching…';

            post(urls.batch, {
                file_no: target.file_number,
                prop_id: target.prop_id,
                ops: ops,
                move_companions: el.companions.checked
            }).then(function (payload) {
                banner(payload.errors && payload.errors.length ? 'warn' : 'ok', payload.message);

                // Done means done: the ticks are spent, and leaving them would invite the
                // same batch being run twice against the next file the officer opens.
                selected.clear();
                renderExisting(payload.existing || []);

                // Re-run the search so the moved permits redraw carrying their new id,
                // rather than showing the officer a stale screen they might act on again.
                if (results.length) searchOps();
                else renderResults();

                updateSummary();
                loadBatches();
            }).catch(function (error) {
                banner('error', error.message);
            }).finally(function () {
                busy = false;
                updateSummary();
            });
        }

        // ------------------------------------------------------------------ history
        function loadBatches() {
            el.batches.innerHTML = '<span class="text-slate-400">Loading…</span>';

            get(urls.batches).then(function (payload) {
                var rows = payload.data || [];
                if (!rows.length) {
                    el.batches.innerHTML = '<span class="text-slate-400">No matches have been made yet.</span>';
                    return;
                }

                el.batches.innerHTML = '<div class="divide-y divide-slate-100">' + rows.map(function (batch) {
                    return '<div class="flex items-center justify-between gap-3 py-2.5 px-1">' +
                        '<div class="min-w-0">' +
                          '<div class="text-sm font-medium text-slate-800">' +
                            dash(batch.file_number) + ' <span class="text-slate-400">→ PROP_ID ' + esc(batch.prop_id) + '</span>' +
                          '</div>' +
                          '<div class="text-[11px] text-slate-400">' +
                            esc(batch.batch_ref) + ' · ' + batch.records + ' record(s) · ' + dash(batch.created_at) +
                          '</div>' +
                        '</div>' +
                        (batch.undone
                          ? '<span class="px-2.5 py-1 rounded-md bg-slate-100 text-slate-500 text-[11px] font-semibold">Undone</span>'
                          : '<button type="button" class="opm-undo px-3 py-1.5 rounded-md border border-rose-200 text-rose-700 hover:bg-rose-50 text-xs font-semibold transition" ' +
                            'data-batch="' + esc(batch.batch_ref) + '">Undo</button>') +
                      '</div>';
                }).join('') + '</div>';
            }).catch(function (error) {
                el.batches.innerHTML = '<span class="text-rose-600">' + esc(error.message) + '</span>';
            });
        }

        function undoBatch(batchRef) {
            if (!window.confirm('Put batch ' + batchRef + ' back the way it was?\n\n' +
                'Records that have been moved again since will be left alone.')) return;

            post(urls.undo, { batch_ref: batchRef }).then(function (payload) {
                banner('ok', payload.message);
                loadBatches();
                if (target) pickFile(target.file_number);
            }).catch(function (error) {
                banner('error', error.message);
            });
        }

        // ------------------------------------------------------------------ wiring
        el.filePickBtn.addEventListener('click', openFilePicker);
        el.fileDisplay.addEventListener('click', openFilePicker);

        el.fileSelected.addEventListener('click', function (event) {
            // Switch the target to a related number that already has a parcel id.
            var use = event.target.closest('.opm-use-related');
            if (use) { pickFile(use.dataset.file); return; }

            // The explicit allocation. Confirmed, because it creates a parcel identity
            // that nothing else in the registry will point at.
            if (event.target.closest('#opm-allocate-btn')) {
                if (!target) return;
                if (!window.confirm(
                    'Allocate a brand-new Property ID for ' + target.file_number + '?\n\n' +
                    'Do this only if none of the other numbers on this file is the same parcel. ' +
                    'The permits you match will sit on a parcel of their own.'
                )) return;
                pickFile(target.file_number, true);
            }
        });

        el.advancedToggle.addEventListener('click', function () {
            var hidden = el.advanced.classList.toggle('hidden');
            el.advancedLabel.textContent = hidden ? 'Advanced search' : 'Hide advanced';
        });

        el.opSearchBtn.addEventListener('click', searchOps);
        [el.serial, el.opFile, el.party, el.propId].forEach(function (input) {
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') { event.preventDefault(); searchOps(); }
            });
        });

        el.opClear.addEventListener('click', function () {
            el.serial.value = '';
            el.opFile.value = '';
            el.party.value = '';
            el.propId.value = '';
            el.opType.value = '';
            results = [];
            selected.clear();
            el.opResults.classList.add('hidden');
            el.opResults.innerHTML = '';
            el.opCount.textContent = '';
            updateSummary();
        });

        el.opResults.addEventListener('change', function (event) {
            if (event.target.id === 'opm-select-all') {
                var on = event.target.checked;
                results.forEach(function (row) {
                    if (target && String(row.prop_id) === String(target.prop_id)) return;
                    if (on) selected.set(key(row), row);
                    else selected.delete(key(row));
                });
                renderResults();
                return;
            }

            if (event.target.classList.contains('opm-op-tick')) {
                var rowKey = event.target.dataset.key;
                var row = findRow(rowKey);
                if (!row) return;

                if (event.target.checked) selected.set(rowKey, row);
                else selected.delete(rowKey);

                event.target.closest('label').classList.toggle('bg-emerald-50/70', event.target.checked);
                el.opCount.textContent = results.length + ' found · ' +
                    results.filter(function (r) { return selected.has(key(r)); }).length + ' ticked here';
                updateSummary();
            }
        });

        el.companions.addEventListener('change', updateSummary);
        el.batchBtn.addEventListener('click', runBatch);

        el.batchesRefresh.addEventListener('click', loadBatches);
        el.batches.addEventListener('click', function (event) {
            var button = event.target.closest('.opm-undo');
            if (button) undoBatch(button.dataset.batch);
        });

        el.banner.addEventListener('click', function (event) {
            if (event.target.closest('.opm-banner-close')) clearBanner();
        });

        el.reset.addEventListener('click', function () {
            target = null;
            results = [];
            selected.clear();
            el.fileDisplay.value = '';
            el.fileSelected.classList.add('hidden');
            el.fileExisting.classList.add('hidden');
            el.opClear.click();
            clearBanner();
            updateSummary();
        });

        updateSummary();
        loadBatches();
        icons();
    });
})();

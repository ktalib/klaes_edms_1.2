// ── Batch capture ──────────────────────────────────────────────────────────
// One recommendation per file, in a single pass. Two kinds share this module and
// the same table: a SUBDIVISION batch, whose rows are the commissioned children
// of one mother file, and a REGULAR batch, whose rows are file numbers the
// officer picked by hand. The rest of the form keeps capturing the values common
// to the whole batch; only what differs per file lives in the table, and the form
// posts to the batch endpoint instead.
document.addEventListener('DOMContentLoaded', function () {
    var BATCH_TYPE     = 'Plot Subdivision';
    var MOTHERS_URL    = 'X';
    var CHILDREN_URL   = 'X';
    var BATCH_ACTION   = 'X';
    var FILES_URL      = 'X';
    var FILE_DETAIL_URL = 'X';
    var PURPOSES_URL   = 'X';

    var toggle      = document.getElementById('batch-mode-toggle');
    var hint        = document.getElementById('batch-mode-hint');
    var fileNoCard  = document.getElementById('file-number-card');
    var fileNoInput = document.getElementById('file_number');
    var appToggle   = document.getElementById('app-type-toggle');
    var oldFileNo   = document.getElementById('old_file_number');
    var card        = document.getElementById('batch-children-card');
    var rowsBody    = document.getElementById('batch-children-rows');
    var motherLabel = document.getElementById('batch-mother-label');
    var countLabel  = document.getElementById('batch-children-count');
    var statusBox   = document.getElementById('batch-children-status');
    var selectAll   = document.getElementById('batch-select-all');
    var reloadBtn   = document.getElementById('batch-reload-children');
    var applyAllBtn = document.getElementById('batch-apply-all');
    var motherPick  = document.getElementById('batch-mother-picker');
    var motherSel   = document.getElementById('batch-mother-select');
    var manualPick  = document.getElementById('atx-old-fileno-manual');
    var motherHelp  = document.getElementById('batch-mother-help');
    var manualHelp  = document.getElementById('atx-old-fileno-help');
    var motherField = document.getElementById('batch-mother-file-no');
    var mothersLoaded = false;

    // Regular-files kind.
    var kindRow     = document.getElementById('batch-kind-row');
    var kindRadios  = document.querySelectorAll('.batch-kind-radio');
    var kindInput   = document.getElementById('batch-kind');
    var filesPicker = document.getElementById('batch-files-picker');
    var fileSel     = document.getElementById('batch-file-select');
    var filesApply  = document.getElementById('batch-files-apply');
    var filesClear  = document.getElementById('batch-files-clear');
    var filesCount  = document.getElementById('batch-files-count');
    var cardTitle   = document.getElementById('batch-card-title');
    var colFileNo   = document.getElementById('batch-col-file-no');
    var footerNote  = document.getElementById('batch-footer-note');
    var filesSelectReady = false;

    // Sections that move up under Batch Mode while it is on, in the order a batch is
    // actually filled in: pick the type, pick the mother, then work the children.
    // Outside a batch they belong where they are in the markup — Old File Number, for
    // one, only makes sense after Application Type, which is what decides whether an
    // old file number is needed at all — so each remembers its home and goes back.
    var relocatable = ['recommendation-type-block', 'application-type-card', 'atx-old-fileno-row']
        .map(function (id) { return { el: document.getElementById(id), home: null }; })
        .filter(function (r) { return r.el; });
    var recForm     = document.getElementById('land-recommendation-form');
    var singleAction = recForm ? recForm.getAttribute('action') : '';

    if (!toggle || !recForm) return;

    var LAND_USES = []->map(fn ($lu) => ['id' => $lu->id, 'name' => $lu->landuse])->values());
    var purposeCache = {};

    function esc(v) {
        return String(v == null ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function setStatus(message, tone) {
        if (!statusBox) return;
        if (!message) { statusBox.classList.add('hidden'); statusBox.textContent = ''; return; }
        statusBox.className = 'mb-3 text-xs font-semibold rounded-lg px-3 py-2 ' + (
            tone === 'error' ? 'bg-rose-50 border border-rose-200 text-rose-700'
                             : 'bg-amber-50 border border-amber-200 text-amber-800'
        );
        statusBox.textContent = message;
    }

    function currentAppType() {
        var checked = document.querySelector('.app-type-radio:checked');
        return (appToggle && !appToggle.checked) ? '' : (checked ? checked.value : '');
    }

    // 'subdivision' | 'regular'. Subdivision is the default and the fallback: it is
    // what every batch was before regular files existed, so nothing that fails to
    // read the radios can silently change the meaning of a post.
    function currentKind() {
        var picked = document.querySelector('.batch-kind-radio:checked');
        return (picked && picked.value === 'regular') ? 'regular' : 'subdivision';
    }

    function isRegular() { return currentKind() === 'regular'; }

    // Fields the table now owns are hidden AND disabled — disabled inputs are not
    // submitted, which also stands down their `required` so the browser cannot
    // block the batch post on a field the user can no longer see.
    //
    // Hiding is done with a class on the form rather than per-element, because the
    // application-type script re-shows #app-type-extra whenever a type is picked
    // and would race a per-element toggle back open.
    function standDownPerChildFields(on) {
        recForm.classList.toggle('batch-mode', on);
        document.querySelectorAll('[data-batch-child]').forEach(function (wrap) {
            wrap.querySelectorAll('input, select, textarea').forEach(function (el) {
                if (on) {
                    if (el.dataset.batchWasDisabled === undefined) {
                        el.dataset.batchWasDisabled = el.disabled ? '1' : '0';
                    }
                    el.disabled = true;
                } else if (el.dataset.batchWasDisabled !== undefined) {
                    el.disabled = el.dataset.batchWasDisabled === '1';
                    delete el.dataset.batchWasDisabled;
                }
            });
        });
    }

    function applyBatchMode(on) {
        if (kindRow) kindRow.classList.toggle('hidden', !on);

        // Autosave only exists for a batch — a single record is short enough that
        // losing it to a timeout is an annoyance rather than a day's work.
        if (draftBar) draftBar.classList.toggle('hidden', !on);
        if (draftKeyInput) draftKeyInput.disabled = !on;
        if (expectedInput) expectedInput.disabled = !on;
        if (on) bootstrapDrafts();

        // The batch has no single file number — each child carries its own.
        if (fileNoCard) fileNoCard.classList.toggle('hidden', on);
        if (fileNoInput) {
            fileNoInput.disabled = on;
            fileNoInput.required = !on;
        }

        standDownPerChildFields(on);
        recForm.setAttribute('action', on ? BATCH_ACTION : singleAction);

        // Only posted by the batch endpoint; a disabled input is never submitted.
        if (motherField) {
            motherField.disabled = !on;
            motherField.value = (on && oldFileNo) ? oldFileNo.value.trim() : '';
        }

        // Which sections belong to which kind, what the table calls its rows, and
        // whether the type panel is forced open all follow from the kind — so they
        // live in one place rather than being decided twice.
        applyKind(on);

        if (!on) {
            card.classList.add('hidden');
            rowsBody.innerHTML = '';
            setStatus('');
        }
        if (window.lucide) window.lucide.createIcons();
    }

    // Everything that differs between the two kinds of batch. Called when batch mode
    // is switched and whenever the kind itself changes; `on` is batch mode's state,
    // so switching batch mode off restores the single-record form regardless of kind.
    function applyKind(on) {
        var regular = on && isRegular();
        var subdivision = on && !regular;

        if (kindInput) {
            kindInput.disabled = !on;
            kindInput.value = regular ? 'regular' : 'subdivision';
        }

        // Subdivision batch mode only exists for an application type, so the type
        // panel is forced open and held there. A regular batch has no such
        // requirement — any type, or none, is valid — so the toggle stays the
        // officer's to set.
        if (appToggle) {
            if (subdivision && !appToggle.checked) {
                appToggle.checked = true;
                appToggle.dispatchEvent(new Event('change', { bubbles: true }));
            }
            appToggle.disabled = subdivision;
        }

        // A subdivision batch letter is the standard Direct / Conversion document —
        // the Plot Subdivision template describes the mother's split, not an
        // individual child's grant. Ticking this also releases the Recommendation
        // Type lock, so Direct / Conversion becomes selectable. The prior state is
        // restored on the way out so leaving the kind (or batch mode) does not
        // leave the box changed. A regular batch prints whatever the form says.
        var stdTemplate = document.getElementById('use-standard-template');
        if (stdTemplate) {
            if (subdivision) {
                if (stdTemplate.dataset.batchPrevChecked === undefined) {
                    stdTemplate.dataset.batchPrevChecked = stdTemplate.checked ? '1' : '0';
                }
                if (!stdTemplate.checked) {
                    stdTemplate.checked = true;
                    stdTemplate.dispatchEvent(new Event('change', { bubbles: true }));
                }
            } else if (stdTemplate.dataset.batchPrevChecked !== undefined) {
                var was = stdTemplate.dataset.batchPrevChecked === '1';
                delete stdTemplate.dataset.batchPrevChecked;
                if (stdTemplate.checked !== was) {
                    stdTemplate.checked = was;
                    stdTemplate.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        }

        // Stack the three sections between Batch Mode and the table, in array order
        // (inserting each before the table preserves it). Only the subdivision kind
        // needs them up there — it is filled in that order, type then mother then
        // children. A regular batch picks its files from the card above the table,
        // so the sections stay where the markup puts them.
        if (card) {
            relocatable.forEach(function (r) {
                if (subdivision) {
                    if (!r.home) {
                        r.home = { parent: r.el.parentNode, next: r.el.nextSibling };
                    }
                    card.parentNode.insertBefore(r.el, card);
                } else if (r.home) {
                    r.home.parent.insertBefore(r.el, r.home.next);
                    r.home = null;
                }
            });
        }

        // Swap the whole-register file picker for the short list of files that
        // actually have subdivision children.
        if (motherPick) motherPick.classList.toggle('hidden', !subdivision);
        if (manualPick) manualPick.classList.toggle('hidden', subdivision);
        if (motherHelp) motherHelp.classList.toggle('hidden', !subdivision);
        if (manualHelp) manualHelp.classList.toggle('hidden', subdivision);

        if (hint) hint.classList.toggle('hidden', !subdivision);
        if (filesPicker) filesPicker.classList.toggle('hidden', !regular);
        if (reloadBtn) reloadBtn.classList.toggle('hidden', !subdivision);

        // The rows mean different things, so the table says which it is holding.
        if (cardTitle)  cardTitle.textContent = regular ? 'Selected files' : 'Children of';
        if (motherLabel) motherLabel.classList.toggle('hidden', regular);
        if (colFileNo)  colFileNo.textContent = regular ? 'File No' : 'Child File No';
        if (footerNote) {
            footerNote.textContent = regular
                ? 'Untick any file that should not receive a recommendation. Every ticked row is saved as its own '
                    + 'RofO recommendation, grouped under one batch.'
                : 'Untick any child that should not receive a recommendation. Every ticked row is saved as its own '
                    + 'RofO recommendation, grouped under one batch.';
        }

        if (!on) return;

        if (subdivision) {
            loadMothers();
            syncForAppType();
        } else {
            initFileSelect();
            setStatus('');
            // A kind switch must not carry the other kind's rows across — they are
            // a different set of files entirely.
            if (!restoring) {
                card.classList.toggle('hidden', rowsBody.querySelectorAll('.batch-row').length === 0);
            }
        }
    }

    // The subdivision table only makes sense for a subdivision; any other type in
    // that kind falls back to an empty table and a note rather than silently doing
    // nothing. A regular batch has no such constraint, so it never comes through here.
    function syncForAppType() {
        if (!toggle.checked || suppressChildLoad || isRegular()) return;
        var type = currentAppType();

        if (type !== BATCH_TYPE) {
            card.classList.add('hidden');
            rowsBody.innerHTML = '';
            setStatus(type ? 'A subdivision batch covers ' + BATCH_TYPE + ' only — switch to Regular files for any other type.' : '', 'warn');
            return;
        }

        setStatus('');
        card.classList.remove('hidden');
        if (oldFileNo && oldFileNo.value.trim()) {
            loadChildren(oldFileNo.value.trim());
        } else {
            rowsBody.innerHTML = '';
            motherLabel.textContent = '—';
            updateCount();
            setStatus('Select the mother file number above to load its children.', 'warn');
        }
    }

    function landUseOptions(selectedId) {
        var out = '<option value="">Select</option>';
        LAND_USES.forEach(function (lu) {
            out += '<option value="' + lu.id + '"' + (String(lu.id) === String(selectedId) ? ' selected' : '') + '>' + esc(lu.name) + '</option>';
        });
        return out;
    }

    function rowHtml(child, index, sourceIndex) {
        var i = index;
        var cell = 'w-full border border-slate-200 rounded-md px-2.5 py-2 text-xs bg-white hover:border-slate-300 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 outline-none transition';
        var pageCell = 'w-full min-w-0 border border-slate-200 rounded-md px-2 py-2 text-xs text-center bg-white hover:border-slate-300 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 outline-none transition';

        // Two tints, matching the two banks of columns: what Apply-to-all copies and
        // what belongs to the plot alone. Whether a copied column overwrites or only
        // fills a blank is said in words under its header, not in a third colour.
        var copiedCell = 'px-2 py-2.5 bg-sky-50/40';
        var ownCell    = 'px-2 py-2.5 bg-amber-50/40';

        // The source row is the user's choice — row 1 only until they say otherwise.
        var isSource = i === (sourceIndex || 0);

        // A child that already has a recommendation starts unticked: the server
        // rejects a batch containing one, so leaving it ticked would only produce a
        // failed save. Its fields still show what was captured.
        var alreadyDone = !!child.has_recommendation;

        return ''
            // The source row is marked with a bar down its left edge rather than a row
            // background, which the column tints would sit on top of anyway.
            + '<tr class="batch-row border-b border-slate-100 transition' + (isSource ? ' batch-source-row' : '') + '" data-index="' + i + '">'
            + '<td class="px-2 py-2.5 text-center">'
            +   '<input type="checkbox" class="batch-row-check w-4 h-4 text-violet-600 border-slate-300 rounded focus:ring-violet-500 cursor-pointer"'
            +     ' data-had-rec="' + (alreadyDone ? '1' : '0') + '"'
            +     ' data-existing-status="' + esc(child.existing_status || '') + '"'
            +     (alreadyDone ? '' : ' checked') + '>'
            + '</td>'
            + '<td class="px-2 py-2.5 text-center text-xs font-bold text-slate-400">' + (i + 1) + '</td>'
            + '<td class="px-1 py-2.5 text-center">'
            +   '<input type="radio" class="batch-row-source w-4 h-4 text-violet-600 border-slate-300 focus:ring-violet-500 cursor-pointer"'
            +     ' name="batch_source_row" value="' + i + '"' + (isSource ? ' checked' : '')
            +     ' title="Copy this row\'s values into the others">'
            + '</td>'
            + '<td class="' + ownCell + '">'
            +   '<div class="font-mono font-bold text-slate-900 text-xs whitespace-nowrap">' + esc(child.file_number) + '</div>'
            +   '<span class="batch-source-badge mt-1 ' + (isSource ? 'inline-flex' : 'hidden') + ' items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-bold bg-violet-100 text-violet-700 uppercase tracking-wide">Source row</span>'
            +   (alreadyDone
                    ? '<span class="mt-1 inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-bold bg-rose-100 text-rose-700" title="' + esc(child.existing_status || '') + ' — already captured, so this row is excluded from the batch">Has a RofO</span>'
                    : '')
            +   '<input type="hidden" name="children[' + i + '][file_number]" value="' + esc(child.file_number) + '">'
            +   '<input type="hidden" name="children[' + i + '][tracking_id]" value="' + esc(child.tracking_id) + '">'
            + '</td>'
            // Left bank — this plot alone.
            + '<td class="' + ownCell + '"><input type="text" class="' + cell + ' batch-f font-mono" data-f="plot_number" name="children[' + i + '][plot_number]" value="' + esc(child.plot_number) + '" placeholder="Plot"></td>'
            + '<td class="' + ownCell + '"><input type="text" required class="' + cell + ' batch-f" data-f="applicant_address" name="children[' + i + '][applicant_address]" value="' + esc(child.applicant_address) + '" placeholder="Applicant address"></td>'
            + '<td class="' + ownCell + '"><select class="' + cell + ' batch-f batch-landuse cursor-pointer" data-f="land_use_id" name="children[' + i + '][land_use_id]">' + landUseOptions(child.land_use_id) + '</select></td>'
            + '<td class="' + ownCell + '"><select class="' + cell + ' batch-f batch-purpose cursor-pointer" data-f="purpose_id" name="children[' + i + '][purpose_id]"><option value="">Select</option></select></td>'
            // Right bank — copied from the source row.
            + '<td class="' + copiedCell + ' batch-group-split"><input type="text" required class="' + cell + ' batch-f" data-f="applicant_name" name="children[' + i + '][applicant_name]" value="' + esc(child.applicant_name) + '" placeholder="Applicant name"></td>'
            + '<td class="' + copiedCell + '"><input type="text" class="' + cell + ' batch-f" data-f="location" name="children[' + i + '][location]" value="' + esc(child.location) + '" placeholder="Location"></td>'
            + '<td class="' + copiedCell + '">'
            +   '<div class="flex items-center gap-1">'
            +     '<input type="number" min="1" class="' + pageCell + ' batch-f" data-f="page"   name="children[' + i + '][page]"   value="' + esc(child.page) + '"   placeholder="Pg" title="Page No.">'
            +     '<input type="number" min="1" class="' + pageCell + ' batch-f" data-f="page_2" name="children[' + i + '][page_2]" value="' + esc(child.page_2) + '" placeholder="Memo" title="Auth. Memo Page">'
            +     '<input type="number" min="1" class="' + pageCell + ' batch-f" data-f="page_3" name="children[' + i + '][page_3]" value="' + esc(child.page_3) + '" placeholder="Plan" title="Site Plan Page">'
            +   '</div>'
            + '</td>'
            + '</tr>';
    }

    // The chosen source row, or the first row if nothing is marked.
    function sourceRow() {
        var picked = rowsBody.querySelector('.batch-row-source:checked');
        return picked ? picked.closest('tr') : rowsBody.querySelector('.batch-row');
    }

    // Move the left-edge bar and the "Source row" badge to the picked row, and
    // name that row on the Apply button so it is unambiguous what will be copied.
    function syncSourceLabel() {
        var current = sourceRow();
        rowsBody.querySelectorAll('.batch-row').forEach(function (tr) {
            var isSrc = tr === current;
            tr.classList.toggle('batch-source-row', isSrc);
            var badge = tr.querySelector('.batch-source-badge');
            if (badge) {
                badge.classList.toggle('hidden', !isSrc);
                badge.classList.toggle('inline-flex', isSrc);
            }
        });

        var label = document.getElementById('batch-apply-all-row');
        if (label) {
            var idx = current ? (parseInt(current.dataset.index, 10) + 1) : 1;
            label.textContent = '#' + (isNaN(idx) ? 1 : idx);
        }
    }

    function updateCount() {
        var n = rowsBody.querySelectorAll('.batch-row-check:checked').length;
        var total = rowsBody.querySelectorAll('.batch-row').length;
        countLabel.textContent = n + ' of ' + total + ' selected';
        // Nothing to copy into with fewer than two rows.
        if (applyAllBtn) applyAllBtn.disabled = total < 2;

        // Keep the header box honest when rows arrive part-selected — children that
        // already have a recommendation come back unticked.
        if (selectAll) {
            selectAll.checked = total > 0 && n === total;
            selectAll.indeterminate = n > 0 && n < total;
        }
    }

    function syncRowEnabled(tr) {
        var on = tr.querySelector('.batch-row-check').checked;
        // The source picker stays live on an unticked row: copying values out of a
        // row that is not itself being saved is a perfectly reasonable thing to do.
        tr.querySelectorAll('input:not(.batch-row-check):not(.batch-row-source), select').forEach(function (el) { el.disabled = !on; });
        tr.classList.toggle('opacity-40', !on);
    }

    function loadPurposes(select, landUseId, selectedPurposeId) {
        select.innerHTML = '<option value="">Select</option>';
        if (!landUseId) return Promise.resolve();

        var fetchList = purposeCache[landUseId]
            ? Promise.resolve(purposeCache[landUseId])
            : fetch(PURPOSES_URL + '?landuseid=' + encodeURIComponent(landUseId), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var list = Array.isArray(data) ? data : (data.data || data.purposes || []);
                    purposeCache[landUseId] = list;
                    return list;
                })
                .catch(function () { return []; });

        return fetchList.then(function (list) {
            var html = '<option value="">Select</option>';
            list.forEach(function (p) {
                var id = p.id != null ? p.id : p.value;
                var name = p.name != null ? p.name : p.text;
                html += '<option value="' + esc(id) + '"' + (String(id) === String(selectedPurposeId) ? ' selected' : '') + '>' + esc(name) + '</option>';
            });
            html += '<option value="other">Other</option>';
            select.innerHTML = html;
        });
    }

    // Short list of files that already have commissioned subdivision children.
    // Fetched once per page — the set only changes when a subdivision is
    // commissioned, which cannot happen while this form is open.
    // Named for what it builds, not for the element it feeds — `motherLabel` is
    // already the card-header node above, and a var assignment beats a hoisted
    // function declaration, so reusing the name left this uncallable.
    // The count is children still to be captured, not children in total — a file
    // whose plots are half done should read as half done. Files with nothing left
    // do not reach the picker at all.
    function formatMotherOption(m) {
        var label = m.file_number + ' — ' + m.children + ' ' + (m.children === 1 ? 'child' : 'children');
        if (m.children_used) {
            label += ' left of ' + m.children_total + ' (' + m.children_used + ' already done)';
        }
        return label;
    }

    // Searchable picker over the subdivided files. Searching server-side (rather
    // than shipping the whole list and filtering in the browser) is what keeps this
    // usable as more files are subdivided — same approach as the TP No. field.
    function loadMothers() {
        if (mothersLoaded || !motherSel) return;
        mothersLoaded = true;

        // Select2 needs jQuery; without it the plain <select> still works, just
        // without a search box.
        if (!window.jQuery || !jQuery.fn || !jQuery.fn.select2) {
            loadMothersPlain();
            return;
        }

        var $sel = jQuery(motherSel);
        $sel.empty().append(new Option('', '', false, false));

        $sel.select2({
            placeholder: 'Search or select a subdivided file…',
            allowClear: true,
            width: '100%',
            minimumInputLength: 0,
            ajax: {
                url: MOTHERS_URL,
                dataType: 'json',
                delay: 250,
                data: function (params) { return { q: params.term || '' }; },
                processResults: function (data) {
                    var list = (data && data.mothers) || [];
                    return {
                        results: list.map(function (m) {
                            return { id: m.file_number, text: formatMotherOption(m) };
                        })
                    };
                },
                cache: true
            }
            // No custom `language` block: Select2 4.x replaces the whole dictionary
            // rather than merging, so overriding one message loses `searching`,
            // `errorLoading` and the rest. The throw that follows is swallowed by
            // jQuery 3's promise chain and the dropdown sits on "Searching…" forever.
        });

        // Select2 fires jQuery events, which native addEventListener handlers never
        // see — so the pick is written through to the real field here.
        $sel.on('select2:select select2:clear', function () {
            if (!oldFileNo) return;
            oldFileNo.value = $sel.val() || '';
            oldFileNo.dispatchEvent(new Event('change', { bubbles: true }));
        });

        // Keep a selection made before batch mode was switched on (or restored on a
        // validation redisplay) — an AJAX Select2 has no option for it otherwise.
        var current = oldFileNo ? oldFileNo.value.trim() : '';
        if (current) {
            $sel.append(new Option(current, current, true, true)).trigger('change.select2');
        }
    }

    // No-jQuery fallback: one fetch, plain options, no search box.
    function loadMothersPlain() {
        fetch(MOTHERS_URL + '?limit=200', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var current = oldFileNo ? oldFileNo.value.trim() : '';
                var list = (data && data.mothers) || [];

                if (!list.length) {
                    motherSel.innerHTML = '<option value="">No subdivided files available</option>';
                    setStatus('No file has commissioned subdivision children yet, so there is nothing to batch.', 'warn');
                    return;
                }

                var html = '<option value="">Select a subdivided file…</option>';
                list.forEach(function (m) {
                    html += '<option value="' + esc(m.file_number) + '"'
                        + (m.file_number === current ? ' selected' : '') + '>' + esc(formatMotherOption(m)) + '</option>';
                });
                motherSel.innerHTML = html;
            })
            .catch(function () {
                mothersLoaded = false;   // let a retry happen on the next toggle
                motherSel.innerHTML = '<option value="">Could not load subdivided files</option>';
                setStatus('Network error while loading subdivided files.', 'error');
            });
    }

    // ── Regular batch: the file picker ─────────────────────────────────────
    // Multi-select with tagging over the register. Searching server-side is the
    // only thing that scales here — the register is the whole point — and tagging
    // is what lets a file number that is not indexed yet still be captured.
    function initFileSelect() {
        if (filesSelectReady || !fileSel) return;

        // Without jQuery/Select2 the plain multiple <select> is useless (nothing
        // populates it), so the field degrades to a comma-separated text box
        // instead. Rare, but a batch that silently cannot be filled is worse.
        if (!window.jQuery || !jQuery.fn || !jQuery.fn.select2) {
            filesSelectReady = true;
            var box = document.createElement('textarea');
            box.id = 'batch-file-manual';
            box.rows = 3;
            box.placeholder = 'One file number per line, or separated by commas';
            box.className = 'w-full border border-violet-300 rounded-lg px-3 py-2.5 bg-white text-sm font-mono outline-none';
            fileSel.parentNode.insertBefore(box, fileSel);
            fileSel.classList.add('hidden');
            return;
        }

        filesSelectReady = true;
        var $sel = jQuery(fileSel);

        $sel.select2({
            placeholder: 'Search file numbers — pick as many as you need',
            width: '100%',
            multiple: true,
            // Anything typed that the search did not return is still a valid pick:
            // paper files being captured for the first time have no register row yet.
            tags: true,
            minimumInputLength: 1,
            createTag: function (params) {
                var term = jQuery.trim(params.term);
                if (!term) return null;
                return { id: term.toUpperCase(), text: term.toUpperCase() + ' (not in the register — added as typed)', isNew: true };
            },
            ajax: {
                url: FILES_URL,
                dataType: 'json',
                delay: 250,
                data: function (params) { return { q: params.term || '' }; },
                processResults: function (data) {
                    return {
                        results: ((data && data.files) || []).map(function (f) {
                            var label = f.file_number;
                            if (f.applicant_name) label += ' — ' + f.applicant_name;
                            if (f.plot_number) label += ' · Plot ' + f.plot_number;
                            return {
                                id: f.file_number,
                                text: f.has_recommendation ? label + '  ⛔ already has a recommendation' : label,
                                // A file that already carries one cannot go into a
                                // batch — storeBatch() rejects the whole post over it,
                                // so it is shown and blocked rather than picked and
                                // then failed at save.
                                disabled: !!f.has_recommendation
                            };
                        })
                    };
                },
                cache: true
            }
        });

        $sel.on('change', function () { updateFilesCount(); });
        updateFilesCount();
    }

    // The file numbers currently picked, from whichever control is in play.
    function pickedFiles() {
        var manual = document.getElementById('batch-file-manual');
        if (manual) {
            // Never split on whitespace: KANGIS numbers contain spaces ("KNML 1").
            return manual.value.split(/[,;\n\r]+/).map(function (s) { return s.trim(); }).filter(Boolean);
        }
        if (!fileSel) return [];
        return Array.prototype.map.call(fileSel.selectedOptions || [], function (o) { return o.value.trim(); })
            .filter(Boolean);
    }

    function updateFilesCount() {
        if (!filesCount) return;
        var n = pickedFiles().length;
        filesCount.textContent = n + ' picked';
        filesCount.className = 'inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold '
            + (n ? 'bg-violet-600 text-white' : 'bg-slate-100 text-slate-600');
    }

    // Put a picked selection into the picker's control — used by a draft restore,
    // which has the file numbers but no Select2 options for them.
    function setPickedFiles(files) {
        files = (files || []).filter(Boolean);
        var manual = document.getElementById('batch-file-manual');
        if (manual) {
            manual.value = files.join('\n');
            updateFilesCount();
            return;
        }
        if (!fileSel) return;

        if (window.jQuery && jQuery.fn && jQuery.fn.select2 && filesSelectReady) {
            var $sel = jQuery(fileSel);
            $sel.empty();
            files.forEach(function (f) { $sel.append(new Option(f, f, true, true)); });
            $sel.trigger('change.select2');
        } else {
            fileSel.innerHTML = files.map(function (f) {
                return '<option value="' + esc(f) + '" selected>' + esc(f) + '</option>';
            }).join('');
        }
        updateFilesCount();
    }

    // Load the picked file numbers into the table. Same row shape and the same
    // renderer the subdivision children use, so everything downstream — Apply to
    // all, autosave, the save itself — cannot tell the two kinds apart.
    function loadPickedFiles(files) {
        var ticket = ++childLoadSeq;

        motherLabel.textContent = '';
        if (motherField) motherField.value = '';

        card.classList.remove('hidden');
        rowsBody.innerHTML = '<tr><td colspan="11" class="px-3 py-10 text-center text-xs text-slate-500">'
            + '<i data-lucide="loader-2" class="h-5 w-5 mx-auto mb-2 text-violet-400 animate-spin"></i>Loading files…</td></tr>';
        if (window.lucide) window.lucide.createIcons();
        setStatus('');

        return fetchFileDetails(files).then(function (result) {
            if (ticket !== childLoadSeq) return;

            if (!result.success) {
                rowsBody.innerHTML = '';
                setStatus(result.message || 'Could not load the selected files.', 'error');
                updateCount();
                return;
            }

            renderChildren(result.children);

            var alreadyDone = result.children.filter(function (c) { return c.has_recommendation; }).length;
            var notes = [];
            if (alreadyDone) {
                notes.push(alreadyDone + ' of these files already carry a recommendation and are unticked — they cannot be captured twice.');
            }
            if (result.unknown && result.unknown.length) {
                notes.push('Nothing is on file for: ' + result.unknown.join(', ') + ' — key their details by hand.');
            }
            setStatus(notes.join(' '), 'warn');
            scheduleSave();
        });
    }

    // The picked files as the registers have them right now, without touching the
    // table. Used by the Apply button and by a draft restore's backfill.
    function fetchFileDetails(files) {
        var body = new FormData();
        body.append('_token', csrfToken());
        files.forEach(function (f) { body.append('file_numbers[]', f); });

        return fetch(FILE_DETAIL_URL, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                return (data && data.success)
                    ? { success: true, children: data.children || [], unknown: data.unknown || [] }
                    : { success: false, message: (data && data.message) || 'Could not load the selected files.' };
            })
            .catch(function () {
                return { success: false, message: 'Network error while loading the selected files.' };
            });
    }

    // Paint a set of child rows into the table. Shared by the live load from the
    // server and by a draft restore, so a resumed capture is byte-for-byte the
    // table the user left — the draft simply supplies the same row objects the
    // children endpoint would have.
    function renderChildren(children) {
        // The source row is the user's, and it survives a re-render and a draft
        // restore — falling back to the first row only when nothing is marked.
        var sourceIndex = 0;
        children.some(function (c, i) {
            if (c.is_source) { sourceIndex = i; return true; }
            return false;
        });

        rowsBody.innerHTML = children.map(function (child, i) {
            return rowHtml(child, i, sourceIndex);
        }).join('');
        syncSourceLabel();

        // Purpose options depend on the row's land use, so they are filled after
        // render — passing the existing purpose so a row that already has a
        // recommendation (or a restored draft row) shows the purpose captured.
        rowsBody.querySelectorAll('.batch-row').forEach(function (tr, i) {
            var child      = children[i] || {};
            var landUseSel = tr.querySelector('.batch-landuse');
            var purposeSel = tr.querySelector('.batch-purpose');
            if (landUseSel && landUseSel.value) {
                loadPurposes(purposeSel, landUseSel.value, child.purpose_id || null);
            }
            // A draft remembers which rows were ticked; a fresh load unticks the
            // children that already have a recommendation. Either way an unticked
            // row must come back disabled, or its inputs would still post.
            if (child.checked === false) {
                tr.querySelector('.batch-row-check').checked = false;
            }
            syncRowEnabled(tr);
        });

        updateCount();
        if (window.lucide) window.lucide.createIcons();
    }

    // Every child load carries a ticket. A response only paints if its ticket is
    // still the current one — anything else is a load that has been overtaken and
    // whose rows would wipe whatever replaced them. That happens for real: a draft
    // restore writes the mother file number in, and the load that fires off the
    // back of it used to land a moment later and overwrite the whole restored
    // table with blank registry rows.
    var childLoadSeq = 0;

    function loadChildren(mother) {
        var ticket = ++childLoadSeq;

        motherLabel.textContent = mother;
        if (motherField) motherField.value = mother;
        rowsBody.innerHTML = '<tr><td colspan="11" class="px-3 py-10 text-center text-xs text-slate-500">'
            + '<i data-lucide="loader-2" class="h-5 w-5 mx-auto mb-2 text-violet-400 animate-spin"></i>Loading children…</td></tr>';
        if (window.lucide) window.lucide.createIcons();
        setStatus('');

        fetch(CHILDREN_URL + '?mother_file_no=' + encodeURIComponent(mother), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (ticket !== childLoadSeq) return;
                if (!data.success) {
                    rowsBody.innerHTML = '';
                    setStatus(data.message || 'Could not load children.', 'error');
                    updateCount();
                    return;
                }
                if (!data.children.length) {
                    rowsBody.innerHTML = '<tr><td colspan="11" class="px-3 py-10 text-center text-xs text-slate-400">'
                        + '<i data-lucide="folder-open" class="h-5 w-5 mx-auto mb-2 text-slate-300"></i>'
                        + 'No commissioned subdivision children found for this file.</td></tr>';
                    if (window.lucide) window.lucide.createIcons();
                    setStatus(data.message || 'No subdivision children are linked to ' + mother + '.', 'warn');
                    updateCount();
                    return;
                }

                renderChildren(data.children);
            })
            .catch(function () {
                if (ticket !== childLoadSeq) return;
                rowsBody.innerHTML = '';
                setStatus('Network error while loading children.', 'error');
                updateCount();
            });
    }

    // The children as the registry has them right now, without touching the table.
    // Used by a draft restore to backfill blanks and to notice children that were
    // commissioned after the draft was started.
    function fetchChildren(mother) {
        return fetch(CHILDREN_URL + '?mother_file_no=' + encodeURIComponent(mother), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) { return (data && data.success && data.children) ? data.children : []; })
            .catch(function () { return []; });
    }

    // ── events ──
    toggle.addEventListener('change', function () { applyBatchMode(toggle.checked); });

    // Switching kind mid-capture throws the table away, because the rows of one
    // kind are simply not the rows of the other. Worth a confirm once anything has
    // been loaded — on a long batch that is real work.
    kindRadios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (!toggle.checked) return;

            if (rowsBody.querySelectorAll('.batch-row').length
                && !confirm('Switching the kind of batch clears the table and everything keyed into it. Continue?')) {
                // Put the radio back: the change already happened by the time this fires.
                var other = isRegular() ? 'subdivision' : 'regular';
                var back = document.querySelector('.batch-kind-radio[value="' + other + '"]');
                if (back) back.checked = true;
                return;
            }

            rowsBody.innerHTML = '';
            card.classList.add('hidden');
            updateCount();
            setStatus('');
            applyKind(true);
        });
    });

    if (filesApply) filesApply.addEventListener('click', function () {
        var files = pickedFiles();
        if (!files.length) {
            setStatus('Pick at least one file number first.', 'error');
            card.classList.remove('hidden');
            return;
        }
        // Same warning the subdivision Reload gives, for the same reason: this
        // repaints the table and discards whatever was keyed into it.
        if (rowsBody.querySelectorAll('.batch-row').length
            && !confirm('Applying reloads the table from the picked files and discards everything keyed into it. Continue?')) {
            return;
        }
        loadPickedFiles(files);
    });

    if (filesClear) filesClear.addEventListener('click', function () {
        setPickedFiles([]);
        rowsBody.innerHTML = '';
        card.classList.add('hidden');
        updateCount();
        setStatus('');
    });

    document.querySelectorAll('.app-type-radio').forEach(function (radio) {
        radio.addEventListener('change', function () { if (toggle.checked) syncForAppType(); });
    });

    if (oldFileNo) {
        oldFileNo.addEventListener('change', function () {
            // A restore writes the mother in directly; re-fetching the children here
            // would wipe the very values being restored. In a regular batch this
            // field is just the old file number — it says nothing about the rows.
            if (!toggle.checked || suppressChildLoad || isRegular() || currentAppType() !== BATCH_TYPE) return;
            var v = this.value.trim();
            if (motherField) motherField.value = v;
            if (v) { loadChildren(v); }
            else { rowsBody.innerHTML = ''; motherLabel.textContent = '—'; updateCount(); }
        });
    }

    // Picking a mother writes through to the real old_file_number field, whose
    // change event is what triggers the child load.
    if (motherSel) {
        motherSel.addEventListener('change', function () {
            if (!oldFileNo) return;
            oldFileNo.value = this.value;
            oldFileNo.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    if (reloadBtn) {
        reloadBtn.addEventListener('click', function () {
            var v = oldFileNo ? oldFileNo.value.trim() : '';
            if (!v) return;
            // Reload re-fetches from the registry and repaints the table, which
            // throws away everything keyed into it. On a 100-child batch that is
            // an afternoon, so it is worth asking.
            if (rowsBody.querySelectorAll('.batch-row').length
                && !confirm('Reloading fetches the children again and discards everything keyed into the table. Continue?')) {
                return;
            }
            loadChildren(v);
        });
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            var on = this.checked;
            rowsBody.querySelectorAll('.batch-row').forEach(function (tr) {
                tr.querySelector('.batch-row-check').checked = on;
                syncRowEnabled(tr);
            });
            updateCount();
        });
    }

    rowsBody.addEventListener('change', function (e) {
        if (e.target.classList.contains('batch-row-source')) {
            syncSourceLabel();
            return;
        }
        if (e.target.classList.contains('batch-row-check')) {
            syncRowEnabled(e.target.closest('tr'));
            updateCount();
            return;
        }
        if (e.target.classList.contains('batch-landuse')) {
            var purposeSel = e.target.closest('tr').querySelector('.batch-purpose');
            loadPurposes(purposeSel, e.target.value, null);
        }
    });

    if (applyAllBtn) applyAllBtn.addEventListener('click', function () {
        var rows = Array.prototype.slice.call(rowsBody.querySelectorAll('.batch-row'));
        if (rows.length < 2) return;

        var src = sourceRow();
        if (!src) return;

        // The page references describe the grant, not the plot, so they are copied
        // over whatever is in the other rows.
        var COPY = ['page', 'page_2', 'page_3'];

        // Applicant name and location are usually shared across a subdivision but
        // not always, so they only fill rows left blank — copying over a filled cell
        // would silently put the wrong name on a letter.
        var FILL_IF_BLANK = ['applicant_name', 'location'];

        // Deliberately copied by nothing: the child file number, applicant address,
        // plot number, land use and purpose. Each identifies or describes that one
        // plot — an address, a land use or a purpose carried across the batch is a
        // wrong letter that reads as a correct one, which is the worst kind.

        rows.forEach(function (tr) {
            if (tr === src) return;

            FILL_IF_BLANK.forEach(function (f) {
                var from = src.querySelector('[data-f="' + f + '"]');
                var dst  = tr.querySelector('[data-f="' + f + '"]');
                if (from && dst && !dst.value.trim()) dst.value = from.value;
            });

            COPY.forEach(function (f) {
                var from = src.querySelector('[data-f="' + f + '"]');
                var dst  = tr.querySelector('[data-f="' + f + '"]');
                if (from && dst) dst.value = from.value;
            });
        });

        // The copy is real work, so it is drafted rather than waiting on the next
        // keystroke — 100 rows changed at once is exactly what should not be lost.
        scheduleSave();
    });

    // ── Draft autosave ─────────────────────────────────────────────────────
    // A subdivision batch can run past a hundred children. Keying that many rows
    // takes longer than the session lasts, and the whole capture used to die on a
    // 419 at submit. Everything is now written to a server-side draft on a
    // debounce, mirrored into local storage, and the periodic save doubles as the
    // thing that keeps the session (and its CSRF token) alive while typing.
    var DRAFT_STORE_URL = 'X';
    var DRAFT_LIST_URL  = 'X';
    var DRAFT_ONE_URL   = 'X';

    var DEBOUNCE_MS  = 2500;    // quiet period after the last keystroke
    var HEARTBEAT_MS = 60000;   // ceiling on how much typing a crash can cost
    var KEEPALIVE_MS = 300000;  // re-save an idle draft purely to hold the session

    var draftBar     = document.getElementById('batch-draft-bar');
    var draftStatus  = document.getElementById('batch-draft-status');
    var draftKeyInput = document.getElementById('batch-draft-key');
    var expectedInput = document.getElementById('batch-children-expected');
    var draftSaveNow = document.getElementById('batch-draft-save-now');
    var draftResume  = document.getElementById('batch-draft-resume');
    var draftDiscard = document.getElementById('batch-draft-discard');
    var draftList    = document.getElementById('batch-draft-list');
    var draftCount   = document.getElementById('batch-draft-count');
    var draftWarning = document.getElementById('batch-draft-session-warning');
    var draftRetry   = document.getElementById('batch-draft-retry');

    var draftKey     = null;
    var draftDirty   = false;
    var draftSaving  = false;
    var sessionLost  = false;
    var lastSavedAt  = null;
    var debounceTimer = null;
    var restoring    = false;   // suppresses autosave while a draft is painted back
    var suppressChildLoad = false;

    var LS_PREFIX = 'lr-batch-draft:';

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        var field = recForm.querySelector('input[name="_token"]');
        return (field && field.value) || (meta && meta.getAttribute('content')) || '';
    }

    // A save hands back a fresh token; writing it into both the form and the meta
    // tag is what keeps the eventual batch POST from 419-ing after a long capture.
    function adoptToken(token) {
        if (!token) return;
        var field = recForm.querySelector('input[name="_token"]');
        if (field) field.value = token;
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) meta.setAttribute('content', token);
    }

    function newDraftKey() {
        if (window.crypto && crypto.randomUUID) return crypto.randomUUID().replace(/-/g, '').slice(0, 32);
        return 'd' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
    }

    function setDraftStatus(text, tone) {
        if (!draftStatus) return;
        draftStatus.textContent = text;
        draftStatus.className = 'text-[11px] font-semibold ' + (
            tone === 'error' ? 'text-rose-700'
            : tone === 'ok'  ? 'text-emerald-700'
            : tone === 'busy' ? 'text-violet-700'
            : 'text-slate-500'
        );
    }

    function clockLabel(date) {
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }

    // ── what a draft is ──
    // Common fields come off the form by name; children come off the table so
    // unticked rows are kept too (their values are real work — an unticked row is
    // one the user decided against, not one they never filled in).
    // Fields that describe the draft or the post rather than the capture. They are
    // set from the live state on every save and every submit, so carrying stale
    // copies through a restore could only ever point at the wrong draft.
    // batch_source_row is a table control, not a form value — it rides with the
    // children (as is_source) so it survives a re-render, and the batch endpoint
    // ignores it entirely.
    // batch_kind rides with the payload as `kind`, restored before anything else,
    // so it is not replayed through the generic common-field restore either.
    var NOT_CAPTURE = ['_token', '_method', 'draft_key', 'children_expected',
                       'batch_mother_file_no', 'batch_source_row', 'batch_kind', 'batch_kind_ui'];

    function serializeCommon() {
        var out = {};
        Array.prototype.forEach.call(recForm.elements, function (el) {
            var name = el.name;
            if (!name || NOT_CAPTURE.indexOf(name) !== -1) return;
            if (name.indexOf('children[') === 0) return;
            if (el.type === 'file' || el.type === 'submit' || el.type === 'button') return;

            if (el.type === 'checkbox' || el.type === 'radio') {
                out[name] = out[name] || { type: el.type, values: [] };
                if (el.checked) out[name].values.push(el.value);
            } else if (el.multiple) {
                out[name] = { type: 'multi', values: Array.prototype.map.call(el.selectedOptions, function (o) { return o.value; }) };
            } else {
                out[name] = { type: 'value', value: el.value };
            }
        });
        return out;
    }

    // Children are stored in the exact shape the children endpoint returns, so a
    // restore feeds renderChildren() the same objects a live load would.
    function serializeChildren() {
        return Array.prototype.map.call(rowsBody.querySelectorAll('.batch-row'), function (tr) {
            function v(f) {
                var el = tr.querySelector('[data-f="' + f + '"]');
                return el ? el.value : '';
            }
            function hid(f) {
                var el = tr.querySelector('input[name$="[' + f + ']"]');
                return el ? el.value : '';
            }
            return {
                file_number:       hid('file_number'),
                tracking_id:       hid('tracking_id'),
                applicant_name:    v('applicant_name'),
                applicant_address: v('applicant_address'),
                plot_number:       v('plot_number'),
                location:          v('location'),
                land_use_id:       v('land_use_id'),
                purpose_id:        v('purpose_id'),
                page:              v('page'),
                page_2:            v('page_2'),
                page_3:            v('page_3'),
                checked:           tr.querySelector('.batch-row-check').checked,
                // Which row Apply-to-all copies from is the user's choice, so it is
                // part of the capture and comes back with a resumed draft.
                is_source:         !!(tr.querySelector('.batch-row-source') || {}).checked,
                // Carried through so a restored row keeps its "Has a RofO" flag
                // without a second round trip to the children endpoint.
                has_recommendation: tr.querySelector('.batch-row-check').dataset.hadRec === '1',
                existing_status:    tr.querySelector('.batch-row-check').dataset.existingStatus || ''
            };
        });
    }

    function buildPayload() {
        return {
            version:          1,
            // Which kind of batch this draft is, and — for a regular one — the files
            // that were picked, so resuming restores the picker as well as the table.
            kind:             currentKind(),
            picked_files:     isRegular() ? pickedFiles() : [],
            application_type: currentAppType(),
            mother_file_no:   isRegular() ? '' : (oldFileNo ? oldFileNo.value.trim() : ''),
            common:           serializeCommon(),
            children:         serializeChildren(),
            saved_at:         new Date().toISOString()
        };
    }

    // Local storage is the backstop for the one case the server cannot cover: the
    // session is gone, so no request of ours will be accepted at all. It is never
    // the source of truth — the server copy wins whenever there is one.
    function writeLocal(payload) {
        if (!draftKey) return;
        try {
            localStorage.setItem(LS_PREFIX + draftKey, JSON.stringify({
                draft_key: draftKey,
                payload:   payload,
                pushed:    false,
                stored_at: new Date().toISOString()
            }));
        } catch (e) { /* quota or private mode — the server copy still stands */ }
    }

    function markLocalPushed() {
        if (!draftKey) return;
        try {
            var raw = localStorage.getItem(LS_PREFIX + draftKey);
            if (!raw) return;
            var obj = JSON.parse(raw);
            obj.pushed = true;
            localStorage.setItem(LS_PREFIX + draftKey, JSON.stringify(obj));
        } catch (e) { /* nothing to do */ }
    }

    function clearLocal(key) {
        try { localStorage.removeItem(LS_PREFIX + (key || draftKey)); } catch (e) { /* ignore */ }
    }

    function showSessionWarning(on) {
        sessionLost = on;
        if (draftWarning) draftWarning.classList.toggle('hidden', !on);
    }

    function saveDraft(reason) {
        if (restoring || !toggle.checked) return Promise.resolve();
        if (draftSaving) { draftDirty = true; return Promise.resolve(); }

        // Nothing worth a row yet: nothing picked and nothing on screen. A regular
        // batch counts its picked files here — they are a choice already made, and
        // losing them to a timeout before Apply is pressed is still lost work.
        var childRows = rowsBody.querySelectorAll('.batch-row').length;
        var mother = isRegular() ? '' : (oldFileNo ? oldFileNo.value.trim() : '');
        var picked = isRegular() ? pickedFiles().length : 0;
        if (!draftKey && !mother && !childRows && !picked) return Promise.resolve();

        if (!draftKey) {
            draftKey = newDraftKey();
            if (draftKeyInput) draftKeyInput.value = draftKey;
            if (draftDiscard) draftDiscard.classList.remove('hidden');
        }

        var payload = buildPayload();
        writeLocal(payload);

        draftSaving = true;
        draftDirty = false;
        setDraftStatus('Saving draft…', 'busy');

        var body = new FormData();
        body.append('_token', csrfToken());
        body.append('draft_key', draftKey);
        body.append('application_type', payload.application_type || '');
        body.append('mother_file_no', payload.mother_file_no || '');
        body.append('children_total', String(payload.children.length));
        body.append('children_selected', String(payload.children.filter(function (c) { return c.checked; }).length));
        // Posted as one JSON string, not as nested form fields: a 100-child batch
        // is thousands of inputs and PHP silently drops everything past
        // max_input_vars (1000 by default), which would truncate the draft.
        body.append('payload', JSON.stringify(payload));

        return fetch(DRAFT_STORE_URL, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (res) {
                // 419 (token expired) and 401/403 (logged out) both mean the same
                // thing to the user: the server will not take this work until they
                // sign in again. The tab and local storage still hold all of it.
                if (res.status === 419 || res.status === 401 || res.status === 403 || res.redirected) {
                    throw { sessionLost: true };
                }
                if (!res.ok) throw { status: res.status };
                return res.json();
            })
            .then(function (data) {
                draftSaving = false;
                if (!data || !data.success) throw { status: 'payload' };

                adoptToken(data.csrf);
                markLocalPushed();
                showSessionWarning(false);
                lastSavedAt = new Date();
                setDraftStatus('Draft saved ' + clockLabel(lastSavedAt)
                    + ' · ' + (data.draft ? data.draft.children_selected + ' of ' + data.draft.children_total + ' children' : ''), 'ok');

                // Anything typed while the request was in flight.
                if (draftDirty) scheduleSave();
            })
            .catch(function (err) {
                draftSaving = false;
                draftDirty = true;   // still unsaved, so keep trying

                if (err && err.sessionLost) {
                    showSessionWarning(true);
                    setDraftStatus('Not saved — session expired. Your work is still here.', 'error');
                    return;
                }
                setDraftStatus('Could not reach the server — retrying. Nothing lost.', 'error');
            });
    }

    function scheduleSave() {
        draftDirty = true;
        if (debounceTimer) clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () { saveDraft('debounce'); }, DEBOUNCE_MS);
        if (!lastSavedAt) setDraftStatus('Unsaved changes…', 'busy');
    }

    // Typing anywhere in the form (common fields or the children table) drafts it.
    // Capture phase, so it still fires for the table rows that are re-rendered.
    ['input', 'change'].forEach(function (evt) {
        recForm.addEventListener(evt, function (e) {
            if (restoring || !toggle.checked) return;
            if (e.target && (e.target.name === '_token' || e.target.id === 'batch-mode-toggle')) return;
            scheduleSave();
        }, true);
    });

    // Backstop for a browser or machine that dies between debounces, and the thing
    // that keeps the session alive across a long capture — an idle draft is
    // re-saved every few minutes purely so the session never ages out under it.
    setInterval(function () {
        if (!toggle.checked || restoring) return;
        if (draftDirty) { saveDraft('heartbeat'); }
    }, HEARTBEAT_MS);

    setInterval(function () {
        if (!toggle.checked || restoring || draftDirty || !draftKey) return;
        saveDraft('keepalive');
    }, KEEPALIVE_MS);

    if (draftSaveNow) draftSaveNow.addEventListener('click', function () { saveDraft('manual'); });
    if (draftRetry) draftRetry.addEventListener('click', function (e) {
        e.preventDefault();
        setDraftStatus('Retrying…', 'busy');
        saveDraft('retry');
    });

    if (draftDiscard) draftDiscard.addEventListener('click', function () {
        if (!draftKey) return;
        if (!confirm('Discard this draft? Everything keyed in this batch will be thrown away.')) return;

        var body = new FormData();
        body.append('_token', csrfToken());
        body.append('_method', 'DELETE');

        fetch(DRAFT_ONE_URL + '/' + encodeURIComponent(draftKey), {
            method: 'POST', body: body, credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).finally(function () {
            clearLocal(draftKey);
            draftKey = null;
            if (draftKeyInput) draftKeyInput.value = '';
            draftDiscard.classList.add('hidden');
            setDraftStatus('Draft discarded.', 'muted');
        });
    });

    // ── resuming ──
    function loadDraftList() {
        if (!draftList) return;
        draftList.innerHTML = '<div class="px-3 py-3 text-[11px] text-slate-500">Loading drafts…</div>';
        draftList.classList.remove('hidden');

        fetch(DRAFT_LIST_URL, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var drafts = (data && data.drafts) || [];
                if (draftCount) {
                    draftCount.textContent = drafts.length;
                    draftCount.classList.toggle('hidden', !drafts.length);
                }
                if (!drafts.length) {
                    draftList.innerHTML = '<div class="px-3 py-3 text-[11px] text-slate-500">No saved drafts.</div>';
                    return;
                }
                draftList.innerHTML = drafts.map(function (d) {
                    return '<div class="flex items-center gap-3 px-3 py-2">'
                        + '<div class="min-w-0">'
                        
                        +   '<div class="font-mono text-xs font-bold text-slate-900 truncate">' + esc(d.mother_file_no || 'Regular files') + '</div>'
                        +   '<div class="text-[10px] text-slate-500">' + d.children_selected + ' of ' + d.children_total
                        +     ' children · saved ' + esc(d.last_saved_human || '') + '</div>'
                        + '</div>'
                        + '<div class="ml-auto flex items-center gap-1.5">'
                        +   '<button type="button" class="batch-draft-open px-2 py-1 text-[10px] font-bold bg-violet-600 text-white rounded hover:bg-violet-700" data-key="' + esc(d.draft_key) + '">Resume</button>'
                        // Offered only when a save actually lost rows, which is the
                        // one case going back a version is the right move.
                        +   (d.has_previous
                                ? '<button type="button" class="batch-draft-prev px-2 py-1 text-[10px] font-semibold border border-amber-300 text-amber-700 rounded hover:bg-amber-50" data-key="' + esc(d.draft_key) + '"'
                                    + ' title="Go back to the copy saved ' + esc(d.previous_saved_human || '') + ', which had ' + (d.previous_children_total || 0) + ' children">Earlier version</button>'
                                : '')
                        +   '<button type="button" class="batch-draft-drop px-2 py-1 text-[10px] font-semibold border border-rose-300 text-rose-700 rounded hover:bg-rose-50" data-key="' + esc(d.draft_key) + '">Delete</button>'
                        + '</div>'
                        + '</div>';
                }).join('');
            })
            .catch(function () {
                draftList.innerHTML = '<div class="px-3 py-3 text-[11px] text-rose-700">Could not load drafts.</div>';
            });
    }

    if (draftResume) draftResume.addEventListener('click', function () {
        if (draftList && !draftList.classList.contains('hidden') && draftList.innerHTML.indexOf('Loading') === -1) {
            draftList.classList.add('hidden');
            return;
        }
        loadDraftList();
    });

    if (draftList) draftList.addEventListener('click', function (e) {
        var open = e.target.closest('.batch-draft-open');
        var prev = e.target.closest('.batch-draft-prev');
        var drop = e.target.closest('.batch-draft-drop');

        if (open || prev) {
            if (draftDirty && !confirm('Resuming replaces what is on screen. Continue?')) return;
            restoreDraft((open || prev).dataset.key, !!prev);
            draftList.classList.add('hidden');
            return;
        }
        if (drop) {
            if (!confirm('Delete this draft?')) return;
            var body = new FormData();
            body.append('_token', csrfToken());
            body.append('_method', 'DELETE');
            fetch(DRAFT_ONE_URL + '/' + encodeURIComponent(drop.dataset.key), {
                method: 'POST', body: body, credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).finally(function () { clearLocal(drop.dataset.key); loadDraftList(); });
        }
    });

    function restoreDraft(key, wantPrevious) {
        setDraftStatus(wantPrevious ? 'Loading the earlier version…' : 'Loading draft…', 'busy');

        fetch(DRAFT_ONE_URL + '/' + encodeURIComponent(key) + (wantPrevious ? '?previous=1' : ''), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.success) throw new Error('missing');
                applyDraftPayload(key, (data.draft && data.draft.payload) || {});
            })
            .catch(function () {
                // The local mirror only ever holds the current version, so it is no
                // answer to a request for the earlier one.
                if (wantPrevious) {
                    setDraftStatus('That draft has no earlier version to go back to.', 'error');
                    return;
                }

                // Fall back to the copy this browser kept — the reason it exists.
                var local = null;
                try { local = JSON.parse(localStorage.getItem(LS_PREFIX + key) || 'null'); } catch (e) { /* ignore */ }
                if (local && local.payload) {
                    applyDraftPayload(key, local.payload);
                    setDraftStatus('Restored from this browser — save the draft to push it back.', 'error');
                    draftDirty = true;
                    return;
                }
                setDraftStatus('Could not load that draft.', 'error');
            });
    }

    function applyDraftPayload(key, payload) {
        // Held for the whole restore, not just the part that writes the mother
        // file number. Restoring the common fields dispatches a change on
        // Application Type and on Old File Number, and both of those are wired to
        // reload the children — which is exactly what used to land a second later
        // and overwrite every restored row with blank registry values.
        restoring = true;
        suppressChildLoad = true;
        // Retire any child load already in flight, so one started before the
        // restore cannot paint over it either.
        childLoadSeq++;

        draftKey = key;
        if (draftKeyInput) draftKeyInput.value = key;
        if (draftDiscard) draftDiscard.classList.remove('hidden');

        var kind   = payload.kind === 'regular' ? 'regular' : 'subdivision';
        var mother = kind === 'regular' ? '' : (payload.mother_file_no || '');

        try {
            // The kind decides which sections are on screen and which are stood
            // down, so it is set before batch mode paints any of them.
            var kindRadio = document.querySelector('.batch-kind-radio[value="' + kind + '"]');
            if (kindRadio) kindRadio.checked = true;

            // Batch mode first: it relocates sections and stands the per-child
            // fields down, so restoring values before it would fight with it.
            if (!toggle.checked) {
                toggle.checked = true;
                applyBatchMode(true);
            } else {
                applyKind(true);
            }

            restoreCommon(payload.common || {});

            if (kind === 'regular') {
                initFileSelect();
                // Fall back to the file numbers in the table when the draft pre-dates
                // picked_files, so an older regular draft still restores its picker.
                setPickedFiles(payload.picked_files && payload.picked_files.length
                    ? payload.picked_files
                    : (payload.children || []).map(function (c) { return c.file_number; }));
            }

            if (oldFileNo) oldFileNo.value = mother;
            if (motherField) motherField.value = mother;
            motherLabel.textContent = mother || '—';
            if (mother && motherSel && window.jQuery && jQuery.fn.select2) {
                jQuery(motherSel).append(new Option(mother, mother, true, true)).trigger('change.select2');
            } else if (mother && motherSel) {
                if (!motherSel.querySelector('option[value="' + mother.replace(/"/g, '\\"') + '"]')) {
                    motherSel.appendChild(new Option(mother, mother, true, true));
                }
                motherSel.value = mother;
            }

            if (card) card.classList.remove('hidden');
            // Painted immediately from the draft alone, so the work is on screen
            // without waiting on the registry.
            renderChildren(payload.children || []);
            setStatus('');
        } catch (e) {
            restoring = false;
            suppressChildLoad = false;
            setDraftStatus('Draft could not be restored cleanly.', 'error');
            throw e;
        }

        lastSavedAt = new Date();
        setDraftStatus('Draft restored — backfilling from the registry…', 'busy');
        if (window.lucide) window.lucide.createIcons();

        // Autosave must not resume until the backfill has settled, or a save could
        // capture the half-merged table. The timer is the safety net: if the
        // registry never answers, autosave still comes back on.
        var released = false;
        function releaseRestore(message, tone) {
            if (released) return;
            released = true;
            restoring = false;
            suppressChildLoad = false;
            setDraftStatus(message, tone);
        }
        setTimeout(function () { releaseRestore('Draft restored — autosave is on.', 'ok'); }, 15000);

        // Where the registry copy comes from is the one thing the two kinds do
        // differently on restore: a subdivision re-reads the mother's children, a
        // regular batch re-reads the files that were picked.
        var freshRows;
        if (kind === 'regular') {
            var files = (payload.children || []).map(function (c) { return c.file_number; }).filter(Boolean);
            if (!files.length) {
                releaseRestore('Draft restored — autosave is on.', 'ok');
                return;
            }
            freshRows = fetchFileDetails(files).then(function (r) { return r.success ? r.children : []; });
        } else {
            if (!mother) {
                releaseRestore('Draft restored — autosave is on.', 'ok');
                return;
            }
            freshRows = fetchChildren(mother);
        }

        // Backfill: the draft is authoritative for everything the user keyed, and
        // the registry fills the gaps around it — blank cells, plus any child
        // commissioned since the draft was started.
        freshRows.then(function (fresh) {
            var merged = mergeDraftWithRegistry(payload.children || [], fresh);
            renderChildren(merged.children);
            releaseRestore(
                'Draft restored' + (merged.filled ? ' · ' + merged.filled + ' blank field(s) backfilled' : '')
                    + (merged.added ? ' · ' + merged.added + ' new child file(s) added' : '')
                    + ' — autosave is on.',
                'ok'
            );
            // The merge is real work the draft does not yet hold, so it is pushed
            // back rather than waiting for the next keystroke.
            if (merged.filled || merged.added) saveDraft('post-restore-backfill');
        });
    }

    // Draft wins over registry, field by field: anything the user keyed stays
    // exactly as they left it, and only a blank takes the registry's value. Rows
    // are keyed by child file number, so registry order changing between sessions
    // cannot shuffle anybody's work onto the wrong plot.
    function mergeDraftWithRegistry(draftChildren, freshChildren) {
        var FIELDS = ['applicant_name', 'applicant_address', 'plot_number', 'location',
                      'land_use_id', 'purpose_id', 'page', 'page_2', 'page_3', 'tracking_id'];

        var freshByFile = {};
        freshChildren.forEach(function (c) { freshByFile[String(c.file_number).trim()] = c; });

        var filled = 0;
        var seen = {};

        var out = draftChildren.map(function (row) {
            var merged = {};
            Object.keys(row).forEach(function (k) { merged[k] = row[k]; });

            var fresh = freshByFile[String(row.file_number).trim()];
            seen[String(row.file_number).trim()] = true;
            if (!fresh) return merged;

            FIELDS.forEach(function (f) {
                var have = merged[f];
                var isBlank = have === '' || have === null || have === undefined;
                var incoming = fresh[f];
                if (isBlank && incoming !== '' && incoming !== null && incoming !== undefined) {
                    merged[f] = incoming;
                    filled++;
                }
            });

            // Whether a child already carries a recommendation is the registry's
            // call, never the draft's — one may have been captured elsewhere while
            // this draft sat waiting.
            merged.has_recommendation = !!fresh.has_recommendation;
            merged.existing_status = fresh.existing_status || '';
            if (merged.has_recommendation) merged.checked = false;

            return merged;
        });

        // Children commissioned after the draft was started. Appended at the end
        // and left ticked, so they are visible as new rather than folded silently
        // into the middle of rows the user has already worked through.
        var added = 0;
        freshChildren.forEach(function (c) {
            if (seen[String(c.file_number).trim()]) return;
            var row = {};
            Object.keys(c).forEach(function (k) { row[k] = c[k]; });
            row.checked = !c.has_recommendation;
            out.push(row);
            added++;
        });

        return { children: out, filled: filled, added: added };
    }

    function restoreCommon(common) {
        Object.keys(common).forEach(function (name) {
            if (NOT_CAPTURE.indexOf(name) !== -1) return;
            var entry = common[name];
            var nodes = recForm.querySelectorAll('[name="' + name.replace(/"/g, '\\"') + '"]');
            if (!nodes.length) return;

            if (entry.type === 'checkbox' || entry.type === 'radio') {
                Array.prototype.forEach.call(nodes, function (el) {
                    var want = entry.values.indexOf(el.value) !== -1;
                    if (el.checked !== want) {
                        el.checked = want;
                        el.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
                return;
            }
            if (entry.type === 'multi') {
                Array.prototype.forEach.call(nodes[0].options, function (o) {
                    o.selected = entry.values.indexOf(o.value) !== -1;
                });
                nodes[0].dispatchEvent(new Event('change', { bubbles: true }));
                return;
            }

            var el = nodes[0];
            // flatpickr wraps every date input on this layout; writing .value on one
            // leaves the visible field showing the old date (see admin/header).
            if (el._flatpickr) {
                el._flatpickr.setDate(entry.value || null, false);
            } else {
                el.value = entry.value;
            }
            el.dispatchEvent(new Event('change', { bubbles: true }));
        });

        // Select2 fields submit through a hidden input, so the value is already
        // back — but the visible picker has no option for it and would read blank.
        if (window.jQuery && jQuery.fn && jQuery.fn.select2) {
            [['#district', '#district_select'], ['#lga', '#lga_select'],
             ['#street_name', '#street_name_select'], ['#layout_plan_no', null]].forEach(function (pair) {
                var hidden = document.querySelector(pair[0]);
                if (!hidden) return;
                var val = pair[1] ? hidden.value : hidden.value;
                var sel = pair[1] ? document.querySelector(pair[1]) : hidden;
                if (!sel || !val) return;
                jQuery(sel).append(new Option(val, val, true, true)).trigger('change.select2');
            });
        }
    }

    // Draft count for the Resume button, plus a sweep of local copies whose draft
    // was submitted or deleted elsewhere, so this browser does not accumulate dead
    // payloads. Runs the first time batch mode is opened rather than on every form
    // load — the single-record form has no use for either.
    var draftBootstrapped = false;
    function bootstrapDrafts() {
        if (draftBootstrapped) return;
        draftBootstrapped = true;

        fetch(DRAFT_LIST_URL, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var live = ((data && data.drafts) || []).map(function (d) { return d.draft_key; });
                if (draftCount) {
                    draftCount.textContent = live.length;
                    draftCount.classList.toggle('hidden', !live.length);
                }
                for (var i = localStorage.length - 1; i >= 0; i--) {
                    var k = localStorage.key(i);
                    if (!k || k.indexOf(LS_PREFIX) !== 0) continue;
                    var thisKey = k.slice(LS_PREFIX.length);
                    if (live.indexOf(thisKey) === -1 && thisKey !== draftKey) localStorage.removeItem(k);
                }
            })
            .catch(function () { /* offline — leave local copies exactly where they are */ });
    }

    // A draft can be opened straight from a link (?draft=KEY), which is how the
    // "Resume" action on the RofO table hands one back to this form.
    (function () {
        var params = new URLSearchParams(window.location.search);
        var key = params.get('draft');
        if (!key) return;
        bootstrapDrafts();
        restoreDraft(key);
    })();

    // Leaving with work the server has not accepted is the one thing this feature
    // exists to prevent, so it is worth the browser's confirm dialog.
    window.addEventListener('beforeunload', function (e) {
        if (!toggle.checked) return;
        if (!draftDirty && !sessionLost) return;
        e.preventDefault();
        e.returnValue = '';
    });

    recForm.addEventListener('submit', function (e) {
        if (!toggle.checked) return;

        // Kept in step here as well as on every kind change: this is the value the
        // server branches on, and it must match what is actually on screen.
        if (kindInput) kindInput.value = currentKind();

        if (!isRegular()) {
            if (currentAppType() !== BATCH_TYPE) {
                e.preventDefault(); e.stopPropagation();
                setStatus('A subdivision batch covers ' + BATCH_TYPE + ' only — pick that type, or switch to Regular files.', 'error');
                return;
            }
            if (!oldFileNo || !oldFileNo.value.trim()) {
                e.preventDefault(); e.stopPropagation();
                setStatus('Select the subdivided (mother) file first.', 'error');
                card.classList.remove('hidden');
                if (motherSel) motherSel.focus();
                return;
            }
        } else if (!rowsBody.querySelectorAll('.batch-row').length) {
            e.preventDefault(); e.stopPropagation();
            setStatus('Pick the file numbers and press Apply to load them into the table first.', 'error');
            if (filesPicker) filesPicker.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        if (!rowsBody.querySelectorAll('.batch-row-check:checked').length) {
            e.preventDefault(); e.stopPropagation();
            setStatus('Tick at least one child to save a batch.', 'error');
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    // Last write before the page goes away. Registered after the validation
    // handler above so it is skipped when that one blocks the submit.
    //
    // sendBeacon rather than fetch: the browser is about to navigate, and a normal
    // request in flight at that moment is cancelled. This is the save that matters
    // most — if the POST itself comes back 419, this draft is exactly what was on
    // screen when the user pressed Save.
    recForm.addEventListener('submit', function (e) {
        if (!toggle.checked || e.defaultPrevented) return;

        // Declare the row count so the server can tell a short POST from a short
        // batch — see the note on #batch-children-expected.
        if (expectedInput) {
            expectedInput.value = String(rowsBody.querySelectorAll('.batch-row-check:checked').length);
        }

        if (draftKey && navigator.sendBeacon) {
            var payload = buildPayload();
            writeLocal(payload);

            var body = new FormData();
            body.append('_token', csrfToken());
            body.append('draft_key', draftKey);
            body.append('application_type', payload.application_type || '');
            body.append('mother_file_no', payload.mother_file_no || '');
            body.append('children_total', String(payload.children.length));
            body.append('children_selected', String(payload.children.filter(function (c) { return c.checked; }).length));
            body.append('payload', JSON.stringify(payload));
            navigator.sendBeacon(DRAFT_STORE_URL, body);
        }

        // The submit is the intended way off this page, so the unload guard stands
        // down — storeBatch() closes the draft out once the rows are committed.
        draftDirty = false;
        sessionLost = false;
    });
});


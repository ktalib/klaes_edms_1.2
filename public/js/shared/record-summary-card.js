/**
 * "Where did this record go?" confirmation card.
 *
 * One save can fan out across a dozen tables — registry, parties, transactions,
 * commissioning mirrors, prop_id lineage — and none of that is visible from the
 * form. The server returns a `storage_summary` (see IndexingStorageSummaryService)
 * counting the rows that now carry the file, and this renders it.
 *
 * Standalone on purpose: it is shown after file indexing, after property
 * transaction capture, and after ST commissioning, and those are three separate
 * pages with three separate script sets. Keeping the renderer here means the
 * three confirmations stay identical instead of drifting apart.
 *
 * Requires SweetAlert (Swal); falls back to alert() when it is absent.
 */
(function () {
    'use strict';

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    var GROUP_PALETTE = {
        primary: { head: 'text-emerald-700', count: 'text-emerald-700', border: 'border-emerald-100' },
        parties: { head: 'text-sky-700', count: 'text-sky-700', border: 'border-sky-100' },
        records: { head: 'text-indigo-700', count: 'text-indigo-700', border: 'border-indigo-100' },
        onward: { head: 'text-amber-700', count: 'text-amber-700', border: 'border-amber-100' }
    };

    function renderGroup(group) {
        if (!group || !Array.isArray(group.rows) || group.rows.length === 0) {
            return '';
        }

        var palette = GROUP_PALETTE[group.tone] || GROUP_PALETTE.records;

        var body = group.rows.map(function (row) {
            return '' +
                '<tr class="border-t ' + palette.border + '">' +
                    '<td class="py-1 pr-3 text-left font-mono text-[11px] text-slate-500 align-top">' + escapeHtml(row.table) + '</td>' +
                    '<td class="py-1 pr-3 text-left align-top">' + escapeHtml(row.label) +
                        (row.detail ? '<span class="block text-[11px] text-slate-400 break-all">' + escapeHtml(row.detail) + '</span>' : '') +
                    '</td>' +
                    '<td class="py-1 text-right font-semibold align-top ' + palette.count + '">' + Number(row.count).toLocaleString() + '</td>' +
                '</tr>';
        }).join('');

        return '' +
            '<div class="mt-3">' +
                '<p class="text-[11px] font-bold uppercase tracking-wider ' + palette.head + ' text-left">' + escapeHtml(group.title) + '</p>' +
                '<table class="w-full text-xs mt-1"><tbody>' + body + '</tbody></table>' +
            '</div>';
    }

    /**
     * Instruments captured in this submission. Server-side these are read back
     * from the rows that were actually written, so anything skipped as a
     * duplicate never appears here.
     */
    function renderInstruments(instruments) {
        if (!Array.isArray(instruments) || instruments.length === 0) {
            return '';
        }

        var body = instruments.map(function (item) {
            var parties = [item.grantor, item.grantee].filter(Boolean).join(' → ');
            var meta = [item.reg_no, item.date].filter(Boolean).join(' · ');

            return '' +
                '<tr class="border-t border-violet-100">' +
                    '<td class="py-1.5 pr-3 text-left align-top">' +
                        '<span class="font-semibold text-slate-800">' + escapeHtml(item.instrument) + '</span>' +
                        (parties ? '<span class="block text-[11px] text-slate-500 break-all">' + escapeHtml(parties) + '</span>' : '') +
                        (meta ? '<span class="block text-[11px] text-slate-400">' + escapeHtml(meta) + '</span>' : '') +
                    '</td>' +
                    '<td class="py-1.5 text-right align-top text-[11px] text-violet-700 whitespace-nowrap">' +
                        escapeHtml(item.destination) +
                        '<span class="block font-mono text-[10px] text-slate-400">' + escapeHtml(item.table) + '</span>' +
                    '</td>' +
                '</tr>';
        }).join('');

        return '' +
            '<div class="mt-3">' +
                '<p class="text-[11px] font-bold uppercase tracking-wider text-violet-700 text-left">Instruments captured (' + instruments.length + ')</p>' +
                '<table class="w-full text-xs mt-1"><tbody>' + body + '</tbody></table>' +
            '</div>';
    }

    function renderIdentity(summary) {
        var rows = [
            ['File number', summary.file_number],
            ['File title', summary.file_title],
            ['Tracking ID', summary.tracking_id],
            ['Registry', summary.registry],
            ['Property ID', summary.prop_id],
            ['Parent property', summary.parent_prop_id]
        ].filter(function (pair) {
            return pair[1] !== null && pair[1] !== undefined && String(pair[1]).trim() !== '';
        });

        if (rows.length === 0) {
            return '';
        }

        return '<div class="mt-3 rounded border border-slate-200 bg-slate-50 px-3 py-2 text-left text-sm space-y-1">' +
            rows.map(function (pair) {
                return '<div class="flex items-baseline gap-2">' +
                    '<span class="w-28 shrink-0 text-[11px] uppercase tracking-wider text-slate-500">' + escapeHtml(pair[0]) + '</span>' +
                    '<span class="flex-1 min-w-0 font-semibold text-slate-800 break-all">' + escapeHtml(pair[1]) + '</span>' +
                '</div>';
            }).join('') +
        '</div>';
    }

    function buildHtml(summary, message, instruments, options) {
        options = options || {};
        summary = summary || {};

        var groups = Array.isArray(summary.groups) ? summary.groups : [];
        var notes = Array.isArray(summary.notes) ? summary.notes : [];

        var notesHtml = notes.map(function (note) {
            var tone = note.tone === 'muted'
                ? 'text-slate-500 bg-slate-50 border-slate-200'
                : 'text-blue-700 bg-blue-50 border-blue-200';
            return '<p class="text-xs ' + tone + ' border rounded p-2 mt-2 text-left">' + escapeHtml(note.text) + '</p>';
        }).join('');

        // The transaction card confirms the instruments themselves, not the file's
        // whole footprint, so it stops after the instruments table.
        var tail = options.instrumentsOnly ? '' :
            groups.map(renderGroup).join('') +
            notesHtml +
            '<p class="text-[11px] text-slate-400 mt-3 text-left">Counts are the rows that now carry this file — including any that already existed.</p>';

        return '' +
            '<p class="text-sm text-slate-600 text-left" style="white-space: pre-line;">' + escapeHtml(message) + '</p>' +
            renderIdentity(summary) +
            // Caller-supplied block (e.g. the EDMS scan folder on commissioning).
            // Trusted HTML built by the caller, not server text — never user input.
            (options.extraHtml || '') +
            renderInstruments(instruments) +
            tail;
    }

    function toText(summary) {
        var lines = [];
        (summary.groups || []).forEach(function (group) {
            lines.push(group.title + ':');
            (group.rows || []).forEach(function (row) {
                lines.push('  - ' + row.table + ': ' + row.count + ' (' + row.label + ')');
            });
        });
        (summary.notes || []).forEach(function (note) { lines.push(note.text); });
        return lines.join('\n');
    }

    /**
     * Just the destination tables, as HTML, for callers that already have their own
     * confirmation dialog and only want the "where did it go" block dropped into it
     * (the MLS Commission Summary card). Returns '' when there is nothing to show.
     */
    window.renderRecordSummaryGroups = function (summary) {
        if (!summary || !Array.isArray(summary.groups) || summary.groups.length === 0) {
            return '';
        }

        return '<div class="text-left">' + summary.groups.map(renderGroup).join('') + '</div>';
    };

    /**
     * Show the card. Resolves once dismissed, so callers can chain what comes next.
     *
     * @param {object} data    Server response: { message, storage_summary, instruments }
     * @param {object} options { title, extraText, instrumentsOnly, fallbackTitle }
     */
    window.showRecordSummaryCard = function (data, options) {
        options = options || {};
        data = data || {};

        var summary = data.storage_summary;
        var instruments = data.instruments || [];
        var message = (data.message || 'Saved successfully.') + (options.extraText || '');

        if (typeof Swal === 'undefined') {
            alert(summary ? message + '\n\n' + toText(summary) : message);
            return Promise.resolve();
        }

        // Nothing to tabulate: a plain dialog beats an empty card. Any caller-supplied
        // block (the EDMS folder line) still has to be shown, so only the bare case
        // gets the auto-dismissing toast.
        if (!summary && instruments.length === 0) {
            if (options.extraHtml) {
                return Swal.fire({
                    title: options.fallbackTitle || 'Success!',
                    html: '<p>' + escapeHtml(message) + '</p>' + options.extraHtml,
                    icon: 'success',
                    confirmButtonColor: '#059669'
                });
            }

            return Swal.fire({
                title: options.fallbackTitle || 'Success!',
                text: message,
                icon: 'success',
                timer: 2500,
                showConfirmButton: false
            });
        }

        return Swal.fire({
            title: options.title || 'Saved — here is where it went',
            html: buildHtml(summary, message, instruments, options),
            icon: 'success',
            width: 620,
            confirmButtonText: 'Continue',
            confirmButtonColor: '#059669',
            allowOutsideClick: false
        });
    };
})();

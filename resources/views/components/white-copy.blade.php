{{-- ═══════════════════════ Print White Copy ═══════════════════════
     The proofing stage that sits in front of the Print Manager.

     A white copy is a draft of a document, run off on ordinary paper in black and
     white, for an officer to read against the record before anything is committed
     to security stock. Everything about it is deliberately separate from the
     official print:

       * its own route per document, so no official print URL can render a proof
         and no proof URL can render an official copy;
       * its own state — which is to say none. Generating one mints no security
         serial, writes no print_logs row, increments no print count and moves
         nothing onto a Printed tab. The proof can therefore be run again after
         every correction, as many times as the record needs;
       * its own answer to "is this approved" — none. Printing a proof says only
         that paper came out of a printer. Whether it was read, and whether it was
         found correct, is asked at the Print Manager, of the person who read it.

     Two things live here. The card that opens a proof (and, where the document
     prints a date of issue, takes that date), and the gate in front of the Print
     Manager. Both are global rather than per-page because the second document to
     want them was already the second copy of them.

     Opened directly:
         WhiteCopy.open({ recordId, ref, url, issueDate, ownsDate })
     or through the Print Manager, which dispatches `open-white-copy` when a letter
     it has been asked to print has no date of issue yet.

     The gate:
         WhiteCopy.openPrintManager(ref, type, url, options)
     in place of SmartPrintManager.open() wherever a document has a proofing stage.
--}}

<div id="whiteCopyModal" class="fixed inset-0 z-[1000095] hidden bg-slate-900/70 p-4 overflow-y-auto">
    <div class="min-h-full flex items-center justify-center">
        <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-slate-800 to-slate-700 flex items-center gap-3">
                <i data-lucide="file-search" class="h-5 w-5 text-slate-300"></i>
                <div class="min-w-0">
                    <h3 class="text-white font-bold text-lg leading-tight">Print White Copy</h3>
                    <p class="text-slate-300 text-[11px] font-semibold uppercase tracking-widest truncate">
                        <span id="whiteCopyFile" class="font-mono"></span>
                        <span class="text-slate-400"> · proof for vetting</span>
                    </p>
                </div>
                <button type="button" onclick="closeWhiteCopyModal()"
                        class="ml-auto p-2 text-slate-300 hover:text-white hover:bg-white/10 rounded-full transition">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <div class="p-6 space-y-5">
                <div class="flex gap-2.5 p-3.5 rounded-xl bg-slate-50 border border-slate-200">
                    <i data-lucide="info" class="h-4 w-4 text-slate-400 shrink-0 mt-0.5"></i>
                    <p class="text-[12px] text-slate-600 font-medium leading-relaxed">
                        A draft of the document on ordinary white paper, in black and white. It carries no
                        coat of arms, no QR, no serial number and no signature block, and it is marked
                        <b>WHITE COPY</b> — it is not an issued copy and cannot stand in for one.
                    </p>
                </div>

                {{-- Shown only for the documents that actually print a date of issue —
                     the RofO does, the recommendation form does not. It is asked for
                     here rather than at the Print Manager because it prints on the
                     letter: it is part of what is being proofread, and asking for it
                     at the printer left the one field still open to change as the one
                     field nobody had read on paper.

                     Blank until someone enters it. date_issued has no fallback
                     anywhere, so a letter that has never been issued has no date, and
                     a proof that invents one proofreads as correct and prints as
                     wrong. --}}
                <div id="whiteCopyDateSection" class="space-y-2">
                    <div class="flex items-center justify-between gap-3">
                        <label class="text-[11px] font-black uppercase tracking-widest text-slate-500">
                            Date Issued <span id="whiteCopyDateRequired" class="text-red-500">*</span>
                        </label>
                        <span id="whiteCopyDateOnRecord"
                              class="hidden text-[10px] font-bold text-slate-400 uppercase tracking-wider">On record</span>
                    </div>
                    <input type="date" id="whiteCopyDate"
                           class="w-full px-3 py-2 bg-white rounded-lg text-sm font-semibold text-slate-800 outline-none focus:ring-2 focus:ring-slate-500 border border-slate-300">
                    <p class="text-[11px] text-slate-500 leading-relaxed">
                        Saved to the record and printed on the white copy as <b>DATE OF ISSUE</b>. The official
                        print then carries the same date — it is not asked for again at the Print Manager.
                    </p>
                </div>

                <p id="whiteCopyError" class="hidden text-[11.5px] font-bold text-red-600"></p>

                <div class="flex gap-2.5 p-3.5 rounded-xl bg-amber-50 border border-amber-200">
                    <i data-lucide="alert-triangle" class="h-4 w-4 text-amber-600 shrink-0 mt-0.5"></i>
                    <p class="text-[12px] text-amber-900 font-semibold leading-relaxed">
                        Printing a white copy does not mark this record as printed and does not approve it.
                        Correct the record and print another as many times as you need — then confirm the
                        proofreading at the Print Manager.
                    </p>
                </div>
            </div>

            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center gap-3">
                <button type="button" onclick="closeWhiteCopyModal()"
                        class="px-4 py-2 text-sm font-bold text-slate-600 hover:text-slate-900 transition">Cancel</button>
                <button type="button" id="whiteCopyGenerate" onclick="generateWhiteCopy()"
                        class="ml-auto px-5 py-2.5 rounded-xl bg-slate-800 text-white text-sm font-bold hover:bg-slate-900 disabled:opacity-40 disabled:cursor-not-allowed transition inline-flex items-center gap-2">
                    <i data-lucide="printer" class="h-4 w-4"></i>
                    Generate White Copy
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var state = { id: null, url: '', ownsDate: false };

    function csrf() {
        var el = document.querySelector('meta[name="csrf-token"]');
        return el ? el.getAttribute('content') : '';
    }

    function escHtml(v) {
        return String(v == null ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // opts.ownsDate — this document prints a DATE OF ISSUE, so the date is taken
    // here and written before the proof opens. Without it the card is only
    // "generate the proof", which is all a document with no printed date needs.
    function open(opts) {
        opts = opts || {};
        state.id       = opts.recordId || null;
        state.url      = opts.url || '';
        state.ownsDate = !!opts.ownsDate;

        document.getElementById('whiteCopyFile').textContent = opts.ref || '';
        document.getElementById('whiteCopyError').classList.add('hidden');
        document.getElementById('whiteCopyGenerate').disabled = false;
        document.getElementById('whiteCopyDateSection').classList.toggle('hidden', !state.ownsDate);

        var issueDate = opts.issueDate || '';
        var onRecord  = !!issueDate;
        document.getElementById('whiteCopyDateOnRecord').classList.toggle('hidden', !onRecord);
        document.getElementById('whiteCopyDateRequired').classList.toggle('hidden', onRecord);

        // Every native date input is wrapped by flatpickr (see
        // admin/header.blade.php), which draws a DD/MM/YYYY field of its own in
        // front of it. Assigning .value updates the hidden original and leaves the
        // visible field showing the last date — so the picker is told, not the input.
        var field = document.getElementById('whiteCopyDate');
        if (field._flatpickr) {
            if (issueDate) { field._flatpickr.setDate(issueDate, false); } else { field._flatpickr.clear(); }
        } else {
            field.value = issueDate;
        }

        document.getElementById('whiteCopyModal').classList.remove('hidden');
        if (window.lucide) window.lucide.createIcons();
    }

    function close() {
        document.getElementById('whiteCopyModal').classList.add('hidden');
        state.id  = null;
        state.url = '';
    }

    function fail(message) {
        var err = document.getElementById('whiteCopyError');
        err.textContent = message;
        err.classList.remove('hidden');
    }

    // Saves the date, then opens the proof. The save comes first and the proof only
    // opens if it succeeded: a white copy printed with a date the record does not
    // hold is a proof of a document that will not print that way.
    function generate() {
        if (!state.url) return;

        var btn = document.getElementById('whiteCopyGenerate');
        document.getElementById('whiteCopyError').classList.add('hidden');

        if (!state.ownsDate) {
            var url = state.url;
            close();
            window.open(url, '_blank');
            return;
        }

        var date = (document.getElementById('whiteCopyDate').value || '').trim();
        if (!date) {
            fail('Enter the date of issue — it prints on the document as DATE OF ISSUE '
                + 'and nothing else supplies it.');
            return;
        }

        btn.disabled = true;

        // 'all' on purpose: this card IS the place the date is decided, so an
        // operator correcting a date between two proofs has to be able to change one
        // already on record. Nothing has been issued yet — that is what a proof is for.
        fetch('{{ route('land-rofos.issue-date') }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
            body: JSON.stringify({ ids: [state.id], issue_date: date, issue_date_apply: 'all' })
        })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
        .then(function (res) {
            if (!res.ok || !res.data.success) {
                throw new Error((res.data && res.data.message) || 'The date of issue could not be saved.');
            }
            var url = state.url;
            close();
            window.open(url, '_blank');
        })
        .catch(function (e) {
            btn.disabled = false;
            fail(e.message || 'Could not save the date of issue. Check your connection and try again.');
        });
    }

    // ── The gate in front of the Print Manager ─────────────────────────────
    // Official printing is the point of no return: the Original goes onto a sheet
    // of security paper, the record is stamped printed and, for a RofO, the
    // applicant is told it is ready. So the manager is not opened straight from a
    // menu any more — it is opened after somebody says the proof has been read and
    // is correct.
    //
    // This is asked of a person rather than derived from the record on purpose.
    // Nothing the system can see distinguishes "a white copy was generated" from
    // "a white copy was read, checked against the file and found right", and only
    // the second is a reason to spend security paper. A stored flag would have
    // turned the first into the second the moment a proof was opened.
    // The gate itself, so anything that leads to official paper can stand behind
    // it — one row's Print Manager, a whole batch, a hand-picked selection.
    //
    //   subject     what is about to be printed, for the question to name
    //   onYes       run the official print
    //   onWhiteCopy offered when the answer is no; omit and only the explanation
    //               is shown
    function confirmProofread(opts) {
        opts = opts || {};
        var subject = opts.subject || 'this document';

        Swal.fire({
            icon: 'question',
            title: 'Has the White Copy been proofread and approved?',
            html: '<div style="font-size:13px;color:#475569;line-height:1.6">'
                + 'The next step prints official copies of <b>' + escHtml(subject) + '</b>'
                + ' — the Original goes onto security paper.'
                + '</div>',
            showCancelButton: true,
            confirmButtonText: 'Yes, it has',
            cancelButtonText: 'No',
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#64748b',
            reverseButtons: true,
            focusCancel: true
        }).then(function (r) {
            if (r.isConfirmed) {
                if (typeof opts.onYes === 'function') opts.onYes();
                return;
            }

            // Not a dead end: the answer to "not proofread yet" is a white copy, so
            // the way to one is offered here rather than left for the operator to
            // find their own way back to a menu.
            Swal.fire({
                icon: 'info',
                title: 'Print the White Copy first',
                html: '<div style="font-size:13px;color:#475569;line-height:1.6">'
                    + 'The white copy has to be printed, proofread and approved before official copies are '
                    + 'run off. Correct the record if you find anything, print another white copy, and come '
                    + 'back here.'
                    + '</div>',
                showCancelButton: !!opts.onWhiteCopy,
                confirmButtonText: opts.onWhiteCopy ? 'Print White Copy' : 'Close',
                cancelButtonText: 'Close',
                confirmButtonColor: '#1e293b',
                cancelButtonColor: '#94a3b8',
                reverseButtons: true
            }).then(function (r2) {
                if (r2.isConfirmed && typeof opts.onWhiteCopy === 'function') opts.onWhiteCopy();
            });
        });
    }

    function openPrintManager(ref, type, url, options) {
        var opts = options || {};

        // No proofing stage for this document — nothing to have proofread.
        if (!opts.whiteCopyUrl) {
            window.SmartPrintManager.open(ref, type, url, opts);
            return;
        }

        confirmProofread({
            subject: ref,
            onYes: function () {
                window.SmartPrintManager.open(ref, type, url, opts);
            },
            onWhiteCopy: function () {
                open({
                    recordId:  opts.recordId,
                    ref:       ref,
                    url:       opts.whiteCopyUrl,
                    issueDate: opts.issueDate || '',
                    ownsDate:  !!opts.whiteCopyOwnsDate
                });
            }
        });
    }

    // The Print Manager asks for a record's white copy card — a letter with no date
    // of issue cannot be printed, and that is where the date is entered.
    window.addEventListener('open-white-copy', function (e) {
        var d = e.detail || {};
        open({ recordId: d.recordId, ref: d.ref, url: d.url || '', issueDate: d.issueDate || '', ownsDate: true });
    });

    window.WhiteCopy = {
        open: open,
        close: close,
        openPrintManager: openPrintManager,
        // Exposed so a batch run — which has its own pipeline and its own proof
        // route — can stand behind the same question a single row does.
        confirmProofread: confirmProofread
    };

    // The inline handlers in the markup above, and the shorthand the row menus use.
    window.openWhiteCopyModal  = function (id, ref, issueDate, url, opts) {
        opts = opts || {};
        open({ recordId: id, ref: ref, issueDate: issueDate, url: url, ownsDate: !!opts.ownsDate });
    };
    window.closeWhiteCopyModal = close;
    window.generateWhiteCopy   = generate;
})();
</script>

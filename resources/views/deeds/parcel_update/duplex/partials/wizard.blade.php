{{--
    The duplex wizard: select + rank, quantities, then one panel per stage in rank order.

    Nothing in here writes to the registry. Step 3 mints HOLDING numbers only
    (DPX-2026-0007-H03) and the real file numbers appear at the Land step.
--}}
<div id="duplex-wizard" class="fixed inset-0 z-[9999] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="closeDuplexWizard()"></div>

    <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-4xl max-h-[92vh] flex flex-col overflow-hidden border border-slate-100">

        <div class="px-8 py-5 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="text-lg font-black text-slate-800">New Duplex Parcel Update</h3>
                <p class="text-xs text-slate-500 mt-0.5" id="wizard-subtitle">
                    Tick the updates in the order you want them carried out.
                </p>
            </div>
            <button onclick="closeDuplexWizard()" class="text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-8">

            {{-- ============ STEP 1 — select and rank ============ --}}
            <div class="wizard-step active" data-step="1">
                <div class="grid md:grid-cols-2 gap-5 mb-6">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Applicant Name *</label>
                        <input type="text" id="dx-applicant" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">File Title</label>
                        <input type="text" id="dx-file-title" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm">
                    </div>
                </div>

                <div class="mb-6">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500">Source File Number(s) *</label>
                        <button type="button" onclick="pickSourceFile()"
                            class="text-xs font-semibold text-blue-600 hover:text-blue-700 inline-flex items-center gap-1">
                            <i data-lucide="plus" class="w-3.5 h-3.5"></i> Add file
                        </button>
                    </div>
                    <div id="dx-sources" class="flex flex-wrap gap-2 p-3 rounded-xl border border-dashed border-slate-300 min-h-[52px]">
                        <span class="text-xs text-slate-400" id="dx-sources-empty">No file selected yet.</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">
                        A Merger consumes several files; the other updates start from one.
                    </p>
                </div>

                <div class="border border-slate-200 rounded-2xl p-5">
                    <p class="text-xs font-black uppercase tracking-wider text-slate-500 mb-1">Select Multiple Parcel Update</p>
                    <p class="text-[11px] text-slate-400 mb-4">
                        The number beside each tick is the order it will be carried out in — it follows the
                        order you tick, not the order they are listed.
                    </p>

                    <div id="dx-type-list" class="space-y-2">
                        @foreach ($types as $key => $label)
                        <label class="type-row flex items-center justify-between gap-3 px-4 py-3 rounded-xl border border-slate-200 cursor-pointer"
                               data-type="{{ $key }}">
                            <span class="flex items-center gap-3">
                                <input type="checkbox" class="dx-type w-4 h-4" value="{{ $key }}" onchange="onTypeToggle(this)">
                                <span class="text-sm font-semibold text-slate-700">{{ $label }}</span>
                            </span>
                            <span class="rank-badge hidden text-center px-2 py-0.5 rounded-lg border text-xs font-black"></span>
                        </label>
                        @endforeach
                    </div>

                    <p id="dx-single-note" class="hidden mt-3 text-[11px] text-slate-500 bg-slate-50 border border-slate-200 rounded-lg px-3 py-2">
                        Only one update ticked — that is fine. It still runs through the duplex pipeline:
                        holding number, one approval, one memo, one commissioning.
                    </p>
                </div>
            </div>

            {{-- ============ STEP 2 — quantities ============ --}}
            <div class="wizard-step" data-step="2">
                <p class="text-sm text-slate-600 mb-4">
                    How many of each? The counts are independent — you can subdivide into four and change
                    the purpose of only two.
                </p>
                <div id="dx-quantities" class="space-y-3"></div>
            </div>

            {{-- ============ STEP 3 — stage runner ============ --}}
            <div class="wizard-step" data-step="3">
                <div id="dx-stage-track" class="flex flex-wrap items-center gap-2 mb-6"></div>
                <div id="dx-stage-panel"></div>
            </div>

            {{-- ============ STEP 4 — done ============ --}}
            <div class="wizard-step" data-step="4">
                <div class="text-center py-8">
                    <i data-lucide="check-circle" class="w-12 h-12 text-emerald-500 mx-auto"></i>
                    <h4 class="text-lg font-black text-slate-800 mt-3">Duplex captured</h4>
                    <p class="text-sm text-slate-500 mt-1" id="dx-done-text"></p>
                    <div id="dx-done-chain" class="mt-6 text-left max-w-xl mx-auto space-y-3"></div>
                    <p class="text-xs text-slate-400 mt-6">
                        Nothing has been commissioned yet. Run KNUPDA, approve, generate the memo and the
                        conveyance, then send it to Land.
                    </p>
                </div>
            </div>
        </div>

        <div class="px-8 py-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/60">
            <button id="dx-back" onclick="wizardBack()"
                class="px-4 py-2 rounded-xl border border-slate-200 text-sm font-semibold text-slate-600 hover:bg-white">
                Back
            </button>
            <div class="flex items-center gap-2">
                <span id="dx-step-label" class="text-xs text-slate-400 font-semibold"></span>
                <button id="dx-next" onclick="wizardNext()"
                    class="px-5 py-2 rounded-xl bg-blue-600 text-white text-sm font-bold hover:bg-blue-700">
                    Start Process
                </button>
            </div>
        </div>
    </div>
</div>

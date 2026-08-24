{{--
    The duplex wizard: select + rank, quantities, then one panel per stage in rank order.

    Nothing in here writes to the registry. Step 3 mints HOLDING numbers only
    (DPX-2026-0007-H03) and the real file numbers appear at the Land step.

    Layout note: step 1 is a two-column card — what the duplex acts ON down the left,
    what will be DONE to it down the right — because those are two different decisions
    and stacking them made the card read as one long undifferentiated form.
--}}
@php
    // Per-type icon + one-line description. Kept here rather than in JS so the markup
    // is server-rendered and the list stays readable.
    $typeMeta = [
        'merger'            => ['combine', 'Several files become one parcel'],
        'subdivision'       => ['split-square-horizontal', 'One parcel becomes several plots'],
        'change_of_purpose' => ['repeat', 'Same parcel, new land use'],
        'extension'         => ['expand', 'Boundary adjusted, one for one'],
        'separation'        => ['scissors', 'Plots separated out of one file'],
    ];
@endphp

<div id="duplex-wizard" class="fixed inset-0 z-[9999] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeDuplexWizard()"></div>

    <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-5xl max-h-[94vh] flex flex-col overflow-hidden border border-slate-200">

        {{-- ===================== header + stepper ===================== --}}
        <div class="px-8 pt-6 pb-0 border-b border-slate-100 bg-gradient-to-b from-slate-50/80 to-white">
            <div class="flex items-start justify-between gap-6">
                <div class="min-w-0">
                    <div class="flex items-center gap-2.5">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-blue-600/10 text-blue-600">
                            <i data-lucide="layers" class="w-4.5 h-4.5"></i>
                        </span>
                        <div>
                            <h3 class="text-lg font-black text-slate-800 leading-tight">New Duplex Parcel Update</h3>
                            <p class="text-xs text-slate-500 mt-0.5" id="wizard-subtitle">
                                Pick the file, then add the updates in the order you want them carried out.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 shrink-0">
                    <span id="dx-duplex-badge"
                          class="hidden holding-no text-xs font-black px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-200"></span>
                    <button onclick="closeDuplexWizard()"
                            class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition">
                        <i data-lucide="x" class="w-4.5 h-4.5"></i>
                    </button>
                </div>
            </div>

            {{-- Stepper. The officer needs to see how far the duplex still has to go,
                 because step 3 can be several panels long. --}}
            <ol id="dx-stepper" class="flex items-center gap-1 mt-5 -mb-px">
                @foreach ([
                    1 => ['Select & rank', 'list-checks'],
                    2 => ['Quantities', 'hash'],
                    3 => ['Stages', 'git-branch'],
                    4 => ['Done', 'check'],
                ] as $n => [$label, $icon])
                <li class="dx-step-tab flex-1" data-tab="{{ $n }}">
                    <button type="button" onclick="goToWizardStep({{ $n }})"
                        class="w-full flex items-center gap-2 px-3 py-2.5 border-b-2 border-transparent">
                        <span class="dx-step-dot w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-black shrink-0">{{ $n }}</span>
                        <span class="dx-step-text text-xs font-bold truncate">{{ $label }}</span>
                    </button>
                </li>
                @endforeach
            </ol>
        </div>

        {{-- ===================== body ===================== --}}
        <div class="flex-1 overflow-y-auto bg-slate-50/40">

            {{-- ============ STEP 1 — select and rank ============ --}}
            <div class="wizard-step active" data-step="1">

                {{-- Shown once the duplex row exists: the plan is fixed from that point,
                     because the stage rows and their holding numbers are built from it. --}}
                <div id="dx-plan-locked" class="hidden mb-5 flex items-start gap-3 p-4 rounded-2xl bg-amber-50 border border-amber-200">
                    <i data-lucide="lock" class="w-4 h-4 text-amber-600 mt-0.5 shrink-0"></i>
                    <p class="text-xs text-amber-800 leading-relaxed">
                        <span class="font-black">This plan is locked.</span>
                        The duplex has been created and its stages are being captured, so the
                        file, the applicant and the order cannot be changed here. Delete the
                        duplex and start again if the plan itself is wrong.
                    </p>
                </div>
                <div class="grid lg:grid-cols-5 gap-0 lg:divide-x divide-slate-100">

                    {{-- LEFT: what the duplex acts on --}}
                    <div class="lg:col-span-2 p-7 space-y-6 bg-white">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 mb-3">Acting on</p>

                            <div class="flex items-center justify-between mb-2">
                                <label class="text-xs font-bold text-slate-600">Source File Number(s) <span class="text-red-500">*</span></label>
                                <button type="button" onclick="pickSourceFile()"
                                    class="text-xs font-bold text-blue-600 hover:text-blue-700 inline-flex items-center gap-1 px-2 py-1 rounded-lg hover:bg-blue-50 transition">
                                    <i data-lucide="plus" class="w-3.5 h-3.5"></i> Add file
                                </button>
                            </div>

                            <div id="dx-sources" class="flex flex-wrap gap-2 p-3 rounded-xl border border-dashed border-slate-300 bg-slate-50/60 min-h-[76px]">
                                <button type="button" onclick="pickSourceFile()"
                                        class="w-full flex flex-col items-center justify-center gap-1.5 py-2 text-slate-400 hover:text-blue-600 transition">
                                    <i data-lucide="file-search" class="w-5 h-5"></i>
                                    <span class="text-xs font-semibold">Choose a file from the registry</span>
                                </button>
                            </div>

                            <p class="text-[11px] text-slate-400 mt-2 leading-relaxed">
                                A Merger consumes several files; every other update starts from one.
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-2">Applicant Name <span class="text-red-500">*</span></label>
                            <input type="text" id="dx-applicant" placeholder="Name as it appears on the file"
                                class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition">
                            <p class="text-[11px] text-slate-400 mt-2">
                                Fills from the first file you pick, and is used as the file title.
                            </p>
                        </div>

                        {{-- Where the parcel is. Carried onto every file the duplex
                             commissions and onto the parcel-update rows it writes, so it is
                             captured once here rather than per stage. --}}
                        <div class="pt-5 border-t border-slate-100">
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 mb-3">Parcel location</p>

                            <div class="grid grid-cols-2 gap-3">
                                <div class="col-span-2">
                                    <label class="block text-[11px] font-bold text-slate-600 mb-1.5">Plot No</label>
                                    <input type="text" id="dx-plot-no-main" placeholder="e.g. 12"
                                        oninput="updateAddressPreview()"
                                        class="w-full px-3 py-2 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition">
                                </div>

                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 mb-1.5">District</label>
                                    <select id="dx-district" onchange="updateAddressPreview()"
                                        class="w-full px-3 py-2 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition">
                                        <option value="">-- Select --</option>
                                        @foreach ($districts as $d)
                                            <option value="{{ $d->name }}">{{ $d->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 mb-1.5">LGA</label>
                                    <select id="dx-lga" onchange="updateAddressPreview()"
                                        class="w-full px-3 py-2 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition">
                                        <option value="">-- Select --</option>
                                        @foreach ($lgas as $l)
                                            <option value="{{ $l->name }}">{{ $l->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            {{-- Built from district + LGA + state, never typed: one location,
                                 assembled the same way every time, is what the printed sheets
                                 read. The plot number is deliberately left out - it belongs to
                                 the parcel, and each subdivided plot carries its own. --}}
                            <div class="mt-3">
                                <label class="block text-[11px] font-bold text-slate-600 mb-1.5">Location</label>
                                <div id="dx-address-preview"
                                     class="px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs font-bold text-slate-600 min-h-[38px] flex items-center">
                                    <span class="text-slate-400 font-normal italic">Fills in from the fields above.</span>
                                </div>
                                <input type="hidden" id="dx-address">
                            </div>
                        </div>

                        {{-- Live read-back of the plan. The order is the whole point of this
                             screen, so it gets its own panel rather than living only in badges. --}}
                        <div class="pt-5 border-t border-slate-100">
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 mb-3">Execution order</p>
                            <div id="dx-order-rail" class="space-y-2">
                                <p class="text-xs text-slate-400 italic">Nothing ticked yet.</p>
                            </div>
                        </div>
                    </div>

                    {{-- RIGHT: what will be done to it --}}
                    <div class="lg:col-span-3 p-7 bg-white">
                        <div class="flex items-baseline justify-between mb-1">
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Select Multiple Parcel Update</p>
                            <span class="text-[11px] font-bold text-slate-400" id="dx-picked-count">0 selected</span>
                        </div>
                        <p class="text-[11px] text-slate-400 mb-4 leading-relaxed">
                            The number beside each tick is the order it will be carried out in — it follows
                            the order you tick, not the order they are listed.
                        </p>

                        {{-- Adding by DROPDOWN, not by ticking down a static list.
                             Ticking left the order badges scattered out of sequence — 3 above
                             2 above 1 — so the list contradicted the order it was recording.
                             The dropdown makes each pick an append, and the rail on the left
                             shows the sequence. --}}
                        <div class="mb-4">
                            <label class="block text-[11px] font-bold text-slate-600 mb-1.5">Add an update</label>
                            <select id="dx-type-picker" onchange="addTypeFromPicker(this)"
                                class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition">
                                <option value="">— Select an update to add —</option>
                            </select>
                            <p class="text-[11px] text-slate-400 mt-1.5">
                                Each one you add is carried out in the order you added it. An update
                                already chosen drops out of this list.
                            </p>
                        </div>

                        {{-- Only what has been ADDED, in the order it was added. Listing all
                             five and marking three of them "not selected" made the order
                             unreadable: the ranks landed in whatever order the types happened
                             to be listed in. What is not chosen lives in the dropdown, not here. --}}
                        <div id="dx-type-list" class="space-y-2.5"></div>

                        <div id="dx-single-note" class="hidden mt-4 flex gap-2.5 text-[11px] text-slate-600 bg-blue-50/70 border border-blue-100 rounded-xl px-3.5 py-3">
                            <i data-lucide="info" class="w-4 h-4 text-blue-500 shrink-0 mt-px"></i>
                            <span>
                                Only one update added — that is fine. It still runs through the duplex
                                pipeline: holding number, one approval, one memo, one commissioning.
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ============ STEP 2 — quantities ============ --}}
            <div class="wizard-step" data-step="2">
                <div class="p-8 max-w-3xl mx-auto">
                    <div class="flex gap-3 mb-6 text-sm text-slate-600 bg-white border border-slate-200 rounded-2xl px-5 py-4">
                        <i data-lucide="hash" class="w-4.5 h-4.5 text-slate-400 shrink-0 mt-0.5"></i>
                        <p class="leading-relaxed">
                            How many of each? The counts are independent — you can subdivide into four and
                            change the purpose of only two.
                        </p>
                    </div>
                    <div id="dx-quantities" class="space-y-3"></div>
                </div>
            </div>

            {{-- ============ STEP 3 — stage runner ============ --}}
            <div class="wizard-step" data-step="3">
                <div class="p-8">
                    <div id="dx-stage-track" class="flex flex-wrap items-center gap-2 mb-6"></div>
                    <div id="dx-stage-panel" class="max-w-4xl"></div>
                </div>
            </div>

            {{-- ============ STEP 4 — done ============ --}}
            <div class="wizard-step" data-step="4">
                <div class="p-8">
                    <div class="max-w-2xl mx-auto text-center">
                        <span class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-emerald-50 border border-emerald-100">
                            <i data-lucide="check" class="w-7 h-7 text-emerald-600"></i>
                        </span>
                        <h4 class="text-xl font-black text-slate-800 mt-4">Duplex captured</h4>
                        <p class="text-sm text-slate-500 mt-1.5" id="dx-done-text"></p>
                    </div>

                    <div id="dx-done-chain" class="mt-7 max-w-2xl mx-auto space-y-3 text-left"></div>
                </div>
            </div>
        </div>

        {{-- ===================== footer ===================== --}}
        <div class="px-8 py-4 border-t border-slate-100 flex items-center justify-between bg-white">
            <button id="dx-back" onclick="wizardBack()"
                class="px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-bold text-slate-600 hover:bg-slate-50 transition inline-flex items-center gap-1.5">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Back
            </button>

            <div class="flex items-center gap-4">
                <span id="dx-step-label" class="text-xs text-slate-400 font-bold"></span>
                <button id="dx-next" onclick="wizardNext()"
                    class="px-6 py-2.5 rounded-xl bg-blue-600 text-white text-sm font-bold hover:bg-blue-700 shadow-sm shadow-blue-600/20 transition inline-flex items-center gap-2">
                    <span id="dx-next-text">Start Process</span>
                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </button>
            </div>
        </div>
    </div>
</div>

{{--
    Existing-allocation capture form.

    The operator picks a file number, the file title and location come back with
    it, and the year is read out of the number itself. Each entry is saved on its
    own over AJAX and appended to the session table below the form, so the form
    stays open for the next one. Closing it clears both the form and that table.
--}}
<div id="aleModal"
    class="dialog-backdrop hidden transition-opacity duration-300 ease-in-out z-50 fixed inset-0 flex items-center justify-center bg-black/50">

    <div class="dialog-content bg-white rounded-xl shadow-2xl w-full flex flex-col m-4 overflow-hidden"
        style="max-width: 1100px; max-height: 92vh;">

        {{-- Header --}}
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-white sticky top-0 z-10">
            <div>
                <h2 id="ale-modal-title" class="text-xl font-bold text-gray-800">Capture Existing Allocation</h2>
                <p class="text-xs text-gray-500 mt-1" id="ale-modal-subtitle">
                    Select a file number — the title, location and year fill in automatically.
                </p>
            </div>
            <button type="button" onclick="aleCloseModal()"
                class="p-2 hover:bg-gray-100 rounded-lg transition-colors text-gray-400 hover:text-gray-600">
                <i data-lucide="x" class="h-6 w-6"></i>
            </button>
        </div>

        {{-- Body --}}
        <div class="flex-1 overflow-auto p-6 bg-gray-50">

            {{-- ── Capture form ──────────────────────────────────────────── --}}
            <form id="ale-form" class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm" novalidate>
                @csrf
                <input type="hidden" id="ale-entry-id" value="">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    {{-- Select FileNo --}}
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">
                            Select FileNo <span class="text-red-500">*</span>
                        </label>
                        <div class="flex gap-2">
                            <input type="text" id="ale-file-no" required autocomplete="off"
                                placeholder="e.g. RES-1982-2081"
                                class="flex-1 min-w-0 px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm uppercase focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all">
                            <button type="button" onclick="aleOpenFileSelector()" title="Browse file numbers"
                                class="shrink-0 px-3 py-2 bg-blue-50 text-blue-700 border border-blue-200 rounded-lg text-sm font-semibold hover:bg-blue-100 transition-all flex items-center gap-1.5">
                                <i data-lucide="search" class="h-4 w-4"></i>
                                Select
                            </button>
                        </div>
                        <p id="ale-file-status" class="text-[11px] mt-1 h-4 text-gray-400"></p>
                    </div>

                    {{-- Name --}}
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">
                            Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="ale-allottee-name" required autocomplete="off"
                            placeholder="Allottee's name"
                            class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm uppercase focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all">
                        <p class="text-[11px] mt-1 h-4 text-gray-400">Name on the existing allocation.</p>
                    </div>

                    {{-- File Title (backfilled from the file number) --}}
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">
                            File Title
                        </label>
                        <input type="text" id="ale-file-title" autocomplete="off"
                            placeholder="Fills in when the file number is selected"
                            class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm uppercase focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all">
                    </div>

                    {{-- Location --}}
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">
                            Location
                        </label>
                        <input type="text" id="ale-location" autocomplete="off"
                            placeholder="District / LGA"
                            class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm uppercase focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all">
                    </div>

                    {{-- Year --}}
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">
                            Year
                            <span class="normal-case font-medium text-gray-400">(auto detected from the FileNo)</span>
                        </label>
                        <input type="text" id="ale-allocation-year" inputmode="numeric" maxlength="4" autocomplete="off"
                            placeholder="—"
                            class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg text-sm font-semibold tracking-wide focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all">
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 mt-5 pt-4 border-t border-gray-100">
                    <button type="button" onclick="aleResetForm()"
                        class="px-4 py-2 text-gray-600 font-medium text-sm bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all">
                        Clear
                    </button>
                    <button type="button" id="ale-save-btn" onclick="aleSubmit()"
                        class="px-7 py-2 text-white font-bold text-sm bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none transition-all shadow-md flex items-center gap-2">
                        <i data-lucide="save" class="h-4 w-4"></i>
                        <span id="ale-save-btn-text">Save Entry</span>
                    </button>
                </div>
            </form>

            {{-- ── Entries captured in this session ──────────────────────── --}}
            <div class="mt-6 bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-gray-700">Captured in this session</h3>
                    <span id="ale-session-count"
                        class="px-2.5 py-0.5 rounded-full bg-blue-50 text-blue-700 text-xs font-bold">0</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-[10px] font-bold text-gray-500 uppercase tracking-wider">
                                <th class="px-4 py-2.5 text-left w-12">SN</th>
                                <th class="px-4 py-2.5 text-left">FileNo</th>
                                <th class="px-4 py-2.5 text-left">File Title</th>
                                <th class="px-4 py-2.5 text-left">Name</th>
                                <th class="px-4 py-2.5 text-left">Location</th>
                                <th class="px-4 py-2.5 text-left w-20">Year</th>
                                <th class="px-4 py-2.5 text-center w-16">Action</th>
                            </tr>
                        </thead>
                        <tbody id="ale-session-body">
                            {{-- Rows appended by aleSubmit() --}}
                        </tbody>
                    </table>
                </div>
                <div id="ale-session-empty" class="px-5 py-8 text-center text-sm text-gray-400">
                    Nothing captured yet — saved entries appear here.
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="px-6 py-4 bg-white border-t border-gray-100 flex items-center justify-between sticky bottom-0 z-10">
            <div class="text-xs text-gray-500 font-medium italic">
                * Required: FileNo and Name. The form stays open until you close it.
            </div>
            <button type="button" onclick="aleCloseModal()"
                class="px-6 py-2 text-gray-700 font-medium bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all shadow-sm">
                Close
            </button>
        </div>

    </div>
</div>

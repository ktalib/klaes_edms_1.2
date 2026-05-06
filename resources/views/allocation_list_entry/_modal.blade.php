<div id="aleModal"
    class="dialog-backdrop hidden transition-opacity duration-300 ease-in-out z-50 fixed inset-0 flex items-center justify-center bg-black/50">
    
    <div class="dialog-content bg-white rounded-xl shadow-2xl w-full flex flex-col m-4 overflow-hidden"
        style="max-width: 1200px; max-height: 90vh;">

        {{-- Header --}}
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-white sticky top-0 z-10">
            <div>
                <h2 id="ale-modal-title" class="text-xl font-bold text-gray-800">Add Allocation Entry</h2>
                <p class="text-xs text-gray-500 mt-1" id="ale-modal-subtitle">Add one or more allocation records below</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="aleCloseModal()"
                    class="p-2 hover:bg-gray-100 rounded-lg transition-colors text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="h-6 w-6"></i>
                </button>
            </div>
        </div>

        {{-- Body --}}
        <div class="flex-1 overflow-auto p-4">
            <form id="ale-form" novalidate>
                @csrf
                <div id="ale-entries-container" class="space-y-4">
                    {{-- Rows will be injected here --}}
                </div>
            </form>
        </div>

        {{-- Footer --}}
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between sticky bottom-0 z-10">
            <div class="text-xs text-gray-500 font-medium italic">
                * Required fields: First Name, Last Name
            </div>
            <div class="flex items-center gap-3">
                <button type="button" onclick="aleCloseModal()"
                    class="px-5 py-2 text-gray-700 font-medium bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all shadow-sm">
                    Cancel
                </button>
                <button type="button" id="ale-save-btn" onclick="aleSubmit()"
                    class="px-8 py-2 text-white font-bold bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none transition-all shadow-md flex items-center gap-2">
                    <i data-lucide="save" class="h-4 w-4"></i>
                    <span id="ale-save-btn-text">Save Entries</span> 
                </button>
            </div>
        </div>

    </div>
</div>

{{-- Row Template --}}
<template id="ale-row-template">
    <div class="ale-row bg-white border border-gray-200 rounded-xl p-4 shadow-sm hover:border-blue-200 transition-all relative group">
        <button type="button" onclick="aleRemoveRow(this)" 
            class="ale-remove-btn absolute -right-2 -top-2 bg-red-100 text-red-600 rounded-full p-1 border border-red-200 opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-200 z-10">
            <i data-lucide="minus" class="h-3 w-3"></i>
        </button>
        
        <div class="grid grid-cols-12 gap-3 items-end">
            {{-- Title --}}
            <div class="col-span-1">
                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Title</label>
                <select name="entries[INDEX][title]" 
                    class="ale-field w-full px-2 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all">
                    <option value="">—</option>
                    @foreach ($titles as $t)
                        <option value="{{ $t->title }}">{{ $t->title }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Names --}}
            <div class="col-span-3">
                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">First Name <span class="text-red-500">*</span></label>
                <input type="text" name="entries[INDEX][first_name]" required
                    class="ale-field w-full px-2 py-1.5 bg-white border border-gray-200 rounded-lg text-sm text-uppercase focus:border-blue-500 outline-none" placeholder="Ali">
            </div>

            <div class="col-span-2">
                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Middle Name</label>
                <input type="text" name="entries[INDEX][middle_name]"
                    class="ale-field w-full px-2 py-1.5 bg-white border border-gray-200 rounded-lg text-sm text-uppercase focus:border-blue-500 outline-none" placeholder="Optional">
            </div>

            <div class="col-span-3">
                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Last Name <span class="text-red-500">*</span></label>
                <input type="text" name="entries[INDEX][last_name]" required
                    class="ale-field w-full px-2 py-1.5 bg-white border border-gray-200 rounded-lg text-sm text-uppercase focus:border-blue-500 outline-none" placeholder="Musa">
            </div>

            <div class="col-span-1 flex justify-center">
                <button type="button" onclick="aleAddRow()" title="Add another row"
                    class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center hover:bg-blue-700 shadow-md transition-all active:scale-95">
                    <i data-lucide="plus-circle" class="h-5 w-5"></i>
                </button>
            </div>
        </div>
    </div>
</template>

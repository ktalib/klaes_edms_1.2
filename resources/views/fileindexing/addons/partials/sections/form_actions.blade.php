<div class="sticky bottom-0 bg-white/95 backdrop-blur-sm px-8 py-6 -mx-8 mt-12 border-t border-gray-100 z-20 shadow-[0_-10px_15px_-3px_rgba(0,0,0,0.05)]">
    <div class="flex justify-between items-center gap-4">
        <button type="button" 
            class="inline-flex items-center px-8 py-3 border border-gray-300 text-sm font-medium rounded-lg shadow-sm text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200" 
            id="cancel-btn">
            <i data-lucide="x-circle" class="h-4 w-4 mr-2"></i>
            Cancel
        </button>
        <button
            type="button"
            class="inline-flex items-center px-8 py-3 border border-transparent text-sm font-medium rounded-lg shadow-lg text-white bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200 transform hover:scale-105"
            id="create-file-btn"
            data-default-label="Create File Index"
            data-edit-label="Update File Index"
            data-mode="create"
        >
            <i data-lucide="save" class="h-4 w-4 mr-2"></i>
            <span data-state="create">Create File Index</span>
            <span data-state="edit" class="hidden">Update File Index</span>
        </button>
    </div>
</div>

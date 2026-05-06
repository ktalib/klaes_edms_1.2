<div class="dialog-overlay hidden" id="new-file-dialog-overlay">
    <div class="dialog">
        <div class="dialog-header">
            <div class="dialog-title">
                <i data-lucide="file-plus" class="h-5 w-5"></i>
                Create New File Index
            </div>
            <button id="close-dialog-btn" class="text-white" style="background: none; border: none; cursor: pointer;">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>
        <div class="dialog-description">
            Enter the details for the new file to be indexed
        </div>
        <div class="dialog-content">
            <form
                id="new-file-form"
                data-default-indexer="{{ Auth::user()->name ?? 'Current User' }}"
                data-check-indexed-url="{{ route('fileindex.check-indexed') }}"
                data-indexed-view-url-template="{{ route('fileindexing.show', ['id' => '__ID__']) }}"
                data-transactions-url-template="{{ route('fileindexing.transactions', ['fileIndexing' => '__ID__']) }}"
            >
                @include('fileindexing.addons.partials.sections.file_identification')
                @include('fileindexing.addons.partials.sections.property_details')
                @include('fileindexing.addons.partials.sections.entity_customer')
                @include('fileindexing.addons.partials.sections.auto_assignment')
                @include('fileindexing.addons.partials.sections.file_flags')
                @include('fileindexing.addons.partials.sections.file_archive_details')
                @include('fileindexing.addons.partials.sections.cofo_details')
                @include('fileindexing.addons.partials.sections.form_actions')
            </form>
        </div>
    </div>
</div>

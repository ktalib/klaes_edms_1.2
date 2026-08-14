<header class="bg-white border-b">
    <div class="container mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">{{ $PageTitle ?? 'File Digital Archive' }}</h1>
                <p class="text-muted-foreground">{{ $PageDescription ?? 'Access and manage digitally archived files' }}</p>
            </div>
            <div class="flex gap-2">
                {{-- Standalone entry point: pick any file, move it between registries --}}
                <button type="button" class="btn btn-outline gap-2"
                        title="Move a file's documents to another registry"
                        onclick="EdmsRegistryTransfer.open(null, null, () => window.location.reload())">
                    <i data-lucide="folder-symlink" class="h-4 w-4"></i>
                    Move Registry
                </button>
                <button id="export-button" class="btn btn-outline gap-2">
                    <i data-lucide="download" class="h-4 w-4"></i>
                    Export
                </button>
                 
            </div>
        </div>
    </div>
</header>

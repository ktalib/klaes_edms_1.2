@extends('layouts.app')
@section('page-title')
    {{ __($PageTitle ?? 'File Digital Archive') }}
@endsection

@section('content')
    @include('filearchive.assets.style')
    <!-- Main Content -->
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        
        @if(isset($moduleTheme) && $moduleTheme)
        <div class="{{ $moduleTheme['bg'] }} px-6 py-3 flex items-center gap-3 shadow-sm">
            <i data-lucide="{{ $moduleTheme['icon'] }}" class="h-5 w-5 text-white shrink-0"></i>
            <div class="flex items-center gap-2">
                <span class="text-white font-bold text-sm uppercase tracking-widest">{{ $moduleTheme['title'] }}</span>
                <span class="{{ $moduleTheme['text_muted'] }} text-sm">&middot;</span>
                <span class="text-white text-sm font-medium">Digital Archive</span>
                <span class="{{ $moduleTheme['text_muted'] }} text-sm">&middot;</span>
                <span class="{{ $moduleTheme['text_muted'] }} text-sm">File Digital Library &ndash; Doc-WARE</span>
            </div>
        </div>
        @endif
        <!-- Dashboard Content -->
        <div class="p-6">
            <div class="flex flex-col min-h-screen">
                <!-- Page Header -->
                @include('filearchive.partials.header')

                <div class="flex-1 p-4 sm:p-6 container mx-auto space-y-6">
                    <!-- Stats Cards -->
                    @include('filearchive.partials.stats')

                    <!-- Search Card -->
                    @include('filearchive.partials.search')

                    <!-- Files Display Area -->
                    @include('filearchive.partials.files_grid')
                </div>
            </div>

            <!-- File Details Dialog -->
            @include('filearchive.partials.file_details_modal')

            <!-- Document Viewer Dialog -->
            @include('filearchive.partials.document_viewer_modal')
        </div>
        <!-- Footer -->
        @include('admin.footer')
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Scripts --> 
    @include('filearchive.assets.js')
@endsection

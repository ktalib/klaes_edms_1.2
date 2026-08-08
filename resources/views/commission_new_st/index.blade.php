@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? 'Commission New ST' }}
@endsection

{{-- Include CSS Assets --}}
@include('sectionaltitling.partials.assets.css')
@include('primaryform.assets.css.styles')

{{-- External Libraries --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://unpkg.com/htmx.org@1.9.10"></script>
<link rel="stylesheet" href="{{ asset('css/global-fileno-modal.css') }}">

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/commission_new_st/main.css') }}">
@endpush

@section('content')
    <script src="{{ asset('js/primaryform/states-lga.js') }}"></script>
    {{-- Shared "where did this record go" card, also used after file indexing and
         property transaction capture. Must load before sua_commission.js, which
         wraps it as showStCommissioningCard(). --}}
    <script src="{{ asset('js/shared/record-summary-card.js') }}?v={{ @filemtime(public_path('js/shared/record-summary-card.js')) }}"></script>
    <script src="{{ asset('js/commission_new_st/location-map.js') }}"></script>
    <script src="{{ asset('js/commission_new_st/applicant-autofill.js') }}"></script>
    <script src="{{ asset('js/commission_new_st/primary.js') }}"></script>
    <script src="{{ asset('js/commission_new_st/sua_commission.js') }}"></script>
    <script src="{{ asset('js/commission_new_st/pua.js') }}"></script>
    <script src="{{ asset('js/commission_new_st/file-modal-integration.js') }}"></script>
    <script src="{{ asset('js/commission_new_st/page-init.js') }}"></script>





   <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6">

        <!-- ST File Number Commissioning Navigation -->
        <div class="mb-6" x-data="{ activeTab: 'primary' }">
            <div class="border-b border-gray-200 bg-white rounded-t-lg">
            <nav class="flex space-x-2 px-6 py-5" aria-label="ST Workflow Tabs">
                <button 
                @click="activeTab = 'primary'"
                :class="activeTab === 'primary' 
                    ? 'bg-blue-600 text-white shadow-md border-blue-600' 
                    : 'bg-white text-gray-700 hover:bg-blue-50 hover:text-blue-700 border-gray-300'"
                class="inline-flex items-center px-6 py-3 text-base font-medium rounded-lg border-2 transition-all duration-200 min-w-[120px] justify-center"
                >
                <i class="fas fa-file-contract w-5 h-5 mr-3"></i>
                Primary
                </button>
                   <button 
                @click="activeTab = 'pua'; $nextTick(() => window.STLocationMaps?.pua?.refresh())"
                :class="activeTab === 'pua' 
                    ? 'bg-purple-600 text-white shadow-md border-purple-600' 
                    : 'bg-white text-gray-700 hover:bg-purple-50 hover:text-purple-700 border-gray-300'"
                class="inline-flex items-center px-6 py-3 text-base font-medium rounded-lg border-2 transition-all duration-200 min-w-[120px] justify-center"
                >
                <i class="fas fa-users w-5 h-5 mr-3"></i>
                PuA
                </button>
                <button 
                @click="activeTab = 'sua'; $nextTick(() => window.STLocationMaps?.sua?.refresh())"
                :class="activeTab === 'sua' 
                    ? 'bg-green-600 text-white shadow-md border-green-600' 
                    : 'bg-white text-gray-700 hover:bg-green-50 hover:text-green-700 border-gray-300'"
                class="inline-flex items-center px-6 py-3 text-base font-medium rounded-lg border-2 transition-all duration-200 min-w-[120px] justify-center"
                >
                <i class="fas fa-building w-5 h-5 mr-3"></i>
                SuA
                </button>
                
             
            </nav>
        </div>
        
        <!-- Tabs Container -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">

            <!-- Tab Content -->
            <div class="p-6">
                <!-- Primary Tab -->
                <div x-show="activeTab === 'primary'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform translate-y-4" x-transition:enter-end="opacity-100 transform translate-y-0">
                    @include('commission_new_st.partials.primary')
                </div>
      <!-- PuA Tab -->
                <div x-show="activeTab === 'pua'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform translate-y-4" x-transition:enter-end="opacity-100 transform translate-y-0">
                    @include('commission_new_st.partials.pua')
                </div>

                
                <!-- SuA Tab-->
                <div x-show="activeTab === 'sua'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform translate-y-4" x-transition:enter-end="opacity-100 transform translate-y-0">
                    @include('commission_new_st.partials.sua')
                </div>

          
            </div>
        </div>

     
    </div>
</div>

{{-- Include Global File Number Modal Component (also loads Select2) --}}
@include('components.global-fileno-modal')

{{-- Searchable District / Street Name dropdowns --}}
@include('components.reference-select')

{{-- JavaScript Assets --}}
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script src="{{ asset('js/primaryform/init.js') }}"></script>

{{-- Google Maps — powers the Property Location Map in Location Details --}}
<script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}" defer></script>

{{-- CSRF Token for JavaScript --}}
<script>
// Set CSRF token for AJAX requests
document.addEventListener('DOMContentLoaded', function() {
    // Add CSRF token meta tag for JavaScript access
    if (!document.querySelector('meta[name="csrf-token"]')) {
        const csrfMeta = document.createElement('meta');
        csrfMeta.name = 'csrf-token';
        csrfMeta.content = '{{ csrf_token() }}';
        document.head.appendChild(csrfMeta);
    }
});
</script>
@endsection
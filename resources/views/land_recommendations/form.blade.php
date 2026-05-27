@extends('layouts.app')

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header')
    
    <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
            <!-- Header -->
            <div class="bg-slate-50 px-8 py-6 flex justify-between items-center border-b border-slate-200">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Recommendation for Grant of Change of Purpose</h1>
                    <p class="text-slate-500 text-sm mt-1">Data entry form</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('land-recommendations.index') }}?type=ROFO" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 rounded-lg transition border border-slate-200 shadow-sm">
                        View Records
                    </a>
                </div>
            </div>

            <form id="land-recommendation-form" action="{{ isset($recommendation) ? route('land-recommendations.update', $recommendation->id) : route('land-recommendations.store') }}" method="POST" class="p-8 space-y-8">
                @csrf
                @if(isset($recommendation))
                    @method('PUT')
                @endif

                @if(request('edit_reason'))
                    <div class="bg-amber-50 border-l-4 border-amber-400 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i data-lucide="alert-circle" class="h-5 w-5 text-amber-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-amber-700">
                                    <span class="font-bold">Reason for Edit:</span> 
                                    {{ request('edit_reason') }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="edit_reason" value="{{ request('edit_reason') }}">
                @elseif(isset($recommendation) && $recommendation->edit_reason)
                    <div class="bg-slate-50 border-l-4 border-slate-300 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i data-lucide="history" class="h-5 w-5 text-slate-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-slate-600">
                                    <span class="font-bold">Last Edit Reason:</span> 
                                    {{ $recommendation->edit_reason }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- File Number Selector -->
                <div class="bg-blue-50/50 rounded-xl p-6 border border-blue-100/50">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="flex-1">
                            <label class="block text-xs font-bold text-blue-700 uppercase tracking-wider mb-2">Selected File Number</label>
                            <input type="text" name="file_number" id="file_number" readonly required
                                value="{{ old('file_number', $recommendation->file_number ?? '') }}"
                                placeholder="NO FILE SELECTED"
                                class="w-full bg-white border border-blue-200 rounded-lg px-4 py-3 text-slate-900 font-bold font-mono placeholder:text-slate-400 text-lg shadow-sm outline-none focus:ring-2 focus:ring-blue-500 transition">
                            <input type="hidden" name="tracking_id" id="tracking_id" value="{{ old('tracking_id', $recommendation->tracking_id ?? '') }}">
                        </div>
                        <div class="flex flex-shrink-0 items-end">
                            <button type="button" id="select-fileno-btn"
                                class="px-6 py-3 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700 transition flex items-center gap-2 shadow-lg shadow-blue-200">
                                <i data-lucide="search" class="h-5 w-5"></i>
                                Select File Number
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Form Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @php
                        $savedAppType = old('application_type', $recommendation->application_type ?? '');
                        $hasAppType   = $savedAppType !== '';
                        $savedRecType = old('type', $recommendation->type ?? 'Direct');
                    @endphp

                    <!-- Recommendation Type Selection -->
                    <div id="recommendation-type-block" class="bg-blue-50/30 border border-blue-100 rounded-xl p-6 col-span-2 transition-opacity {{ $hasAppType ? 'opacity-40 pointer-events-none' : '' }}">
                        <label class="block text-xs font-bold text-blue-700 uppercase tracking-wider mb-3">Recommendation Type</label>
                        <div class="flex flex-col sm:flex-row gap-4">
                            <label class="flex items-center gap-3 cursor-pointer p-4 bg-white border border-blue-200 rounded-xl hover:border-blue-500 transition shadow-sm flex-1 group">
                                <input type="radio" id="rec-direct" name="type" value="Direct"
                                    {{ $savedRecType == 'Direct' && !$hasAppType ? 'checked' : '' }}
                                    {{ $hasAppType ? 'disabled' : '' }}
                                    class="w-5 h-5 text-blue-600 focus:ring-blue-500 border-slate-300">
                                <div>
                                    <span class="block text-sm font-bold text-slate-900 group-hover:text-blue-700 transition">Direct</span>
                                </div>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer p-4 bg-white border border-blue-200 rounded-xl hover:border-amber-500 transition shadow-sm flex-1 group">
                                <input type="radio" id="rec-conversion" name="type" value="Conversion"
                                    {{ $savedRecType == 'Conversion' && !$hasAppType ? 'checked' : '' }}
                                    {{ $hasAppType ? 'disabled' : '' }}
                                    class="w-5 h-5 text-amber-600 focus:ring-amber-500 border-slate-300">
                                <div>
                                    <span class="block text-sm font-bold text-slate-900 group-hover:text-amber-700 transition">Conversion</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Application Type -->
                    <div class="bg-slate-50/60 border border-slate-100 rounded-xl p-6 col-span-2">
                        <div class="flex items-center justify-between mb-3">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider">Application Type</label>
                            <!-- Toggle switch -->
                            <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                                <span class="text-xs text-slate-500 font-medium">Enable</span>
                                <div class="relative">
                                    <input type="checkbox" id="app-type-toggle" class="sr-only peer" {{ $hasAppType ? 'checked' : '' }}>
                                    <div class="w-9 h-5 bg-slate-300 peer-checked:bg-blue-600 rounded-full transition-colors"></div>
                                    <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform peer-checked:translate-x-4"></div>
                                </div>
                            </label>
                        </div>
                        <input type="hidden" id="application-type-hidden" name="application_type" value="">
                        <div id="application-type-panel" class="{{ $hasAppType ? '' : 'hidden' }}">
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                @foreach([
                                    'Private Layout',
                                    'Plot Subdivision',
                                    'Plot Extension',
                                    'Temporary File No',
                                    'Ministry of Works',
                                    'Change of Purpose',
                                ] as $appType)
                                <label class="flex items-center gap-3 cursor-pointer p-3 bg-white border border-slate-200 rounded-xl hover:border-blue-400 transition shadow-sm group">
                                    <input type="radio" name="application_type_radio" value="{{ $appType }}"
                                        {{ $savedAppType === $appType ? 'checked' : '' }}
                                        class="app-type-radio w-4 h-4 text-blue-600 focus:ring-blue-500 border-slate-300">
                                    <span class="text-sm font-medium text-slate-700 group-hover:text-blue-700 transition leading-tight">{{ $appType }}</span>
                                </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <script>
                    (function () {
                        const toggle   = document.getElementById('app-type-toggle');
                        const panel    = document.getElementById('application-type-panel');
                        const hidden   = document.getElementById('application-type-hidden');
                        const recBlock = document.getElementById('recommendation-type-block');
                        const recDirect = document.getElementById('rec-direct');
                        const recConv   = document.getElementById('rec-conversion');
                        const appRadios = document.querySelectorAll('.app-type-radio');

                        function applyState(enabled) {
                            if (enabled) {
                                panel.classList.remove('hidden');
                                recBlock.classList.add('opacity-40', 'pointer-events-none');
                                recDirect.checked = false;
                                recConv.checked   = false;
                                recDirect.disabled = true;
                                recConv.disabled   = true;
                                const checked = document.querySelector('.app-type-radio:checked');
                                hidden.value = checked ? checked.value : '';
                            } else {
                                panel.classList.add('hidden');
                                recBlock.classList.remove('opacity-40', 'pointer-events-none');
                                recDirect.disabled = false;
                                recConv.disabled   = false;
                                recDirect.checked  = true;
                                appRadios.forEach(r => r.checked = false);
                                hidden.value = '';
                            }
                        }

                        toggle.addEventListener('change', () => applyState(toggle.checked));

                        appRadios.forEach(r => r.addEventListener('change', () => {
                            hidden.value = r.value;
                        }));

                        // Sync hidden field on load if toggle is already on
                        if (toggle.checked) {
                            const checked = document.querySelector('.app-type-radio:checked');
                            hidden.value = checked ? checked.value : '';
                        }
                    })();
                    </script>

                    {{-- ── Application Type: Conditional Extra Fields ── --}}
                    @php $savedAppType = old('application_type', $recommendation->application_type ?? ''); @endphp
                    <div id="app-type-extra" class="{{ $savedAppType ? '' : 'hidden' }} col-span-2 bg-indigo-50/30 border border-indigo-100 rounded-xl p-6 space-y-5">
                        <div class="flex items-center gap-2 mb-1">
                            <i data-lucide="settings-2" class="h-4 w-4 text-indigo-600"></i>
                            <h3 class="text-sm font-bold text-indigo-900 uppercase tracking-tight">
                                Additional Fields &mdash; <span id="app-type-extra-label" class="text-indigo-600 normal-case font-semibold">{{ $savedAppType }}</span>
                            </h3>
                        </div>

                        {{-- Page No. (common to all application types) --}}
                        <div class="grid grid-cols-4 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Page No.</label>
                                <input type="number" name="page" id="atx_page" min="1"
                                    value="{{ old('page', $recommendation->page ?? '') }}"
                                    placeholder="e.g. 4"
                                    class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                            </div>
                        </div>

                        {{-- PANEL: Private Layout (with No. count) --}}
                        <div id="atx-panel-private-layout" class="atx-panel {{ $savedAppType === 'Private Layout' ? '' : 'hidden' }} space-y-4">
                            <div class="grid grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Number of Plots / Portions</label>
                                    <input type="number" name="num_plots" id="num_plots" min="1"
                                        value="{{ old('num_plots', $recommendation->num_plots ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Auth. Memo Page</label>
                                    <input type="number" name="page_2" min="1"
                                        value="{{ old('page_2', $recommendation->page_2 ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Site Plan Page</label>
                                    <input type="number" name="page_3" min="1"
                                        value="{{ old('page_3', $recommendation->page_3 ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-2">Plot Dimensions <span class="text-slate-400 normal-case font-normal">(Length × Width — No.)</span></label>
                                <div id="plot-sizes-rows-pl" class="space-y-2 mb-2"></div>
                                <button type="button" onclick="addPlotSizeRow('pl', null, true)"
                                    class="px-4 py-1.5 text-xs font-semibold bg-indigo-100 text-indigo-700 hover:bg-indigo-200 rounded-lg transition">
                                    + Add Dimension Row
                                </button>
                            </div>
                        </div>

                        {{-- PANEL: Plot Subdivision (no count) --}}
                        <div id="atx-panel-subdivision" class="atx-panel {{ $savedAppType === 'Plot Subdivision' ? '' : 'hidden' }} space-y-4">
                            <div class="grid grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Number of Portions</label>
                                    <input type="number" name="num_plots" min="1"
                                        value="{{ old('num_plots', $recommendation->num_plots ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Auth. Memo Page</label>
                                    <input type="number" name="page_2" min="1"
                                        value="{{ old('page_2', $recommendation->page_2 ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Site Plan Page</label>
                                    <input type="number" name="page_3" min="1"
                                        value="{{ old('page_3', $recommendation->page_3 ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-2">Plot Dimensions <span class="text-slate-400 normal-case font-normal">(Length × Width)</span></label>
                                <div id="plot-sizes-rows-sub" class="space-y-2 mb-2"></div>
                                <button type="button" onclick="addPlotSizeRow('sub', null, false)"
                                    class="px-4 py-1.5 text-xs font-semibold bg-indigo-100 text-indigo-700 hover:bg-indigo-200 rounded-lg transition">
                                    + Add Dimension Row
                                </button>
                            </div>
                        </div>

                        {{-- PANEL: Plot Extension (no count) --}}
                        <div id="atx-panel-extension" class="atx-panel {{ $savedAppType === 'Plot Extension' ? '' : 'hidden' }} space-y-4">
                            <div class="grid grid-cols-4 gap-4 mb-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Planning Views Page</label>
                                    <input type="number" name="page_2" min="1"
                                        value="{{ old('page_2', $recommendation->page_2 ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">KNUPDA Page</label>
                                    <input type="number" name="page_3" min="1"
                                        value="{{ old('page_3', $recommendation->page_3 ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Site Plan Page</label>
                                    <input type="number" name="page_4" min="1"
                                        value="{{ old('page_4', $recommendation->page_4 ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-2">Plot Dimensions <span class="text-slate-400 normal-case font-normal">(Length × Width)</span></label>
                                <div id="plot-sizes-rows-ext" class="space-y-2 mb-2"></div>
                                <button type="button" onclick="addPlotSizeRow('ext', null, false)"
                                    class="px-4 py-1.5 text-xs font-semibold bg-indigo-100 text-indigo-700 hover:bg-indigo-200 rounded-lg transition">
                                    + Add Dimension Row
                                </button>
                            </div>
                        </div>

                        {{-- PANEL: Temporary File No --}}
                        <div id="atx-panel-temp" class="atx-panel {{ $savedAppType === 'Temporary File No' ? '' : 'hidden' }}"></div>

                        {{-- PANEL: Ministry of Works (Premium / Purchase Price) --}}
                        <div id="atx-panel-premium" class="atx-panel {{ $savedAppType === 'Ministry of Works' ? '' : 'hidden' }} space-y-4">
                            <div class="grid grid-cols-4 gap-4 mb-2">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Clearance Letter Page</label>
                                    <input type="number" name="page_2" min="1"
                                        value="{{ old('page_2', $recommendation->page_2 ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Receipt Page</label>
                                    <input type="number" name="page_3" min="1"
                                        value="{{ old('page_3', $recommendation->page_3 ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Premium / Purchase Price (&#x20A6;)</label>
                                    <input type="number" step="0.01" name="premium" id="premium"
                                        value="{{ old('premium', $recommendation->premium ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Premium in Words <span class="text-slate-400 normal-case font-normal">(auto)</span></label>
                                    <input type="text" name="premium_words" id="premium_words"
                                        value="{{ old('premium_words', $recommendation->premium_words ?? '') }}"
                                        class="w-full border border-blue-100 bg-blue-50 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition shadow-sm"
                                        placeholder="Auto-filled from amount above">
                                </div>
                            </div>
                        </div>

                        {{-- PANEL: Change of Purpose --}}
                        <div id="atx-panel-change-of-purpose" class="atx-panel {{ $savedAppType === 'Change of Purpose' ? '' : 'hidden' }} space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="col-span-2">
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Purpose Description <span class="text-slate-400 normal-case font-normal">(e.g. Commercial (Warehouse))</span></label>
                                    <input type="text" name="purpose_description" id="purpose_description"
                                        value="{{ old('purpose_description', $recommendation->purpose_description ?? '') }}"
                                        placeholder="e.g. Commercial (Warehouse)"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Planning Dept Page</label>
                                    <input type="number" name="page_2" id="cop_page_2" min="1"
                                        value="{{ old('page_2', $recommendation->page_2 ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Conditions Page</label>
                                    <input type="number" name="page_3" id="cop_page_3" min="1"
                                        value="{{ old('page_3', $recommendation->page_3 ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Acceptance Letter Page</label>
                                    <input type="number" name="page_4" id="cop_page_4" min="1"
                                        value="{{ old('page_4', $recommendation->page_4 ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Site Plan Page</label>
                                    <input type="number" name="page_5" id="cop_page_5" min="1"
                                        value="{{ old('page_5', $recommendation->page_5 ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Plot Dimensions <span class="text-slate-400 normal-case font-normal">(polygon measurements)</span></label>
                                    <textarea name="dimensions_text" rows="2"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm"
                                        placeholder="e.g. 307m x 65.92 x 106.72m ... = 39,591m²/3.9591ha">{{ old('dimensions_text', $recommendation->dimensions_text ?? '') }}</textarea>
                                </div>
                            </div>
                        </div>

                        {{-- Hidden: serialized plot sizes (written by JS on submit) --}}
                        <input type="hidden" name="plot_sizes" id="plot_sizes_json"
                            value="{{ old('plot_sizes', $recommendation->plot_sizes ?? '') }}">
                    </div>

                    <script>
                    // Panel show/hide wired to application type radios
                    (function () {
                        var panelMap = {
                            'Private Layout':               'atx-panel-private-layout',
                            'Plot Subdivision':             'atx-panel-subdivision',
                            'Plot Extension':               'atx-panel-extension',
                            'Temporary File No':            'atx-panel-temp',
                            'Ministry of Works':            'atx-panel-premium',
                            'Change of Purpose':            'atx-panel-change-of-purpose',
                        };

                        var extraContainer = document.getElementById('app-type-extra');
                        var extraLabel     = document.getElementById('app-type-extra-label');

                        function showExtraPanel(appType) {
                            document.querySelectorAll('.atx-panel').forEach(function (p) { p.classList.add('hidden'); });
                            var panelId = panelMap[appType];
                            if (panelId) {
                                document.getElementById(panelId).classList.remove('hidden');
                                extraContainer.classList.remove('hidden');
                                if (extraLabel) extraLabel.textContent = appType;
                            } else {
                                extraContainer.classList.add('hidden');
                            }
                        }

                        function hideExtra() {
                            extraContainer.classList.add('hidden');
                            document.querySelectorAll('.atx-panel').forEach(function (p) { p.classList.add('hidden'); });
                        }

                        window._showExtraPanel = showExtraPanel;
                        window._hideExtraPanel  = hideExtra;

                        document.querySelectorAll('.app-type-radio').forEach(function (r) {
                            r.addEventListener('change', function () {
                                if (this.checked) showExtraPanel(this.value);
                            });
                        });

                        document.getElementById('app-type-toggle').addEventListener('change', function () {
                            if (!this.checked) hideExtra();
                        });
                    })();

                    // Global helpers for plot size rows (called via onclick)
                    // showCount=true → Private Layout (has No. column); false → Subdivision/Extension
                    function addPlotSizeRow(suffix, data, showCount) {
                        if (showCount === undefined) showCount = true;
                        var container = document.getElementById('plot-sizes-rows-' + suffix);
                        if (!container) return;
                        var idx = container.children.length;
                        var labels = ['i.','ii.','iii.','iv.','v.','vi.','vii.','viii.'];
                        var lbl = labels[idx] !== undefined ? labels[idx] : (idx + 1) + '.';
                        var row = document.createElement('div');
                        row.className = 'plot-size-row flex items-center gap-2 flex-wrap';
                        var countHtml = showCount
                            ? '<input type="text" placeholder="No." class="plot-count w-20 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:border-indigo-400 outline-none bg-white">' +
                              '<span class="text-slate-500 text-sm shrink-0">No.</span>'
                            : '<input type="hidden" class="plot-count" value="">';
                        row.innerHTML =
                            '<span class="text-xs font-semibold text-slate-500 w-6 shrink-0">' + lbl + '</span>' +
                            '<input type="text" placeholder="Length" class="plot-length w-24 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:border-indigo-400 outline-none bg-white">' +
                            '<span class="text-slate-500 text-sm shrink-0">m \xd7</span>' +
                            '<input type="text" placeholder="Width" class="plot-width w-24 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:border-indigo-400 outline-none bg-white">' +
                            '<span class="text-slate-500 text-sm shrink-0">m</span>' +
                            countHtml +
                            '<button type="button" onclick="removePlotSizeRow(this)" class="text-red-400 hover:text-red-600 text-sm px-2 shrink-0">\xd7</button>';
                        if (data) {
                            row.querySelector('.plot-length').value = data.length || '';
                            row.querySelector('.plot-width').value  = data.width  || '';
                            var cEl = row.querySelector('.plot-count');
                            if (cEl) cEl.value = data.count || '';
                        }
                        container.appendChild(row);
                    }

                    function removePlotSizeRow(btn) {
                        var row = btn.closest('.plot-size-row');
                        if (row) row.remove();
                    }
                    </script>

                    <!-- Section 1: Applicant & Property (Template a-e) -->
                    <div class="bg-slate-50 border border-slate-100 rounded-xl p-6 space-y-4">
                        <div class="flex items-center gap-2 mb-2">
                            <i data-lucide="user" class="h-4 w-4 text-blue-600"></i>
                            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-tight">Applicant & Property</h3>
                        </div>
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Name of Applicant</label>
                                    <input type="text" name="applicant_name" id="applicant_name" value="{{ old('applicant_name', $recommendation->applicant_name ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Application Date</label>
                                    <input type="date" name="application_date" id="application_date" value="{{ old('application_date', (isset($recommendation) && $recommendation->application_date) ? $recommendation->application_date->format('Y-m-d') : '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Applicant Address</label>
                                <input type="text" name="applicant_address" id="applicant_address" value="{{ old('applicant_address', $recommendation->applicant_address ?? '') }}"
                                    class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                       <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Land Use</label>
                                    <select name="land_use_id" id="land_use_id" required
                                        class="w-full border @error('land_use_id') border-red-500 @else border-slate-200 @enderror rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm text-slate-900">
                                        <option value="">Select Land Use</option>
                                        @if(isset($landUses))
                                            @foreach($landUses as $lu)
                                                <option value="{{ $lu->id }}" {{ (old('land_use_id', $recommendation->land_use_id ?? '') == $lu->id) ? 'selected' : '' }}>
                                                    {{ $lu->landuse }}
                                                </option>
                                            @endforeach
                                        @endif
                                    </select>
                                    @error('land_use_id')
                                        <p class="text-red-500 text-[10px] mt-1 font-semibold uppercase">{{ $message }}</p>
                                    @enderror
                                    <input type="hidden" name="land_use" id="land_use_text" value="{{ old('land_use', $recommendation->land_use ?? '') }}">
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">(b) Purpose Clause</label>
                                    <select name="purpose_id" id="purpose_id" required
                                        data-selected="{{ old('purpose_id', $recommendation->purpose_id ?? '') }}"
                                        class="w-full border @error('purpose_id') border-red-500 @else border-slate-200 @enderror rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                        <option value="">Select Purpose</option>
                                        @if(isset($purposes))
                                            @foreach($purposes as $p)
                                                <option value="{{ $p->id }}" {{ (old('purpose_id', $recommendation->purpose_id ?? '') == $p->id) ? 'selected' : '' }}>
                                                    {{ $p->name }}
                                                </option>
                                            @endforeach
                                        @endif
                                    </select>
                                    @error('purpose_id')
                                        <p class="text-red-500 text-[10px] mt-1 font-semibold uppercase">{{ $message }}</p>
                                    @enderror
                                    <input type="hidden" name="purpose_of_clause" id="purpose_of_clause_text" value="{{ old('purpose_of_clause', $recommendation->purpose_of_clause ?? '') }}">
                                </div>
                            </div>
                            {{-- TP No. — placed here after Land Use / Purpose --}}
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">TP No.</label>
                                    <select name="layout_plan_no" id="layout_plan_no" style="width:100%;">
                                        @php $existingTp = old('layout_plan_no', $recommendation->layout_plan_no ?? ''); @endphp
                                        @if($existingTp)
                                            <option value="{{ $existingTp }}" selected>{{ $existingTp }}</option>
                                        @endif
                                    </select>
                                </div>
                            </div>
                            {{-- Location structured fields --}}
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">House No</label>
                                    <input type="text" name="house_no" id="house_no" value="{{ old('house_no', $recommendation->house_no ?? '') }}"
                                        class="loc-part w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm" placeholder="e.g. 15">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Plot No</label>
                                    <input type="text" name="plot_number" id="plot_number" value="{{ old('plot_number', $recommendation->plot_number ?? '') }}"
                                        class="loc-part w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm" placeholder="e.g. 1002">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Street Name</label>
                                    @php $existingStreet = old('street_name', $recommendation->street_name ?? ''); @endphp
                                    <input type="hidden" name="street_name" id="street_name" value="{{ $existingStreet }}">
                                    <select id="street_name_select" style="width:100%;">
                                        @if($existingStreet)
                                            <option value="{{ $existingStreet }}" selected>{{ $existingStreet }}</option>
                                        @endif
                                    </select>
                                    <input type="text" id="street_name_other" placeholder="Specify street name..."
                                        class="mt-2 w-full border border-amber-300 rounded-lg px-3 py-2 text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500 outline-none transition bg-amber-50" style="display:none;">
                                </div>
                            </div>
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">District</label>
                                    @php $existingDistrict = old('district', $recommendation->district ?? ''); @endphp
                                    <input type="hidden" name="district" id="district" value="{{ $existingDistrict }}">
                                    <select id="district_select" style="width:100%;">
                                        @if($existingDistrict)
                                            <option value="{{ $existingDistrict }}" selected>{{ $existingDistrict }}</option>
                                        @endif
                                    </select>
                                    <input type="text" id="district_other" placeholder="Specify district..."
                                        class="mt-2 w-full border border-amber-300 rounded-lg px-3 py-2 text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500 outline-none transition bg-amber-50" style="display:none;">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">LGA</label>
                                    @php $existingLga = old('lga', $recommendation->lga ?? ''); @endphp
                                    <input type="hidden" name="lga" id="lga" value="{{ $existingLga }}">
                                    <select id="lga_select" style="width:100%;">
                                        @if($existingLga)
                                            <option value="{{ $existingLga }}" selected>{{ $existingLga }}</option>
                                        @endif
                                    </select>
                                    <input type="text" id="lga_other" placeholder="Specify LGA..."
                                        class="mt-2 w-full border border-amber-300 rounded-lg px-3 py-2 text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500 outline-none transition bg-amber-50" style="display:none;">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">State</label>
                                    <input type="hidden" name="state" value="{{ old('state', $recommendation->state ?? 'Kano State') }}">
                                    <input type="text" id="state" value="{{ old('state', $recommendation->state ?? 'Kano State') }}"
                                        class="loc-part w-full border border-slate-200 rounded-lg px-4 py-2.5 outline-none bg-slate-100 text-slate-400 cursor-not-allowed" disabled>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Full Location <span class="text-slate-400 normal-case font-normal">(auto-generated)</span></label>
                                <input type="text" name="location" id="location" value="{{ old('location', $recommendation->location ?? '') }}"
                                    class="w-full border border-blue-200 rounded-lg px-4 py-2.5 bg-blue-50 focus:border-blue-500 outline-none transition shadow-sm font-medium" readonly>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Grant Conditions (Financials) -->
                    <div class="bg-slate-50 border border-slate-100 rounded-xl p-6 space-y-4">
                        <div class="flex items-center gap-2 mb-2">
                            <i data-lucide="banknote" class="h-4 w-4 text-blue-600"></i>
                            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-tight">Grant Conditions</h3>
                        </div>
                        <div class="space-y-4">
                            <input type="hidden" id="base_term" value="{{ old('term', $recommendation->term ?? '99') }}">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Year of CoFO</label>
                                    <input type="number" name="cofo_year" id="cofo_year"
                                        value="{{ old('cofo_year', $recommendation->cofo_year ?? '') }}"
                                        min="1900" max="{{ date('Y') }}" placeholder="{{ date('Y') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Term (Years):</label>
                                    <input type="text" name="term" id="term_input"
                                        value="{{ old('term', $recommendation->term ?? '99') }}" readonly
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 bg-slate-100 text-slate-500 cursor-not-allowed outline-none">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Value for Proposed Dev. (₦):</label>
                                <input type="number" step="0.01" name="development_value" value="{{ old('development_value', $recommendation->development_value ?? '') }}"
                                    class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Time for completion of proposed development: </label>
                                <input type="text" name="development_period" value="{{ old('development_period', $recommendation->development_period ?? '2') }}"
                                    class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Ground Rent (₦):</label>
                                    <input type="number" step="0.01" name="ground_rent" value="{{ old('ground_rent', $recommendation->ground_rent ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Dev. Charge: </label>
                                    <input type="number" step="0.01" name="development_charge" value="{{ old('development_charge', $recommendation->development_charge ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Survey & Processing Fees (₦)</label>
                                    <input type="number" step="0.01" name="survey_fees" id="survey_fees" value="{{ old('survey_fees', $recommendation->survey_fees ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Preparation Fees (₦)</label>
                                    <input type="number" step="0.01" name="preparation_fees" id="preparation_fees" value="{{ old('preparation_fees', $recommendation->preparation_fees ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Preparation Fees in Words <span class="text-slate-400 normal-case font-normal">(auto)</span></label>
                                <input type="text" name="preparation_fees_words" id="preparation_fees_words"
                                    value="{{ old('preparation_fees_words', $recommendation->preparation_fees_words ?? '') }}"
                                    class="w-full border border-blue-100 bg-blue-50 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition shadow-sm"
                                    placeholder="Auto-filled from Preparation Fees above">
                            </div>
                        </div>
                    </div>

              <!-- Section: Conversion Specific Fields (Conditional) -->
                    <div id="conversion-fields-section" class="{{ old('type', $recommendation->type ?? '') == 'Conversion' ? '' : 'hidden' }} bg-amber-50/50 border border-amber-200 rounded-xl p-6 space-y-4 col-span-2">
                        <div class="flex items-center gap-2 mb-2">
                            <i data-lucide="refresh-cw" class="h-4 w-4 text-amber-600"></i>
                            <h3 class="text-sm font-bold text-amber-900 uppercase tracking-tight">Conversion Metadata</h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5 font-mono">Page</label>
                                <input type="text" name="page" value="{{ old('page', $recommendation->page ?? '') }}"
                                    class="w-full border border-slate-200 rounded-lg px-4 py-2.5 bg-white shadow-sm outline-none focus:ring-1 focus:ring-amber-500 transition">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5 font-mono">Survey Report</label>
                                <div class="flex gap-4">
                                    <div class="flex-1">
                                        <input type="text" name="survey_report" value="{{ old('survey_report', $recommendation->survey_report ?? '') }}"
                                            class="w-full border border-slate-200 rounded-lg px-4 py-2.5 bg-white shadow-sm outline-none focus:ring-1 focus:ring-amber-500 transition"
                                            placeholder="Reference/Description">
                                    </div>
                                    <div class="w-32">
                                        <input type="text" name="page_survey_report" value="{{ old('page_survey_report', $recommendation->page_survey_report ?? '') }}"
                                            class="w-full border border-slate-200 rounded-lg px-4 py-2.5 bg-white shadow-sm outline-none focus:ring-1 focus:ring-amber-500 transition"
                                            placeholder="Add. Page">
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5 font-mono">Improvement</label>
                                <input type="text" name="improvement" value="{{ old('improvement', $recommendation->improvement ?? '') }}"
                                    class="w-full border border-slate-200 rounded-lg px-4 py-2.5 bg-white shadow-sm outline-none focus:ring-1 focus:ring-amber-500 transition">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5 font-mono">Revision Period</label>
                                <input type="text" name="revision_period" value="{{ old('revision_period', $recommendation->revision_period ?? '') }}"
                                    class="w-full border border-slate-200 rounded-lg px-4 py-2.5 bg-white shadow-sm outline-none focus:ring-1 focus:ring-amber-500 transition">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5 font-mono">Time of Erection</label>
                                <input type="text" name="time_of_erection" value="{{ old('time_of_erection', $recommendation->time_of_erection ?? '') }}"
                                    class="w-full border border-slate-200 rounded-lg px-4 py-2.5 bg-white shadow-sm outline-none focus:ring-1 focus:ring-amber-500 transition">
                            </div>
                        </div>
                    </div>

                    <!-- Section: RofO Generation Data -->
                    <div class="bg-green-50/40 border border-green-200 rounded-xl p-6 space-y-4 col-span-2">
                        <div class="flex items-center gap-2 mb-2">
                            <i data-lucide="zap" class="h-4 w-4 text-green-600"></i>
                            <h3 class="text-sm font-bold text-green-900 uppercase tracking-tight">RofO Generation Data</h3>
                        </div>
                        <div class="space-y-4">
                            <div class="p-4 bg-white rounded-xl border border-green-100">
                                <span class="block text-xs font-bold text-slate-500 uppercase mb-3">Survey Method (Select One)</span>
                                <div class="space-y-3">
                                    <label class="flex items-center gap-3 cursor-pointer p-3 border border-slate-200 rounded-lg hover:bg-slate-50 transition">
                                        <input type="radio" name="rofo_survey_method" value="DIRECTOR"
                                            {{ old('rofo_survey_method', ($recommendation->rofo_director_survey ?? '') === 'YES' ? 'DIRECTOR' : (($recommendation->rofo_licensed_surveyor ?? '') === 'YES' ? 'LICENSED' : '')) === 'DIRECTOR' ? 'checked' : '' }}
                                            class="w-5 h-5 text-green-600 border-slate-300 focus:ring-green-500">
                                        <span class="text-sm font-medium text-slate-700">Require <strong>Director Survey</strong> to carry out survey</span>
                                    </label>
                                    <label class="flex items-center gap-3 cursor-pointer p-3 border border-slate-200 rounded-lg hover:bg-slate-50 transition">
                                        <input type="radio" name="rofo_survey_method" value="LICENSED"
                                            {{ old('rofo_survey_method', ($recommendation->rofo_director_survey ?? '') === 'YES' ? 'DIRECTOR' : (($recommendation->rofo_licensed_surveyor ?? '') === 'YES' ? 'LICENSED' : '')) === 'LICENSED' ? 'checked' : '' }}
                                            class="w-5 h-5 text-green-600 border-slate-300 focus:ring-green-500">
                                        <span class="text-sm font-medium text-slate-700">Require <strong>Licensed Surveyor</strong> to carry out survey</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Recommendation & Reasons -->
                    <div class="bg-slate-50 border border-slate-100 rounded-xl p-6 space-y-4 col-span-2">
                        <div class="flex items-center gap-2 mb-2">
                            <i data-lucide="check-circle" class="h-4 w-4 text-blue-600"></i>
                            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-tight">Recommendation & Reasons</h3>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">The Director of Land recommends/does not recommend for the following reasons:</label>
                            <textarea name="recommendation" rows="4" placeholder="Enter reasons for recommendation..."
                                class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm resize-none">{{ old('recommendation', $recommendation->recommendation ?? '') }}</textarea>
                        </div>
                    </div>

                      <!-- Section 4: System Audit metadata -->
                    <div class="bg-slate-50 border border-slate-100 rounded-xl p-6 space-y-4">
                        <div class="flex items-center gap-2 mb-2">
                            <i data-lucide="info" class="h-4 w-4 text-blue-600"></i>
                            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-tight">Additional Data</h3>
                        </div>
                        <div class="grid grid-cols-2 gap-4">

                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Time Generated</label>
                                <input type="text" readonly value="{{ (isset($recommendation) && $recommendation->created_at) ? $recommendation->created_at->format('h:i:s A') : now()->format('h:i:s A') }}"
                                    class="w-full border border-slate-200 rounded-lg px-4 py-2.5 bg-slate-100 text-slate-500 outline-none transition shadow-sm cursor-not-allowed">
                            </div>

                            
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Date Generated</label>
                                <input type="text" readonly value="{{ (isset($recommendation) && $recommendation->created_at) ? $recommendation->created_at->format('Y-m-d') : now()->format('Y-m-d') }}"
                                    class="w-full border border-slate-200 rounded-lg px-4 py-2.5 bg-slate-100 text-slate-500 outline-none transition shadow-sm cursor-not-allowed">
                            </div>
                          
                            <div class="col-span-2">
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Generated By</label>
                                <input type="text" readonly value="{{ isset($recommendation) ? ($recommendation->creator->name ?? 'System') : auth()->user()->name }}"
                                    class="w-full border border-slate-200 rounded-lg px-4 py-2.5 bg-slate-100 text-slate-500 outline-none transition shadow-sm cursor-not-allowed">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Footer -->
                <div class="pt-8 border-t border-slate-100 flex justify-end gap-3">
                    <button type="submit" class="px-8 py-3 bg-slate-900 text-white font-bold rounded-lg hover:bg-slate-800 transition shadow-lg">
                        {{ isset($recommendation) ? 'Update' : 'Generate Recommendation' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    @include('admin.footer')
</div>

@include('components.global-fileno-modal')

@push('scripts')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"/>
<style>
    /* Make Select2 match Tailwind form inputs */
    .select2-container--default .select2-selection--single {
        height: 42px;
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        background-color: #fff;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / .05);
        display: flex;
        align-items: center;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #0f172a;
        line-height: 42px;
        padding-left: 1rem;
        padding-right: 2rem;
        font-size: 0.875rem;
    }
    .select2-container--default .select2-selection--single .select2-selection__placeholder {
        color: #94a3b8;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 42px;
        right: 8px;
    }
    .select2-container--default.select2-container--focus .select2-selection--single,
    .select2-container--default.select2-container--open .select2-selection--single {
        border-color: #3b82f6;
        box-shadow: 0 0 0 1px #3b82f6;
        outline: none;
    }
    .select2-dropdown {
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        box-shadow: 0 4px 6px -1px rgb(0 0 0 / .1);
        font-size: 0.875rem;
    }
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #3b82f6;
    }
    .select2-search--dropdown .select2-search__field {
        border: 1px solid #e2e8f0;
        border-radius: 0.375rem;
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
        outline: none;
    }
    .select2-search--dropdown .select2-search__field:focus {
        border-color: #3b82f6;
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script src="{{ asset('js/land_recommendations.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // TP No Select2 — lazy search against tp_lookups (200k+ rows)
        $('#layout_plan_no').select2({
            placeholder: 'Type to search TP No...',
            allowClear: true,
            minimumInputLength: 1,
            ajax: {
                url: '{{ route("instruments.tpLookups.search") }}',
                dataType: 'json',
                delay: 300,
                data: function (params) { return { q: params.term }; },
                processResults: function (data) { return data; },
                cache: true
            }
        });

        // Helper: wire up a Select2 with an "Other → specify" pattern
        // selectId: jQuery selector for the <select>
        // hiddenId: jQuery selector for the hidden input (actual submitted value)
        // otherId:  jQuery selector for the specify text input
        function initOtherSelect2(selectId, hiddenId, otherId, ajaxUrl, searchParam) {
            var $sel    = $(selectId);
            var $hidden = $(hiddenId);
            var $other  = $(otherId);

            $sel.select2({
                placeholder: 'Type to search...',
                allowClear: true,
                minimumInputLength: 1,
                ajax: {
                    url: ajaxUrl,
                    dataType: 'json',
                    delay: 300,
                    data: function (params) {
                        var d = {};
                        d[searchParam] = params.term;
                        return d;
                    },
                    processResults: function (data) {
                        return {
                            results: (data.data || []).map(function (item) {
                                return { id: item.name, text: item.name };
                            })
                        };
                    },
                    cache: true
                }
            });

            $sel.on('change', function () {
                var val = $(this).val() || '';
                if (val.toLowerCase() === 'other') {
                    $other.show().focus();
                    $hidden.val('');
                } else {
                    $other.hide().val('');
                    $hidden.val(val);
                }
                if (window._buildLocation) window._buildLocation();
            });

            $other.on('input', function () {
                $hidden.val($(this).val());
                if (window._buildLocation) window._buildLocation();
            });
        }

        initOtherSelect2('#street_name_select', '#street_name', '#street_name_other',
            '/api/reference/streets', 'search');

        initOtherSelect2('#district_select', '#district', '#district_other',
            '/api/reference/districts', 'search');

        initOtherSelect2('#lga_select', '#lga', '#lga_other',
            '/api/reference/lgas', 'search');

        // ── Number to Naira Words ──
        function numberToNairaWords(num) {
            num = parseFloat(num);
            if (isNaN(num) || num < 0) return '';
            if (num === 0) return 'Zero Naira Only';
            var ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine',
                        'Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen',
                        'Seventeen','Eighteen','Nineteen'];
            var tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
            function h(n) {
                if (n === 0) return '';
                if (n < 20) return ones[n] + ' ';
                if (n < 100) return tens[Math.floor(n/10)] + (n%10 ? '-'+ones[n%10] : '') + ' ';
                return ones[Math.floor(n/100)] + ' Hundred ' + h(n % 100);
            }
            function convert(n) {
                if (n === 0) return '';
                if (n < 1000) return h(n);
                if (n < 1000000) return convert(Math.floor(n/1000)) + 'Thousand ' + h(n%1000);
                if (n < 1000000000) return convert(Math.floor(n/1000000)) + 'Million ' + convert(n%1000000);
                return convert(Math.floor(n/1000000000)) + 'Billion ' + convert(n%1000000000);
            }
            var intPart = Math.floor(Math.abs(num));
            var decPart = Math.round((Math.abs(num) - intPart) * 100);
            var result = convert(intPart).trim() + ' Naira';
            if (decPart > 0) result += ' and ' + h(decPart).trim() + ' Kobo';
            return result.trim() + ' Only';
        }

        // Auto-fill Premium in Words from Premium ₦
        var premiumEl = document.getElementById('premium');
        var premiumWordsEl = document.getElementById('premium_words');
        if (premiumEl && premiumWordsEl) {
            premiumEl.addEventListener('input', function () {
                premiumWordsEl.value = this.value ? numberToNairaWords(this.value) : '';
            });
        }

        // Auto-fill Preparation Fees in Words from Preparation Fees ₦
        var prepEl = document.getElementById('preparation_fees');
        var prepWordsEl = document.getElementById('preparation_fees_words');
        if (prepEl && prepWordsEl) {
            prepEl.addEventListener('input', function () {
                prepWordsEl.value = this.value ? numberToNairaWords(this.value) : '';
            });
        }

        // ── Plot sizes: serialize rows to JSON on form submit ──
        document.getElementById('land-recommendation-form').addEventListener('submit', function () {
            var activeSuffix = null;
            var plPanel  = document.getElementById('atx-panel-private-layout');
            var subPanel = document.getElementById('atx-panel-subdivision');
            var extPanel = document.getElementById('atx-panel-extension');
            if (plPanel  && !plPanel.classList.contains('hidden'))  activeSuffix = 'pl';
            if (subPanel && !subPanel.classList.contains('hidden')) activeSuffix = 'sub';
            if (extPanel && !extPanel.classList.contains('hidden')) activeSuffix = 'ext';
            if (!activeSuffix) return;
            var rows  = document.querySelectorAll('#plot-sizes-rows-' + activeSuffix + ' .plot-size-row');
            var sizes = Array.from(rows).map(function (row) {
                var cEl = row.querySelector('.plot-count');
                return {
                    length: row.querySelector('.plot-length').value.trim(),
                    width:  row.querySelector('.plot-width').value.trim(),
                    count:  cEl ? cEl.value.trim() : '',
                };
            }).filter(function (s) { return s.length || s.width || s.count; });
            document.getElementById('plot_sizes_json').value = sizes.length ? JSON.stringify(sizes) : '';
        });

        // ── Plot sizes: load saved rows on page load (edit mode) ──
        (function () {
            var el = document.getElementById('plot_sizes_json');
            if (!el || !el.value) return;
            var savedAppType = @json($savedAppType);
            var suffix = null, showCount = true;
            if (savedAppType === 'Private Layout')    { suffix = 'pl';  showCount = true; }
            else if (savedAppType === 'Plot Subdivision') { suffix = 'sub'; showCount = false; }
            else if (savedAppType === 'Plot Extension')   { suffix = 'ext'; showCount = false; }
            if (!suffix) return;
            var sizes;
            try { sizes = JSON.parse(el.value); } catch (e) { return; }
            if (!Array.isArray(sizes)) return;
            sizes.forEach(function (s) { addPlotSizeRow(suffix, s, showCount); });
        })();

        @if($errors->any())
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    html: `<ul style="text-align:left;padding-left:16px">{{ implode('', array_map(fn($e) => "<li>$e</li>", $errors->all())) }}</ul>`,
                    confirmButtonColor: '#dc2626',
                });
            }
        @endif

        @if(session('success'))
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: @json(session('success')),
                    confirmButtonColor: '#059669',
                    timer: 4000,
                    timerProgressBar: true,
                });
            }
        @endif
    });
</script>
@endpush
@endsection

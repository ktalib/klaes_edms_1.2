@extends('layouts.app')

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header')
    
    <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
            <!-- Header -->
            <div class="bg-slate-50 px-8 py-6 flex justify-between items-center border-b border-slate-200">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Recommendation for Grant of Statutory Right of Occupancy</h1>
                    <p class="text-slate-500 text-sm mt-1">Data entry form</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('land-recommendations.index') }}" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 rounded-lg transition border border-slate-200 shadow-sm">
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
                    <!-- Recommendation Type Selection -->
                    <div class="bg-blue-50/30 border border-blue-100 rounded-xl p-6 col-span-2">
                        <label class="block text-xs font-bold text-blue-700 uppercase tracking-wider mb-3">Recommendation Type</label>
                        <div class="flex flex-col sm:flex-row gap-4">
                            <label class="flex items-center gap-3 cursor-pointer p-4 bg-white border border-blue-200 rounded-xl hover:border-blue-500 transition shadow-sm flex-1 group">
                                <input type="radio" name="type" value="Direct" 
                                    {{ old('type', $recommendation->type ?? 'Direct') == 'Direct' ? 'checked' : '' }}
                                    class="w-5 h-5 text-blue-600 focus:ring-blue-500 border-slate-300">
                                <div>
                                    <span class="block text-sm font-bold text-slate-900 group-hover:text-blue-700 transition">Direct</span>
                                    <!-- <span class="block text-[10px] text-slate-500">Standard grant of occupancy</span> -->
                                </div>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer p-4 bg-white border border-blue-200 rounded-xl hover:border-amber-500 transition shadow-sm flex-1 group">
                                <input type="radio" name="type" value="Conversion"
                                    {{ old('type', $recommendation->type ?? '') == 'Conversion' ? 'checked' : '' }}
                                    class="w-5 h-5 text-amber-600 focus:ring-amber-500 border-slate-300">
                                <div>
                                    <span class="block text-sm font-bold text-slate-900 group-hover:text-amber-700 transition">Conversion</span>
                                    <!-- <span class="block text-[10px] text-slate-500">From customary to statutory</span> -->
                                </div>
                            </label>
                        </div>
                    </div>

                   
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
                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">(c) Location</label>
                                    <input type="text" name="location" id="location" value="{{ old('location', $recommendation->location ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">(d) Plot Number</label>
                                    <input type="text" name="plot_number" id="plot_number" value="{{ old('plot_number', $recommendation->plot_number ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">(e) Layout Plan No.</label>
                                    <input type="text" name="layout_plan_no" id="layout_plan_no" value="{{ old('layout_plan_no', $recommendation->layout_plan_no ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
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
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Term:</label>
                                <input type="text" name="term" value="{{ old('term', $recommendation->term ?? '99') }}"  
                                    class="w-full border border-slate-200 rounded-lg px-4 py-2.5 bg-slate-100 text-slate-500">
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
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Survey & Processing charges (₦)</label>
                                <input type="number" step="0.01" name="preparation_fees" value="{{ old('preparation_fees', $recommendation->preparation_fees ?? '') }}"
                                    class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Recommendation & Reasons -->
                    <div class="bg-slate-50 border border-slate-100 rounded-xl p-6 space-y-4 hidden">
                        <div class="flex items-center gap-2 mb-2">
                            <i data-lucide="check-circle" class="h-4 w-4 text-blue-600"></i>
                            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-tight">Recommendation & Reasons</h3>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">The Director of Land recommends/does not recommend for the following reasons:</label>
                                <textarea name="recommendation" rows="6" placeholder="Enter reasons for recommendation..."
                                    class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">{{ old('recommendation', $recommendation->recommendation ?? '') }}</textarea>
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
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script src="{{ asset('js/land_recommendations.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
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

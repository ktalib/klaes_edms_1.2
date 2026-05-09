@extends('layouts.app')

@section('page-title')
    {{ __('Project Manager Console') }}
@endsection

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle' => 'Project Manager Console',
        'PageDescription' => 'Create and manage VFC projects and worker assignments'
    ])

    <div class="p-6 space-y-8 max-w-7xl mx-auto">
        <!-- Header Actions -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-900">VFC Projects</h1>
                <p class="text-slate-500 mt-1">Manage project-based valuation workflows</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('valuation-compensations.index') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-white border border-slate-200 text-slate-700 font-semibold shadow-sm hover:bg-slate-50 transition">
                    <i data-lucide="arrow-left" class="h-5 w-5"></i>
                    <span>Back to VFC</span>
                </a>
                <button type="button" onclick="openProjectModal()" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-blue-600 text-white font-semibold shadow-lg shadow-blue-100 hover:bg-blue-700 transition">
                    <i data-lucide="folder-plus" class="h-5 w-5"></i>
                    <span>Create New Project</span>
                </button>
            </div>
        </div>

        <!-- Project List -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            @forelse($projects as $project)
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden hover:shadow-md transition group">
                <div class="p-6 border-b border-slate-50 bg-slate-50/30">
                    <div class="flex justify-between items-start">
                        <div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800 uppercase tracking-wider mb-2">
                                {{ $project->project_type }}
                            </span>
                            <h3 class="text-xl font-bold text-slate-900">{{ $project->project_name }}</h3>
                            <div class="flex items-center gap-2 mt-2">
                                <i data-lucide="hash" class="h-3 w-3 text-indigo-500"></i>
                                <span class="text-[13px] font-black font-mono text-indigo-600 bg-indigo-50/50 px-2 py-0.5 rounded border border-indigo-100/50 shadow-sm">{{ $project->project_code }}</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest">Template Rows</p>
                            <p class="text-2xl font-black text-slate-800">{{ $project->number_of_items }}</p>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <i data-lucide="users" class="h-4 w-4 text-slate-400"></i>
                        <span class="text-xs font-bold text-slate-500 uppercase">Assigned Workers ({{ $project->workers->count() }})</span>
                    </div>
                    <div class="flex flex-wrap gap-2 mb-6">
                        @foreach($project->workers->take(3) as $worker)
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-50 border border-slate-100" title="{{ $worker->user->phone_number }}">
                            <div class="w-6 h-6 rounded-full bg-blue-600 flex items-center justify-center text-[10px] text-white font-bold">
                                {{ substr($worker->user->first_name, 0, 1) }}{{ substr($worker->user->last_name, 0, 1) }}
                            </div>
                            <span class="text-xs font-semibold text-slate-700">{{ $worker->user->first_name }} {{ $worker->user->last_name }}</span>
                            <span class="text-[10px] text-emerald-600 font-black font-mono bg-emerald-50 px-1.5 py-0.5 rounded tracking-tighter">{{ $worker->worker_code }}</span>
                        </div>
                        @endforeach

                        @if($project->workers->count() > 3)
                        <div id="more-workers-{{ $project->id }}" class="hidden flex flex-wrap gap-2 w-full mt-2">
                            @foreach($project->workers->slice(3) as $worker)
                            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-50 border border-slate-100" title="{{ $worker->user->phone_number }}">
                                <div class="w-6 h-6 rounded-full bg-blue-600 flex items-center justify-center text-[10px] text-white font-bold">
                                    {{ substr($worker->user->first_name, 0, 1) }}{{ substr($worker->user->last_name, 0, 1) }}
                                </div>
                                <span class="text-xs font-semibold text-slate-700">{{ $worker->user->first_name }} {{ $worker->user->last_name }}</span>
                                <span class="text-[10px] text-emerald-600 font-black font-mono bg-emerald-50 px-1.5 py-0.5 rounded tracking-tighter">{{ $worker->worker_code }}</span>
                            </div>
                            @endforeach
                        </div>
                        <button type="button" onclick="toggleWorkers({{ $project->id }}, this)" class="text-[11px] font-bold text-blue-600 hover:text-blue-700 mt-2 flex items-center gap-1 transition-all py-1 px-2 rounded-lg hover:bg-blue-50">
                            <i data-lucide="plus-circle" class="h-3.5 w-3.5"></i>
                            <span>View {{ $project->workers->count() - 3 }} More</span>
                        </button>
                        @endif
                    </div>
                    
                    <div class="flex items-center justify-between pt-4 border-t border-slate-50">
                        <div class="flex items-center gap-2 text-xs text-slate-400">
                            <i data-lucide="calendar" class="h-3 w-3"></i>
                            <span>Created {{ $project->created_at->format('d M, Y') }}</span>
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ route('valuation-compensations.projects.templates', $project->id) }}" target="_blank" class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Generate Templates">
                                <i data-lucide="download-cloud" class="h-5 w-5"></i>
                            </a>
                            <button class="p-2 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition" title="View Report">
                                <i data-lucide="bar-chart-3" class="h-5 w-5"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="lg:col-span-2 py-20 text-center bg-white rounded-3xl border-2 border-dashed border-slate-200">
                <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="folder-search" class="h-10 w-10 text-slate-300"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-900">No Projects Found</h3>
                <p class="text-slate-500 mt-2 max-w-md mx-auto">Create your first project to start managing structured valuation workflows and worker assignments.</p>
                <button type="button" onclick="openProjectModal()" class="mt-6 px-6 py-3 rounded-xl bg-blue-600 text-white font-bold shadow-lg shadow-blue-100 hover:bg-blue-700 transition">
                    Get Started
                </button>
            </div>
            @endforelse
        </div>
    </div>
</div>

<!-- Project Modal -->
<div id="project-modal" class="fixed inset-0 z-[100] hidden flex items-center justify-center p-4">
    <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" id="modal-overlay"></div>
    <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden border border-slate-100 transform transition-all">
        <!-- Header -->
        <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-white shrink-0">
            <div>
                <h3 class="text-xl font-bold text-slate-900">Create New VFC Project</h3>
                <p class="text-xs text-slate-500 mt-1 uppercase tracking-widest font-semibold">Define project scope and assign workers</p>
            </div>
            <button type="button" onclick="closeModal()" class="text-slate-400 hover:text-slate-600 p-2 hover:bg-slate-50 rounded-xl transition">
                <i data-lucide="x" class="h-6 w-6"></i>
            </button>
        </div>

        <!-- Body -->
        <form id="project-form" class="flex-1 overflow-y-auto">
            @csrf
            <div class="px-8 py-8 space-y-8">
                <!-- Project Info -->
                <div class="space-y-6">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center text-blue-600">
                            <i data-lucide="info" class="h-4 w-4"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider">General Information</h4>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Project Title <span class="text-red-500">*</span></label>
                            <input type="text" name="project_name" required class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium" placeholder="e.g. Zaria Road Expansion Phase 1">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Project FileNo <span class="text-slate-400 font-normal italic">(Auto-generated)</span></label>
                            <input type="text" name="project_fileno" id="project_fileno" readonly class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-100 text-sm font-bold font-mono text-slate-500" placeholder="Generating...">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Project Code <span class="text-slate-400 font-normal italic">(Auto-generated)</span></label>
                            <input type="text" name="project_code" id="project_code" readonly class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-100 text-sm font-bold font-mono text-slate-500" placeholder="Generating...">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Number of Template Rows <span class="text-slate-400 font-normal italic">(Estimated)</span> <span class="text-red-500">*</span></label>
                            <input type="number" name="number_of_items" required min="1" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium" placeholder="e.g. 100">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Project Type <span class="text-red-500">*</span></label>
                            <select name="project_type" id="project_type" required class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium">
                                <option value="">Select Type</option>
                                <option value="Road Construction">Road Construction</option>
                                <option value="Airport">Airport</option>
                                <option value="Housing">Housing</option>
                                <option value="Infrastructure">Infrastructure</option>
                                <option value="Other">Other</option>
                            </select>
                            <input type="text" name="project_type_other" id="project_type_other" class="hidden mt-3 w-full px-4 py-3 rounded-xl border border-blue-200 bg-blue-50/30 text-sm font-medium" placeholder="Specify project type...">
                        </div>
                    </div>
                </div>

                <!-- Location -->
                <div class="pt-8 border-t border-slate-100 space-y-6">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-8 h-8 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600">
                            <i data-lucide="map-pin" class="h-4 w-4"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Location Scope</h4>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Street Name</label>
                            <select name="street" id="proj_street" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium">
                                <option value="">Select Street</option>
                                @foreach($streets as $street)
                                <option value="{{ $street->name }}">{{ $street->name }}</option>
                                @endforeach
                                <option value="Other">Other (Please Specify)</option>
                            </select>
                            <input type="text" name="street_other" id="proj_street_other" class="hidden mt-3 w-full px-4 py-3 rounded-xl border border-blue-200 bg-blue-50/30 text-sm font-medium" placeholder="Type street name...">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">District</label>
                            <select name="district" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium">
                                <option value="">Select District</option>
                                @foreach($districts as $district)
                                <option value="{{ $district->name }}">{{ $district->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">LGA</label>
                            <select name="lga" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium">
                                <option value="">Select LGA</option>
                                @foreach($lgas as $lga)
                                <option value="{{ $lga->LGAName }}">{{ $lga->LGAName }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">State</label>
                            <input type="text" name="state" readonly value="Kano" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-100 text-sm font-bold text-slate-500 cursor-not-allowed">
                        </div>
                    </div>
                </div>

                <!-- Worker Assignment -->
                <div class="pt-8 border-t border-slate-100 space-y-6">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-8 h-8 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600">
                            <i data-lucide="users" class="h-4 w-4"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Worker Assignment</h4>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Number of Workers <span class="text-red-500">*</span></label>
                        <input type="number" id="num_workers" min="1" max="10" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white shadow-inner focus:border-blue-500 transition text-sm font-bold" placeholder="How many workers?">
                        <p class="text-[10px] text-slate-400 mt-1 italic">Enter a number to generate worker assignment cards below.</p>
                    </div>

                    <div id="worker-cards" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Dynamic cards inserted here -->
                    </div>
                </div>
            </div>
        </form>

        <!-- Footer -->
        <div class="px-8 py-6 border-t border-slate-100 flex items-center justify-end gap-3 bg-white shrink-0 shadow-inner">
            <button type="button" onclick="closeModal()" class="px-6 py-3 rounded-xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 transition">
                Cancel
            </button>
            <button type="submit" form="project-form" id="submit-btn" class="px-8 py-3 rounded-xl bg-blue-600 text-white font-bold shadow-lg shadow-blue-100 hover:bg-blue-700 transition flex items-center gap-2">
                <span>Save Project</span>
                <i data-lucide="save" class="h-4 w-4"></i>
            </button>
        </div>
    </div>
</div>

@push('scripts')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<style>
    .select2-container {
        width: 100% !important;
    }
    .select2-container--default .select2-selection--single {
        border-radius: 0.75rem;
        height: 46px;
        border: 1px solid #e2e8f0;
        background-color: #f8fafc;
        width: 100%;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 44px;
        padding-left: 1rem;
        font-size: 0.875rem;
        font-weight: 500;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 44px;
    }
</style>
<script>
    function toggleWorkers(projectId, btn) {
        const container = $(`#more-workers-${projectId}`);
        const isHidden = container.hasClass('hidden');
        
        if (isHidden) {
            container.removeClass('hidden').addClass('flex');
            $(btn).find('span').text('Show Less');
            $(btn).find('i').attr('data-lucide', 'minus-circle');
        } else {
            container.addClass('hidden').removeClass('flex');
            const count = container.children().length;
            $(btn).find('span').text(`View ${count} More`);
            $(btn).find('i').attr('data-lucide', 'plus-circle');
        }
        
        if (window.lucide) window.lucide.createIcons();
    }

    $(document).ready(function() {
        if (window.lucide) window.lucide.createIcons();

        $('#project_type').on('change', function() {
            if ($(this).val() === 'Other') {
                $('#project_type_other').removeClass('hidden').prop('required', true).focus();
            } else {
                $('#project_type_other').addClass('hidden').prop('required', false);
            }
        });

        $('#proj_street').on('change', function() {
            if ($(this).val() === 'Other') {
                $('#proj_street_other').removeClass('hidden').prop('required', true).focus();
            } else {
                $('#proj_street_other').addClass('hidden').prop('required', false);
            }
        });

        $('#proj_street').select2({
            dropdownParent: $('#project-modal'),
            width: '100%'
        });

        $('#num_workers').on('input', function() {
            generateWorkerCards($(this).val());
        });

        $('#project-form').on('submit', function(e) {
            e.preventDefault();
            saveProject();
        });
    });

    function generateWorkerCards(num) {
        const container = $('#worker-cards');
        const projectCode = $('#project_code').val() || 'PRJ-XXXX';
        container.empty();

        if (!num || num < 1) return;

        for (let i = 1; i <= num; i++) {
            const workerIndex = String(i).padStart(3, '0');
            const workerCode = `WRK-${projectCode}-${workerIndex}`;
            
            const card = `
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Worker #${i}</span>
                        <span class="text-[11px] font-mono font-black text-blue-600 bg-blue-50 px-2 py-0.5 rounded border border-blue-100 shadow-sm">${workerCode}</span>
                    </div>
                    <div>
                        <select name="worker_assignments[${i-1}][user_id]" class="worker-select w-full" required>
                            <option value="">Select Staff</option>
                            @foreach($workers as $worker)
                            <option value="{{ $worker->id }}">{{ $worker->first_name }} {{ $worker->last_name }} ({{ $worker->phone_number }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            `;
            container.append(card);
        }

        $('.worker-select').select2({
            dropdownParent: $('#project-modal'),
            width: '100%'
        });
    }

    // Refresh IDs if project code changes
    $('#project_code').on('input', function() {
        const num = $('#num_workers').val();
        if (num > 0) generateWorkerCards(num);
    });

    function openProjectModal() {
        $('#project-form')[0].reset();
        $('#worker-cards').empty();
        $('#project_type_other').addClass('hidden');
        $('#project_fileno').val('Generating...');
        $('#project_code').val('Generating...');
        
        // Fetch next available code
        $.get("{{ route('valuation-compensations.projects.next-code') }}", function(data) {
            $('#project_code').val(data.code);
            $('#project_fileno').val(data.fileno);
            // Refresh worker cards if number already entered
            const num = $('#num_workers').val();
            if (num > 0) generateWorkerCards(num);
        });

        $('#project-modal').removeClass('hidden').addClass('flex');
        setTimeout(() => $('#modal-overlay').addClass('opacity-100'), 10);
    }

    function closeModal() {
        $('#modal-overlay').removeClass('opacity-100');
        setTimeout(() => {
            $('#project-modal').addClass('hidden').removeClass('flex');
        }, 300);
    }

    function saveProject() {
        Swal.fire({
            title: 'Saving Project...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.ajax({
            url: "{{ route('valuation-compensations.projects.store') }}",
            type: "POST",
            data: $('#project-form').serialize(),
            success: function(response) {
                if (response.success) {
                    Swal.fire('Success!', response.message, 'success').then(() => {
                        window.location.reload();
                    });
                }
            },
            error: function(xhr) {
                let msg = 'Something went wrong.';
                if (xhr.status === 422) {
                    const errors = xhr.responseJSON.errors;
                    msg = Object.values(errors).flat().join('<br>');
                }
                Swal.fire('Error!', msg, 'error');
            }
        });
    }
</script>
@endpush
@endsection

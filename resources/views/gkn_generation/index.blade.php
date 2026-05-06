@extends('layouts.app')

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60" x-data='gknGeneratorFixed({
    districts: @json($districts),
    lgas: @json($lgas),
    landUses: @json($landUses),
    purposes: @json($purposes),
    csrfToken: "{{ csrf_token() }}",
    availableUrl: "{{ route('gkn-generation.available') }}",
    worldTimeUrl: "{{ route('world-time') }}",
    storeUrl: "{{ route('gkn-generation.store') }}",
    currentDate: "{{ date('Y-m-d') }}",
    currentTimeOnly: "{{ date('H:i:s') }}",
    customerTypes: @json($customerTypes),
    serialStatuses: @json($serialStatuses)
})'>
    @include('admin.header')
 
    @include('partials.serial_control_modal', [
        'showVariable' => 'showSerialModal',
        'title' => 'Initialize GKN Serial',
        'type' => 'gkn',
        'prefixes' => $gknPrefixes,
        'serialStatuses' => $serialStatuses
    ])
    
    <div class="py-12 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">GKN File Numbers</h1>
                    <p class="text-slate-500 text-sm mt-1">Manage and generate GKN file numbers.</p>
                </div>
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <form action="{{ route('gkn-generation.index') }}" method="GET" class="relative group flex-1 md:w-80">
                        <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
                        <input type="text" 
                               name="search" 
                               value="{{ request('search') }}"
                               placeholder="Search file, title, or location..." 
                               class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all shadow-sm">
                    </form>
                    @if(isset($user->assign_role) && str_contains($user->assign_role, 'Supper Admin'))
                    <div class="flex flex-wrap gap-4">
                        <button
                            @click="showGenerateModal = true"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition-all shadow-sm hover:shadow-md active:scale-95">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                            <span class="font-bold">Generate GKN FileNo</span>
                        </button>

                        <button
                            @click="openSerialModal()"
                            class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-xl flex items-center space-x-2 transition-all shadow-sm hover:shadow-md active:scale-95">
                            <i data-lucide="settings" class="w-4 h-4 text-slate-500"></i>
                            <span class="font-bold">Serial Number Control</span>
                        </button>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                <!-- Daily Stats -->
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                    <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:scale-110 transition-transform duration-500">
                        <i data-lucide="calendar-check" class="h-32 w-32 text-blue-600"></i>
                    </div>
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-blue-50 text-blue-600 rounded-2xl border border-blue-100 shadow-sm">
                            <i data-lucide="calendar-check" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Daily Generation</p>
                            <h3 class="text-2xl font-black text-slate-800 tracking-tight">{{ number_format($stats['today']) }}</h3>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-slate-50 flex items-center justify-between text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                        <span>New Files Today</span>
                        <span class="text-emerald-500 flex items-center gap-1">
                            Live Update
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                            </span>
                        </span>
                    </div>
                </div>

                <!-- Monthly Stats -->
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                    <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:scale-110 transition-transform duration-500">
                        <i data-lucide="bar-chart-3" class="h-32 w-32 text-indigo-600"></i>
                    </div>
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-indigo-50 text-indigo-600 rounded-2xl border border-indigo-100 shadow-sm">
                            <i data-lucide="bar-chart-3" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Monthly Generation</p>
                            <h3 class="text-2xl font-black text-slate-800 tracking-tight">{{ number_format($stats['month']) }}</h3>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-slate-50 flex items-center justify-between text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                        <span>Total this month</span>
                        <span class="text-indigo-500">{{ date('F Y') }}</span>
                    </div>
                </div>

                <!-- Total Stats -->
                <div class="p-6 rounded-3xl shadow-sm hover:shadow-md transition-all group overflow-hidden relative text-white bg-gradient-to-br from-blue-600 to-blue-800 border-none">
                    <div class="absolute -right-4 -bottom-4 opacity-10 group-hover:scale-110 transition-transform duration-500">
                        <i data-lucide="database" class="h-32 w-32 text-white"></i>
                    </div>
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-white/20 text-white rounded-2xl border border-white/30 shadow-sm backdrop-blur-md">
                            <i data-lucide="database" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-blue-100 uppercase tracking-widest">Total</p>
                            <h3 class="text-2xl font-black tracking-tight text-white">{{ number_format($stats['total']) }}</h3>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-white/10 flex items-center justify-between text-[10px] font-bold text-blue-100 uppercase tracking-widest">
                        <span>All GKN Records</span>
                        <span class="px-2 py-0.5 bg-white/20 text-white rounded-lg border border-white/20">GKN</span>
                    </div>
                </div>
            </div>

            <!-- Existing Records Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                    <h3 class="font-bold text-slate-800">GKN Records</h3>
                    <div class="flex items-center gap-3 text-xs">
                        <span class="flex items-center gap-1.5 px-2 py-1 bg-emerald-50 text-emerald-700 border border-emerald-100 rounded-lg font-black uppercase tracking-widest">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span>COMMISSIONED
                        </span>
                        <span class="flex items-center gap-1.5 px-2 py-1 bg-amber-50 text-amber-700 border border-amber-100 rounded-lg font-black uppercase tracking-widest">
                            <span class="w-2 h-2 rounded-full bg-amber-400 inline-block"></span>File Indexing
                        </span>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/50 border-b border-slate-200 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                <th class="px-6 py-4 whitespace-nowrap">File Number</th>
                                <th class="px-6 py-4 whitespace-nowrap">Title / Name</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Plot No</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">TP No</th>
                                <th class="px-6 py-4 whitespace-nowrap">Land Use</th>
                                <th class="px-6 py-4 whitespace-nowrap">Purpose</th>
                                <th class="px-6 py-4 whitespace-nowrap">Location</th>
                                <th class="px-6 py-4 whitespace-nowrap">LGA</th>
                                <th class="px-6 py-4 whitespace-nowrap">Commissioned By</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Time Commissioned</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Date Commissioned</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Source</th>
                                <th class="px-6 py-4 text-right whitespace-nowrap">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm text-slate-600">
                            @forelse($records as $record)
                            <tr class="hover:bg-slate-50/50 transition-colors group" style="background-color: #f0fdf4;">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col">
                                        <span class="font-mono font-bold text-blue-600 text-xs whitespace-nowrap">{{ $record->full_file_number }}</span>
                                        @if($record->batch_count > 1)
                                            <button @click="openBatchModal('{{ $record->batch_no }}')" class="mt-1 flex items-center gap-1.5 text-[9px] font-black text-blue-500 hover:text-blue-700 underline underline-offset-4 decoration-blue-200 uppercase tracking-widest whitespace-nowrap">
                                                <i data-lucide="layers" class="h-2.5 w-2.5"></i>
                                                Batch Group ({{ $record->batch_count }})
                                            </button>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 font-medium text-slate-700 whitespace-nowrap">{{ Str::limit($record->file_name, 30) }}</td>
                                <td class="px-6 py-4 text-center font-mono text-xs whitespace-nowrap">{{ $record->plot_no ?? '—' }}</td>
                                <td class="px-6 py-4 text-center font-mono text-xs whitespace-nowrap">{{ $record->tp_no ?? '—' }}</td>
                                <td class="px-6 py-4 text-xs font-bold text-slate-600 whitespace-nowrap">{{ $record->landUse->landuse ?? '—' }}</td>
                                <td class="px-6 py-4 text-xs font-bold text-slate-600 whitespace-nowrap">{{ $record->purpose->name ?? '—' }}</td>
                                <td class="px-6 py-4 text-xs text-slate-500 whitespace-nowrap">{{ Str::limit($record->location, 40) }}</td>
                                <td class="px-6 py-4 text-xs font-bold text-slate-600 whitespace-nowrap">{{ $record->lga ?? '—' }}</td>
                                <td class="px-6 py-4 text-xs whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-lg bg-blue-50 flex items-center justify-center text-[10px] font-black text-blue-600 border border-blue-100">
                                            {{ strtoupper(substr($record->user->name ?? 'S', 0, 1)) }}
                                        </div>
                                        <span class="font-bold text-slate-600">{{ $record->user->name ?? 'System' }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center font-black text-slate-500 text-[11px] whitespace-nowrap">
                                    <span class="px-2 py-1 bg-slate-100 rounded-md border border-slate-200">{{ $record->formatted_time }}</span>
                                </td>
                                <td class="px-6 py-4 text-center font-black text-blue-600 text-[11px] whitespace-nowrap">
                                    <span class="px-2 py-1 bg-blue-50 rounded-md border border-blue-100">{{ $record->formatted_date }}</span>
                                </td>
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-[9px] font-black bg-emerald-50 text-emerald-700 border border-emerald-100 uppercase tracking-widest">
                                         COMMISSIONED
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="relative inline-block" x-data="{ open: false }">
                                        <button @click="open = !open" @click.away="open = false" class="p-1 hover:bg-slate-100 rounded-md transition-colors">
                                            <i data-lucide="more-vertical" class="h-4 w-4 text-slate-400"></i>
                                        </button>
                                        <div x-show="open" 
                                             x-transition:enter="transition ease-out duration-100"
                                             x-transition:enter-start="transform opacity-0 scale-95"
                                             x-transition:enter-end="transform opacity-100 scale-100"
                                             class="absolute right-0 {{ $loop->remaining < 2 ? 'bottom-full mb-1' : 'top-full mt-1' }} w-32 bg-white border border-slate-200 rounded-lg shadow-xl z-50 overflow-hidden">
                                            <button @click="editRecord({{ $record->id }})" class="w-full px-4 py-2 text-left text-xs font-bold text-slate-700 hover:bg-blue-50 hover:text-blue-600 flex items-center gap-2">
                                                <i data-lucide="edit-3" class="h-3.5 w-3.5"></i>
                                                Edit
                                            </button>
                                            <button @click="viewRecord({{ $record->id }})" class="w-full px-4 py-2 text-left text-xs font-bold text-slate-700 hover:bg-blue-50 hover:text-blue-600 flex items-center gap-2">
                                                <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                                                View
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            @if($indexedFiles->isEmpty())
                            <tr>
                                <td colspan="13" class="px-6 py-12 text-center text-slate-400 italic">No records found. Click "Generate New GKN" to start.</td>
                            </tr>
                            @endif
                            @endforelse

                            {{-- Indexed GKN files from file_indexings (registry = Survey, not yet in gkn_file_no) --}}
                            @foreach($indexedFiles as $fi)
                            <tr class="hover:bg-amber-50 transition-colors group" style="background-color: #fffbeb;">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="font-mono font-bold text-blue-600 text-xs whitespace-nowrap">{{ $fi->file_number }}</span>
                                </td>
                                <td class="px-6 py-4 font-medium text-slate-700 whitespace-nowrap">{{ Str::limit($fi->file_title, 30) }}</td>
                                <td class="px-6 py-4 text-center font-mono text-xs whitespace-nowrap">{{ $fi->plot_number ?? '—' }}</td>
                                <td class="px-6 py-4 text-center font-mono text-xs whitespace-nowrap">{{ $fi->tp_no ?? '—' }}</td>
                                <td class="px-6 py-4 text-xs font-bold text-slate-600 whitespace-nowrap">{{ $fi->land_use_type ?? '—' }}</td>
                                <td class="px-6 py-4 text-xs text-slate-400 whitespace-nowrap">—</td>
                                <td class="px-6 py-4 text-xs text-slate-500 whitespace-nowrap">{{ Str::limit($fi->location, 40) }}</td>
                                <td class="px-6 py-4 text-xs font-bold text-slate-600 whitespace-nowrap">{{ $fi->lga ?? '—' }}</td>
                                <td class="px-6 py-4 text-xs whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-lg bg-amber-50 flex items-center justify-center text-[10px] font-black text-amber-600 border border-amber-100">
                                            {{ strtoupper(substr($fi->created_by ?? 'S', 0, 1)) }}
                                        </div>
                                        <span class="font-bold text-slate-600">{{ $fi->created_by ?? 'System' }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center font-black text-amber-600 text-[11px] whitespace-nowrap">
                                    <span class="px-2 py-1 bg-amber-50 rounded-md border border-amber-100">{{ $fi->created_at ? $fi->created_at->format('h:i A') : '—' }}</span>
                                </td>
                                <td class="px-6 py-4 text-center font-black text-amber-700 text-[11px] whitespace-nowrap">
                                    <span class="px-2 py-1 bg-amber-50 rounded-md border border-amber-100">{{ $fi->created_at ? $fi->created_at->format('jS M, Y') : '—' }}</span>
                                </td>
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-[9px] font-black bg-amber-50 text-amber-700 border border-amber-100 uppercase tracking-widest">
                                        FILE INDEXING
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <span class="text-slate-300 text-xs">—</span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($records->hasPages())
                <div class="px-6 py-4 border-t border-slate-100">
                    {{ $records->links() }}
                </div>
                @endif
            </div>

            <!-- Generation Modal -->
            <!-- Generation Modal -->
            <div 
                x-show="showGenerateModal" 
                class="fixed inset-0 z-[100] overflow-y-auto"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                style="display: none;"
                @keydown.escape.window="showGenerateModal = false"
            >
                <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-sm" @click="showGenerateModal = false"></div>

                    <div 
                        class="inline-block w-full max-w-4xl my-8 overflow-hidden text-left align-middle transition-all transform bg-transparent rounded-3xl"
                        x-show="showGenerateModal"
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave="transition ease-in duration-200"
                        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    >
                        <div class="bg-slate-50 flex flex-col max-h-[85vh] shadow-2xl rounded-3xl border border-slate-200 overflow-hidden">
                            <!-- Modal Header -->
                            <div class="bg-white px-8 py-5 border-b border-slate-200 flex justify-between items-center">
                                <div>
                                    <h2 class="text-xl font-bold text-slate-900">Generate GKN File Numbers</h2>
                                    <p class="text-sm text-slate-500">Configure and generate new serial-based records</p>
                                </div>
                                <div class="flex items-center gap-6">
                                    <div class="flex items-center gap-3 bg-slate-50 px-4 py-2 rounded-xl border border-slate-200 shadow-sm">
                                        <span class="text-xs font-bold text-slate-600 uppercase tracking-tight">Batch Mode</span>
                                        <button 
                                            @click="batchMode = !batchMode" 
                                            type="button"
                                            :class="batchMode ? 'bg-blue-600' : 'bg-slate-200'"
                                            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                        >
                                            <span :class="batchMode ? 'translate-x-5' : 'translate-x-0'" class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                                        </button>
                                    </div>
                                    <button @click="showGenerateModal = false" class="text-slate-400 hover:text-red-500 transition-colors">
                                        <i data-lucide="x-circle" class="h-8 w-8"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="flex-1 overflow-y-auto p-8">
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                    <!-- Card 1: File Details -->
                                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                                        <div class="bg-slate-50 px-6 py-3 border-b border-slate-200 flex items-center gap-2">
                                            <i data-lucide="file-text" class="h-4 w-4 text-blue-600"></i>
                                            <h2 class="font-bold text-sm text-slate-800 uppercase tracking-wide">File Details</h2>
                                        </div>
                                        <div class="p-5 space-y-4">
                                            <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Prefix <span class="text-red-500">*</span></label>
                                                <select x-model="formData.type" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm">
                                                    <option value="">Select Prefix</option>
                                                    <option value="GKN">GKN</option>
                                                    <option value="MISCS">MISC KN</option>
                                                    <option value="LPKN">LPKN</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Customer Type <span class="text-red-500">*</span></label>
                                                <select x-model="formData.customer_type" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm">
                                                     <template x-for="ct in customerTypes" :key="ct.customer_type_id">
                                                         <option :value="ct.customer_type_name" x-text="ct.customer_type_name"></option>
                                                     </template>
                                                 </select>
                                             </div>
                                            <div x-show="formData.customer_type === 'Other'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="mt-2 text-left">
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Please Specify Type <span class="text-red-500">*</span></label>
                                                <input type="text" x-model="formData.other_customer_type" placeholder="e.g. NGO, Embassy..." class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">File Title <span class="text-red-500">*</span></label>
                                                <input type="text" x-model="formData.file_title" placeholder="Enter File Title" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm">
                                            </div>
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Land Use <span class="text-red-500">*</span></label>
                                                    <select x-model="formData.land_use_id" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm">
                                                        <option value="">Select Land Use</option>
                                                        <template x-for="lu in landUses" :key="lu.id">
                                                            <option :value="lu.id" x-text="lu.landuse"></option>
                                                        </template>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Purpose <span class="text-red-500">*</span></label>
                                                    <select x-model="formData.purpose_id" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm">
                                                        <option value="">Select Purpose</option>
                                                        <template x-for="p in filteredPurposes" :key="p.id">
                                                            <option :value="p.id" x-text="p.name"></option>
                                                        </template>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Card 2: File Number Details -->
                                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                                        <div class="bg-slate-50 px-6 py-3 border-b border-slate-200 flex items-center gap-2">
                                            <i data-lucide="hash" class="h-4 w-4 text-blue-600"></i>
                                            <h2 class="font-bold text-sm text-slate-800 uppercase tracking-wide">File Number Details</h2>
                                        </div>
                                        <div class="p-5 space-y-4">
                                            <div x-show="batchMode" x-transition>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Batch Quantity</label>
                                                <input type="number" x-model="formData.quantity" min="1" max="50" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm">
                                            </div>

                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Serial Number</label>
                                                    <div class="px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg font-mono text-xs text-slate-600 shadow-inner" 
                                                         x-text="batchMode ? formData.serial_number + ' - ' + (parseInt(formData.serial_number) + parseInt(formData.quantity) - 1) : formData.serial_number"></div>
                                                </div>
                                                <div>
                                                     <label class="block text-xs font-bold text-slate-600 mb-1 uppercase" x-text="(editMode ? 'Full File Number' : (batchMode ? 'Number Range' : 'Full File Number'))"></label>
                                                      <div class="px-4 py-2 bg-blue-50 border border-blue-200 rounded-lg font-mono text-xs text-blue-700 font-bold shadow-inner"
                                                           :class="editMode ? 'opacity-50 select-none' : ''" 
                                                           x-text="editMode ? formData.full_file_number : (batchMode ? (formData.prefix + formData.serial_number + ' - ' + formData.prefix + (parseInt(formData.serial_number) + parseInt(formData.quantity) - 1)) : (formData.prefix + formData.serial_number))"></div>
                                                 </div>
                                            </div>
 
                                            <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Tracking ID</label>
                                                <div class="px-4 py-2 bg-red-50 border border-red-100 rounded-lg font-mono text-xs text-red-600 font-extrabold shadow-inner" 
                                                     x-text="loadingTrackingId ? 'Searching...' : (nextGkn ? (batchMode ? nextGkn.tracking_id + ' (start)' : nextGkn.tracking_id) : 'Not Available')"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Card 3: Location Details -->
                                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                                        <div class="bg-slate-50 px-6 py-3 border-b border-slate-200 flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <i data-lucide="map-pin" class="h-4 w-4 text-blue-600"></i>
                                                <h2 class="font-bold text-sm text-slate-800 uppercase tracking-wide">Location Details</h2>
                                            </div>
                                            <template x-if="batchMode && !editMode">
                                                <div class="flex items-center gap-2">
                                                    <span class="px-2 py-0.5 bg-blue-100 text-blue-700 text-[10px] font-black rounded-full shadow-sm">Entry <span x-text="currentEntryIndex + 1"></span> of <span x-text="formData.quantity"></span></span>
                                                    <div class="flex items-center gap-1.5 ml-1">
                                                        <button @click="previousEntry()" :disabled="currentEntryIndex === 0" type="button" class="w-7 h-7 flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white rounded-full disabled:bg-slate-200 disabled:text-slate-400 transition-all shadow-md active:scale-90">
                                                            <i data-lucide="chevron-left" class="h-4 w-4 stroke-[3]"></i>
                                                        </button>
                                                        <button @click="nextEntry()" :disabled="currentEntryIndex >= locationEntries.length - 1" type="button" class="w-7 h-7 flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white rounded-full disabled:bg-slate-200 disabled:text-slate-400 transition-all shadow-md active:scale-90">
                                                            <i data-lucide="chevron-right" class="h-4 w-4 stroke-[3]"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                        <div class="p-5">
                                            <!-- Batch Sync Controls -->
                                            <template x-if="batchMode && !editMode">
                                                <div class="mb-5 space-y-3 pb-4 border-b border-slate-100">
                                                    <label class="flex items-center gap-2 cursor-pointer group">
                                                        <input type="checkbox" x-model="applyLocationToAll" class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
                                                        <span class="text-xs font-bold text-slate-700 group-hover:text-blue-600 transition-colors uppercase tracking-tight">Apply Location to All Files in Batch</span>
                                                    </label>
                                                    
                                                    <button @click="applyLocationToBatch()" type="button" class="w-full flex items-center justify-center gap-2.5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-[11px] font-black uppercase tracking-wider rounded-xl transition-all active:scale-[0.98] shadow-lg shadow-blue-100/50">
                                                        <i data-lucide="layers" class="h-4 w-4"></i>
                                                        <span>Apply Current Location to All <span x-text="formData.quantity"></span> Files</span>
                                                    </button>
                                                </div>
                                            </template>

                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Plot Number</label>
                                                    <input type="text" 
                                                           x-model="current.plot_number"
                                                           :disabled="viewMode"
                                                           @input="if(applyLocationToAll) syncToAll('plot_number')"
                                                           placeholder="e.g., 402" 
                                                           class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50 disabled:text-slate-500">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">TP Number</label>
                                                    <input type="text" 
                                                           x-model="current.tp_no"
                                                           :disabled="viewMode"
                                                           @input="if(applyLocationToAll) syncToAll('tp_no')"
                                                           placeholder="e.g., TP/1024" 
                                                           class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50 disabled:text-slate-500">
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase text-left">District</label>
                                                    <select x-model="current.district_id" 
                                                            :disabled="viewMode"
                                                            @change="if(applyLocationToAll) syncToAll('district_id')"
                                                            class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50 disabled:text-slate-500 appearance-none bg-no-repeat bg-[right_1rem_center] bg-[length:1em_1em]"
                                                            style="background-image: url('data:image/svg+xml;charset=utf-8,%3Csvg xmlns=%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22 fill=%22none%22 viewBox=%220 0 20 20%22%3E%3Cpath stroke=%22%236b7280%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22 stroke-width=%221.5%22 d=%22m6 8 4 4 4-4%22%2F%3E%3C%2Fsvg%3E')">
                                                        <option value="">Select District</option>
                                                        <template x-for="district in districts" :key="district.id">
                                                            <option :value="district.id" x-text="district.name"></option>
                                                        </template>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase text-left">LGA</label>
                                                    <select x-model="current.lga_id" 
                                                            :disabled="viewMode"
                                                            @change="if(applyLocationToAll) syncToAll('lga_id')"
                                                            class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50 disabled:text-slate-500 appearance-none bg-no-repeat bg-[right_1rem_center] bg-[length:1em_1em]"
                                                            style="background-image: url('data:image/svg+xml;charset=utf-8,%3Csvg xmlns=%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22 fill=%22none%22 viewBox=%220 0 20 20%22%3E%3Cpath stroke=%22%236b7280%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22 stroke-width=%221.5%22 d=%22m6 8 4 4 4-4%22%2F%3E%3C%2Fsvg%3E')">
                                                        <option value="">Select LGA</option>
                                                        <template x-for="lga in lgas" :key="lga.id">
                                                            <option :value="lga.id" x-text="lga.name"></option>
                                                        </template>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="md:col-span-2">
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase tracking-tight">Location Preview (Auto-Generated)</label>
                                                    <textarea x-text="location" rows="1" readonly class="w-full bg-slate-50 border-slate-200 rounded-lg text-slate-500 px-4 py-2.5 text-xs font-medium italic shadow-inner outline-none"></textarea>
                                                </div>
                                            </div>
                                        </div>

                                    <!-- Card 4: Metadata -->
                                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                                        <div class="bg-slate-50 px-6 py-3 border-b border-slate-200 flex items-center gap-2">
                                            <i data-lucide="info" class="h-4 w-4 text-blue-600"></i>
                                            <h2 class="font-bold text-sm text-slate-800 uppercase tracking-wide">Generation Metadata</h2>
                                        </div>
                                        <div class="p-5 space-y-4">
                                            <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Commissioning Date</label>
                                                <div class="px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-600 flex items-center gap-2">
                                                    <i data-lucide="calendar" class="h-3 w-3"></i>
                                                    <span x-text="currentDate"></span>
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Commissioning Time</label>
                                                <div class="px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-600 flex items-center gap-2">
                                                    <i data-lucide="clock" class="h-3 w-3"></i>
                                                    <span x-text="currentTimeOnly"></span>
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Commissioned By</label>
                                                <div class="px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-600 flex items-center gap-2">
                                                    <i data-lucide="user" class="h-3 w-3"></i>
                                                    <span>{{ $user->name ?? $user->email }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div> 
                                </div>
                            </div> <!-- End of Modal Body (overflow-y-auto) -->

                            <!-- Modal Footer -->
                            <div class="px-8 py-5 border-t border-slate-100 bg-white flex items-center justify-end gap-3 sticky bottom-0 z-10 rounded-b-3xl">
                                <button @click="showGenerateModal = false" type="button" class="px-6 py-2.5 text-[11px] font-black uppercase tracking-wider text-slate-500 hover:text-slate-700 hover:bg-slate-50 rounded-xl transition-all">
                                    <span>Cancel</span>
                                </button>
                                <button @click="generateGkn()" :disabled="loading || !nextGkn || nextGkn.tracking_id === 'Not Found' || nextGkn.tracking_id === 'Error' || loadingTrackingId" type="button" class="px-8 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-slate-400 disabled:cursor-not-allowed text-white text-[11px] font-black uppercase tracking-widest rounded-xl transition-all active:scale-95 shadow-lg shadow-blue-100 flex items-center gap-2 min-w-[140px] justify-center">
                                    <template x-if="loading">
                                            <i data-lucide="loader-2" class="h-4 w-4 animate-spin"></i>
                                    </template>
                                    <span x-text="loading ? 'GENERATING...' : 'GENERATE'">GENERATE GKN FileNo</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit/View Modal -->
            <div 
                x-show="showEditModal" 
                class="fixed inset-0 z-[100] overflow-y-auto"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                style="display: none;"
                @keydown.escape.window="showEditModal = false"
            >
                <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-sm" @click="showEditModal = false"></div>

                    <div 
                        class="inline-block w-full max-w-4xl my-8 overflow-hidden text-left align-middle transition-all transform bg-transparent rounded-3xl"
                        x-show="showEditModal"
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave="transition ease-in duration-200"
                        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    >
                        <div class="bg-slate-50 flex flex-col max-h-[85vh] shadow-2xl rounded-3xl border border-slate-200 overflow-hidden">
                            <!-- Modal Header -->
                            <div class="bg-white px-8 py-5 border-b border-slate-200 flex justify-between items-center">
                                <div>
                                    <h2 class="text-xl font-bold text-slate-900" x-text="viewMode ? 'View GKN Record' : 'Edit GKN Record'"></h2>
                                    <p class="text-sm text-slate-500" x-text="viewMode ? 'Viewing metadata for ' + formData.full_file_number : 'Updating metadata for ' + formData.full_file_number"></p>
                                </div>
                                <button @click="showEditModal = false" class="text-slate-400 hover:text-red-500 transition-colors">
                                    <i data-lucide="x-circle" class="h-8 w-8"></i>
                                </button>
                            </div>

                            <div class="flex-1 overflow-y-auto p-8 relative min-h-[400px]">
                                <!-- Loading Overlay -->
                                <div x-show="loading" 
                                     class="absolute inset-0 z-50 bg-white/80 backdrop-blur-[2px] flex flex-col items-center justify-center gap-4 transition-all"
                                     x-transition:enter="transition ease-out duration-300"
                                     x-transition:enter-start="opacity-0"
                                     x-transition:enter-end="opacity-100"
                                >
                                    <div class="relative">
                                        <div class="w-16 h-16 border-4 border-blue-100 border-t-blue-600 rounded-full animate-spin"></div>
                                        <div class="absolute inset-0 flex items-center justify-center">
                                            <i data-lucide="file-text" class="h-6 w-6 text-blue-600 animate-pulse"></i>
                                        </div>
                                    </div>
                                    <div class="flex flex-col items-center">
                                        <span class="text-sm font-black text-slate-800 uppercase tracking-widest">Fetching Data</span>
                                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter mt-1">Please wait a moment...</span>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6" x-show="!loading" x-transition>
                                    <!-- Card 1: File Details -->
                                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                                        <div class="bg-slate-50 px-6 py-3 border-b border-slate-200 flex items-center gap-2">
                                            <i data-lucide="file-text" class="h-4 w-4 text-blue-600"></i>
                                            <h2 class="font-bold text-sm text-slate-800 uppercase tracking-wide">File Details</h2>
                                        </div>
                                        <div class="p-5 space-y-4">
                                            <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Full File Number</label>
                                                <div class="px-4 py-2 bg-blue-50 border border-blue-100 rounded-lg font-mono text-xs text-blue-700 font-bold shadow-inner" x-text="formData.full_file_number"></div>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Customer Type <span class="text-red-500">*</span></label>
                                                <select x-model="formData.customer_type" :disabled="viewMode" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50">
                                                     <template x-for="ct in customerTypes" :key="ct.customer_type_id">
                                                         <option :value="ct.customer_type_name" x-text="ct.customer_type_name"></option>
                                                     </template>
                                                 </select>
                                             </div>
                                            <div x-show="formData.customer_type === 'Other'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="mt-2 text-left">
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Please Specify Type <span class="text-red-500">*</span></label>
                                                <input type="text" x-model="formData.other_customer_type" :disabled="viewMode" placeholder="e.g. NGO, Embassy..." class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">File Title <span class="text-red-500">*</span></label>
                                                <input type="text" x-model="formData.file_title" :disabled="viewMode" placeholder="Enter File Title" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50 disabled:text-slate-500">
                                            </div>
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Land Use <span class="text-red-500">*</span></label>
                                                    <select x-model="formData.land_use_id" :disabled="viewMode" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50">
                                                        <option value="">Select Land Use</option>
                                                        <template x-for="lu in landUses" :key="lu.id">
                                                            <option :value="lu.id" x-text="lu.landuse"></option>
                                                        </template>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Purpose <span class="text-red-500">*</span></label>
                                                    <select x-model="formData.purpose_id" :disabled="viewMode" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50">
                                                        <option value="">Select Purpose</option>
                                                        <template x-for="p in filteredPurposes" :key="p.id">
                                                            <option :value="p.id" x-text="p.name"></option>
                                                        </template>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Card 2: Location Details -->
                                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                                        <div class="bg-slate-50 px-6 py-3 border-b border-slate-200 flex items-center gap-2">
                                            <i data-lucide="map-pin" class="h-4 w-4 text-blue-600"></i>
                                            <h2 class="font-bold text-sm text-slate-800 uppercase tracking-wide">Location Details</h2>
                                        </div>
                                        <div class="p-5 space-y-4">
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Plot Number</label>
                                                    <input type="text" x-model="formData.plot_number" :disabled="viewMode" placeholder="e.g., 402" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">TP Number</label>
                                                    <input type="text" x-model="formData.tp_no" :disabled="viewMode" placeholder="e.g., TP/1024" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50">
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">District</label>
                                                    <select x-model="formData.district_id" :disabled="viewMode" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50">
                                                        <option value="">Select District</option>
                                                        <template x-for="district in districts" :key="district.id">
                                                            <option :value="district.id" x-text="district.name"></option>
                                                        </template>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">LGA</label>
                                                    <select x-model="formData.lga_id" :disabled="viewMode" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50">
                                                        <option value="">Select LGA</option>
                                                        <template x-for="lga in lgas" :key="lga.id">
                                                            <option :value="lga.id" x-text="lga.name"></option>
                                                        </template>
                                                    </select>
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase italic opacity-60">Location Preview</label>
                                                <textarea x-text="location" rows="1" readonly class="w-full bg-slate-50 border-slate-100 rounded-lg text-slate-400 px-4 py-2 text-xs font-medium italic outline-none"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                                <div class="mt-8 px-8 py-5 border-t border-slate-100 bg-white flex items-center justify-end gap-3 font-[Inter] sticky bottom-0 z-10 rounded-b-3xl">
                                    <button @click="showEditModal = false" class="px-6 py-2.5 text-[11px] font-black uppercase tracking-wider text-slate-500 hover:text-slate-700 hover:bg-slate-50 rounded-xl transition-all">
                                        <span x-text="viewMode ? 'Close' : 'Cancel'"></span>
                                    </button>
                                    <button x-show="!viewMode" @click="updateRecord()" :disabled="loading" class="px-8 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-slate-400 disabled:cursor-not-allowed text-white text-[11px] font-black uppercase tracking-widest rounded-xl transition-all active:scale-95 shadow-lg shadow-blue-100 flex items-center gap-2 min-w-[140px] justify-center">
                                        <template x-if="loading">
                                             <i data-lucide="loader-2" class="h-4 w-4 animate-spin"></i>
                                        </template>
                                        <span x-text="loading ? 'UPDATING' : 'UPDATE'">UPDATE</span>
                                    </button>
                                </div>
                            </div>
        </div>
    </div>

    <!-- Batch Members Modal -->
    <div x-show="batchModalOpen" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" style="display: none;">
        
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden" @click.away="batchModalOpen = false">
            <div class="px-8 py-5 border-b border-slate-100 flex items-center justify-between bg-white sticky top-0 z-10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-blue-50 flex items-center justify-center">
                        <i data-lucide="layers" class="h-5 w-5 text-blue-600"></i>
                    </div>
                        <div>
                            <h2 class="text-lg font-black text-slate-800 uppercase tracking-tight">Batch GKN Records</h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest">Viewing all records in batch #<span x-text="currentBatchNo"></span></p>
                        </div>
                </div>
                <button @click="batchModalOpen = false" class="p-2.5 hover:bg-slate-100 rounded-xl transition-all text-slate-400 hover:text-slate-600">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-8">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/50 border-b border-slate-200 text-[9px] font-black text-slate-500 uppercase tracking-widest">
                            <th class="px-4 py-3 whitespace-nowrap">File Number</th>
                            <th class="px-4 py-3 text-center whitespace-nowrap">Plot No</th>
                            <th class="px-4 py-3 whitespace-nowrap">Location</th>
                            <th class="px-4 py-3 whitespace-nowrap">LGA</th>
                            <th class="px-4 py-3 text-center whitespace-nowrap">Time</th>
                            <th class="px-4 py-3 text-center whitespace-nowrap">Date</th>
                            <th class="px-4 py-3 text-right whitespace-nowrap">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-[11px]">
                        <template x-for="member in batchMembers" :key="member.id">
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-4 py-3 font-mono font-bold text-blue-600 whitespace-nowrap" x-text="member.full_file_number"></td>
                                <td class="px-4 py-3 text-center font-mono whitespace-nowrap" x-text="member.plot_no || '—'"></td>
                                <td class="px-4 py-3 text-slate-500 whitespace-nowrap" x-text="member.location"></td>
                                <td class="px-4 py-3 font-bold whitespace-nowrap" x-text="member.lga || '—'"></td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <span class="px-1.5 py-0.5 bg-slate-100 rounded text-slate-600 font-bold" x-text="member.formatted_time"></span>
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <span class="px-1.5 py-0.5 bg-blue-50 rounded text-blue-600 font-bold" x-text="member.formatted_date"></span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <div class="flex justify-end gap-2">
                                        <button @click="editRecordFromBatch(member.id)" class="p-1.5 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-600 hover:text-white transition-all shadow-sm">
                                            <i data-lucide="edit-3" class="h-3 w-3"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div> <!-- End of Modal Body (overflow-y-auto) -->

            <!-- Modal Footer -->
            <div class="px-8 py-5 border-t border-slate-100 bg-white flex items-center justify-end sticky bottom-0 z-10 rounded-b-3xl">
                <button @click="batchModalOpen = false" type="button" class="px-8 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] font-black uppercase tracking-widest rounded-xl transition-all active:scale-95 shadow-sm">
                    Close
                </button>
            </div>
        </div>
    </div>



    @include('admin.footer')
</div>

@push('scripts')
<script src="{{ asset('js/gkn_generation.js') }}"></script>
@endpush
@endsection

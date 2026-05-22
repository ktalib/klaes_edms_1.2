@php
    $showVariable = $showVariable ?? 'showSerialModal';
    $title = $title ?? 'Serial Number Control';
    $type = $type ?? 'dciv';
    $prefixes = $prefixes ?? [];
    $year = $year ?? date('Y');
@endphp
<style>
    /* Ensures SweetAlert popups triggered inside this modal render on top of it */
    .swal-above-modal.swal2-container { z-index: 100000 !important; }
</style>

<div x-show="{{ $showVariable }}" 
     x-cloak
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 bg-black bg-opacity-60 overflow-y-auto h-full w-full z-[9999]"
     style="display: none;">
    <div class="relative top-10 mx-auto p-0 border w-full max-w-5xl shadow-2xl rounded-2xl bg-white overflow-hidden transform transition-all"
         @click.away="{{ $showVariable }} = false">
        <!-- Modal Header -->
        <div class="bg-white border-b border-slate-100 px-8 py-6 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="p-2 bg-indigo-50 rounded-lg">
                    <i data-lucide="settings" class="w-6 h-6 text-indigo-600"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-slate-800 tracking-tight">{{ $title }}</h3>
                    <p class="text-slate-500 text-sm mt-0.5">Manage initialization and locking of serial numbers for year {{ $year }}</p>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <button @click="refreshSerialControl()" 
                        class="text-slate-600 hover:text-indigo-600 flex items-center space-x-2 text-sm bg-white border border-slate-200 hover:bg-slate-50 hover:border-indigo-200 px-3 py-1.5 rounded-lg transition-all shadow-sm"
                        :class="{ 'animate-pulse': refreshing }">
                    <i data-lucide="refresh-cw" class="w-4 h-4" :class="{ 'animate-spin': refreshing }"></i>
                    <span>Refresh</span>
                </button>
                <button type="button" @click="{{ $showVariable }} = false" class="text-slate-400 hover:text-slate-600 transition-colors p-2 hover:bg-slate-100 rounded-full">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
        </div>

        <div class="p-8">
            <!-- Warning Alert -->
            <div class="bg-amber-50 border-l-4 border-amber-400 p-4 mb-8 rounded-r-xl shadow-sm">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i data-lucide="alert-triangle" class="h-5 w-5 text-amber-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-amber-800">
                            <span class="font-bold">Important:</span> Serial initialization is a <span class="underline">one-time</span> operation. Once initialized and locked, values cannot be modified through the UI to prevent system errors and duplicate file numbers.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Serial Control Table -->
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200">
                                <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Prefix</th>
                                @if($type === 'dciv')
                                <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Year</th>
                                @endif
                                <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Last Serial</th>
                                <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Initialized By</th>
                                <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Initialized At</th>
                                <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($prefixes as $prefix)
                                @php
                                    $status = $serialStatuses[$prefix] ?? null;
                                    $isInitialized = $status ? $status->is_initialized : false;
                                    $isLocked = $status ? $status->is_locked : false;
                                    
                                    // Map MISCS to MISC KN for display
                                    $prefixLabel = $prefix === 'MISCS' ? 'MISC KN' : $prefix;
                                @endphp
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="font-bold text-slate-700 tracking-wide">{{ $prefixLabel }}</span>
                                    </td>
                                    @if($type === 'dciv')
                                    <td class="px-6 py-4 whitespace-nowrap text-slate-600 font-medium">
                                        {{ $year }}
                                    </td>
                                    @endif
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($isInitialized || $isLocked)
                                            <div class="bg-slate-100 px-3 py-1.5 rounded border border-slate-200 w-24 text-center font-mono font-bold text-slate-700">
                                                {{ $status->last_serial }}
                                            </div>
                                        @else
                                            <input type="number" 
                                                   id="serial_start_{{ $type }}_{{ $prefix }}" 
                                                   class="w-24 px-3 py-1.5 border border-slate-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono text-center font-bold"
                                                   placeholder="e.g. 0">
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-xs">
                                        @if($isLocked)
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700 border border-green-200">
                                                <i data-lucide="lock" class="w-3 h-3 mr-1"></i>
                                                Locked
                                            </span>
                                        @elseif($isInitialized)
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-700 border border-blue-200">
                                                <i data-lucide="check-circle" class="w-3 h-3 mr-1"></i>
                                                Initialized
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-500 border border-slate-200">
                                                <i data-lucide="circle" class="w-3 h-3 mr-1"></i>
                                                Pending
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                                        {{ ($status && $status->createdBy) ? $status->createdBy->name : ($isInitialized ? 'System' : '--') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 font-mono text-xs">
                                        {{ $status ? $status->created_at->format('M j, Y H:i') : '--' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        @if(!$isInitialized && !$isLocked)
                                            <button @click="submitRowInitialization('{{ $prefix }}')" 
                                                    class="inline-flex items-center px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold transition-all shadow-sm hover:shadow-md active:scale-95 space-x-1">
                                                <i data-lucide="play-circle" class="w-3.5 h-3.5"></i>
                                                <span>Initialize</span>
                                            </button>
                                        @else
                                            <button disabled class="inline-flex items-center px-4 py-1.5 bg-slate-100 text-slate-400 rounded-lg text-xs font-bold cursor-not-allowed opacity-60 border border-slate-200">
                                                <i data-lucide="lock" class="w-3.5 h-3.5 mr-1"></i>
                                                <span>Locked</span>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="px-8 py-5 bg-slate-50 flex items-center justify-between border-t border-slate-200 text-slate-500 text-sm italic">
            <span class="flex items-center">
                <i data-lucide="info" class="w-4 h-4 mr-2 text-slate-400"></i>
                All operations are logged for audit purposes.
            </span>
            <button type="button" @click="{{ $showVariable }} = false" 
                    class="px-6 py-2 bg-white border border-slate-300 rounded-xl text-slate-700 font-bold hover:bg-slate-50 transition-all shadow-sm active:scale-95">
                Close Panel
            </button>
        </div>
    </div>
</div>

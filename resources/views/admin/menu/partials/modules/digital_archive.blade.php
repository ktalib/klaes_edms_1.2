    <!-- 2. Digital Files -->
    @if(
      $hasRole('Log a File') || $hasRole('File Tracker/Tracking - RFID') || $hasRole('File Digital Library - Doc-WARE') || $hasRole('EDMS Update')
    )
    <div class="py-1 px-3 mb-0.5 border-t border-slate-100">
      <div class="sidebar-module-header flex items-center justify-between py-2 px-3 mb-0.5 cursor-pointer hover:bg-slate-50 rounded-md" data-module="digitalFiles">
      <div class="flex items-center gap-2">
        <i data-lucide="folder" class="h-5 w-5 text-blue-600"></i>
        <span class="text-sm font-bold uppercase tracking-wider">Digital File Archive</span>
      </div>
      <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="digitalFiles"></i>
      </div>
    
      <div class="pl-4 mt-1 space-y-0.5 hidden" data-content="digitalFiles">

         <a href="{{ route('file-tracker.dashboard') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('file-tracker.dashboard') && !request('url') ? 'active' : '' }}">
        <i data-lucide="radio-tower" class="h-4 w-4 text-blue-500"></i>
        <span>File Tracker Dashboard</span>
      </a>
    <a href="{{ route('track-file-archive.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('track-file-archive.index') && !request('url') ? 'active' : '' }}">
        <i data-lucide="radio-tower" class="h-4 w-4 text-blue-500"></i>
        <span>Track File (Archive)</span>
      </a>

      <a href="{{ route('create-file-tracker.quick-search') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('create-file-tracker.quick-search') && !request('url') ? 'active' : '' }}">
        <i data-lucide="search" class="h-4 w-4 text-indigo-500"></i>
        <span>Quick Search</span>
      </a>

      @if($hasRole('Log a File'))
      <a href="{{ route('create-file-tracker.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('create-file-tracker.index') && !request('url') ? 'active' : '' }}">
        <i data-lucide="file-plus" class="h-4 w-4 text-blue-500"></i>
        <span>Log a File</span>
      </a>
      @endif

      @if($hasRole('File Tracker/Tracking - RFID'))
      {{-- <a href="{{ route('filetracker.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('filetracker.index') ? 'active' : '' }}">
        <i data-lucide="radio-tower" class="h-4 w-4 text-blue-500"></i>
        <span>File Tracker/Tracking</span>
      </a> --}}
      @endif

      @if($hasRole('File Digital Library - Doc-WARE'))
      <a href="{{ route('filearchive.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('filearchive.index') && !request('url') ? 'active' : '' }}">
        <i data-lucide="archive" class="h-4 w-4 text-blue-500"></i>
        <span>File Digital Library - Doc-WARE</span>
      </a>
      @endif

      @if($hasRole('EDMS Update'))
      <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="digitalFilesEdmsUpdate">
        <div class="flex items-center gap-2">
        <i data-lucide="refresh-cw" class="h-4 w-4 text-blue-500"></i>
        <span>EDMS Update</span>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="digitalFilesEdmsUpdate"></i>
      </div>

      <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="digitalFilesEdmsUpdate">
        <a href="/scanning?url=scmore" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
        <i data-lucide="scan-line" class="h-3.5 w-3.5 text-blue-400"></i>
        <span>Scan More</span>
        </a>

        <a href="/pagetyping?url=ptmore" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
        <i data-lucide="file-text" class="h-3.5 w-3.5 text-blue-400"></i>
        <span>Type More</span>
        </a>
      </div>
      @endif
      </div>
    </div>
    @endif

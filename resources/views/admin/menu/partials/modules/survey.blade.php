    <!-- 9. Survey -->

     
    @if(
      $hasRole('Survey - Records') || $hasRole('Survey â€“ AI Digital Assistant') || 
      $hasRole('Survey - GIS') || $hasRole('Survey - Approvals') || 
      $hasRole('Survey - E-Registry') || $hasRole('Survey Reports') || $hasRole('Generate New FileNo (GKNFileNo)')
    )
    <div class="py-1 px-3 mb-0.5 border-t border-slate-100">
      <div class="sidebar-module-header flex items-center justify-between py-2 px-3 mb-0.5 cursor-pointer hover:bg-slate-50 rounded-md" data-module="survey">
        <div class="flex items-center gap-2">
          <i data-lucide="compass" class="h-5 w-5 text-pink-600"></i>
          <span class="text-sm font-bold uppercase tracking-wider">Survey</span>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="survey"></i>
      </div>

      <div class="pl-4 mt-1 space-y-0.5 hidden" data-content="survey">

          @if($hasRole('Generate New FileNo (GKNFileNo)'))
        <a href="{{ route('gkn-generation.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('gkn-generation.index') ? 'active' : '' }}">
          <i data-lucide="plus-square" class="h-4 w-4 text-pink-500"></i>
          <span>Generate New FileNo (GKNFileNo)</span>
        </a>
        @endif

        @if($hasRole('Survey - Records'))
        <a href="{{route('survey_record.index')}}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey_record.index') ? 'active' : '' }}">
          <i data-lucide="clipboard" class="h-4 w-4 text-pink-500"></i>
          <span>Records</span>
        </a>
        @endif
        @if($hasRole('Survey - GIS'))
        <a href="/survey/gis" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
          <i data-lucide="map" class="h-4 w-4 text-pink-500"></i>
          <span>GIS</span>
        </a>
        @endif
        @if($hasRole('Survey - Approvals'))
        <a href="/survey/approvals" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
          <i data-lucide="check-circle" class="h-4 w-4 text-pink-500"></i>
          <span>Approvals</span>
        </a>
        @endif
        @if($hasRole('Survey - E-Registry'))
        <a href="/survey/e-registry" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
          <i data-lucide="database" class="h-4 w-4 text-pink-500"></i>
          <span>E-Registry</span>
        </a>
        @endif

        @if($hasRole('Survey Reports'))
        <a href="{{ route('survey_plan_extraction.index') }}?url=survey" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200  {{ request()->routeIs('survey_plan_extraction.index') && request()->query('url') === 'survey' ? 'active' : '' }}">
          <i data-lucide="sparkles" class="h-4 w-4 text-pink-500"></i>
          <span>Survey Plan Extraction</span>
        </a>
        @endif

        {{-- f. Digital Archive --}}
        @if($hasRole('Survey - E-Registry') || $hasRole('Supper Admin'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="digitalArchive-survey">
          <div class="flex items-center gap-2">
            <i data-lucide="archive" class="h-4 w-4 text-pink-500"></i>
            <span>Digital Archive</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="digitalArchive-survey"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="digitalArchive-survey">
          {{-- i. File Tracker Dashboard --}}
          <a href="{{ route('file-tracker.dashboard', ['url' => 'survey']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('file-tracker.dashboard') && request('url') === 'survey' ? 'active' : '' }}">
            <i data-lucide="bar-chart-2" class="h-3.5 w-3.5 text-pink-400"></i>
            <span>File Tracker Dashboard</span>
          </a>

          {{-- ii. File Tracker (Archive) --}}
          <a href="{{ route('track-file-archive.index', ['url' => 'survey']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('track-file-archive.index') && request('url') === 'survey' ? 'active' : '' }}">
            <i data-lucide="archive" class="h-3.5 w-3.5 text-pink-400"></i>
            <span>File Tracker (Archive)</span>
          </a>

          {{-- iii. Quick Search --}}
          <a href="{{ route('create-file-tracker.quick-search', ['url' => 'survey']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('create-file-tracker.quick-search') && request('url') === 'survey' ? 'active' : '' }}">
            <i data-lucide="search" class="h-3.5 w-3.5 text-pink-400"></i>
            <span>Quick Search</span>
          </a>

          {{-- iv. Log a File --}}
          <a href="{{ route('create-file-tracker.index', ['url' => 'survey']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('create-file-tracker.index') && request('url') === 'survey' ? 'active' : '' }}">
            <i data-lucide="file-plus" class="h-3.5 w-3.5 text-pink-400"></i>
            <span>Log a File</span>
          </a>

          {{-- v. File Digital Library – Doc-WARE --}}
          <a href="{{ route('filearchive.index', ['url' => 'survey']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('filearchive.index') && request('url') === 'survey' ? 'active' : '' }}">
            <i data-lucide="library" class="h-3.5 w-3.5 text-pink-400"></i>
            <span>File Digital Library – Doc-WARE</span>
          </a>

          {{-- vi. EDMS Update --}}
          <a href="#"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
            <i data-lucide="refresh-cw" class="h-3.5 w-3.5 text-pink-400"></i>
            <span>EDMS Update</span>
          </a>
        </div>
        @endif

        @if($hasRole('Survey Reports'))
        <a href="/survey/reports" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
          <i data-lucide="file-bar-chart" class="h-4 w-4 text-pink-500"></i>
          <span>Survey Reports</span>
        </a>
        @endif

    
      </div>
    </div>
    @endif

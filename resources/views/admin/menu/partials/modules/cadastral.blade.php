<!-- 10. Cadastral -->
@if(
    $hasRole('Cad - Records') || $hasRole('Cad - GIS') || $hasRole('Cad - Approvals') ||
    $hasRole('Cad - E-Registry') || $hasRole('Cadastral Reports')|| $hasRole('Match Existing FileNo (MLSFileNo) ') ||
    $hasRole('Deeds – Property Index Cards Assistant (Legacy Records)') || $hasRole('Cad - Digital Archive') || $hasRole('Supper Admin')
  )
  <div class="py-1 px-3 mb-0.5 border-t border-slate-100">
    <div
      class="sidebar-module-header flex items-center justify-between py-2 px-3 mb-0.5 cursor-pointer hover:bg-slate-50 rounded-md"
      data-module="cadastral">
      <div class="flex items-center gap-2">
        <i data-lucide="map" class="h-5 w-5 text-rose-600"></i>
        <span class="text-sm font-bold uppercase tracking-wider">Cadastral</span>
      </div>
      <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="cadastral"></i>
    </div>

    <div class="pl-4 mt-1 space-y-0.5 hidden" data-content="cadastral">

      <!-- a. Commission Correspondence File (Match MLSFileNo) -->
      @if($hasRole('Match Existing FileNo (MLSFileNo) '))
        <a href="{{ route('mls-file-no-matching.index') }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('mls-file-no-matching.index') ? 'active' : '' }}">
          <i data-lucide="check" class="h-4 w-4 text-rose-500"></i>
          <span>Commission Correspondence File (Match MLSFileNo)</span>
        </a>
      @endif

      <!-- b. Cadastral Print File Label -->
      <a href="{{ route('cadastral_printlabel.index') }}"
        class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('cadastral_printlabel.index') ? 'active' : '' }}">
        <i data-lucide="printer" class="h-4 w-4 text-rose-500"></i>
        <span>Cadastral Print File Label</span>
      </a>

      <!-- c. Title Status -->
      <a href="{{ route('title-status.index') }}?url=cadastral"
        class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('title-status.index') && request('url') === 'cadastral' ? 'active' : '' }}">
        <i data-lucide="badge-check" class="h-4 w-4 text-rose-500"></i>
        <span>Title Status Update</span>
      </a>

      <!-- c. Cadastral to Lands (Lands 12) -->
      <a href="{{ route('survey-report.index') }}?url=cadastral"
        class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-report.index') && request()->query('url') === 'cadastral' ? 'active' : '' }}">
        <i data-lucide="file-text" class="h-4 w-4 text-rose-500"></i>
        <span>Cadastral to Land (Land 12)</span>
      </a>

      <!-- c. Records -->
      @if($hasRole('Cad - Records'))
        <a href="{{route('survey_cadastral.index')}}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey_cadastral.index') ? 'active' : '' }}">
          <i data-lucide="clipboard" class="h-4 w-4 text-rose-500"></i>
          <span>Records</span>
        </a>
      @endif

      <!-- d. Property Index Cards Assistant (Legacy Records) -->
      @if($hasRole('Deeds – Property Index Cards Assistant (Legacy Records)'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="propertyIndexCards-cadastral">
          <div class="flex items-center gap-2">
            <i data-lucide="id-card" class="h-4 w-4 text-rose-500"></i>
            <span>Property Index Cards Assistant (Legacy Records)</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="propertyIndexCards-cadastral"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="propertyIndexCards-cadastral">
          <!-- i. Table -->
          <a href="{{ route('property_index_card.index') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('property_index_card.index') ? 'active' : '' }}">
            <i data-lucide="table" class="h-3.5 w-3.5 text-rose-400"></i>
            <span>Table</span>
          </a>

          <!-- ii. Index Cards -->
          <a href="{{ route('cadastral.index-cards.index') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('cadastral.index-cards.index') ? 'active' : '' }}">
            <i data-lucide="id-card" class="h-3.5 w-3.5 text-rose-400"></i>
            <span>Index Cards</span>
          </a>
        </div>
      @endif

      <!-- e. Survey Plan Extraction -->
      @if($hasRole('Cadastral Reports'))
        <a href="{{ route('survey_plan_extraction.index') }}?url=survey"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey_plan_extraction.index') && request()->query('url') === 'survey' ? 'active' : '' }}">
          <i data-lucide="sparkles" class="h-4 w-4 text-rose-500"></i>
          <span>Survey Plan Extraction</span>
        </a>
      @endif

      <!-- f. GIS -->
      @if($hasRole('Cad - GIS'))
        <a href="/cadastral/gis"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->is('cadastral/gis*') ? 'active' : '' }}">
          <i data-lucide="map" class="h-4 w-4 text-rose-500"></i>
          <span>GIS</span>
        </a>
      @endif

      <!-- g. Approvals -->
      @if($hasRole('Cad - Approvals'))
        <a href="/cadastral/approvals"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->is('cadastral/approvals*') ? 'active' : '' }}">
          <i data-lucide="check-circle" class="h-4 w-4 text-rose-500"></i>
          <span>Approvals</span>
        </a>
      @endif

      <!-- h. File Digital Library – Doc-WARE -->
      @if($hasRole('Cad - Digital Archive') || $hasRole('Supper Admin'))
        <a href="{{ route('filearchive.index', ['url' => 'cadastral']) }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('filearchive.index') && request('url') === 'cadastral' ? 'active' : '' }}">
          <i data-lucide="library" class="h-4 w-4 text-rose-500"></i>
          <span>File Digital Library – Doc-WARE</span>
        </a>
      @endif

      <!-- i. Digital Archive -->
      @if($hasRole('Cad - Digital Archive') || $hasRole('Supper Admin'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="digitalArchive-cadastral">
          <div class="flex items-center gap-2">
            <i data-lucide="archive" class="h-4 w-4 text-rose-500"></i>
            <span>Digital Archive</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="digitalArchive-cadastral"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="digitalArchive-cadastral">
          <!-- i. File Tracker Dashboard -->
          <a href="{{ route('file-tracker.dashboard', ['url' => 'cadastral']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('file-tracker.dashboard') && request('url') === 'cadastral' ? 'active' : '' }}">
            <i data-lucide="bar-chart-2" class="h-3.5 w-3.5 text-rose-400"></i>
            <span>File Tracker Dashboard</span>
          </a>

          <!-- ii. File Tracker (Archive) -->
          <a href="{{ route('track-file-archive.index', ['url' => 'cadastral']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('track-file-archive.index') && request('url') === 'cadastral' ? 'active' : '' }}">
            <i data-lucide="archive" class="h-3.5 w-3.5 text-rose-400"></i>
            <span>File Tracker (Archive)</span>
          </a>

          <!-- iii. Quick Search (scoped to Cadastral registry files) -->
          <a href="{{ route('create-file-tracker.quick-search', ['url' => 'cadastral']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('create-file-tracker.quick-search') && request('url') === 'cadastral' ? 'active' : '' }}">
            <i data-lucide="search" class="h-3.5 w-3.5 text-rose-400"></i>
            <span>Quick Search</span>
          </a>

          <!-- iv. Log a File -->
          <a href="{{ route('create-file-tracker.index', ['url' => 'cadastral']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('create-file-tracker.index') && request('url') === 'cadastral' ? 'active' : '' }}">
            <i data-lucide="file-plus" class="h-3.5 w-3.5 text-rose-400"></i>
            <span>Log a File</span>
          </a>

          <!-- v. EDMS Update -->
          <a href="#"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
            <i data-lucide="refresh-cw" class="h-3.5 w-3.5 text-rose-400"></i>
            <span>EDMS Update</span>
          </a>
        </div>
      @endif

      <!-- j. Cadastral Reports -->
      @if($hasRole('Cadastral Reports'))
        <a href="/cadastral/reports"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->is('cadastral/reports*') ? 'active' : '' }}">
          <i data-lucide="file-bar-chart" class="h-4 w-4 text-rose-500"></i>
          <span>Cadastral Reports</span>
        </a>
      @endif
    </div>
  </div>
@endif
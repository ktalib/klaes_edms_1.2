    <!-- 4. Programmes -->
    @if(
      $hasRole('Allocation') || $hasRole('Compensation/Resettlement') || 
      $hasRole('Recertification - Application') || $hasRole('Recertification - Bills & Payments') || 
      $hasRole('Recertification - Migrate Data') || $hasRole('Recertification - Verification Sheet') || 
      $hasRole('GIS - Data Capture') || $hasRole('Recertification - Vetting Sheet') ||
      $hasRole('Recertification - EDMS') || $hasRole('Recertification - Certification') || 
      $hasRole("Recertification - DG's List") || $hasRole('Recertification - Governors List') || 
      $hasRole('Conversion/Regularization') || $hasRole('Land Property Enumeration - Data Repository') || 
      $hasRole('Land Property Enumeration - Migrate Data')
    )
    <div class="py-1 px-3 mb-0.5 border-t border-slate-100">
      <div class="sidebar-module-header flex items-center justify-between py-2 px-3 mb-0.5 cursor-pointer hover:bg-slate-50 rounded-md" data-module="programmes">
      <div class="flex items-center gap-2">
        <i data-lucide="briefcase" class="h-5 w-5 module-icon-programmes text-purple-600"></i>
        <span class="text-sm font-bold uppercase tracking-wider">Programmes</span>
      </div>
      <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="programmes"></i>
      </div>

      <div class="pl-4 mt-1 space-y-0.5 hidden" data-content="programmes">
      <!-- Allocation Section -->
      @if($hasRole('Allocation'))
      <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="allocation">
        <div class="flex items-center gap-2">
        <i data-lucide="building" class="h-4 w-4 text-purple-500"></i>
        <span>Allocation</span>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="allocation"></i>
      </div>

      <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="allocation">
        @if($hasRole('Governors List'))
        <a href="/programmes/allocation/governors-list" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
        <i data-lucide="list" class="h-3.5 w-3.5 text-purple-400"></i>
        <span>Governors List</span>
        </a>
        @endif
        @if($hasRole('Commissioners List'))
        <a href="/programmes/allocation/commissioners-list" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
        <i data-lucide="list-checks" class="h-3.5 w-3.5 text-purple-400"></i>
        <span>Commissioners List</span>
        </a>
        @endif
      </div>
      @endif

      <!-- Compensation/Resettlement Section -->
      @if($hasRole('Compensation/Resettlement'))
      <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="resettlement">
        <div class="flex items-center gap-2">
          <i data-lucide="home" class="h-4 w-4 text-purple-500"></i>
          <span>Resettlement/Compensation</span>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="resettlement"></i>
      </div>
      <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="resettlement">
        <a href="/programmes/resettlement/governors-list" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
          <i data-lucide="list" class="h-3.5 w-3.5 text-purple-400"></i>
          <span>Governors List</span>
        </a>
        <a href="/programmes/resettlement/commissioners-list" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
          <i data-lucide="list-checks" class="h-3.5 w-3.5 text-purple-400"></i>
          <span>Commissioners List</span>
        </a>
      </div>
      @endif

      <!-- Recertification Section --> 
      @if($hasRole('Recertification - Application') || $hasRole('Recertification - Bills & Payments') || 
          $hasRole('Recertification - Migrate Data') || $hasRole('Recertification - Verification Sheet') || 
          $hasRole('GIS - Data Capture') || $hasRole('Recertification - Vetting Sheet') ||
          $hasRole('Recertification - EDMS') || $hasRole('Recertification - Certification') || 
          $hasRole("Recertification - DG's List") || $hasRole('Recertification - Governors List'))
  <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="recertification">
    <div class="flex items-center gap-2">
      <i data-lucide="file-cog" class="h-4 w-4 text-purple-500"></i>
      <span>Recertification</span>
    </div>
    <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="recertification"></i>
  </div>

  <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="recertification">
    <a href="{{ route('recertification.index') }}" 
       class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('recertification.index') && request('url') !== 'kangis' ? 'active' : '' }}">
      <i data-lucide="file-plus" class="h-3.5 w-3.5 text-purple-400"></i>
      <span>Application</span>
    </a>
  
    <a href="{{ route('recertification.bills-payments') }}" 
       class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('recertification.bills-payments') ? 'active' : '' }}">
      <i data-lucide="dollar-sign" class="h-3.5 w-3.5 text-purple-400"></i>
      <span>Bills & Payments</span>
    </a>

    <a href="{{ route('recertification.migrate') }}" 
       class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('recertification.migrate') ? 'active' : '' }}">
      <i data-lucide="database-backup" class="h-3.5 w-3.5 text-purple-400"></i>
      <span>Application Migrate Data</span>
    </a>



    <a href="{{ route('recertification.verification-sheet') }}" 
       class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('recertification.verification-sheet') ? 'active' : '' }}">
      <i data-lucide="check-square" class="h-3.5 w-3.5 text-purple-400"></i>
      <span>Verification Sheet</span>
    </a>

    <a href="{{ route('recertification.gis-data-capture') }}" 
       class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('recertification.gis-data-capture') ? 'active' : '' }}">
      <i data-lucide="map" class="h-3.5 w-3.5 text-purple-400"></i>
      <span>GIS Data Capture</span>
    </a>

    <a href="{{ route('recertification.vetting-sheet') }}" 
       class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('recertification.vetting-sheet') ? 'active' : '' }}">
      <i data-lucide="clipboard-check" class="h-3.5 w-3.5 text-purple-400"></i>
      <span>Vetting Sheet</span>
    </a>

    <a href="{{ route('recertification.edms') }}" 
       class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('recertification.edms') ? 'active' : '' }}">
      <i data-lucide="hard-drive" class="h-3.5 w-3.5 text-purple-400"></i>
      <span>EDMS</span>
    </a>

    <a href="{{ route('recertification.certification') }}" 
       class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('recertification.certification') ? 'active' : '' }}">
      <i data-lucide="award" class="h-3.5 w-3.5 text-purple-400"></i>
      <span>Certification</span>
    </a>

    <a href="{{ route('recertification.dg-list') }}" 
       class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('recertification.dg-list') ? 'active' : '' }}">
      <i data-lucide="list-end" class="h-3.5 w-3.5 text-purple-400"></i>
      <span>DG's List</span>
    </a>

    <a href="{{ route('recertification.governors-list') }}" 
       class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('recertification.governors-list') ? 'active' : '' }}">
      <i data-lucide="list" class="h-3.5 w-3.5 text-purple-400"></i>
      <span>Governors List</span>
    </a>
  </div>
@endif


      <!-- Conversion/Regularization Section -->
      @if($hasRole('Conversion/Regularization'))
    <a href="/programmes/regularization" 
       class="sidebar-item flex items-center gap-2 py-2 rounded-md transition-all duration-200">
        <i data-lucide="arrow-left-right" class="h-4 w-4 text-purple-500"></i>
        <span class="truncate">Conversion/Regularization</span>
    </a>
@endif

      <!-- Land Property Enumeration Section -->
      @if($hasRole('Land Property Enumeration - Data Repository') || $hasRole('Land Property Enumeration - Migrate Data'))
      <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="enumeration">
        <div class="flex items-center gap-2">
        <i data-lucide="clipboard-list" class="h-4 w-4 text-purple-500"></i>
        <span>Land Property Enumeration</span>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="enumeration"></i>
      </div>

      <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="enumeration">
        @if($hasRole('Land Property Enumeration - Data Repository'))
        <a href="/programmes/enumeration/data-repository" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
        <i data-lucide="database" class="h-3.5 w-3.5 text-purple-400"></i>
        <span>Data Repository</span>
        </a>
        @endif
        @if($hasRole('Land Property Enumeration - Migrate Data'))
        <a href="/programmes/enumeration/migrate-data" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
        <i data-lucide="file-input" class="h-3.5 w-3.5 text-purple-400"></i>
        <span>Migrate Data</span>
        </a>
        @endif
      </div>
      @endif
      </div>
    </div>
    @endif

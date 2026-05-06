    <!-- 5. Search -->
    @if(
      $hasRole('Deeds - Official (for filing purpose)') || $hasRole('Deeds - On-Premise (Pay-Per-Search)') || $hasRole('Deeds - Legal Search Reports')
    )
    <div class="py-1 px-3 mb-0.5 border-t border-slate-100">
        
      <div class="sidebar-module-header flex items-center justify-between py-2 px-3 mb-0.5 cursor-pointer hover:bg-slate-50 rounded-md" data-module="search">
      <div class="flex items-center gap-2"> 
        <i data-lucide="file-search" class="h-6 w-6 module-icon-legal-search text-cyan-600"></i>
        <span class="text-sm font-bold uppercase tracking-wider">Search</span>
      </div>
      <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="search"></i>
      </div>


      

      <div class="pl-4 mt-1 space-y-0.5 hidden" data-content="search">

           
      <a href="{{ route('property-search.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('property-search.*') ? 'active' : '' }}">
        <i data-lucide="file-bar-chart" class="h-3.5 w-3.5 text-cyan-400"></i>
        <span>Property Records</span>
      </a>
     

      @if($hasRole('Deeds - Official (for filing purpose)') || $hasRole('Deeds - On-Premise (Pay-Per-Search)'))
      <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md" data-section="legalSearch">
        <div class="flex items-center gap-2">
        <i data-lucide="scale" class="h-4 w-4 text-cyan-500"></i>
        <span>Legal Search</span>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="legalSearch"></i>
      </div>

      <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="legalSearch">
        @if($hasRole('Deeds - Official (for filing purpose)'))
        <a href="{{route('legal_search.index')}}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('legal_search.index') ? 'active' : '' }}">
        <i data-lucide="file-check-2" class="h-3.5 w-3.5 text-cyan-400"></i>
        <span>Official (for filing purpose)</span>
        </a>
        @endif
        @if($hasRole('Deeds - On-Premise (Pay-Per-Search)'))
        <a href="{{route('onpremise.index')}}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('onpremise.index') ? 'active' : '' }}">
        <i data-lucide="building" class="h-3.5 w-3.5 text-cyan-400"></i>
        <span>On-Premise - Pay-per-Search</span>
        </a>
        @endif
        <a href="{{ route('legal_search.online') }}" target="_blank" rel="noopener noreferrer" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('legal_search.online') ? 'active' : '' }}">
          <i data-lucide="globe" class="h-3.5 w-3.5 text-cyan-400"></i>
          <span>Online</span>
        </a>
      </div>
      @endif
      @if($hasRole('Deeds - Legal Search Reports'))
      <a href="{{route('legalsearchreports.index')}}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('legalsearchreports.index') ? 'active' : '' }}">
        <i data-lucide="file-bar-chart" class="h-3.5 w-3.5 text-cyan-400"></i>
        <span>Legal Search Reports</span>
      </a>
      @endif
      </div>
    </div>
    @endif

   

 

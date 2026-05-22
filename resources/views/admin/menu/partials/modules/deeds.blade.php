<!-- 4. Deeds -->
@if(
    $hasRole('PRA') || $hasRole('Bill Balance') || $hasRole('Valuation') ||
    $hasRole('Deeds - Consent') || $hasRole('Deeds Registration') ||
    $hasRole('Deeds - Encumbrance') || $hasRole('Deeds - Surrender') ||
    $hasRole('Surrender & Release') || $hasRole('Other Applications') ||
    $hasRole('Activity Monitoring') || $hasRole('Supper Admin')
  ) 
  <div class="py-1 px-3 mb-0.5 border-t border-slate-100">
    <div
      class="sidebar-module-header flex items-center justify-between py-2 px-3 mb-0.5 cursor-pointer hover:bg-slate-50 rounded-md"
      data-module="deeds">
      <div class="flex items-center gap-2">
        <i data-lucide="book-open" class="h-6 w-6 text-teal-600"></i>
        <span class="text-sm font-bold uppercase tracking-wider">Deeds</span>
      </div>
      <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="deeds"></i>
    </div>

    <div class="pl-4 mt-1 space-y-0.5 hidden" data-content="deeds">
      
      <!-- a. PRA -->
      @if($hasRole('PRA') || $hasRole('Supper Admin'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
          data-section="pra-deeds">
          <div class="flex items-center gap-2">
            <i data-lucide="folder-search" class="h-4 w-4 text-teal-500"></i>
            <span>PRA</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="pra-deeds"></i>
        </div>
        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="pra-deeds">
          @if($hasRole('PRA') || $hasRole('Supper Admin'))
            <a href="{{ route('pra-pic-audit.index') }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('pra-pic-audit.*') ? 'active' : '' }}">
              <i data-lucide="layers" class="h-3.5 w-3.5 text-teal-400"></i>
              <span>PRA & PIC User Output Tracking</span>
            </a>
          @endif
          @if($hasRole('PRA') || $hasRole('Supper Admin'))
            <a href="{{route('propertycard.index')}}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('propertycard.index') ? 'active' : '' }}">
              <i data-lucide="sparkles" class="h-3.5 w-3.5 text-teal-400"></i>
              <span>Property Records Assistant (Legacy Records)</span>
            </a>
          @endif
          @if($hasRole('PRA') || $hasRole('Supper Admin'))
            <a href="/deeds/ai-pra-file-transactions"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
              <i data-lucide="bot" class="h-3.5 w-3.5 text-teal-400"></i>
              <span>AI PRA (File Transactions)</span>
            </a>
          @endif
        </div>
      @endif

      <!-- b. Bill Balance -->
      @if($hasRole('Bill Balance') || $hasRole('Supper Admin'))
        <a href="{{ route('bill-balance.index') }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('bill-balance.*') ? 'active' : '' }}">
          <i data-lucide="calculator" class="h-4 w-4 text-teal-500"></i>
          <span>Bill Balance</span>
        </a>
      @endif

      <!-- c. Valuation -->
      @if($hasRole('Valuation') || $hasRole('Supper Admin'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
          data-section="valuation-deeds">
          <div class="flex items-center gap-2">
            <i data-lucide="bar-chart-3" class="h-4 w-4 text-teal-500"></i>
            <span>Valuation</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="valuation-deeds"></i>
        </div>
        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="valuation-deeds">
          <a href="{{ route('valuation-reports.index') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('valuation-reports.*') ? 'active' : '' }}">
            <i data-lucide="file-check" class="h-3.5 w-3.5 text-teal-400"></i>
            <span>Valuation for Assignment (Report)</span>
          </a>
          <a href="{{ route('valuation-compensations.index') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('valuation-compensations.*') ? 'active' : '' }}">
            <i data-lucide="file-signature" class="h-3.5 w-3.5 text-teal-400"></i>
            <span>Valuation for Compensation</span>
          </a>
        </div>
      @endif

      <!-- d. Consent -->
      @if($hasRole('Deeds - Consent') || $hasRole('Supper Admin'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
          data-section="consent">
          <div class="flex items-center gap-2">
            <i data-lucide="file-signature" class="h-4 w-4 text-teal-500"></i>
            <span>Consent</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="consent"></i>
        </div>
        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="consent">
          <a href="{{ route('deeds-applications.index') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('deeds-applications.*') ? 'active' : '' }}">
            <i data-lucide="file-text" class="h-3.5 w-3.5 text-teal-400"></i>
            <span>Application</span>
          </a>
        </div>
      @endif

      <!-- e. Deeds Registration -->
      @if($hasRole('Deeds Registration') || $hasRole('Supper Admin'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
          data-section="deeds-registration">
          <div class="flex items-center gap-2">
            <i data-lucide="book-marked" class="h-4 w-4 text-teal-500"></i>
            <span>Deeds Registration</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="deeds-registration"></i>
        </div>
        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="deeds-registration">
          @if($hasRole('Deeds Registration') || $hasRole('Supper Admin'))
            <a href="{{route('instruments.index')}}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('instruments.*') ? 'active' : '' }}">
              <i data-lucide="file-input" class="h-3.5 w-3.5 text-teal-400"></i>
              <span>Instrument Capture (New Records)</span>
            </a>
          @endif
          @if($hasRole('Deeds Registration') || $hasRole('Supper Admin'))
            <a href="{{route('instrument_registration.index')}}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('instrument_registration.*') && request()->query('url') !== 'st_deeds' ? 'active' : '' }}">
              <i data-lucide="book-open" class="h-3.5 w-3.5 text-teal-400"></i>
              <span>Instrument Registration (New Registration)</span>
            </a>
          @endif
          @if($hasRole('Deeds Registration') || $hasRole('Supper Admin'))
            <a href="/instrument-registration-reports"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
              <i data-lucide="file-bar-chart" class="h-3.5 w-3.5 text-teal-400"></i>
              <span>Instrument Registration Reports</span>
            </a>
          @endif
        </div>
      @endif
 
      <!-- f. Encumbrance -->
      @if($hasRole('Deeds - Encumbrance') || $hasRole('Supper Admin'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
          data-section="encumbrance">
          <div class="flex items-center gap-2">
            <i data-lucide="shield" class="h-4 w-4 text-teal-500"></i>
            <span>Encumbrance</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="encumbrance"></i>
        </div>
        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="encumbrance">
          @if($hasRole('Deeds - Encumbrance') || $hasRole('Supper Admin'))
            <a href="{{route('caveat.index')}}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('caveat.*') ? 'active' : '' }}">
              <i data-lucide="shield-alert" class="h-3.5 w-3.5 text-teal-400"></i>
              <span>Caveat</span>
            </a>
            
            <a href="{{route('mortgages.index')}}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('mortgages.*') ? 'active' : '' }}">
              <i data-lucide="landmark" class="h-3.5 w-3.5 text-teal-400"></i>
              <span>Mortgage</span>
            </a>

            <a href="#"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200">
              <i data-lucide="lock" class="h-3.5 w-3.5 text-teal-400"></i>
              <span>Lien</span>
            </a>
          @endif
        </div>
      @endif

      <!-- g. Surrender & Release -->
      @if($hasRole('Deeds - Surrender') || $hasRole('Surrender & Release') || $hasRole('Supper Admin'))
        <a href="{{ route('surrender-release.index') }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('surrender-release.*') ? 'active' : '' }}">
          <i data-lucide="key-round" class="h-4 w-4 text-teal-500"></i>
          <span>Surrender & Release</span>
        </a>
      @endif

      <!-- h. Other Applications -->
      @if($hasRole('Other Applications') || $hasRole('Supper Admin'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
          data-section="otherApplications-deeds">
          <div class="flex items-center gap-2">
            <i data-lucide="files" class="h-4 w-4 text-teal-500"></i>
            <span>Other Applications</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200"
            data-chevron="otherApplications-deeds"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="otherApplications-deeds">
          <!-- i. Change of Purpose -->
          <a href="{{ route('change-of-purpose.index') }}?mode=deeds"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ (request()->routeIs('change-of-purpose.*') && request()->query('mode') === 'deeds') ? 'active' : '' }}">
            <i data-lucide="repeat" class="h-3.5 w-3.5 text-teal-400"></i>
            <span>Change of Purpose</span>
          </a>

          <!-- ii. Parcel Update -->
          <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
            data-section="parcelUpdate-deeds">
            <div class="flex items-center gap-2">
              <i data-lucide="map" class="h-3.5 w-3.5 text-teal-400"></i>
              <span>Parcel Update</span>
            </div>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 transition-transform duration-200" data-chevron="parcelUpdate-deeds"></i>
          </div>

          <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="parcelUpdate-deeds">
            <!-- 1. Plot Subdivision -->
            <a href="{{ route('plot-subdivision.index') }}?mode=deeds"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ (request()->routeIs('plot-subdivision.*') && request()->query('mode') === 'deeds') ? 'active' : '' }}">
              <i data-lucide="split-square-horizontal" class="h-3.5 w-3.5 text-teal-400"></i>
              <span>Plot Subdivision</span>
            </a>

            <!-- 2. Plot Merger -->
            <a href="{{ route('plot-merger.index') }}?mode=deeds"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ (request()->routeIs('plot-merger.*') && request()->query('mode') === 'deeds') ? 'active' : '' }}">
              <i data-lucide="combine" class="h-3.5 w-3.5 text-teal-400"></i>
              <span>Plot Merger</span>
            </a>

            <!-- 3. Plot Extension -->
            <a href="{{ route('plot-extension.index') }}?mode=deeds"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ (request()->routeIs('plot-extension.*') && request()->query('mode') === 'deeds') ? 'active' : '' }}">
              <i data-lucide="expand" class="h-3.5 w-3.5 text-teal-400"></i>
              <span>Plot Extension</span>
            </a>

            <!-- 4. Legacy Parcel Update Registry -->
            <a href="{{ route('admin.manual-linkage.index') }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('admin.manual-linkage.*') ? 'active' : '' }}">
              <i data-lucide="archive-restore" class="h-3.5 w-3.5 text-teal-400"></i>
              <span>Legacy Parcel Update</span>
            </a>
          </div>
        </div>
      @endif

      @if($hasRole('Activity Monitoring') || $hasRole('Supper Admin'))
        <a href="{{ route('activity-monitoring.index') }}?url=deeds"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ (request()->routeIs('activity-monitoring.*') && request()->query('url') === 'deeds') ? 'active' : '' }}">
          <i data-lucide="activity" class="h-3.5 w-3.5 text-teal-400"></i>
          <span>Activity Monitoring</span>
        </a>
      @endif

    </div>
  </div>
@endif
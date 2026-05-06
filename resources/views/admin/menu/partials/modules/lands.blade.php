<!-- 7. Lands -->
@if(
    $hasRole('Land') ||
    $hasRole('Land One Stop Shop') ||
    $hasRole('File History View') ||
    $hasRole('File Search') ||
    $hasRole('Letter of Grant (RofO)') ||
    $hasRole('Land to Cad/Survey') ||
    $hasRole('Other Applications-Land') ||
    $hasRole('EDMS Update')
  )
  <div class="py-1 px-3 mb-0.5 border-t border-slate-100">
    <div
      class="sidebar-module-header flex items-center justify-between py-2 px-3 mb-0.5 cursor-pointer hover:bg-slate-50 rounded-md"
      data-module="lands">
      <div class="flex items-center gap-2">
        <i data-lucide="landmark" class="h-5 w-5 text-orange-600"></i>
        <span class="text-sm font-bold uppercase tracking-wider">Land</span>
      </div>
      <i data-lucide="chevron-right" class="h-4 w-4 text-black transition-transform duration-200"
        data-chevron="lands"></i>
    </div>

    <div class="pl-4 mt-1 space-y-0.5 hidden" data-content="lands">

      <!-- a. Land -->
      @if($hasRole('Land'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
          data-section="land-main">
          <div class="flex items-center gap-2">
            <i data-lucide="map-pin" class="h-4 w-4 text-orange-500"></i>
            <span>Land</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200"
            data-chevron="land-main"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="land-main">
          <!-- i. Allocation List -->
          <a href="{{ route('allocation-list.index') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('allocation-list.index') ? 'active' : '' }}">
            <i data-lucide="list" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>Allocation List</span>
          </a>

          <!-- ii. Generate New FileNo (MLSFileNo) -->
          <a href="{{ route('file-numbers.index') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('file-numbers.index') ? 'active' : '' }}">
            <i data-lucide="hash" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>Generate New FileNo (MLSFileNo)</span>
          </a>

          <!-- iii. New Applications (Existing OP) -->
          <a href="{{ route('lands-one-stop-shop.all-applications.index', ['type' => 'no-change-of-name']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('lands-one-stop-shop.all-applications.index') && request()->query('type') === 'no-change-of-name' ? 'active' : '' }}">
            <i data-lucide="file-text" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>New Applications (Existing OP)</span>
          </a>

          <!-- iv. Bill -->
          <a href="{{ route('lands-one-stop-shop.bill.index') }}?type=land"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('lands-one-stop-shop.bill.index') && request()->query('type') === 'land' ? 'active' : '' }}">
            <i data-lucide="receipt" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>Bill</span>
          </a>

          <!-- v. Land to Cad/Survey -->
          <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
            data-section="cadSurvey-land-main">
            <div class="flex items-center gap-2">
              <i data-lucide="map" class="h-3.5 w-3.5 text-orange-400"></i>
              <span>Land to Cad/Survey</span>
            </div>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 transition-transform duration-200" data-chevron="cadSurvey-land-main"></i>
          </div>

          <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="cadSurvey-land-main">
            <!-- 1. Land 12 (Request for Survey Report) -->
            <a href="{{ route('survey-report.index') }}?url=lands12"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-report.index') && request()->query('url') === 'lands12' ? 'active' : '' }}">
              <i data-lucide="file-text" class="h-3.5 w-3.5 text-orange-400"></i>
              <span>Land 12 (Request for Survey Report)</span>
            </a>

            <!-- 2. For Information -->
            <a href="{{ route('for-information.index') }}"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('for-information.index') ? 'active' : '' }}">
              <i data-lucide="info" class="h-3.5 w-3.5 text-orange-400"></i>
              <span>For Information</span>
            </a>
          </div>

          <!-- vi. Capture/Manage an Existing File -->
          <a href="{{ route('existing-file-numbers.index') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('existing-file-numbers.index') ? 'active' : '' }}">
            <i data-lucide="folder-plus" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>Capture/Manage an Existing File</span>
          </a>

          <!-- vii. File Decommissioning -->
          <a href="{{ route('file-decommissioning.index') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('file-decommissioning.index') ? 'active' : '' }}">
            <i data-lucide="archive" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>File Decommissioning</span>
          </a>
        </div>
      @endif

      <!-- b. Land One Stop Shop -->
      @if($hasRole('Land One Stop Shop'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
          data-section="lands-one-stop-shop">
          <div class="flex items-center gap-2">
            <i data-lucide="store" class="h-4 w-4 text-orange-500"></i>
            <span>Land One Stop Shop</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200"
            data-chevron="lands-one-stop-shop"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="lands-one-stop-shop">
          <!-- i. Generate FileNos (Change of Name) -->
          <a href="{{ route('lands-one-stop-shop.applications.index', ['source' => 'lands-one-stop-shop', 'type' => 'change-of-name']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('lands-one-stop-shop.applications.index') && request()->query('source') === 'lands-one-stop-shop' && request()->query('type') === 'change-of-name' ? 'active' : '' }}">
            <i data-lucide="hash" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>Generate FileNos (Change of Name)</span>
          </a>

          <!-- ii. New Applications (Existing OP Change of Name) -->
          <a href="{{ route('lands-one-stop-shop.all-applications.index', ['type' => 'change-of-name']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('lands-one-stop-shop.all-applications.index') && request()->query('type') === 'change-of-name' ? 'active' : '' }}">
            <i data-lucide="users" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>New Applications (Existing OP Change of Name)</span>
          </a>

          <!-- iii. Bill -->
          <a href="{{ route('lands-one-stop-shop.bill.index') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('lands-one-stop-shop.bill.index') && !request()->has('type') ? 'active' : '' }}">
            <i data-lucide="receipt" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>Bill</span>
          </a>

          <!-- iv. OSS to Cad/Survey -->
          <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
            data-section="cadSurvey-oss">
            <div class="flex items-center gap-2">
              <i data-lucide="map" class="h-3.5 w-3.5 text-orange-400"></i>
              <span>OSS to Cad/Survey</span>
            </div>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 transition-transform duration-200" data-chevron="cadSurvey-oss"></i>
          </div>

          <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="cadSurvey-oss">
            <!-- 1. Land 12 -->
            <a href="{{ route('survey-report.index') }}?url=lands"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('survey-report.index') && request()->query('url') === 'lands' ? 'active' : '' }}">
              <i data-lucide="file-text" class="h-3.5 w-3.5 text-orange-400"></i>
              <span>Land 12</span>
            </a>

            <!-- 2. For Information -->
            <a href="{{ route('for-information.index') }}?url=oss"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('for-information.index') && request()->query('url') === 'oss' ? 'active' : '' }}">
              <i data-lucide="info" class="h-3.5 w-3.5 text-orange-400"></i>
              <span>For Information</span>
            </a>
          </div>

          <!-- v. OSS Recommendation -->
          <a href="{{ route('land-recommendations.index', ['type' => 'OSS']) }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('land-recommendations.index') && strtoupper((string) request()->query('type')) === 'OSS' ? 'active' : '' }}">
            <i data-lucide="check-circle" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>OSS Recommendation</span>
          </a>
        </div>
      @endif

      <!-- c. Letter of Grant (RofO) -->
      @if($hasRole('Letter of Grant (RofO)'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
          data-section="rofo-lands">
          <div class="flex items-center gap-2">
            <i data-lucide="scroll" class="h-4 w-4 text-orange-500"></i>
            <span>Letter of Grant (RofO)</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="rofo-lands"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="rofo-lands">
          <!-- i. Land Recommendation -->
          <a href="{{ route('land-recommendations.index', ['type' => 'ROFO']) }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('land-recommendations.index') && strtoupper((string) request()->query('type')) === 'ROFO' ? 'active' : '' }}">
            <i data-lucide="check-circle" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>Land Recommendation</span>
          </a>

          <!-- ii. RofO -->
          <a href="{{ route('land-rofos.index') }}" class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('land-rofos.index') ? 'active' : '' }}">
            <i data-lucide="file-check" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>RofO</span>
          </a>
        </div>
      @endif

      <!-- d. File History View -->
      @if($hasRole('File History View'))
        <a href="{{ route('file-index-view.index', ['url' => 'land']) }}"
          class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('file-index-view.index') ? 'active' : '' }}">
          <i data-lucide="eye" class="h-4 w-4 text-orange-500"></i>
          <span>File History View</span>
        </a>
      @endif

      <!-- e. File Search -->
      @if($hasRole('File Search'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
          data-section="fileSearch-lands">
          <div class="flex items-center gap-2">
            <i data-lucide="search" class="h-4 w-4 text-orange-500"></i>
            <span>File Search</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200"
            data-chevron="fileSearch-lands"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="fileSearch-lands">
          <!-- i. Scans -->
          <a href="{{ route('file-search.scans') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('file-search.scans') ? 'active' : '' }}">
            <i data-lucide="image" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>Scans</span>
          </a>

          <!-- ii. Table -->
          <a href="{{ route('file-search-db.index') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('file-search-db.index') ? 'active' : '' }}">
            <i data-lucide="table" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>Table</span>
          </a>
        </div>
      @endif

      <!-- f. Other Applications -->
      @if($hasRole('Other Applications-Land'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
          data-section="otherApplications-lands">
          <div class="flex items-center gap-2">
            <i data-lucide="files" class="h-4 w-4 text-orange-500"></i>
            <span>Other Applications</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200"
            data-chevron="otherApplications-lands"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="otherApplications-lands">
          <!-- i. Change of Purpose -->
          <a href="{{ route('change-of-purpose.index') }}?mode=land"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ (request()->routeIs('change-of-purpose.*') && request()->query('mode') === 'land') ? 'active' : '' }}">
            <i data-lucide="repeat" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>Change of Purpose</span>
          </a>

          <!-- ii. Loss of Document -->
          <a href="{{ route('loss-of-document.index') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('loss-of-document.index') ? 'active' : '' }}">
            <i data-lucide="file-warning" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>Loss of Document</span>
          </a>

          <!-- iii. Temporary File -->
          <a href="{{ route('temporary-file.index') }}"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('temporary-file.*') ? 'active' : '' }}">
            <i data-lucide="file-clock" class="h-3.5 w-3.5 text-orange-400"></i>
            <span>Temporary File</span>
          </a>

          <!-- iv. Parcel Update -->
          <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
            data-section="parcelUpdate-lands">
            <div class="flex items-center gap-2">
              <i data-lucide="map" class="h-3.5 w-3.5 text-orange-400"></i>
              <span>Parcel Update</span>
            </div>
            <i data-lucide="chevron-right" class="h-3.5 w-3.5 transition-transform duration-200" data-chevron="parcelUpdate-lands"></i>
          </div>

          <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="parcelUpdate-lands">
            <!-- 1. Plot Subdivision -->
            <a href="{{ route('plot-subdivision.index') }}?mode=land"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ (request()->routeIs('plot-subdivision.*') && request()->query('mode') === 'land') ? 'active' : '' }}">
              <i data-lucide="split-square-horizontal" class="h-3.5 w-3.5 text-orange-400"></i>
              <span>Plot Subdivision</span>
            </a>

            <!-- 2. Plot Merger -->
            <a href="{{ route('plot-merger.index') }}?mode=land"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ (request()->routeIs('plot-merger.*') && request()->query('mode') === 'land') ? 'active' : '' }}">
              <i data-lucide="combine" class="h-3.5 w-3.5 text-orange-400"></i>
              <span>Plot Merger</span>
            </a>

            <!-- 3. Plot Extension -->
            <a href="{{ route('plot-extension.index') }}?mode=land"
              class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ (request()->routeIs('plot-extension.*') && request()->query('mode') === 'land') ? 'active' : '' }}">
              <i data-lucide="expand" class="h-3.5 w-3.5 text-orange-400"></i>
              <span>Plot Extension</span>
            </a>
          </div>
        </div>
      @endif

      <!-- g. EDMS Update -->
      @if($hasRole('EDMS Update'))
        <div class="sidebar-submodule-header flex items-center justify-between py-1.5 px-3 cursor-pointer rounded-md"
          data-section="edmsUpdate-lands">
          <div class="flex items-center gap-2">
            <i data-lucide="refresh-cw" class="h-4 w-4 text-orange-500"></i>
            <span>EDMS Update</span>
          </div>
          <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200"
            data-chevron="edmsUpdate-lands"></i>
        </div>

        <div class="pl-4 mt-1 mb-1 space-y-0.5 hidden" data-content="edmsUpdate-lands">
          <a href="/scanning?url=scmore"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->is('scanning') && request()->query('url') === 'scmore' ? 'active' : '' }}">
            <i data-lucide="scan-line" class="h-3.5 w-3.5 text-orange-500"></i>
            <span>Scan More</span>
          </a>
          <a href="/pagetyping?url=ptmore"
            class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->is('pagetyping') && request()->query('url') === 'ptmore' ? 'active' : '' }}">
            <i data-lucide="file-text" class="h-3.5 w-3.5 text-orange-500"></i>
            <span>Type More</span>
          </a>
        </div>
      @endif

    </div>
  </div>
@endif

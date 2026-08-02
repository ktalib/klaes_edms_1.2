<!-- PRS Management (after Cadastral) -->
@if($hasRole('Dashboard') || $hasRole('Supper Admin'))
  <div class="py-1 px-3 mb-0.5 border-t border-slate-100">
    <div
      class="sidebar-module-header flex items-center justify-between py-2 px-3 mb-0.5 cursor-pointer hover:bg-slate-50 rounded-md"
      data-module="prs-management">
      <div class="flex items-center gap-2">
        <i data-lucide="bar-chart-3" class="h-5 w-5 text-emerald-600"></i>
        <span class="text-sm font-bold uppercase tracking-wider">PRS Management</span>
      </div>
      <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200" data-chevron="prs-management"></i>
    </div>

    <div class="pl-4 mt-1 space-y-0.5 hidden" data-content="prs-management">
      <a href="{{ route('prs-report.index') }}"
        class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('prs-report.*') ? 'active' : '' }}">
        <i data-lucide="file-chart-column" class="h-4 w-4 text-emerald-500"></i>
        <span>PRS Annual Report</span>
      </a>

      <a href="{{ route('prs-report.index') }}"
        class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('prs-report.*') ? 'active' : '' }}">
        <i data-lucide="file-signature" class="h-4 w-4 text-emerald-500"></i>
        <span>Deeds Instruments</span>
      </a>

      <a href="{{ route('prs-report.index') }}"
        class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('prs-report.*') ? 'active' : '' }}">
        <i data-lucide="search" class="h-4 w-4 text-emerald-500"></i>
        <span>Legal Search</span>
      </a>

      <a href="{{ route('prs-report.index') }}"
        class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('prs-report.*') ? 'active' : '' }}">
        <i data-lucide="folder-tree" class="h-4 w-4 text-emerald-500"></i>
        <span>Land File Commissioning</span>
      </a>

      <a href="{{ route('prs-report.index') }}"
        class="sidebar-item flex items-center gap-2 py-2 px-3 rounded-md transition-all duration-200 {{ request()->routeIs('prs-report.*') ? 'active' : '' }}">
        <i data-lucide="landmark" class="h-4 w-4 text-emerald-500"></i>
        <span>Land Allocation</span>
      </a>
    </div>
  </div>
@endif

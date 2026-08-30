@php
    $userRoles = auth()->user()->assignedRoleNames();
    $hasRole = function($role) use ($userRoles) {
        // normalize helper
        $normalize = function($s) {
            $s = trim((string)$s);
            $s = str_replace(['–','—'], '-', $s); // en/em dash -> hyphen
            $s = preg_replace('/\s*-\s*/u', ' - ', $s); // normalize spaces around hyphen
            $s = preg_replace('/\s+/u', ' ', $s);       // collapse spaces
            return mb_strtolower($s, 'UTF-8');
        };

        // Super Admin (accept both spellings)
        foreach ($userRoles as $r) {
            $nr = $normalize($r);
            if ($nr === 'supper admin' || $nr === 'super admin') {
                return true;
            }
        }

        $target = $normalize($role);
        foreach ($userRoles as $userRole) {
            if ($normalize($userRole) === $target) {
                return true;
            }
        }
        return false;
    };
@endphp
{{-- Greyed out and inert while the account has no passport photo. This is the visual
     half of the rule; RequireProfilePhoto blocks the URLs themselves. --}}
@php $sidebarPhotoLocked = auth()->check() && auth()->user()->needs_profile_photo; @endphp
<div class="sidebar border-r border-gray-200 bg-white transition-all duration-300 ease-in-out{{ $sidebarPhotoLocked ? ' sidebar-locked' : '' }}"
  @if($sidebarPhotoLocked) aria-disabled="true" @endif>
  <!-- Sidebar Header -->
  @include('admin.menu.partials.sidebar_header')
 
  <!-- Sidebar Content -->
  <div class="sidebar-content p-2 overflow-y-auto max-h-[calc(100vh-8rem)] scroll-smooth scrollbar-visible">
    @include('admin.menu.partials.modules.dashboard')
    @include('admin.menu.partials.modules.hc_ps_view')
    @include('admin.menu.partials.modules.crm')
    @include('admin.menu.partials.modules.edms')
    @include('admin.menu.partials.modules.digital_archive')
    @include('admin.menu.partials.modules.programmes')
    @include('admin.menu.partials.modules.information_products')
    @include('admin.menu.partials.modules.revenue_management')
    @include('admin.menu.partials.modules.kangis')
    @include('admin.menu.partials.modules.deeds')
    @include('admin.menu.partials.modules.search')
    @include('admin.menu.partials.modules.lands')
    @include('admin.menu.partials.modules.dciv')
    @include('admin.menu.partials.modules.physical_planning')
    @include('admin.menu.partials.modules.survey')
    @include('admin.menu.partials.modules.cadastral')
    @include('admin.menu.partials.modules.prs_management')
    @include('admin.menu.partials.modules.gis')
    @include('admin.menu.partials.modules.sectional_titling')
    @include('admin.menu.partials.modules.sltr')
    @include('admin.menu.partials.modules.special_assignment')
    @include('admin.menu.partials.modules.systems')
    @include('admin.menu.partials.modules.legacy_systems')
    @include('admin.menu.partials.modules.payroll')
    @include('admin.menu.partials.modules.system_admin')
  </div>

  <!-- Sidebar Footer -->
  @include('admin.menu.partials.sidebar_footer')

  <!-- Loading Spinner Overlay -->
</div>

@include('admin.menu.partials.sidebar_styles')
@include('admin.menu.partials.sidebar_scripts')

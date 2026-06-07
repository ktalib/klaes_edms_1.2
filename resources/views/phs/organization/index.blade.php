<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>KLAES Enterprise - Organization Settings</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>

  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: "#3b82f6",
            "primary-foreground": "#ffffff",
            muted: "#f3f4f6",
            "muted-foreground": "#6b7280",
            border: "#e5e7eb",
            destructive: "#ef4444",
            "destructive-foreground": "#ffffff",
            secondary: "#f1f5f9",
            "secondary-foreground": "#0f172a",
          },
        },
      },
    };
  </script>

  <style>
    .user-card {
      transition: all 0.3s ease;
    }
    .user-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }

    .modal-overlay {
      background: rgba(0, 0, 0, 0.5);
      backdrop-filter: blur(4px);
    }

    .slide-in {
      animation: slideIn 0.3s ease-out;
    }

    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .tab-active {
      border-bottom: 2px solid #3b82f6;
      color: #3b82f6;
    }

    .token-usage-bar {
      transition: width 0.5s ease;
    }

    .modal-scrollable {
      max-height: 70vh;
      overflow-y: auto;
    }

    .preview-card {
      transition: all 0.3s ease;
    }
    .preview-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }

    .table-row {
      transition: all 0.2s ease;
    }
    .table-row:hover {
      background-color: #f8fafc;
    }

    .dropzone-active {
      border-color: #3b82f6 !important;
      background-color: #eff6ff !important;
    }

    .dropzone {
      transition: all 0.2s ease;
    }

    /* Mobile menu styles */
    .mobile-menu-open {
      max-height: 500px;
      opacity: 1;
      visibility: visible;
    }

    .mobile-menu-closed {
      max-height: 0;
      opacity: 0;
      visibility: hidden;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }
      
      .preview-card-container {
        margin-top: 1rem;
      }
    }

    @media (max-width: 640px) {
      .stats-grid {
        grid-template-columns: 1fr;
      }
      
      .tab-buttons {
        gap: 1rem;
      }
    }
  </style>
</head>
<body class="bg-gray-50">
  <!-- User Management Main View -->
  <div id="user-management-container" class="min-h-screen bg-gray-50 lg:pl-64">
    <aside class="hidden lg:flex fixed inset-y-0 left-0 z-50 w-64 flex-col border-r border-gray-200 bg-white">
      <div class="h-16 px-5 flex items-center gap-3 border-b border-gray-100">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-r from-blue-600 to-purple-600 flex items-center justify-center text-white shadow-sm">
          <i data-lucide="users" class="w-5 h-5"></i>
        </div>
        <div class="min-w-0">
          <p class="text-sm font-bold text-gray-900 truncate" id="sidebar-org-name">Organization Settings</p>
          <p class="text-xs text-gray-500 truncate">Manage workspace</p>
        </div>
      </div>
      <nav class="flex-1 px-3 py-5 space-y-1">
        <a href="{{ route('phs.dashboard') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900">
          <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
          Dashboard
        </a>
        <button type="button" data-sidebar-tab="users" class="w-full flex items-center gap-3 rounded-xl bg-blue-50 px-3 py-2.5 text-sm font-semibold text-blue-700">
          <i data-lucide="users" class="w-4 h-4"></i>
          Team Members
        </button>
        <button type="button" data-sidebar-tab="roles" class="w-full flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900">
          <i data-lucide="shield" class="w-4 h-4"></i>
          Roles & Permissions
        </button>
        <button type="button" data-sidebar-tab="activity" class="w-full flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900">
          <i data-lucide="activity" class="w-4 h-4"></i>
          Activity Log
        </button>
        <button type="button" data-sidebar-tab="branding" class="w-full flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900">
          <i data-lucide="palette" class="w-4 h-4"></i>
          Branding
        </button>
      </nav>
      <div class="border-t border-gray-100 p-4 space-y-3">
        <div class="rounded-xl bg-gray-50 px-3 py-3">
          <p class="text-xs text-gray-500">Org Tokens</p>
          <p class="text-2xl font-bold text-blue-600" id="sidebar-org-tokens-display">0</p>
        </div>
        <button id="sidebar-org-logout-btn" type="button" class="w-full flex items-center justify-center gap-2 rounded-xl border border-gray-300 px-3 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
          <i data-lucide="log-out" class="w-4 h-4"></i>
          Logout
        </button>
      </div>
    </aside>
    <!-- Responsive Header -->
    <div class="bg-white border-b border-gray-200 sticky top-0 z-20" id="org-header">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center py-3 sm:py-0 h-auto sm:h-16 gap-3">
          <div class="flex items-center space-x-3 sm:space-x-4">
            <div
              id="org-header-logo"
              class="w-8 h-8 sm:w-10 sm:h-10 bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg flex items-center justify-center shadow-md"
            >
              <i data-lucide="users" class="h-5 w-5 sm:h-6 sm:w-6 text-white"></i>
            </div>
            <div>
              <h1 class="text-lg sm:text-xl font-bold text-gray-900">Organization Settings</h1>
              <p class="text-xs sm:text-sm text-gray-500 hidden sm:block" id="org-header-name">
                Manage your organization's branding
              </p>
            </div>
          </div>
          <div class="flex items-center space-x-2 sm:space-x-3">
            <div class="text-right">
              <p class="text-xs text-gray-500">Org Tokens</p>
              <p class="text-base sm:text-lg font-bold text-blue-600" id="org-tokens-display">5,000</p>
            </div>
            <button id="close-user-management" class="p-1 sm:p-2 hover:bg-gray-100 rounded-lg transition">
              <i data-lucide="x" class="w-4 h-4 sm:w-5 sm:h-5 text-gray-500"></i>
            </button>
          </div>
        </div>
        <p class="text-xs text-gray-500 pb-3 sm:hidden" id="mobile-org-header-name">
          Manage your organization's branding
        </p>
      </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-8">
      <!-- Organization Info Banner - Responsive -->
      <div
        id="org-banner"
        class="rounded-2xl shadow-sm mb-6 sm:mb-8 h-32 sm:h-40 bg-gradient-to-r from-blue-600 to-purple-600 flex items-center justify-center relative overflow-hidden"
      >
        <p id="banner-text" class="text-white text-lg sm:text-xl font-medium z-10 px-4 text-center">
          Organization Banner
        </p>
      </div>

      <!-- Stats Overview - Responsive Grid -->
      <div class="stats-grid grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-6 mb-6 sm:mb-8">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 sm:p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs sm:text-sm text-gray-500">Total Users</p>
              <p class="text-2xl sm:text-3xl font-bold text-gray-900" id="total-users">0</p>
            </div>
            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-blue-100 rounded-2xl flex items-center justify-center">
              <i data-lucide="users" class="w-5 h-5 sm:w-6 sm:h-6 text-blue-600"></i>
            </div>
          </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 sm:p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs sm:text-sm text-gray-500">Active Users</p>
              <p class="text-2xl sm:text-3xl font-bold text-green-600" id="active-users">0</p>
            </div>
            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-green-100 rounded-2xl flex items-center justify-center">
              <i data-lucide="user-check" class="w-5 h-5 sm:w-6 sm:h-6 text-green-600"></i>
            </div>
          </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 sm:p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs sm:text-sm text-gray-500">Super Admins</p>
              <p class="text-2xl sm:text-3xl font-bold text-purple-600" id="super-admin-count">0</p>
            </div>
            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-purple-100 rounded-2xl flex items-center justify-center">
              <i data-lucide="crown" class="w-5 h-5 sm:w-6 sm:h-6 text-purple-600"></i>
            </div>
          </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 sm:p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs sm:text-sm text-gray-500">Regular Users</p>
              <p class="text-2xl sm:text-3xl font-bold text-orange-600" id="regular-user-count">0</p>
            </div>
            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-orange-100 rounded-2xl flex items-center justify-center">
              <i data-lucide="user" class="w-5 h-5 sm:w-6 sm:h-6 text-orange-600"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Content Tabs - Responsive -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="border-b border-gray-200 overflow-x-auto">
          <div class="flex space-x-4 sm:space-x-8 px-4 sm:px-6 min-w-max">
            <button
              class="py-3 sm:py-4 text-xs sm:text-sm font-medium border-b-2 tab-active whitespace-nowrap flex items-center gap-1 sm:gap-2"
              data-tab="users"
            >
              <i data-lucide="users" class="w-3 h-3 sm:w-4 sm:h-4"></i> Team Members
            </button>
            <button
              class="py-3 sm:py-4 text-xs sm:text-sm font-medium text-gray-500 hover:text-gray-700 transition-colors whitespace-nowrap flex items-center gap-1 sm:gap-2"
              data-tab="roles"
            >
              <i data-lucide="shield" class="w-3 h-3 sm:w-4 sm:h-4"></i> Roles &amp; Permissions
            </button>
            <button
              class="py-3 sm:py-4 text-xs sm:text-sm font-medium text-gray-500 hover:text-gray-700 transition-colors whitespace-nowrap flex items-center gap-1 sm:gap-2"
              data-tab="activity"
            >
              <i data-lucide="activity" class="w-3 h-3 sm:w-4 sm:h-4"></i> Activity Log
            </button>
            <button
              class="py-3 sm:py-4 text-xs sm:text-sm font-medium text-gray-500 hover:text-gray-700 transition-colors whitespace-nowrap flex items-center gap-1 sm:gap-2"
              data-tab="branding"
            >
              <i data-lucide="palette" class="w-3 h-3 sm:w-4 sm:h-4"></i> Branding
            </button>
          </div>
        </div>

        <!-- Team Members Tab -->
        <div id="users-tab" class="p-4 sm:p-6">
          <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <div>
              <h2 class="text-xl sm:text-2xl font-semibold text-gray-900">Team Members</h2>
              <p class="text-xs sm:text-sm text-gray-500">Manage access and permissions</p>
            </div>
            <button
              id="add-user-btn"
              class="inline-flex items-center gap-2 px-4 sm:px-6 py-2 sm:py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl transition shadow-sm text-sm sm:text-base w-full sm:w-auto justify-center"
            >
              <i data-lucide="user-plus" class="w-4 h-4 sm:w-5 sm:h-5"></i>
              <span>Add Member</span>
            </button>
          </div>

          <div class="overflow-x-auto rounded-2xl border border-gray-100">
            <table class="w-full min-w-[800px]">
              <thead class="bg-gray-50">
                <tr>
                  <th class="text-left py-3 sm:py-4 px-4 sm:px-6 text-xs font-medium text-gray-500 uppercase tracking-wider">Member</th>
                  <th class="text-left py-3 sm:py-4 px-4 sm:px-6 text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                  <th class="text-left py-3 sm:py-4 px-4 sm:px-6 text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">Job Title</th>
                  <th class="text-left py-3 sm:py-4 px-4 sm:px-6 text-xs font-medium text-gray-500 uppercase tracking-wider">User Type</th>
                  <th class="text-left py-3 sm:py-4 px-4 sm:px-6 text-xs font-medium text-gray-500 uppercase tracking-wider">Tokens</th>
                  <th class="text-left py-3 sm:py-4 px-4 sm:px-6 text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                  <th class="text-left py-3 sm:py-4 px-4 sm:px-6 text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody id="users-table-body" class="divide-y divide-gray-100"></tbody>
            </table>
          </div>
        </div>

        <!-- Roles Tab -->
        <div id="roles-tab" class="p-4 sm:p-6 hidden">
          <h2 class="text-xl sm:text-2xl font-semibold text-gray-900 mb-2">Roles &amp; Permissions</h2>
          <p class="text-sm text-gray-500 mb-6 sm:mb-8">Define what each user type can do</p>

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Super Admin -->
            <div class="bg-gradient-to-br from-purple-50 to-white border border-purple-100 rounded-2xl sm:rounded-3xl p-6 sm:p-8">
              <div class="flex gap-3 sm:gap-4 items-start">
                <div class="w-12 h-12 sm:w-14 sm:h-14 bg-purple-100 rounded-2xl flex items-center justify-center flex-shrink-0">
                  <i data-lucide="crown" class="w-6 h-6 sm:w-7 sm:h-7 text-purple-600"></i>
                </div>
                <div class="flex-1">
                  <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-2">
                    <h3 class="text-lg sm:text-xl font-semibold">Super Administrator</h3>
                    <span class="px-3 sm:px-4 py-1 text-xs font-medium bg-purple-100 text-purple-700 rounded-2xl text-center sm:text-left">Full Access</span>
                  </div>
                  <ul class="mt-4 sm:mt-6 space-y-2 sm:space-y-3 text-xs sm:text-sm">
                    <li class="flex items-center gap-2"><i data-lucide="check-circle" class="w-4 h-4 text-green-500"></i> Full platform management</li>
                    <li class="flex items-center gap-2"><i data-lucide="check-circle" class="w-4 h-4 text-green-500"></i> User &amp; role management</li>
                    <li class="flex items-center gap-2"><i data-lucide="check-circle" class="w-4 h-4 text-green-500"></i> Token allocation &amp; billing</li>
                    <li class="flex items-center gap-2"><i data-lucide="check-circle" class="w-4 h-4 text-green-500"></i> Branding &amp; organization settings</li>
                  </ul>
                </div>
              </div>
            </div>

            <!-- Regular User -->
            <div class="bg-gradient-to-br from-blue-50 to-white border border-blue-100 rounded-2xl sm:rounded-3xl p-6 sm:p-8">
              <div class="flex gap-3 sm:gap-4 items-start">
                <div class="w-12 h-12 sm:w-14 sm:h-14 bg-blue-100 rounded-2xl flex items-center justify-center flex-shrink-0">
                  <i data-lucide="user" class="w-6 h-6 sm:w-7 sm:h-7 text-blue-600"></i>
                </div>
                <div class="flex-1">
                  <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-2">
                    <h3 class="text-lg sm:text-xl font-semibold">Regular User</h3>
                    <span class="px-3 sm:px-4 py-1 text-xs font-medium bg-blue-100 text-blue-700 rounded-2xl text-center sm:text-left">Limited</span>
                  </div>
                  <ul class="mt-4 sm:mt-6 space-y-2 sm:space-y-3 text-xs sm:text-sm">
                    <li class="flex items-center gap-2"><i data-lucide="check-circle" class="w-4 h-4 text-green-500"></i> Perform legal searches</li>
                    <li class="flex items-center gap-2"><i data-lucide="check-circle" class="w-4 h-4 text-green-500"></i> View personal history</li>
                    <li class="flex items-center gap-2"><i data-lucide="check-circle" class="w-4 h-4 text-green-500"></i> Download own documents</li>
                    <li class="flex items-center gap-2 text-gray-400"><i data-lucide="x-circle" class="w-4 h-4"></i> Cannot manage users</li>
                    <li class="flex items-center gap-2 text-gray-400"><i data-lucide="x-circle" class="w-4 h-4"></i> Cannot change branding</li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Activity Log Tab -->
        <div id="activity-tab" class="p-4 sm:p-6 hidden">
          <h2 class="text-xl sm:text-2xl font-semibold text-gray-900 mb-4 sm:mb-6">Recent Activity</h2>
          <div id="activity-log-container" class="space-y-3 sm:space-y-4"></div>
        </div>

        <!-- Branding Tab -->
        <div id="branding-tab" class="p-4 sm:p-6 hidden">
          <div class="flex justify-between items-end mb-4 sm:mb-6">
            <div>
              <h2 class="text-xl sm:text-2xl font-semibold text-gray-900">Organization Branding</h2>
              <p class="text-xs sm:text-sm text-gray-500">Customize appearance across the platform</p>
            </div>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 sm:gap-8">
            <!-- Settings Form -->
            <div class="space-y-4 sm:space-y-6">
              <!-- Organization Name -->
              <div class="bg-gray-50 rounded-2xl p-4 sm:p-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Organization Name</label>
                <input type="text" id="org-name" class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm sm:text-base" placeholder="Enter organization name" />
                <p class="text-xs text-gray-500 mt-2">This will appear in headers and user interfaces</p>
              </div>

              <!-- Colors -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                <div class="bg-gray-50 rounded-2xl p-4 sm:p-6">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Primary Color</label>
                  <div class="flex gap-2 sm:gap-3">
                    <input type="color" id="primary-color" class="w-10 h-10 sm:w-14 sm:h-14 rounded-xl border border-gray-300 cursor-pointer" />
                    <input type="text" id="primary-color-hex" class="flex-1 px-2 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-xl font-mono text-xs sm:text-sm" />
                  </div>
                </div>

                <div class="bg-gray-50 rounded-2xl p-4 sm:p-6">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Secondary Color</label>
                  <div class="flex gap-2 sm:gap-3">
                    <input type="color" id="secondary-color" class="w-10 h-10 sm:w-14 sm:h-14 rounded-xl border border-gray-300 cursor-pointer" />
                    <input type="text" id="secondary-color-hex" class="flex-1 px-2 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-xl font-mono text-xs sm:text-sm" />
                  </div>
                </div>
              </div>

              <!-- Logo Upload -->
              <div class="bg-gray-50 rounded-2xl p-4 sm:p-6">
                <label class="block text-sm font-medium text-gray-700 mb-3">Organization Logo</label>
                <div id="logo-dropzone" class="dropzone border-2 border-dashed border-gray-300 rounded-2xl p-4 sm:p-8 text-center hover:border-blue-400 transition-all cursor-pointer">
                  <input type="file" id="logo-upload" accept="image/*" class="hidden" />
                  <div id="logo-preview-area" class="mx-auto w-20 h-20 sm:w-28 sm:h-28 bg-white border border-gray-200 rounded-2xl flex items-center justify-center overflow-hidden mb-3 sm:mb-4 shadow-sm">
                    <i data-lucide="image" class="w-8 h-8 sm:w-12 sm:h-12 text-gray-300"></i>
                  </div>
                  <button type="button" id="upload-logo-btn" class="inline-flex items-center px-3 sm:px-5 py-1.5 sm:py-2.5 bg-white border border-gray-300 rounded-xl text-xs sm:text-sm font-medium hover:bg-gray-50">
                    <i data-lucide="upload" class="w-3 h-3 sm:w-4 sm:h-4 mr-1 sm:mr-2"></i> Upload Logo
                  </button>
                  <p class="text-xs text-gray-500 mt-2 sm:mt-3">PNG, JPG or SVG â€¢ 200Ã—200px recommended â€¢ Max 2MB</p>
                  <button id="remove-logo-btn" class="hidden mt-2 sm:mt-3 text-red-600 hover:text-red-700 text-xs sm:text-sm font-medium flex items-center gap-1 mx-auto">
                    <i data-lucide="trash-2" class="w-3 h-3 sm:w-4 sm:h-4"></i> Remove Logo
                  </button>
                </div>
              </div>

              <!-- Banner Upload -->
              <div class="bg-gray-50 rounded-2xl p-4 sm:p-6">
                <label class="block text-sm font-medium text-gray-700 mb-3">Banner Image</label>
                <div id="banner-dropzone" class="border-2 border-dashed border-gray-300 rounded-2xl p-4 sm:p-8 text-center hover:border-blue-400 transition-all cursor-pointer">
                  <input type="file" id="banner-upload" accept="image/*" class="hidden" />
                  <div id="banner-preview-area" class="mx-auto w-full h-20 sm:h-28 bg-white border border-gray-200 rounded-2xl flex items-center justify-center overflow-hidden mb-3 sm:mb-4 shadow-sm">
                    <i data-lucide="image" class="w-8 h-8 sm:w-12 sm:h-12 text-gray-300"></i>
                  </div>
                  <button type="button" id="upload-banner-btn" class="inline-flex items-center px-3 sm:px-5 py-1.5 sm:py-2.5 bg-white border border-gray-300 rounded-xl text-xs sm:text-sm font-medium hover:bg-gray-50">
                    <i data-lucide="upload" class="w-3 h-3 sm:w-4 sm:h-4 mr-1 sm:mr-2"></i> Upload Banner
                  </button>
                  <p class="text-xs text-gray-500 mt-2 sm:mt-3">1200Ã—300px recommended</p>
                </div>
              </div>
            </div>

            <!-- Live Preview -->
            <div class="preview-card-container">
              <div class="bg-gradient-to-br from-gray-900 to-gray-800 rounded-2xl sm:rounded-3xl p-5 sm:p-8 text-white h-full flex flex-col">
                <div class="flex justify-between items-center mb-4 sm:mb-6">
                  <h3 class="text-base sm:text-lg font-semibold">Live Preview</h3>
                  <span class="text-xs bg-white/10 px-2 sm:px-3 py-1 rounded-full">How your team sees it</span>
                </div>

                <div class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-5 mb-4 sm:mb-6 text-gray-900 shadow-inner">
                  <div class="flex items-center gap-3 sm:gap-4">
                    <div id="logo-preview" class="w-10 h-10 sm:w-12 sm:h-12 rounded-2xl flex items-center justify-center overflow-hidden shadow bg-gradient-to-r from-blue-600 to-purple-600">
                      <i data-lucide="building" class="h-5 w-5 sm:h-6 sm:w-6 text-white"></i>
                    </div>
                    <div>
                      <p id="org-name-preview" class="font-semibold text-base sm:text-xl">Your Organization</p>
                      <p class="text-xs sm:text-sm text-gray-500">Legal Intelligence Platform</p>
                    </div>
                  </div>
                </div>

                <div class="space-y-4 sm:space-y-6">
                  <div>
                    <p class="text-xs text-gray-400 mb-2">PRIMARY BUTTON</p>
                    <button id="button-preview-primary" class="px-4 sm:px-6 py-2 sm:py-3 rounded-2xl text-white font-medium w-full text-sm sm:text-base bg-blue-600">Continue to Search</button>
                  </div>
                  <div>
                    <p class="text-xs text-gray-400 mb-2">SECONDARY BUTTON</p>
                    <button id="button-preview-secondary" class="px-4 sm:px-6 py-2 sm:py-3 border-2 rounded-2xl font-medium w-full text-sm sm:text-base border-blue-600 text-blue-600">View Reports</button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="flex flex-col sm:flex-row justify-end gap-3 sm:gap-4 mt-6 sm:mt-10">
            <button id="reset-settings" class="px-4 sm:px-6 py-2 sm:py-3 border border-gray-300 rounded-2xl text-gray-700 hover:bg-gray-50 transition font-medium text-sm sm:text-base order-2 sm:order-1">Reset to Defaults</button>
            <button id="save-settings" class="px-5 sm:px-8 py-2 sm:py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl transition font-medium text-sm sm:text-base order-1 sm:order-2">Save Branding Changes</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Add User Modal - Responsive -->
  <div id="add-user-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-3 sm:p-4">
    <div class="modal-overlay absolute inset-0"></div>
    <div class="bg-white rounded-3xl shadow-2xl max-w-2xl w-full slide-in relative max-h-[90vh] sm:max-h-[95vh] flex flex-col">
      <div class="p-5 sm:p-8 border-b flex-shrink-0">
        <div class="flex justify-between items-center">
          <h2 class="text-xl sm:text-2xl font-bold">Add New Team Member</h2>
          <button id="close-modal-btn" class="text-gray-400 hover:text-gray-600">
            <i data-lucide="x" class="w-5 h-5 sm:w-6 sm:h-6"></i>
          </button>
        </div>
      </div>

      <div class="p-5 sm:p-8 space-y-6 sm:space-y-8 overflow-y-auto flex-1">
        <!-- Basic Information -->
        <div>
          <h3 class="font-semibold mb-3 sm:mb-4 flex items-center gap-2 text-base sm:text-lg"><i data-lucide="user" class="w-4 h-4 sm:w-5 sm:h-5"></i> Basic Information</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
            <div>
              <label class="block text-sm font-medium mb-1.5">Full Name <span class="text-red-500">*</span></label>
              <input id="user-fullname" type="text" class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-2xl focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base" placeholder="John Doe" />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1.5">Email Address <span class="text-red-500">*</span></label>
              <input id="user-email" type="email" class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-2xl focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base" placeholder="john@example.com" />
            </div>
          </div>
        </div>

        <!-- Job Information -->
        <div>
          <h3 class="font-semibold mb-3 sm:mb-4 flex items-center gap-2 text-base sm:text-lg"><i data-lucide="briefcase" class="w-4 h-4 sm:w-5 sm:h-5"></i> Job Information</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
            <div>
              <label class="block text-sm font-medium mb-1.5">Job Title <span class="text-red-500">*</span></label>
              <input id="job-title" type="text" class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-2xl focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base" placeholder="Legal Officer" />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1.5">Department</label>
              <input id="department" type="text" class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-2xl focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base" placeholder="Legal Department" />
            </div>
          </div>
        </div>

        <!-- Account Security -->
        <div>
          <h3 class="font-semibold mb-3 sm:mb-4 flex items-center gap-2 text-base sm:text-lg"><i data-lucide="lock" class="w-4 h-4 sm:w-5 sm:h-5"></i> Account Security</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
            <div>
              <label class="block text-sm font-medium mb-1.5">Temporary Password <span class="text-red-500">*</span></label>
              <div class="relative">
                <i data-lucide="lock" class="absolute left-3 sm:left-4 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4 sm:w-5 sm:h-5"></i>
                <input id="temp-password" type="password" class="w-full pl-9 sm:pl-12 pr-3 sm:pr-4 py-2 sm:py-3 border border-gray-300 rounded-2xl focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base" placeholder="Enter password" />
              </div>
              <p class="text-xs text-gray-500 mt-1.5">User will be prompted to change password on first login</p>
            </div>
            <div>
              <label class="block text-sm font-medium mb-1.5">Confirm Password <span class="text-red-500">*</span></label>
              <div class="relative">
                <i data-lucide="lock" class="absolute left-3 sm:left-4 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4 sm:w-5 sm:h-5"></i>
                <input id="confirm-password" type="password" class="w-full pl-9 sm:pl-12 pr-3 sm:pr-4 py-2 sm:py-3 border border-gray-300 rounded-2xl focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base" placeholder="Confirm password" />
              </div>
            </div>
          </div>
        </div>

        <!-- User Type Selection -->
        <div>
          <h3 class="font-semibold mb-3 sm:mb-4 text-base sm:text-lg">User Type</h3>
          <div class="bg-yellow-50 rounded-2xl p-3 sm:p-4 mb-3 sm:mb-4">
            <p class="text-xs text-yellow-800 flex items-center gap-2"><i data-lucide="info" class="w-3 h-3 sm:w-4 sm:h-4"></i> User type cannot be changed after the user is added</p>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
            <label class="cursor-pointer border border-gray-200 hover:border-purple-300 rounded-2xl p-4 sm:p-5 transition-all has-[:checked]:border-purple-500 has-[:checked]:bg-purple-50">
              <input type="radio" name="user-type" value="super_admin" class="hidden" />
              <div class="flex items-center gap-3">
                <div class="text-purple-600"><i data-lucide="crown" class="w-5 h-5 sm:w-6 sm:h-6"></i></div>
                <div>
                  <p class="font-medium text-sm sm:text-base">Super Administrator</p>
                  <p class="text-xs text-gray-500">Full access & management rights</p>
                </div>
              </div>
            </label>
            <label class="cursor-pointer border border-gray-200 hover:border-blue-300 rounded-2xl p-4 sm:p-5 transition-all has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
              <input type="radio" name="user-type" value="regular_user" class="hidden" checked />
              <div class="flex items-center gap-3">
                <div class="text-blue-600"><i data-lucide="user" class="w-5 h-5 sm:w-6 sm:h-6"></i></div>
                <div>
                  <p class="font-medium text-sm sm:text-base">Regular User</p>
                  <p class="text-xs text-gray-500">Limited access - personal only</p>
                </div>
              </div>
            </label>
          </div>
        </div>

        <!-- Access Role Assignment -->
        <div>
          <h3 class="font-semibold mb-3 sm:mb-4 text-base sm:text-lg">Access Role</h3>
          <div class="grid grid-cols-1 gap-2 sm:gap-3">
            <label class="flex items-center gap-3 sm:gap-4 p-3 sm:p-4 border border-gray-200 rounded-2xl cursor-pointer hover:bg-gray-50 transition">
              <input type="radio" name="access-role" value="search_only" class="w-3 h-3 sm:w-4 sm:h-4" checked />
              <div>
                <p class="font-medium text-sm sm:text-base">Search Only</p>
                <p class="text-xs text-gray-500">Can only perform searches and view own results</p>
              </div>
            </label>
            <label class="flex items-center gap-3 sm:gap-4 p-3 sm:p-4 border border-gray-200 rounded-2xl cursor-pointer hover:bg-gray-50 transition">
              <input type="radio" name="access-role" value="report_viewer" class="w-3 h-3 sm:w-4 sm:h-4" />
              <div>
                <p class="font-medium text-sm sm:text-base">Report Viewer</p>
                <p class="text-xs text-gray-500">Can view and download all reports within permission scope</p>
              </div>
            </label>
            <label class="flex items-center gap-3 sm:gap-4 p-3 sm:p-4 border border-gray-200 rounded-2xl cursor-pointer hover:bg-gray-50 transition">
              <input type="radio" name="access-role" value="analytics_viewer" class="w-3 h-3 sm:w-4 sm:h-4" />
              <div>
                <p class="font-medium text-sm sm:text-base">Analytics Viewer</p>
                <p class="text-xs text-gray-500">Can view organization analytics and reports</p>
              </div>
            </label>
          </div>
        </div>

        <!-- Token Allocation -->
        <div>
          <label class="block text-sm font-medium mb-2">Initial Token Allocation</label>
          <input id="token-allocation" type="number" value="250" class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-2xl focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base" />
          <p class="text-xs text-gray-500 mt-2">Tokens will be deducted from organization balance</p>
        </div>
      </div>

      <div class="p-4 sm:p-6 border-t flex justify-end gap-3 sm:gap-4 bg-gray-50 rounded-b-3xl flex-shrink-0">
        <button id="cancel-add-user" class="px-5 sm:px-8 py-2 sm:py-3.5 border border-gray-300 rounded-2xl hover:bg-gray-100 transition text-sm sm:text-base">Cancel</button>
        <button id="confirm-add-user" class="px-5 sm:px-8 py-2 sm:py-3.5 bg-blue-600 text-white rounded-2xl hover:bg-blue-700 transition font-medium text-sm sm:text-base">Create Member</button>
      </div>
    </div>
  </div>

  <script>
    window.PHS_ORG = {
      institution: @json($institution),
      member: @json($member),
      members: @json($members),
      routes: {
        dashboard: "{{ route('phs.dashboard') }}",
        logout: "{{ route('phs.logout') }}",
        membersStore: "{{ route('phs.org.members.store') }}",
        membersBase: "{{ url('/phs/organization/members') }}",
        branding: "{{ route('phs.org.branding') }}",
        activity: "{{ route('phs.org.activity') }}"
      },
      assets: {
        logo: "{{ $institution->logo_path ? asset('storage/' . $institution->logo_path) : '' }}",
        banner: "{{ $institution->banner_path ? asset('storage/' . $institution->banner_path) : '' }}"
      }
    };
  </script>
  <script src="{{ asset('js/phs/organization.js') }}"></script>
</body>
</html>

<div class="sidebar-footer border-t border-gray-200 p-4">
  <div class="flex items-center gap-3">
    <div class="relative">
      <div class="h-10 w-10 rounded-full border-2 border-blue-600 cursor-pointer hover:scale-105 transition-transform overflow-hidden">
        <img src="https://img.freepik.com/free-vector/blue-circle-with-white-user_78370-4707.jpg?semt=ais_hybrid&w=740" alt="User" class="h-full w-full object-cover" />
      </div>
    </div>
    <div class="flex flex-col">
      @if(strtolower(trim(auth()->user()->email)) =='ict_director@klas.com.ng')
        <span class="text-sm font-medium">Supper Admin</span>
      @else
        <span class="text-sm font-medium">User</span>
      @endif
      <span class="text-xs text-gray-500">{{ auth()->user()->email }}</span>
    </div>
    <div class="relative ml-auto">
      <button class="p-1.5 rounded-md hover:bg-gray-100" id="userMenuButton">
        <i data-lucide="settings" class="h-4 w-4"></i>
      </button>
      <div class="absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 hidden" id="userMenu">
        <div class="py-1">
          <div class="px-4 py-2 text-sm font-medium border-b border-gray-100">My Account</div>
          <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
            <div class="flex items-center">
              <i data-lucide="user-circle" class="mr-2 h-4 w-4"></i>
              <span>Profile</span>
            </div>
          </a>
          <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
            <div class="flex items-center">
              <i data-lucide="settings" class="mr-2 h-4 w-4"></i>
              <span>Settings</span>
            </div>
          </a>
          <div class="border-t border-gray-100"></div>
          <a href="#" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
            <div class="flex items-center">
              <i data-lucide="lock" class="mr-2 h-4 w-4"></i>
              <span>Logout</span>
            </div>
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

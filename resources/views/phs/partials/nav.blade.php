<header class="border-b border-slate-200 bg-white">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
        <a href="{{ route('phs.dashboard') }}" class="flex items-center gap-3">
            <span class="grid h-9 w-9 place-items-center rounded-md bg-slate-900 text-white"><i data-lucide="landmark" class="h-5 w-5"></i></span>
            <span>
                <span class="block text-sm font-extrabold leading-4">PHS Portal</span>
                <span class="block text-xs text-slate-500">{{ $institution->name ?? 'Institution' }}</span>
            </span>
        </a>
        <nav class="flex items-center gap-2">
            <a href="{{ route('phs.dashboard') }}" class="rounded-md px-3 py-2 text-sm font-semibold {{ request()->routeIs('phs.dashboard') ? 'bg-slate-100 text-slate-950' : 'text-slate-600 hover:bg-slate-100' }}">Search</a>
            @if(($member ?? null) && $member->isSuperAdmin())
                <a href="{{ route('phs.org.index') }}" class="rounded-md px-3 py-2 text-sm font-semibold {{ request()->routeIs('phs.org.*') ? 'bg-slate-100 text-slate-950' : 'text-slate-600 hover:bg-slate-100' }}">Organization</a>
            @endif
            <form method="POST" action="{{ route('phs.logout') }}">
                @csrf
                <button class="inline-flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">
                    <i data-lucide="log-out" class="h-4 w-4"></i>
                    Logout
                </button>
            </form>
        </nav>
    </div>
</header>

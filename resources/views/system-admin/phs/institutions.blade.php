@extends('layouts.app')

@section('page-title', $PageTitle)

@section('content')
<div class="flex-1 overflow-auto bg-slate-50 flex flex-col min-h-full">
    @include('admin.header', ['PageTitle' => $PageTitle, 'PageDescription' => 'Manage Organizational PHS accounts and token balances.'])

    <div class="flex-1 p-6">
        <div class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-start gap-4">
                    <div class="grid h-12 w-12 place-items-center rounded-lg bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100">
                        <i data-lucide="building-2" class="h-6 w-6"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-extrabold text-slate-900">Organization Directory</h2>
                        <p class="mt-1 max-w-2xl text-sm text-slate-500">Review PHS organizations, wallet balances, invoice requests, and account status from one staff console.</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('system-admin.phs.requests.index') }}" class="inline-flex items-center gap-2 rounded-md border border-sky-200 bg-sky-50 px-4 py-2 text-sm font-bold text-sky-800 hover:bg-sky-100">
                        <i data-lucide="user-plus" class="h-4 w-4"></i>
                        Onboarding requests
                    </a>
                    <a href="{{ route('system-admin.phs.pending-invoices') }}" class="inline-flex items-center gap-2 rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-bold text-amber-800 hover:bg-amber-100">
                        <i data-lucide="file-clock" class="h-4 w-4"></i>
                        Pending invoices
                    </a>
                    <a href="{{ route('system-admin.phs.invoices') }}" class="inline-flex items-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-bold text-emerald-800 hover:bg-emerald-100">
                        <i data-lucide="receipt" class="h-4 w-4"></i>
                        Subscriptions
                    </a>
                    <a href="{{ route('system-admin.phs.usage') }}" class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">
                        <i data-lucide="bar-chart-3" class="h-4 w-4"></i>
                        Usage and revenue
                    </a>
                    <a href="{{ route('system-admin.phs.packages.index') }}" class="inline-flex items-center gap-2 rounded-md border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-bold text-indigo-800 hover:bg-indigo-100">
                        <i data-lucide="package" class="h-4 w-4"></i>
                        Token packages
                    </a>
                    <a href="{{ route('system-admin.phs.topups') }}" class="inline-flex items-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-bold text-emerald-800 hover:bg-emerald-100">
                        <i data-lucide="wallet" class="h-4 w-4"></i>
                        Token topups
                    </a>
                    <a href="{{ route('system-admin.phs.wallets') }}" class="inline-flex items-center gap-2 rounded-md border border-sky-200 bg-sky-50 px-4 py-2 text-sm font-bold text-sky-800 hover:bg-sky-100">
                        <i data-lucide="landmark" class="h-4 w-4"></i>
                        Wallets
                    </a>
                </div>
            </div>
        </div>

        @php
            $statCards = [
                ['label' => 'Organizations', 'value' => $stats['institutions'], 'icon' => 'building-2', 'tone' => 'bg-indigo-50 text-indigo-700 ring-indigo-100'],
                ['label' => 'Active', 'value' => $stats['active'], 'icon' => 'check-circle-2', 'tone' => 'bg-emerald-50 text-emerald-700 ring-emerald-100'],
                ['label' => 'Total tokens', 'value' => $stats['total_tokens'], 'icon' => 'coins', 'tone' => 'bg-sky-50 text-sky-700 ring-sky-100'],
                ['label' => 'Pending invoices', 'value' => $stats['pending_invoices'], 'icon' => 'file-clock', 'tone' => 'bg-amber-50 text-amber-700 ring-amber-100'],
            ];
        @endphp

        <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach($statCards as $card)
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ $card['label'] }}</p>
                            <p class="mt-2 text-3xl font-extrabold text-slate-900">{{ number_format($card['value']) }}</p>
                        </div>
                        <div class="grid h-11 w-11 place-items-center rounded-lg ring-1 {{ $card['tone'] }}">
                            <i data-lucide="{{ $card['icon'] }}" class="h-5 w-5"></i>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-5 py-4">
                <div class="flex items-center gap-3">
                    <div class="grid h-9 w-9 place-items-center rounded-md bg-slate-100 text-slate-700">
                        <i data-lucide="table-2" class="h-4 w-4"></i>
                    </div>
                    <div>
                        <h3 class="font-extrabold text-slate-900">Registered organizations</h3>
                        <p class="text-xs text-slate-500">{{ number_format($institutions->count()) }} organization(s) found</p>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Organization</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Members</th>
                            <th class="px-4 py-3">Tokens</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($institutions as $institution)
                            <tr class="transition hover:bg-slate-50/80">
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="grid h-10 w-10 shrink-0 place-items-center rounded-md bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100">
                                            <i data-lucide="landmark" class="h-5 w-5"></i>
                                        </div>
                                        <div>
                                            <p class="font-extrabold text-slate-900">{{ $institution->name }}</p>
                                            <p class="mt-0.5 flex items-center gap-1.5 text-xs text-slate-500">
                                                <i data-lucide="mail" class="h-3.5 w-3.5"></i>
                                                {{ $institution->email }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-slate-100 px-2.5 py-1 text-xs font-bold capitalize text-slate-700">
                                        <i data-lucide="briefcase-business" class="h-3.5 w-3.5"></i>
                                        {{ str_replace('_', ' ', $institution->type) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex items-center gap-1.5 font-bold text-slate-700">
                                        <i data-lucide="users" class="h-4 w-4 text-slate-400"></i>
                                        {{ $institution->members_count }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex items-center gap-1.5 font-extrabold text-slate-900">
                                        <i data-lucide="coins" class="h-4 w-4 text-sky-500"></i>
                                        {{ number_format((int) $institution->token_balance) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    @if($institution->status === 'active')
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 ring-1 ring-emerald-100">
                                            <i data-lucide="check-circle-2" class="h-3.5 w-3.5"></i>
                                            Active
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2.5 py-1 text-xs font-bold text-red-700 ring-1 ring-red-100">
                                            <i data-lucide="pause-circle" class="h-3.5 w-3.5"></i>
                                            {{ ucfirst($institution->status) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <a class="inline-flex items-center gap-2 rounded-md bg-slate-900 px-3 py-2 text-xs font-bold text-white hover:bg-slate-800" href="{{ route('system-admin.phs.institutions.show', $institution->id) }}">
                                        Open
                                        <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-14 text-center text-slate-400">
                                    <i data-lucide="building-2" class="mx-auto mb-3 h-8 w-8 opacity-40"></i>
                                    No organizations registered.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @include('admin.footer')
</div>
@endsection

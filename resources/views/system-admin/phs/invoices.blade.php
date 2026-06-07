@extends('layouts.app')

@section('page-title', $PageTitle)

@section('content')
<div class="flex-1 overflow-auto bg-slate-50 flex flex-col min-h-full">
    @include('admin.header', ['PageTitle' => $PageTitle, 'PageDescription' => 'Approve invoice token purchases.'])
    <div class="flex-1 p-6">
        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-bold uppercase text-slate-500"><tr><th class="px-4 py-3">Institution</th><th class="px-4 py-3">Package</th><th class="px-4 py-3">Tokens</th><th class="px-4 py-3">Amount</th><th class="px-4 py-3">Reference</th><th class="px-4 py-3 text-right">Action</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($pending as $txn)
                        <tr>
                            <td class="px-4 py-3 font-bold">{{ optional($txn->institution)->name }}</td>
                            <td class="px-4 py-3">{{ $txn->package_name }}</td>
                            <td class="px-4 py-3">{{ number_format($txn->tokens) }}</td>
                            <td class="px-4 py-3">NGN {{ number_format((float) $txn->amount, 2) }}</td>
                            <td class="px-4 py-3">{{ $txn->reference_no }}</td>
                            <td class="px-4 py-3 text-right"><form method="POST" action="{{ route('system-admin.phs.invoices.approve', $txn->id) }}">@csrf<button class="rounded-md bg-emerald-600 px-3 py-2 text-xs font-bold text-white">Approve</button></form></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">No pending invoices.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @include('admin.footer')
</div>
@endsection

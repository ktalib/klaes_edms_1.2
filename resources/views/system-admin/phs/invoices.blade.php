@extends('layouts.app')

@section('page-title', $PageTitle)

@section('content')
<div class="flex-1 overflow-auto bg-slate-50 flex flex-col min-h-full">
    @include('admin.header', ['PageTitle' => $PageTitle, 'PageDescription' => 'Manage subscriptions created from approved invoice purchases.'])
    <div class="flex-1 p-6">
        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-bold uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Institution</th>
                        <th class="px-4 py-3">Amount</th>
                        <th class="px-4 py-3">Tokens</th>
                        <th class="px-4 py-3">Balance</th>
                        <th class="px-4 py-3">Activation date</th>
                        <th class="px-4 py-3">Expires</th>
                        <th class="px-4 py-3">Reference</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($pending as $txn)
                        <tr>
                            <td class="px-4 py-3 font-bold">{{ optional($txn->institution)->name }}</td>
                            <td class="px-4 py-3">NGN {{ number_format((float) $txn->amount, 2) }}</td>
                            <td class="px-4 py-3">{{ number_format($txn->tokens) }}</td>
                            <td class="px-4 py-3">{{ number_format((int) optional($txn->institution)->token_balance) }}</td>
                            <td class="px-4 py-3">
                                @if (optional($txn->institution)->created_at)
                                    {{ \Carbon\Carbon::parse($txn->institution->created_at)->format('M j, Y') }}
                                @elseif ($txn->approved_at)
                                    {{ \Carbon\Carbon::parse($txn->approved_at)->format('M j, Y') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($txn->expires_at)
                                    {{ \Carbon\Carbon::parse($txn->expires_at)->format('M j, Y') }}
                                @elseif ($txn->approved_at)
                                    {{ \Carbon\Carbon::parse($txn->approved_at)->addYear()->format('M j, Y') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $txn->reference_no }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">No subscriptions found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @include('admin.footer')
</div>
@endsection

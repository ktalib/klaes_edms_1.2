@extends('layouts.app')

@section('page-title', $PageTitle)

@section('content')
<div class="flex-1 overflow-auto bg-slate-50 flex flex-col min-h-full">
    @include('admin.header', ['PageTitle' => $PageTitle, 'PageDescription' => 'Review and approve PHS onboarding requests submitted by organizations.'])
    <div class="flex-1 p-6">
        <div class="mb-8">
            <h1 class="text-3xl font-bold mb-4">{{ $PageTitle }}</h1>

            <!-- Status Filter Tabs -->
        <div class="flex space-x-4 border-b mb-6">
            <a href="{{ route('system-admin.phs.requests.index') }}"
                class="px-4 py-2 border-b-2 font-medium {{ !$statusFilter ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-600 hover:text-gray-800' }}">
                All ({{ array_sum($statsByStatus) }})
            </a>
            @foreach(['pending', 'documents_approved', 'payment_pending', 'payment_received', 'sla_uploaded', 'sla_approved', 'approved', 'activated', 'rejected'] as $status)
                <a href="{{ route('system-admin.phs.requests.index', ['status' => $status]) }}"
                    class="px-4 py-2 border-b-2 font-medium {{ $statusFilter === $status ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-600 hover:text-gray-800' }}">
                    {{ ucwords(str_replace('_', ' ', $status)) }} ({{ $statsByStatus[$status] }})
                </a>
            @endforeach
        </div>
    </div>

    @if (session('success'))
        <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
            {{ session('success') }}
        </div>
    @endif

    @if ($requests->isEmpty())
        <div class="text-center py-12">
            <p class="text-gray-600">No onboarding requests found.</p>
        </div>
    @else
        <div class="bg-white rounded-lg shadow overflow-visible">
            <table class="w-full">
                <thead class="bg-gray-100 border-b">
                    <tr>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900 w-10">S/N</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Organization</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Contact</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Email</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Type</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Subscription</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Status</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Payment</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">SLA</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Submitted</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($requests as $req)
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-400">{{ $loop->iteration }}</td>
                            <td class="px-6 py-4 text-sm">
                                <strong>{{ $req->organization_name }}</strong>
                            </td>
                            <td class="px-6 py-4 text-sm">{{ $req->contact_name }}</td>
                            <td class="px-6 py-4 text-sm">{{ $req->contact_email }}</td>
                            <td class="px-6 py-4 text-sm capitalize">{{ str_replace('_', ' ', $req->organization_type) }}</td>
                            <td class="px-6 py-4 text-sm">
                                @if ($req->initial_token_package)
                                    @php $pkg = \App\Http\Controllers\Phs\PhsTokenController::packages()[strtolower($req->initial_token_package)] ?? null; @endphp
                                    <div class="font-medium text-gray-900">{{ $req->initial_token_package }}</div>
                                    @if ($pkg)
                                        <div class="text-xs text-gray-500">{{ number_format($pkg['tokens']) }} tokens</div>
                                    @endif
                                    @if ($req->payment_amount)
                                        <div class="text-xs text-gray-500">₦{{ number_format((float) $req->payment_amount) }}</div>
                                    @endif
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm">
                                @php
                                    $statusColors = [
                                        'pending'            => 'bg-yellow-100 text-yellow-800',
                                        'documents_approved' => 'bg-teal-100 text-teal-800',
                                        'payment_pending'    => 'bg-orange-100 text-orange-800',
                                        'payment_received'   => 'bg-blue-100 text-blue-800',
                                        'sla_uploaded'       => 'bg-indigo-100 text-indigo-800',
                                        'sla_approved'       => 'bg-cyan-100 text-cyan-800',
                                        'approved'           => 'bg-purple-100 text-purple-800',
                                        'activated'          => 'bg-green-100 text-green-800',
                                        'rejected'           => 'bg-red-100 text-red-800',
                                    ];
                                @endphp
                                <span class="px-3 py-1 rounded-full text-xs font-medium {{ $statusColors[$req->status] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ ucwords(str_replace('_', ' ', $req->status)) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                @php
                                    $payColors = [
                                        'completed' => 'bg-green-100 text-green-800',
                                        'incomplete' => 'bg-amber-100 text-amber-800',
                                        'overpaid' => 'bg-sky-100 text-sky-800',
                                        'not_paid' => 'bg-gray-100 text-gray-600',
                                    ];
                                    $payLabels = [
                                        'completed' => 'Completed',
                                        'incomplete' => 'Incomplete',
                                        'overpaid' => 'Overpaid',
                                        'not_paid' => 'Not paid',
                                    ];
                                    $ps = $req->payment_status ?: 'not_paid';
                                @endphp
                                <span class="px-3 py-1 rounded-full text-xs font-medium {{ $payColors[$ps] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ $payLabels[$ps] ?? ucfirst($ps) }}
                                </span>
                                @if ($ps === 'incomplete' && $req->outstanding_amount)
                                    <div class="text-xs text-amber-700 mt-1">₦{{ number_format((float) $req->outstanding_amount) }} outstanding</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm">
                                @if ($req->lsa_signed_document_path)
                                    <span class="px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">Signed</span>
                                @else
                                    <span class="px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Pending</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm">{{ $req->created_at->format('M j, Y') }}</td>
                            <td class="px-6 py-4 text-sm relative">
                                <details class="relative inline-block">
                                                                        <summary class="list-none p-2 rounded-full text-slate-600 hover:bg-slate-100 cursor-pointer" title="Actions" aria-haspopup="true">
                                                                                <!-- vertical ellipsis icon -->
                                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                                                    <path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4z" />
                                                                                </svg>
                                                                        </summary>
                                    <div class="absolute mt-2 right-0 bg-white border rounded shadow-lg z-50 w-40">
                                        <a href="{{ route('system-admin.phs.requests.show', ['id' => $req->id]) }}" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path d="M2.94 10.94a10.97 10.97 0 0114.12 0 1 1 0 01-1.28 1.54 8.97 8.97 0 00-11.56 0 1 1 0 01-1.28-1.54z" opacity=".2" />
                                                <path d="M10 4a6 6 0 100 12 6 6 0 000-12zM10 8a2 2 0 110 4 2 2 0 010-4z" />
                                            </svg>
                                            View
                                        </a>
                                        <a href="{{ route('system-admin.phs.requests.invoice', ['id' => $req->id]) }}" target="_blank" rel="noopener" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                            Print Invoice
                                        </a>
                                        @php
                                            $canSendPayment  = $req->status === 'documents_approved';
                                            $canFinalApprove = $req->status === 'sla_approved';
                                            $approveDisabled = !$canSendPayment && !$canFinalApprove;
                                            $approveRoute    = $canFinalApprove
                                                ? route('system-admin.phs.requests.final-approve', ['id' => $req->id])
                                                : route('system-admin.phs.requests.approve',       ['id' => $req->id]);
                                            $approveLabel    = $canFinalApprove ? 'Send Onboarding Link' : 'Approve';
                                            $approveMsg      = $canFinalApprove
                                                ? 'Send the onboarding link to ' . $req->organization_name . '?'
                                                : 'Approve ' . $req->organization_name . ' and send the payment link?';
                                        @endphp
                                        <form action="{{ $approveRoute }}" method="POST" class="m-0 approve-form">
                                            @csrf
                                            <button type="button"
                                                data-org="{{ $req->organization_name }}"
                                                data-msg="{{ $approveMsg }}"
                                                class="approve-btn w-full flex items-center gap-2 text-left px-4 py-2 text-sm {{ $approveDisabled ? 'text-gray-400 cursor-not-allowed' : 'text-green-700 hover:bg-gray-50' }}"
                                                {{ $approveDisabled ? 'disabled' : '' }}>
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 00-1.414-1.414L8 11.172 4.707 7.879a1 1 0 00-1.414 1.414l4 4a1 1 0 001.414 0l8-8z" clip-rule="evenodd" />
                                                </svg>
                                                {{ $approveLabel }}
                                            </button>
                                        </form>
                                    </div>
                                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                                    <script>
                                        document.addEventListener('DOMContentLoaded', function () {
                                            document.querySelectorAll('.approve-btn').forEach(function (btn) {
                                                btn.addEventListener('click', function (e) {
                                                    if (btn.disabled) return;
                                                    var msg = btn.getAttribute('data-msg') || 'Approve this request?';
                                                    Swal.fire({
                                                        title: 'Approve request?',
                                                        text: msg,
                                                        icon: 'question',
                                                        showCancelButton: true,
                                                        confirmButtonText: 'Yes, approve',
                                                        cancelButtonText: 'No'
                                                    }).then(function (result) {
                                                        if (result.isConfirmed) {
                                                            // submit the enclosing form
                                                            var form = btn.closest('form');
                                                            if (form) form.submit();
                                                        }
                                                    });
                                                });
                                            });
                                        });
                                    </script>
                                </details>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection

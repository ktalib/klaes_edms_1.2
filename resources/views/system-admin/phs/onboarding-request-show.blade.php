@extends('layouts.app')

@section('page-title', $PageTitle)

@section('content')
<div class="flex-1 overflow-auto bg-slate-50 flex flex-col min-h-full">
    @include('admin.header', ['PageTitle' => $PageTitle, 'PageDescription' => 'View the onboarding request details and approve or reject the applicant.'])
    <div class="flex-1 p-6">
        <div class="mb-8">
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h1 class="text-3xl font-bold">{{ $PageTitle }}</h1>
                    <a href="{{ route('system-admin.phs.requests.index') }}" class="text-blue-600 hover:text-blue-800 mt-2">← Back to Requests</a>
                </div>
                <a href="{{ route('system-admin.phs.requests.invoice', ['id' => $request->id]) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    <i data-lucide="printer" class="h-4 w-4"></i> Print Invoice
                </a>
                @php
                $statusColors = [
                    'pending' => 'bg-yellow-100 text-yellow-800',
                    'payment_received' => 'bg-blue-100 text-blue-800',
                    'approved' => 'bg-purple-100 text-purple-800',
                    'activated' => 'bg-green-100 text-green-800',
                    'rejected' => 'bg-red-100 text-red-800',
                ];
            @endphp
            <span class="px-4 py-2 rounded-full text-sm font-medium {{ $statusColors[$request->status] ?? 'bg-gray-100 text-gray-800' }}">
                {{ ucwords(str_replace('_', ' ', $request->status)) }}
            </span>
        </div>

        @if (session('success'))
            <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                {{ session('error') }}
            </div>
        @endif
    </div>

    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="border-b border-slate-200 bg-slate-50 px-6 py-5 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-sm uppercase tracking-[0.24em] text-slate-500">Request Overview</p>
                <h2 class="text-2xl font-bold text-slate-900">{{ $request->organization_name }} — {{ str_replace('_', ' ', $request->organization_type) }}</h2>
            </div>
            <span class="inline-flex items-center rounded-full bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 ring-1 ring-emerald-100">
                {{ ucwords(str_replace('_', ' ', $request->status)) }}
            </span>
        </div>

        <div class="p-6 space-y-6">
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="space-y-6">
                    <section class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <h3 class="text-lg font-semibold text-slate-900 mb-3">Organization</h3>
                        <div class="space-y-2 text-sm text-slate-700">
                            <p class="flex items-start gap-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 mt-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 6a3 3 0 013-3h8a3 3 0 013 3v8a3 3 0 01-3 3H6a3 3 0 01-3-3V6z"/></svg><span class="text-slate-500">Name:</span> <span class="font-semibold">{{ $request->organization_name }}</span></p>
                            <p class="flex items-start gap-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 mt-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 5a1 1 0 011-1h12a1 1 0 011 1v10a1 1 0 01-1 1H4a1 1 0 01-1-1V5z"/></svg><span class="text-slate-500">Type:</span> <span class="font-semibold capitalize">{{ str_replace('_', ' ', $request->organization_type) }}</span></p>
                            <p class="flex items-start gap-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 mt-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2.94 10.94a8 8 0 1114.12 0L10 18l-7.06-7.06z"/></svg><span class="text-slate-500">Address:</span> <span class="font-semibold">{{ $request->address ?? 'Not provided' }}</span></p>
                            <p class="flex items-start gap-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 mt-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2 5a2 2 0 012-2h3.28a1 1 0 01.97.757L9.9 7.9a11.04 11.04 0 005.2 5.2l4.14-1.66A1 1 0 0120 11.72V15a2 2 0 01-2 2h-1C9.163 17 3 10.837 3 3V2a1 1 0 011-1h1z"/></svg><span class="text-slate-500">Phone:</span> <span class="font-semibold">{{ $request->phone ?? 'Not provided' }}</span></p>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <h3 class="text-lg font-semibold text-slate-900 mb-3">Request Details</h3>
                        <div class="space-y-2 text-sm text-slate-700">
                            <p class="flex items-start gap-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 mt-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6 2a1 1 0 000 2h8a1 1 0 100-2H6zM3 7a1 1 0 011-1h12a1 1 0 011 1v8a1 1 0 01-1 1H4a1 1 0 01-1-1V7z"/></svg><span class="text-slate-500">Submitted:</span> <span class="font-semibold">{{ $request->created_at->format('F j, Y \a\t g:i A') }}</span></p>
                            <p class="flex items-start gap-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 mt-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M4 3h12v4H4V3zM3 9h14v8a1 1 0 01-1 1H4a1 1 0 01-1-1V9z"/></svg><span class="text-slate-500">Preferred Token Package:</span> <span class="font-semibold">{{ $request->initial_token_package ?? 'No preference' }}</span></p>
                            <p class="flex items-start gap-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 mt-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zM8 9h4v2H8V9z"/></svg><span class="text-slate-500">Payment Status:</span>
                                @if ($request->payment_received_at)
                                    <span class="font-semibold">Received on {{ $request->payment_received_at->format('M j, Y') }}</span>
                                @else
                                    <span class="font-semibold">Pending</span>
                                @endif
                            </p>
                        </div>
                    </section>
                </div>

                <div class="space-y-6">
                    <section class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <h3 class="text-lg font-semibold text-slate-900 mb-3">Contact</h3>
                        <div class="space-y-2 text-sm text-slate-700">
                            <p class="flex items-start gap-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 mt-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 2a4 4 0 100 8 4 4 0 000-8zM2 18a8 8 0 0116 0H2z"/></svg><span class="text-slate-500">Name:</span> <span class="font-semibold">{{ $request->contact_name }}</span></p>
                            <p class="flex items-start gap-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 mt-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2 5a2 2 0 012-2h12a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V5z"/></svg><span class="text-slate-500">Email:</span> <span class="font-semibold">{{ $request->contact_email }}</span></p>
                            <p class="flex items-start gap-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 mt-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M4 3h12v4H4V3zM3 9h14v8a1 1 0 01-1 1H4a1 1 0 01-1-1V9z"/></svg><span class="text-slate-500">Job Title:</span> <span class="font-semibold">{{ $request->job_title ?? 'Not provided' }}</span></p>
                            <p class="flex items-start gap-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 mt-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M4 4h12v12H4z"/></svg><span class="text-slate-500">Department:</span> <span class="font-semibold">{{ $request->department ?? 'Not provided' }}</span></p>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <h3 class="text-lg font-semibold text-slate-900 mb-3">Notes</h3>
                        <p class="text-sm text-slate-700">
                            @if ($request->additional_notes)
                                {{ $request->additional_notes }}
                            @else
                                <span class="text-slate-500 italic">None provided</span>
                            @endif
                        </p>
                    </section>
                </div>
            </div>

            <section class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                <h3 class="text-lg font-semibold text-slate-900 mb-3">CAC Documentation</h3>
                <div class="grid gap-4 sm:grid-cols-2 text-sm text-slate-700">
                    <div>
                        <p class="text-slate-500">CAC Registration Number</p>
                        <p class="font-semibold">{{ $request->cac_registration_number ?: 'Not provided' }}</p>
                    </div>
                    <div>
                        <p class="text-slate-500">CAC Certificate</p>
                        @if ($request->cac_document_path)
                            <a href="{{ asset('storage/' . $request->cac_document_path) }}" target="_blank" rel="noopener"
                               class="mt-1 inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                <i data-lucide="file-text" class="h-3.5 w-3.5"></i> View CAC Certificate
                            </a>
                        @else
                            <p class="font-semibold text-slate-400">Not uploaded</p>
                        @endif
                    </div>
                    @if (!empty($request->additional_documents))
                        <div class="sm:col-span-2">
                            <p class="text-slate-500 mb-1">Additional Documents</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($request->additional_documents as $i => $doc)
                                    <a href="{{ asset('storage/' . $doc) }}" target="_blank" rel="noopener"
                                       class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                        <i data-lucide="paperclip" class="h-3.5 w-3.5"></i> Document {{ $i + 1 }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </section>

            @if ($request->rejection_reason)
                <div class="rounded-2xl border border-red-200 bg-red-50 p-5">
                    <h3 class="text-lg font-semibold text-red-900 mb-3">Rejection Reason</h3>
                    <p class="text-sm text-red-800">{{ $request->rejection_reason }}</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Payment Verification -->
    @php
        $pkg = \App\Http\Controllers\Phs\PhsTokenController::packages()[strtolower((string) $request->initial_token_package)] ?? null;
        $expected = $request->expected_amount ?? ($pkg['price'] ?? $request->payment_amount ?? 0);
        $ps = $request->payment_status ?: 'not_paid';
        $payBadge = [
            'completed' => ['Completed', 'bg-green-100 text-green-800 ring-green-200'],
            'incomplete' => ['Incomplete', 'bg-amber-100 text-amber-800 ring-amber-200'],
            'overpaid' => ['Overpaid', 'bg-sky-100 text-sky-800 ring-sky-200'],
            'not_paid' => ['Not Paid', 'bg-gray-100 text-gray-600 ring-gray-200'],
        ][$ps] ?? ['Unknown', 'bg-gray-100 text-gray-600 ring-gray-200'];
    @endphp
    <div class="mt-8 bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="border-b border-slate-200 bg-slate-50 px-6 py-5 flex items-center justify-between">
            <div>
                <p class="text-sm uppercase tracking-[0.24em] text-slate-500">Payment Verification</p>
                <h2 class="text-xl font-bold text-slate-900">Confirm the amount received</h2>
            </div>
            <span class="inline-flex items-center rounded-full px-4 py-2 text-sm font-semibold ring-1 {{ $payBadge[1] }}">{{ $payBadge[0] }}</span>
        </div>
        <div class="p-6 grid gap-6 lg:grid-cols-2">
            <div class="space-y-3 text-sm">
                <div class="flex justify-between border-b border-slate-100 pb-2"><span class="text-slate-500">Expected (package price)</span><span class="font-semibold text-slate-900">₦{{ number_format((float) $expected, 2) }}</span></div>
                <div class="flex justify-between border-b border-slate-100 pb-2"><span class="text-slate-500">Amount entered by organization</span><span class="font-semibold text-slate-900">₦{{ number_format((float) $request->payment_amount, 2) }}</span></div>
                <div class="flex justify-between border-b border-slate-100 pb-2"><span class="text-slate-500">Reference</span><span class="font-mono text-xs text-slate-700">{{ $request->payment_reference ?: '—' }}</span></div>
                <div class="flex justify-between border-b border-slate-100 pb-2"><span class="text-slate-500">Verified amount</span><span class="font-semibold text-slate-900">{{ $request->verified_amount !== null ? '₦' . number_format((float) $request->verified_amount, 2) : '—' }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Outstanding balance</span>
                    <span class="font-bold {{ (float) $request->outstanding_amount > 0 ? 'text-amber-700' : 'text-green-700' }}">₦{{ number_format((float) ($request->outstanding_amount ?? 0), 2) }}</span>
                </div>
                @if ($request->payment_verified_at)
                    <p class="text-xs text-slate-400 pt-2">Verified {{ $request->payment_verified_at->format('M j, Y g:i A') }}{{ optional($request->paymentVerifier)->name ? ' by ' . $request->paymentVerifier->name : '' }}.</p>
                @endif
                @if ($request->payment_verification_notes)
                    <p class="text-xs text-slate-500 italic">“{{ $request->payment_verification_notes }}”</p>
                @endif
            </div>

            <form method="POST" action="{{ route('system-admin.phs.requests.verify-payment', ['id' => $request->id]) }}" class="rounded-2xl border border-slate-200 bg-slate-50 p-5 space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Expected amount (₦)</label>
                    <input type="number" name="expected_amount" min="0" step="0.01" value="{{ (float) $expected }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Amount received (₦) <span class="text-rose-500">*</span></label>
                    <input type="number" name="verified_amount" min="0" step="0.01" required value="{{ $request->verified_amount }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" placeholder="0.00">
                    <p class="text-[11px] text-slate-400 mt-1">Enter 0 if nothing has been received yet.</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Notes</label>
                    <textarea name="payment_verification_notes" rows="2" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" placeholder="Bank reference, discrepancy, etc.">{{ $request->payment_verification_notes }}</textarea>
                </div>
                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700">
                    <i data-lucide="badge-check" class="h-4 w-4"></i> Confirm Payment
                </button>
            </form>
        </div>
    </div>

    <!-- Actions -->
    @if ($request->status === 'pending' || $request->status === 'payment_received')
        <div class="mt-8 bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold mb-4">Admin Actions</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Approve Form -->
                <form method="POST" action="{{ route('system-admin.phs.requests.approve', ['id' => $request->id]) }}" class="approve-form">
                    @csrf
                    <div class="border border-green-300 rounded-lg p-4 bg-green-50">
                        <h3 class="font-semibold text-green-900 mb-2">Approve Request</h3>
                        <p class="text-sm text-green-800 mb-4">Approving will send an activation email to the organization with a registration link.</p>
                        <button type="button" data-org="{{ $request->organization_name }}" class="approve-btn-show w-full flex items-center justify-center gap-2 px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 font-medium">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 00-1.414-1.414L8 11.172 4.707 7.879a1 1 0 00-1.414 1.414l4 4a1 1 0 001.414 0l8-8z" clip-rule="evenodd" />
                            </svg>
                            Approve Request
                        </button>
                    </div>
                </form>

                <!-- Reject Form -->
                <form method="POST" action="{{ route('system-admin.phs.requests.reject', ['id' => $request->id]) }}">
                    @csrf
                    <div class="border border-red-300 rounded-lg p-4 bg-red-50">
                        <h3 class="font-semibold text-red-900 mb-2">Reject Request</h3>
                        <label for="rejection_reason" class="block text-sm text-red-800 mb-2">Rejection Reason *</label>
                        <textarea id="rejection_reason" name="rejection_reason" required rows="3"
                            class="w-full px-3 py-2 border border-red-300 rounded-md text-sm mb-3"
                            placeholder="Explain why this request is being rejected..."></textarea>
                        <button type="submit" class="w-full flex items-center justify-center gap-2 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 font-medium">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-10.293a1 1 0 00-1.414-1.414L10 8.586 7.707 6.293a1 1 0 00-1.414 1.414L8.586 10l-2.293 2.293a1 1 0 001.414 1.414L10 11.414l2.293 2.293a1 1 0 001.414-1.414L11.414 10l2.293-2.293z" clip-rule="evenodd" />
                            </svg>
                            Reject Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.approve-btn-show').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                if (btn.disabled) return;
                var org = btn.getAttribute('data-org') || 'this request';
                Swal.fire({
                    title: 'Approve request?',
                    text: 'Approve ' + org + ' and send activation email?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, approve',
                    cancelButtonText: 'No'
                }).then(function (result) {
                    if (result.isConfirmed) {
                        var form = btn.closest('form');
                        if (form) form.submit();
                    }
                });
            });
        });
    });
</script>
@endsection

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
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                    <h3 class="text-lg font-semibold text-slate-900 mb-3">Organization Information</h3>
                    <dl class="grid gap-3 text-sm text-slate-700">
                        <div>
                            <dt class="font-semibold text-slate-500">Name</dt>
                            <dd>{{ $request->organization_name }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-500">Type</dt>
                            <dd class="capitalize">{{ str_replace('_', ' ', $request->organization_type) }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-500">Address</dt>
                            <dd>{{ $request->address ?? 'Not provided' }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-500">Phone</dt>
                            <dd>{{ $request->phone ?? 'Not provided' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                    <h3 class="text-lg font-semibold text-slate-900 mb-3">Contact Information</h3>
                    <dl class="grid gap-3 text-sm text-slate-700">
                        <div>
                            <dt class="font-semibold text-slate-500">Contact Name</dt>
                            <dd>{{ $request->contact_name }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-500">Email</dt>
                            <dd>{{ $request->contact_email }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-500">Job Title</dt>
                            <dd>{{ $request->job_title ?? 'Not provided' }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-500">Department</dt>
                            <dd>{{ $request->department ?? 'Not provided' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                    <h3 class="text-lg font-semibold text-slate-900 mb-3">Request Details</h3>
                    <dl class="grid gap-3 text-sm text-slate-700">
                        <div>
                            <dt class="font-semibold text-slate-500">Submitted</dt>
                            <dd>{{ $request->created_at->format('F j, Y \a\t g:i A') }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-500">Preferred Token Package</dt>
                            <dd>{{ $request->initial_token_package ?? 'No preference' }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-500">Payment Status</dt>
                            <dd>
                                @if ($request->payment_received_at)
                                    Received on {{ $request->payment_received_at->format('M j, Y') }}
                                @else
                                    Pending
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                    <h3 class="text-lg font-semibold text-slate-900 mb-3">Additional Notes</h3>
                    <p class="text-sm text-slate-700">
                        @if ($request->additional_notes)
                            {{ $request->additional_notes }}
                        @else
                            <span class="text-slate-500 italic">None provided</span>
                        @endif
                    </p>
                </div>
            </div>

            @if ($request->rejection_reason)
                <div class="rounded-2xl border border-red-200 bg-red-50 p-5">
                    <h3 class="text-lg font-semibold text-red-900 mb-3">Rejection Reason</h3>
                    <p class="text-sm text-red-800">{{ $request->rejection_reason }}</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Actions -->
    @if ($request->status === 'pending' || $request->status === 'payment_received')
        <div class="mt-8 bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold mb-4">Admin Actions</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Approve Form -->
                <form method="POST" action="{{ route('system-admin.phs.requests.approve', ['id' => $request->id]) }}">
                    @csrf
                    <div class="border border-green-300 rounded-lg p-4 bg-green-50">
                        <h3 class="font-semibold text-green-900 mb-2">Approve Request</h3>
                        <p class="text-sm text-green-800 mb-4">Approving will send an activation email to the organization with a registration link.</p>
                        <button type="submit" class="w-full px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 font-medium">
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
                        <button type="submit" class="w-full px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 font-medium">
                            Reject Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection

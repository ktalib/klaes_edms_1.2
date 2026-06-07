@extends('layouts.app')

@section('page-title', $PageTitle)

@section('content')
<div class="flex-1 overflow-auto bg-slate-50 flex flex-col min-h-full">
    @include('admin.header', ['PageTitle' => $PageTitle, 'PageDescription' => 'Review and approve PHS onboarding requests submitted by institutions.'])
    <div class="flex-1 p-6">
        <div class="mb-8">
            <h1 class="text-3xl font-bold mb-4">{{ $PageTitle }}</h1>

            <!-- Status Filter Tabs -->
        <div class="flex space-x-4 border-b mb-6">
            <a href="{{ route('system-admin.phs.requests.index') }}"
                class="px-4 py-2 border-b-2 font-medium {{ !$statusFilter ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-600 hover:text-gray-800' }}">
                All ({{ array_sum($statsByStatus) }})
            </a>
            @foreach(['pending', 'payment_received', 'approved', 'activated', 'rejected'] as $status)
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
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-100 border-b">
                    <tr>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Organization</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Contact</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Email</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Type</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Status</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Submitted</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($requests as $req)
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm">
                                <strong>{{ $req->organization_name }}</strong>
                            </td>
                            <td class="px-6 py-4 text-sm">{{ $req->contact_name }}</td>
                            <td class="px-6 py-4 text-sm">{{ $req->contact_email }}</td>
                            <td class="px-6 py-4 text-sm capitalize">{{ str_replace('_', ' ', $req->organization_type) }}</td>
                            <td class="px-6 py-4 text-sm">
                                @php
                                    $statusColors = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'payment_received' => 'bg-blue-100 text-blue-800',
                                        'approved' => 'bg-purple-100 text-purple-800',
                                        'activated' => 'bg-green-100 text-green-800',
                                        'rejected' => 'bg-red-100 text-red-800',
                                    ];
                                @endphp
                                <span class="px-3 py-1 rounded-full text-xs font-medium {{ $statusColors[$req->status] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ ucwords(str_replace('_', ' ', $req->status)) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm">{{ $req->created_at->format('M j, Y') }}</td>
                            <td class="px-6 py-4 text-sm">
                                <a href="{{ route('system-admin.phs.requests.show', ['id' => $req->id]) }}"
                                    class="text-blue-600 hover:text-blue-800 font-medium">
                                    View
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection

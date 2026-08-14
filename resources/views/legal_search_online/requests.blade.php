@extends('layouts.app')
@section('page-title', $PageTitle)

@section('content')
<div class="flex-1 overflow-auto bg-slate-50 flex flex-col min-h-full">
    @include('admin.header', ['PageTitle' => $PageTitle, 'PageDescription' => 'Approve or decline Legal Search requests submitted from the Online portal. Approved reports are emailed to the requester as a PDF.'])

    <div class="flex-1 p-6 space-y-6">

        @if (session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">
                {{ session('error') }}
            </div>
        @endif

        @unless($canApprove)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                You can view this queue, but only a <strong>Director</strong> or <strong>Deputy Director</strong> may approve or decline requests.
            </div>
        @endunless

        {{-- Status tabs --}}
        @php
            $tabs = [
                'pending'  => ['Awaiting Approval', $counts['pending'],  'bg-amber-50 text-amber-700 ring-amber-200'],
                'approved' => ['Approved',          $counts['approved'], 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
                'rejected' => ['Declined',          $counts['rejected'], 'bg-red-50 text-red-700 ring-red-200'],
                'all'      => ['All Requests',      array_sum($counts),  'bg-slate-100 text-slate-700 ring-slate-200'],
            ];
        @endphp
        <div class="flex flex-wrap items-center gap-3">
            @foreach($tabs as $key => [$label, $count, $tone])
                <a href="{{ route('legal-search-online.admin.requests', ['status' => $key, 'q' => $q]) }}"
                   class="rounded-lg px-4 py-2.5 text-sm font-bold ring-1 transition {{ $status === $key ? 'bg-slate-900 text-white ring-slate-900' : $tone }}">
                    {{ $label }} <span class="ml-1 opacity-70">({{ number_format($count) }})</span>
                </a>
            @endforeach

            <form method="GET" action="{{ route('legal-search-online.admin.requests') }}" class="ml-auto flex items-center gap-2">
                <input type="hidden" name="status" value="{{ $status }}">
                <input type="text" name="q" value="{{ $q }}" placeholder="Request no, file no, or email"
                       class="w-72 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                <button class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">Search</button>
            </form>
        </div>

        {{-- Requests table --}}
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr class="text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-3">Request</th>
                            <th class="px-4 py-3">File Number</th>
                            <th class="px-4 py-3">Requester</th>
                            <th class="px-4 py-3">Payment Ref</th>
                            <th class="px-4 py-3">Submitted</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($requests as $req)
                            <tr id="req-{{ $req->id }}" class="{{ $highlight === $req->id ? 'bg-amber-50' : '' }}">
                                <td class="px-4 py-3 font-bold text-slate-900">{{ $req->request_no }}</td>
                                <td class="px-4 py-3 font-semibold text-slate-700">{{ $req->file_number ?: '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $req->requester_email }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $req->tracking_id ?: $req->reference }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ optional($req->submitted_at)->format('d M Y, g:i A') ?: '—' }}</td>
                                <td class="px-4 py-3">
                                    @if($req->isPending())
                                        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800">Awaiting Approval</span>
                                    @elseif($req->isApproved())
                                        <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800">Approved</span>
                                        <div class="mt-1 text-xs text-slate-500">
                                            by {{ $req->reviewer_name ?: '—' }}
                                            @if($req->emailed_at)
                                                · emailed {{ $req->emailed_at->format('d M, g:i A') }}
                                            @elseif($req->email_error)
                                                · <span class="font-semibold text-red-600">email failed</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-bold text-red-800">Declined</span>
                                        <div class="mt-1 max-w-xs truncate text-xs text-slate-500" title="{{ $req->rejection_reason }}">{{ $req->rejection_reason }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if($canApprove)
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('legal-search-online.admin.requests.preview', $req->id) }}" target="_blank"
                                               class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Preview report</a>

                                            @if($req->isPending())
                                                <button type="button"
                                                        onclick="olsApprove({{ $req->id }}, '{{ $req->request_no }}', @js($req->requester_email))"
                                                        class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-700">Approve &amp; Email</button>
                                                <button type="button"
                                                        onclick="olsReject({{ $req->id }}, '{{ $req->request_no }}')"
                                                        class="rounded-md bg-red-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-red-700">Decline</button>
                                            @elseif($req->isApproved())
                                                <form method="POST" action="{{ route('legal-search-online.admin.requests.resend', $req->id) }}">
                                                    @csrf
                                                    <button class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Resend report</button>
                                                </form>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-400">View only</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">No requests in this view.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3">
                {{ $requests->links() }}
            </div>
        </div>
    </div>
</div>

@if($canApprove)
{{-- Approve modal --}}
<div id="olsApproveModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <form method="POST" id="olsApproveForm" class="w-full max-w-md rounded-xl bg-white shadow-xl">
        @csrf
        <div class="border-b border-slate-200 px-5 py-4">
            <h3 class="text-lg font-bold text-slate-900">Approve request <span id="olsApproveNo"></span></h3>
            <p class="mt-1 text-sm text-slate-500">The Legal Search report will be generated and emailed as a PDF to <strong id="olsApproveEmail"></strong>.</p>
        </div>
        <div class="px-5 py-4">
            <label class="mb-1 block text-sm font-semibold text-slate-700">Internal note (optional)</label>
            <textarea name="review_note" rows="3" maxlength="1000"
                      class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
                      placeholder="Recorded against the request; not shown to the requester."></textarea>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4">
            <button type="button" onclick="olsCloseModals()" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
            <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-700">Approve &amp; Email Report</button>
        </div>
    </form>
</div>

{{-- Decline modal --}}
<div id="olsRejectModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <form method="POST" id="olsRejectForm" class="w-full max-w-md rounded-xl bg-white shadow-xl">
        @csrf
        <div class="border-b border-slate-200 px-5 py-4">
            <h3 class="text-lg font-bold text-slate-900">Decline request <span id="olsRejectNo"></span></h3>
            <p class="mt-1 text-sm text-slate-500">The reason below is emailed to the requester.</p>
        </div>
        <div class="px-5 py-4">
            <label class="mb-1 block text-sm font-semibold text-slate-700">Reason <span class="text-red-600">*</span></label>
            <textarea name="rejection_reason" rows="3" required maxlength="1000"
                      class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
                      placeholder="e.g. The file number supplied does not match any record."></textarea>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4">
            <button type="button" onclick="olsCloseModals()" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
            <button class="rounded-lg bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700">Decline Request</button>
        </div>
    </form>
</div>

<script>
    const OLS_APPROVE_URL = @json(route('legal-search-online.admin.requests.approve', ['id' => '__ID__']));
    const OLS_REJECT_URL  = @json(route('legal-search-online.admin.requests.reject',  ['id' => '__ID__']));

    function olsOpen(el) { el.classList.remove('hidden'); el.classList.add('flex'); }
    function olsCloseModals() {
        ['olsApproveModal', 'olsRejectModal'].forEach(function (id) {
            const el = document.getElementById(id);
            el.classList.add('hidden');
            el.classList.remove('flex');
        });
    }

    function olsApprove(id, requestNo, email) {
        document.getElementById('olsApproveForm').action = OLS_APPROVE_URL.replace('__ID__', id);
        document.getElementById('olsApproveNo').textContent = requestNo;
        document.getElementById('olsApproveEmail').textContent = email;
        olsOpen(document.getElementById('olsApproveModal'));
    }

    function olsReject(id, requestNo) {
        document.getElementById('olsRejectForm').action = OLS_REJECT_URL.replace('__ID__', id);
        document.getElementById('olsRejectNo').textContent = requestNo;
        olsOpen(document.getElementById('olsRejectModal'));
    }

    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') olsCloseModals(); });
</script>
@endif
@endsection

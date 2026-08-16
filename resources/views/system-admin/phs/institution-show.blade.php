@extends('layouts.app')

@section('page-title', $PageTitle)

@section('content')
@php
    $transactionRows = collect($transactions);
    $isActive = $institution->status === 'active';
    $pendingInvoices = $transactionRows->where('status', 'pending')->count();
    $completedPurchases = $transactionRows->where('type', 'purchase')->where('status', 'completed')->count();
    $emailCount = $emailHistory->count();
@endphp

<div class="flex-1 overflow-auto bg-slate-50 flex flex-col min-h-full">
    @include('admin.header', ['PageTitle' => $PageTitle, 'PageDescription' => 'Wallet, members, invoices, and recent PHS activity.'])

    <div class="flex-1 p-6">
        <div class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-start gap-4">
                    <div class="grid h-14 w-14 place-items-center rounded-lg bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100">
                        <i data-lucide="landmark" class="h-7 w-7"></i>
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-xl font-extrabold text-slate-900">{{ \Illuminate\Support\Str::title($institution->name) }}</h2>
                            @if($isActive)
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
                        </div>
                        <div class="mt-2 flex flex-wrap gap-4 text-sm text-slate-500">
                            <span class="inline-flex items-center gap-1.5"><i data-lucide="mail" class="h-4 w-4"></i>{{ $institution->email }}</span>
                            <span class="inline-flex items-center gap-1.5 capitalize"><i data-lucide="briefcase-business" class="h-4 w-4"></i>{{ str_replace('_', ' ', $institution->type) }}</span>
                            @if($institution->phone)
                                <span class="inline-flex items-center gap-1.5"><i data-lucide="phone" class="h-4 w-4"></i>{{ $institution->phone }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                <form method="POST" action="{{ $isActive ? route('system-admin.phs.institutions.suspend', $institution->id) : route('system-admin.phs.institutions.activate', $institution->id) }}">
                    @csrf
                    <button class="inline-flex items-center gap-2 rounded-md border px-4 py-2 text-sm font-bold {{ $isActive ? 'border-red-200 bg-red-50 text-red-700 hover:bg-red-100' : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                        <i data-lucide="{{ $isActive ? 'pause-circle' : 'play-circle' }}" class="h-4 w-4"></i>
                        {{ $isActive ? ' Suspend Organization' : 'Activate Organization' }}
                    </button>
                </form>
            </div>
        </div>

        <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Token balance</p><p class="mt-2 text-3xl font-extrabold text-slate-900">{{ number_format((int) $institution->token_balance) }}</p></div>
                    <div class="grid h-11 w-11 place-items-center rounded-lg bg-sky-50 text-sky-700 ring-1 ring-sky-100"><i data-lucide="coins" class="h-5 w-5"></i></div>
                </div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Members</p><p class="mt-2 text-3xl font-extrabold text-slate-900">{{ number_format($institution->members->count()) }}</p></div>
                    <div class="grid h-11 w-11 place-items-center rounded-lg bg-indigo-50 text-indigo-700 ring-1 ring-indigo-100"><i data-lucide="users" class="h-5 w-5"></i></div>
                </div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Pending invoices</p><p class="mt-2 text-3xl font-extrabold text-slate-900">{{ number_format($pendingInvoices) }}</p></div>
                    <div class="grid h-11 w-11 place-items-center rounded-lg bg-amber-50 text-amber-700 ring-1 ring-amber-100"><i data-lucide="file-clock" class="h-5 w-5"></i></div>
                </div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Searches</p><p class="mt-2 text-3xl font-extrabold text-slate-900">{{ number_format($searchLogs->count()) }}</p></div>
                    <div class="grid h-11 w-11 place-items-center rounded-lg bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100"><i data-lucide="search-check" class="h-5 w-5"></i></div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[360px_1fr]">
            <aside class="space-y-6">
                <form method="POST" action="{{ route('system-admin.phs.institutions.tokens', $institution->id) }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    @csrf
                    <h3 class="flex items-center gap-2 font-extrabold text-slate-900">
                        <i data-lucide="wallet-cards" class="h-5 w-5 text-sky-600"></i>
                        Adjust tokens
                    </h3>
                    <input name="tokens" type="number" required placeholder="Positive or negative amount" class="mt-4 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                    <textarea name="notes" placeholder="Notes" class="mt-3 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none"></textarea>
                    <button class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-md bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800">
                        <i data-lucide="plus-circle" class="h-4 w-4"></i>
                        Apply adjustment
                    </button>
                </form>

                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="flex items-center gap-2 font-extrabold text-slate-900">
                        <i data-lucide="users" class="h-5 w-5 text-indigo-600"></i>
                        Members
                    </h3>
                    <div class="mt-4 space-y-3">
                        @foreach($institution->members as $member)
                            <div class="rounded-md border border-slate-200 p-4">
                                <div class="flex items-start gap-3">
                                    <div class="grid h-9 w-9 place-items-center rounded-md bg-slate-100 text-slate-600">
                                        <i data-lucide="{{ $member->isSuperAdmin() ? 'crown' : 'user' }}" class="h-4 w-4"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-bold text-slate-900">{{ \Illuminate\Support\Str::title($member->name) }}</p>
                                        <p class="truncate text-xs text-slate-500">{{ $member->email }}</p>
                                        @if($member->phone)
                                            <p class="mt-0.5 flex items-center gap-1 text-xs text-slate-500"><i data-lucide="phone" class="h-3 w-3"></i>{{ $member->phone }}</p>
                                        @endif
                                        <p class="mt-2 text-[11px] font-bold uppercase text-slate-500">{{ str_replace('_', ' ', $member->user_type) }} / {{ $member->status }}</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </aside>

            <section class="space-y-6">
                <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center gap-3 border-b border-slate-200 px-5 py-4">
                        <div class="grid h-9 w-9 place-items-center rounded-md bg-emerald-50 text-emerald-700"><i data-lucide="history" class="h-4 w-4"></i></div>
                        <div><h3 class="font-extrabold text-slate-900">Recent searches</h3><p class="text-xs text-slate-500">Latest PHS requests from this organization</p></div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500">
                                <tr><th class="px-5 py-3">Query</th><th class="px-5 py-3">Reference</th><th class="px-5 py-3">Results</th><th class="px-5 py-3">Date</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($searchLogs as $log)
                                    <tr class="hover:bg-slate-50/80">
                                        <td class="px-5 py-3 font-bold text-slate-900">{{ $log->query }}</td>
                                        <td class="px-5 py-3 text-slate-600">{{ $log->reference_no }}</td>
                                        <td class="px-5 py-3">{{ $log->result_count }} results</td>
                                        <td class="px-5 py-3 text-slate-500">{{ optional($log->created_at)->format('d M Y H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-5 py-10 text-center text-slate-400"><i data-lucide="search" class="mx-auto mb-2 h-7 w-7 opacity-40"></i>No searches yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center gap-3 border-b border-slate-200 px-5 py-4">
                        <div class="grid h-9 w-9 place-items-center rounded-md bg-sky-50 text-sky-700"><i data-lucide="wallet" class="h-4 w-4"></i></div>
                        <div><h3 class="font-extrabold text-slate-900">Wallet ledger</h3><p class="text-xs text-slate-500">{{ $completedPurchases }} completed purchase(s), {{ $pendingInvoices }} pending invoice(s)</p></div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500">
                                <tr><th class="px-5 py-3">Type</th><th class="px-5 py-3">Tokens</th><th class="px-5 py-3">Balance</th><th class="px-5 py-3">Reference</th><th class="px-5 py-3">Status</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($transactions as $txn)
                                    <tr class="hover:bg-slate-50/80">
                                        <td class="px-5 py-3 font-bold capitalize text-slate-900">{{ str_replace('_', ' ', $txn->type) }}</td>
                                        <td class="px-5 py-3 font-bold {{ $txn->tokens < 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ number_format($txn->tokens) }}</td>
                                        <td class="px-5 py-3">{{ number_format($txn->balance_after) }}</td>
                                        <td class="px-5 py-3 text-slate-600">{{ $txn->reference_no }}</td>
                                        <td class="px-5 py-3">
                                            <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $txn->status === 'completed' ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100' : 'bg-amber-50 text-amber-700 ring-1 ring-amber-100' }}">{{ ucfirst($txn->status) }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">No transactions yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center gap-3 border-b border-slate-200 px-5 py-4">
                        <div class="grid h-9 w-9 place-items-center rounded-md bg-indigo-50 text-indigo-700"><i data-lucide="mail" class="h-4 w-4"></i></div>
                        <div><h3 class="font-extrabold text-slate-900">Email history</h3><p class="text-xs text-slate-500">{{ number_format($emailCount) }} email copy(ies) recorded for this organization</p></div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500">
                                <tr><th class="px-5 py-3">Date</th><th class="px-5 py-3">Recipient</th><th class="px-5 py-3">Subject</th><th class="px-5 py-3">Template</th><th class="px-5 py-3 text-right">Action</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($emailHistory as $mail)
                                    <tr class="hover:bg-slate-50/80">
                                        <td class="px-5 py-3 text-slate-500">{{ optional($mail->sent_at ?? $mail->created_at)->format('d M Y H:i') }}</td>
                                        <td class="px-5 py-3 text-slate-700">{{ $mail->recipient_email }}</td>
                                        <td class="px-5 py-3 font-bold text-slate-900">{{ $mail->subject ?: 'No subject' }}</td>
                                        <td class="px-5 py-3 text-xs text-slate-500">{{ \Illuminate\Support\Str::afterLast((string) $mail->mailable, '\\') ?: '—' }}</td>
                                        <td class="px-5 py-3 text-right">
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50"
                                                data-email-view
                                                data-email-subject="{{ e($mail->subject ?: 'Email copy') }}"
                                                data-email-recipient="{{ e($mail->recipient_email) }}"
                                                data-email-sent="{{ e(optional($mail->sent_at ?? $mail->created_at)->format('d M Y H:i')) }}"
                                                data-email-html='@json($mail->body_html)'
                                                data-email-text='@json($mail->body_text)'
                                            >
                                                <i data-lucide="file-text" class="h-3.5 w-3.5"></i>
                                                View copy
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-5 py-10 text-center text-slate-400"><i data-lucide="mail" class="mx-auto mb-2 h-7 w-7 opacity-40"></i>No email history yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <div id="email-copy-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-4xl rounded-lg bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <h4 id="email-copy-title" class="font-extrabold text-slate-900">Email copy</h4>
                <button type="button" id="email-copy-close" class="rounded-md border border-slate-200 px-2 py-1 text-xs font-bold text-slate-600 hover:bg-slate-50">Close</button>
            </div>
            <div class="px-5 py-3 text-xs text-slate-500">
                <div><span class="font-bold text-slate-700">To:</span> <span id="email-copy-recipient">—</span></div>
                <div class="mt-1"><span class="font-bold text-slate-700">Sent:</span> <span id="email-copy-sent">—</span></div>
            </div>
            <div id="email-copy-body" class="max-h-[65vh] overflow-auto border-t border-slate-200 px-5 py-4 text-sm text-slate-700"></div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('email-copy-modal');
            const closeBtn = document.getElementById('email-copy-close');
            const titleEl = document.getElementById('email-copy-title');
            const recipientEl = document.getElementById('email-copy-recipient');
            const sentEl = document.getElementById('email-copy-sent');
            const bodyEl = document.getElementById('email-copy-body');

            function closeModal() {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                bodyEl.innerHTML = '';
            }

            closeBtn.addEventListener('click', closeModal);
            modal.addEventListener('click', function (event) {
                if (event.target === modal) closeModal();
            });

            document.querySelectorAll('[data-email-view]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const subject = button.getAttribute('data-email-subject') || 'Email copy';
                    const recipient = button.getAttribute('data-email-recipient') || '—';
                    const sent = button.getAttribute('data-email-sent') || '—';
                    let htmlBody = '';
                    let textBody = '';

                    try {
                        htmlBody = JSON.parse(button.getAttribute('data-email-html') || '""') || '';
                    } catch (e) {
                        htmlBody = '';
                    }
                    try {
                        textBody = JSON.parse(button.getAttribute('data-email-text') || '""') || '';
                    } catch (e) {
                        textBody = '';
                    }

                    titleEl.textContent = subject;
                    recipientEl.textContent = recipient;
                    sentEl.textContent = sent;

                    if (htmlBody) {
                        bodyEl.innerHTML = '<div class="rounded-md border border-slate-200 bg-white p-3">' + htmlBody + '</div>';
                    } else if (textBody) {
                        bodyEl.innerHTML = '<pre class="whitespace-pre-wrap rounded-md border border-slate-200 bg-slate-50 p-3 text-xs text-slate-700"></pre>';
                        bodyEl.querySelector('pre').textContent = textBody;
                    } else {
                        bodyEl.innerHTML = '<p class="text-slate-500">No message body available.</p>';
                    }

                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                });
            });
        });
    </script>

    @include('admin.footer')
</div>
@endsection

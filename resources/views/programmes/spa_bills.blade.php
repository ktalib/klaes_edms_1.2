@extends('layouts.app')
@section('page-title'){{ $PageTitle ?? 'Special Assignment – Bills' }}@endsection

@section('content')
<div class="flex-1 overflow-auto">
    @include('admin.header')
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">{{ $PageTitle }}</h1>
                <p class="text-sm text-gray-500 mt-1">{{ $PageDescription }}</p>
            </div>
            <button id="btn-add-bill"
                class="inline-flex items-center gap-2 px-4 py-2 bg-[rgb(186,191,12)] hover:opacity-90 text-white text-sm font-medium rounded-lg">
                <i data-lucide="plus" class="h-4 w-4"></i> Add Bill
            </button>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-3">
                <div class="p-2 rounded-full" style="background:rgba(186,191,12,.12)"><i data-lucide="receipt" class="h-5 w-5 text-[rgb(186,191,12)]"></i></div>
                <div><p class="text-xs text-gray-500">Total Bills</p><p class="text-xl font-bold text-gray-800">{{ $stats['total'] }}</p></div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-3">
                <div class="p-2 rounded-full bg-red-50"><i data-lucide="alert-circle" class="h-5 w-5 text-red-500"></i></div>
                <div><p class="text-xs text-gray-500">Unpaid</p><p class="text-xl font-bold text-gray-800">{{ $stats['unpaid'] }}</p></div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-3">
                <div class="p-2 rounded-full bg-amber-50"><i data-lucide="clock" class="h-5 w-5 text-amber-500"></i></div>
                <div><p class="text-xs text-gray-500">Partial</p><p class="text-xl font-bold text-gray-800">{{ $stats['partial'] }}</p></div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-3">
                <div class="p-2 rounded-full bg-green-50"><i data-lucide="check-circle" class="h-5 w-5 text-green-600"></i></div>
                <div><p class="text-xs text-gray-500">Paid</p><p class="text-xl font-bold text-gray-800">{{ $stats['paid'] }}</p></div>
            </div>
        </div>

        {{-- DataTable --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100"><h2 class="text-sm font-semibold text-gray-700">All SPAS Bills</h2></div>
            <div class="p-4">
                <table id="spa-bills-table" class="w-full text-sm" style="width:100%">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 uppercase">
                            <th class="px-3 py-2">#</th>
                            <th class="px-3 py-2">Ref ID</th>
                            <th class="px-3 py-2">File No</th>
                            <th class="px-3 py-2">Owner</th>
                            <th class="px-3 py-2">Bill Type</th>
                            <th class="px-3 py-2">Description</th>
                            <th class="px-3 py-2">Amount (₦)</th>
                            <th class="px-3 py-2">Due Date</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Add Bill Modal --}}
<div id="modal-add-bill" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-800">Add Bill</h3>
            <button class="modal-close text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>
        <form id="form-add-bill" class="p-6 space-y-4">
            @csrf
            {{-- Auto-generated reference badge --}}
            <div class="flex items-center gap-2 px-3 py-2 bg-red-50 border border-red-200 rounded-lg">
                <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                <div>
                    <p class="text-xs font-bold text-red-600">Bill Reference ID</p>
                    <p class="text-xs text-red-500">Auto-generated on save &mdash; e.g. <span class="font-semibold">SPA-BILL-{{ now()->year }}-001</span></p>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Application <span class="text-red-500">*</span></label>
                    <select name="spa_application_id" required
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                        <option value="">Select application…</option>
                        @foreach($applications as $app)
                            <option value="{{ $app->id }}">{{ $app->file_number }} – {{ $app->owner_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Bill Type <span class="text-red-500">*</span></label>
                    <select name="bill_type" required
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                        <option value="">Select…</option>
                        <option value="Application Fee">Application Fee</option>
                        <option value="Processing Fee">Processing Fee</option>
                        <option value="Change of Use Fee">Change of Use Fee</option>
                        <option value="Penalty Fee">Penalty Fee</option>
                        <option value="Survey Fee">Survey Fee</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Amount (₦) <span class="text-red-500">*</span></label>
                    <input type="number" name="amount" required min="0" step="0.01"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Due Date</label>
                    <input type="date" name="due_date"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Description</label>
                    <input type="text" name="description"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]"
                        placeholder="Optional description…">
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" class="modal-close px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-5 py-2 text-sm text-white bg-[rgb(186,191,12)] rounded-lg hover:opacity-90">Save Bill</button>
            </div>
        </form>
    </div>
</div>

{{-- Quick Pay Modal --}}
<div id="modal-pay" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-800">Record Payment</h3>
            <button class="pay-close text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>
        <form id="form-pay" class="p-6 space-y-4">
            @csrf
            <input type="hidden" name="spa_bill_id" id="pay-bill-id">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Amount Paid (₦) <span class="text-red-500">*</span></label>
                    <input type="number" name="amount_paid" required min="0.01" step="0.01"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Payment Date <span class="text-red-500">*</span></label>
                    <input type="date" name="payment_date" required
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Receipt Number</label>
                    <input type="text" name="receipt_number"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Payment Method</label>
                    <select name="payment_method"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                        <option value="cash">Cash</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="pos">POS</option>
                        <option value="cheque">Cheque</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" class="pay-close px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-5 py-2 text-sm text-white bg-[rgb(186,191,12)] rounded-lg hover:opacity-90">Record Payment</button>
            </div>
        </form>
    </div>
</div>

<style>
    table.dataTable thead th { background:#f9fafb; font-weight:600; }
    .dataTables_wrapper .dataTables_filter input { border:1px solid #e5e7eb; border-radius:.5rem; padding:.35rem .75rem; font-size:.85rem; }
    .dataTables_wrapper .dataTables_length select { border:1px solid #e5e7eb; border-radius:.5rem; padding:.25rem .5rem; }
</style>

<script>
const CSRF      = '{{ csrf_token() }}';
const BILLS_URL = window.location.href;
const PAY_STORE = '{{ route("special-assignment.bills.pay") }}';

const STATUS_COLORS = { unpaid:'bg-red-100 text-red-700', partial:'bg-amber-100 text-amber-700', paid:'bg-green-100 text-green-700' };

$(document).ready(function () {
    const table = $('#spa-bills-table').DataTable({
        processing: true, serverSide: true,
        ajax: { url: BILLS_URL, data: d => ({ ...d, ajax:1 }) },
        columns: [
            { data:'DT_RowIndex', orderable:false, searchable:false },
            { data:'reference_id', render: d => d && d!=='—' ? `<span class="font-semibold text-red-600">${d}</span>` : '<span class="text-gray-400">—</span>' },
            { data:'file_number' }, { data:'owner_name' }, { data:'bill_type' },
            { data:'description', render: d => d||'—' },
            { data:'amount' }, { data:'due_date' },
            { data:'status', render: d => `<span class="px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_COLORS[d]||'bg-gray-100 text-gray-600'}">${d||'—'}</span>` },
            { data:'action', orderable:false, searchable:false },
        ],
    });

    // Pay button
    $('#spa-bills-table').on('click', '.btn-pay', function () {
        document.getElementById('pay-bill-id').value = $(this).data('id');
        const m = document.getElementById('modal-pay');
        m.classList.remove('hidden'); m.classList.add('flex');
    });

    // Modals
    const modalBill = document.getElementById('modal-add-bill');
    document.getElementById('btn-add-bill').addEventListener('click', () => { modalBill.classList.remove('hidden'); modalBill.classList.add('flex'); });
    document.querySelectorAll('.modal-close').forEach(b => b.addEventListener('click', () => { modalBill.classList.add('hidden'); modalBill.classList.remove('flex'); }));
    const modalPay = document.getElementById('modal-pay');
    document.querySelectorAll('.pay-close').forEach(b => b.addEventListener('click', () => { modalPay.classList.add('hidden'); modalPay.classList.remove('flex'); }));

    // Add bill
    document.getElementById('form-add-bill').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = this.querySelector('[type=submit]');
        btn.disabled = true; btn.textContent = 'Saving…';
        const res  = await fetch('{{ route("special-assignment.bills.store") }}', { method:'POST', headers:{'X-CSRF-TOKEN':CSRF,'Content-Type':'application/json'}, body: JSON.stringify(Object.fromEntries(new FormData(this))) });
        const data = await res.json();
        if (data.success) {
            modalBill.classList.add('hidden'); modalBill.classList.remove('flex');
            this.reset(); table.ajax.reload();
            Swal.fire({ icon:'success', title:'Saved', text:data.message, timer:2000, showConfirmButton:false });
        } else {
            Swal.fire({ icon:'error', title:'Error', text:data.message||'Save failed.' });
        }
        btn.disabled=false; btn.textContent='Save Bill';
    });

    // Record payment
    document.getElementById('form-pay').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = this.querySelector('[type=submit]');
        btn.disabled = true; btn.textContent = 'Saving…';
        const res  = await fetch('{{ url("spa/bills/pay") }}', { method:'POST', headers:{'X-CSRF-TOKEN':CSRF,'Content-Type':'application/json'}, body: JSON.stringify(Object.fromEntries(new FormData(this))) });
        const data = await res.json();
        if (data.success) {
            modalPay.classList.add('hidden'); modalPay.classList.remove('flex');
            this.reset(); table.ajax.reload();
            Swal.fire({ icon:'success', title:'Recorded', text:data.message, timer:2000, showConfirmButton:false });
        } else {
            Swal.fire({ icon:'error', title:'Error', text:data.message||'Save failed.' });
        }
        btn.disabled=false; btn.textContent='Record Payment';
    });
});
</script>
@endsection

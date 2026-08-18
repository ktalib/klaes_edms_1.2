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
            {{-- Bills are no longer typed by hand. A contravention raises its
                 own bill from the tariff below, so the only thing left to
                 manage is what a contravention costs. --}}
            <button id="btn-bill-items"
                class="inline-flex items-center gap-2 px-4 py-2 bg-[rgb(186,191,12)] hover:opacity-90 text-white text-sm font-medium rounded-lg">
                <i data-lucide="settings" class="h-4 w-4"></i> Bill Items Setting
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

{{-- Bill Items Setting --}}
{{--
    The contravention tariff. Editing an amount changes what FUTURE bills cost;
    bills already raised keep their own copy of each line, so an old bill still
    adds up after a change here.
--}}
<div id="modal-bill-items" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl mx-4">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h3 class="text-base font-semibold text-gray-800">Bill Items Setting</h3>
                <p class="text-xs text-gray-500 mt-0.5">Charged automatically when a property is in contravention.</p>
            </div>
            <button class="items-close text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>

        <div class="px-6 py-4">
            <div class="flex items-start gap-2 px-3 py-2 mb-4 bg-amber-50 border border-amber-200 rounded-lg">
                <i data-lucide="info" class="h-4 w-4 text-amber-500 shrink-0 mt-0.5"></i>
                <p class="text-xs text-amber-700">
                    A bill is raised once per application, the moment its approved land use
                    differs from the use prevailing on the ground. Items set to
                    <strong>0</strong> or switched off are not charged.
                </p>
            </div>

            <div class="max-h-80 overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-gray-500 border-b border-gray-100">
                        <tr>
                            <th class="text-left py-2 font-medium">Item</th>
                            <th class="text-right py-2 font-medium w-40">Amount (&#8358;)</th>
                            <th class="text-center py-2 font-medium w-20">Active</th>
                            <th class="w-10"></th>
                        </tr>
                    </thead>
                    <tbody id="bill-items-body"></tbody>
                </table>
            </div>

            <div class="flex items-center gap-2 mt-4 pt-4 border-t border-gray-100">
                <input id="new-item-name" type="text" placeholder="New item name"
                    class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                <input id="new-item-amount" type="number" min="0" step="0.01" placeholder="0.00"
                    class="w-32 border border-gray-200 rounded-lg px-3 py-2 text-sm text-right outline-none focus:border-[rgb(186,191,12)]">
                <button id="btn-add-item" type="button"
                    class="px-4 py-2 text-sm text-white bg-gray-800 rounded-lg hover:opacity-90">Add</button>
            </div>

            <p id="bill-items-total" class="text-right text-sm text-gray-600 mt-3"></p>
            <p id="bill-items-msg" class="text-xs mt-2"></p>
        </div>

        <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-100">
            <button type="button" class="items-close px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Close</button>
            <button id="btn-save-items" type="button"
                class="px-5 py-2 text-sm text-white bg-[rgb(186,191,12)] rounded-lg hover:opacity-90">Save Tariff</button>
        </div>
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
    const modalItems = document.getElementById('modal-bill-items');
    const openItems  = () => { modalItems.classList.remove('hidden'); modalItems.classList.add('flex'); loadItems(); };
    const closeItems = () => { modalItems.classList.add('hidden'); modalItems.classList.remove('flex'); };
    document.getElementById('btn-bill-items').addEventListener('click', openItems);
    document.querySelectorAll('.items-close').forEach(b => b.addEventListener('click', closeItems));

    const modalPay = document.getElementById('modal-pay');
    document.querySelectorAll('.pay-close').forEach(b => b.addEventListener('click', () => { modalPay.classList.add('hidden'); modalPay.classList.remove('flex'); }));

    // ---- Bill items (the contravention tariff) --------------------------
    const itemsBody = document.getElementById('bill-items-body');
    const itemsMsg  = document.getElementById('bill-items-msg');

    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    function itemRow(it) {
        return `<tr class="border-b border-gray-50" data-id="${it.id ?? ''}">
            <td class="py-2 pr-2"><input class="it-name w-full border border-transparent hover:border-gray-200 rounded px-2 py-1 text-sm" value="${esc(it.name)}"></td>
            <td class="py-2"><input class="it-amount w-full border border-gray-200 rounded px-2 py-1 text-sm text-right" type="number" min="0" step="0.01" value="${Number(it.amount || 0).toFixed(2)}"></td>
            <td class="py-2 text-center"><input class="it-active" type="checkbox" ${it.is_active ? 'checked' : ''}></td>
            <td class="py-2 text-center"><button type="button" class="it-remove text-gray-300 hover:text-red-500" title="Remove">&times;</button></td>
        </tr>`;
    }

    function recalcTotal() {
        let total = 0;
        itemsBody.querySelectorAll('tr').forEach(tr => {
            if (tr.querySelector('.it-active').checked) total += parseFloat(tr.querySelector('.it-amount').value || 0);
        });
        document.getElementById('bill-items-total').innerHTML =
            'Every contravention will be billed <strong>&#8358;' + total.toLocaleString(undefined,{minimumFractionDigits:2}) + '</strong>';
    }

    async function loadItems() {
        itemsBody.innerHTML = '<tr><td colspan="4" class="py-4 text-center text-gray-400 text-sm">Loading…</td></tr>';
        itemsMsg.textContent = '';
        const res = await fetch('{{ route("special-assignment.bill-items.index") }}');
        const data = await res.json();
        itemsBody.innerHTML = (data.data || []).map(itemRow).join('')
            || '<tr><td colspan="4" class="py-4 text-center text-gray-400 text-sm">No items yet — add one below.</td></tr>';
        recalcTotal();
        if (window.lucide) lucide.createIcons();
    }

    itemsBody.addEventListener('input', recalcTotal);
    itemsBody.addEventListener('change', recalcTotal);
    itemsBody.addEventListener('click', e => {
        if (e.target.closest('.it-remove')) { e.target.closest('tr').remove(); recalcTotal(); }
    });

    document.getElementById('btn-add-item').addEventListener('click', () => {
        const name = document.getElementById('new-item-name').value.trim();
        if (!name) return;
        const amount = document.getElementById('new-item-amount').value || 0;
        if (itemsBody.querySelector('td[colspan]')) itemsBody.innerHTML = '';
        itemsBody.insertAdjacentHTML('beforeend', itemRow({ name, amount, is_active: true }));
        document.getElementById('new-item-name').value = '';
        document.getElementById('new-item-amount').value = '';
        recalcTotal();
    });

    document.getElementById('btn-save-items').addEventListener('click', async function () {
        const items = [...itemsBody.querySelectorAll('tr[data-id], tr:not([data-id])')]
            .filter(tr => tr.querySelector('.it-name'))
            .map((tr, i) => ({
                id: tr.dataset.id || null,
                name: tr.querySelector('.it-name').value.trim(),
                amount: parseFloat(tr.querySelector('.it-amount').value || 0),
                is_active: tr.querySelector('.it-active').checked ? 1 : 0,
                sort_order: i + 1
            }))
            .filter(it => it.name !== '');

        this.disabled = true; this.textContent = 'Saving…';
        const res = await fetch('{{ route("special-assignment.bill-items.save") }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json' },
            body: JSON.stringify({ items })
        });
        const data = await res.json();
        this.disabled = false; this.textContent = 'Save Tariff';

        if (data.success) {
            itemsMsg.className = 'text-xs mt-2 text-green-600';
            itemsMsg.textContent = data.message;
            await loadItems();
            table.ajax.reload();
        } else {
            itemsMsg.className = 'text-xs mt-2 text-red-600';
            itemsMsg.textContent = data.message || 'Save failed.';
        }
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

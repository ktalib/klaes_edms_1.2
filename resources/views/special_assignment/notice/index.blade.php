@extends('layouts.app')
@section('page-title'){{ $PageTitle ?? 'Special Assignment – Notice' }}@endsection

@section('content')
<div class="flex-1 overflow-auto">
    @include('admin.header')
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Notice</h1>
                <p class="text-sm text-gray-500 mt-1">{{ $PageDescription ?? '' }}</p>
            </div>
            <button id="btn-issue-notice"
                class="inline-flex items-center gap-2 px-4 py-2 bg-[rgb(186,191,12)] hover:opacity-90 text-white text-sm font-medium rounded-lg">
                <i data-lucide="send" class="h-4 w-4"></i> Issue Notice
            </button>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-4">
                <div class="p-3 rounded-full" style="background:rgba(186,191,12,.12)"><i data-lucide="send" class="h-5 w-5 text-[rgb(186,191,12)]"></i></div>
                <div><p class="text-xs text-gray-500">First Serves</p><p class="text-xl font-bold text-gray-800">{{ $stats['first_total'] }}</p></div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-4">
                <div class="p-3 rounded-full bg-blue-50"><i data-lucide="send" class="h-5 w-5 text-blue-500"></i></div>
                <div><p class="text-xs text-gray-500">Second Serves</p><p class="text-xl font-bold text-gray-800">{{ $stats['second_total'] }}</p></div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-4">
                <div class="p-3 rounded-full bg-amber-50"><i data-lucide="clock" class="h-5 w-5 text-amber-500"></i></div>
                <div><p class="text-xs text-gray-500">Pending</p><p class="text-xl font-bold text-gray-800">{{ $stats['pending'] }}</p></div>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="flex gap-1 bg-gray-100 rounded-lg p-1 mb-6 w-fit">
            <button data-tab="first-serve" class="tab-btn active px-5 py-2 text-sm font-medium rounded-md transition-all">
                <span class="flex items-center gap-2"><i data-lucide="send" class="h-4 w-4"></i> First Serve</span>
            </button>
            <button data-tab="second-serve" class="tab-btn px-5 py-2 text-sm font-medium rounded-md text-gray-500 transition-all">
                <span class="flex items-center gap-2"><i data-lucide="send" class="h-4 w-4"></i> Second Serve</span>
            </button>
        </div>

        {{-- First Serve Table --}}
        <div id="tab-first-serve" class="tab-content">
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100"><h2 class="text-sm font-semibold text-gray-700">First Serve Notices</h2></div>
                <div class="p-4">
                    <table id="first-serve-table" class="w-full text-sm" style="width:100%">
                        <thead><tr class="text-left text-xs text-gray-500 uppercase">
                            <th class="px-3 py-2">#</th><th class="px-3 py-2">File No</th>
                            <th class="px-3 py-2">Recipient</th><th class="px-3 py-2">Phone</th>
                            <th class="px-3 py-2">Date Served</th><th class="px-3 py-2">SMS</th>
                            <th class="px-3 py-2">Days Ago</th><th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">2nd Serve</th>
                        </tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Second Serve Table --}}
        <div id="tab-second-serve" class="tab-content hidden">
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100"><h2 class="text-sm font-semibold text-gray-700">Second Serve Notices</h2></div>
                <div class="p-4">
                    <table id="second-serve-table" class="w-full text-sm" style="width:100%">
                        <thead><tr class="text-left text-xs text-gray-500 uppercase">
                            <th class="px-3 py-2">#</th><th class="px-3 py-2">File No</th>
                            <th class="px-3 py-2">Recipient</th><th class="px-3 py-2">Phone</th>
                            <th class="px-3 py-2">Date Served</th><th class="px-3 py-2">SMS</th>
                            <th class="px-3 py-2">Status</th>
                        </tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Issue Notice Modal --}}
<div id="modal-notice" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-800">Issue First Serve Notice</h3>
            <button class="modal-close text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>
        <form id="form-notice" class="p-6 space-y-4">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Application</label>
                    <select name="spa_application_id" id="notice-app-select" required
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none">
                        <option value="">Select…</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">File No</label>
                    <input type="text" name="file_number" id="notice-file-no" readonly class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Recipient Name <span class="text-red-500">*</span></label>
                    <input type="text" name="recipient_name" id="notice-recipient" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Phone <span class="text-red-500">*</span></label>
                    <input type="text" name="phone" id="notice-phone" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Date Served <span class="text-red-500">*</span></label>
                    <input type="date" name="served_date" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none">
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" class="modal-close px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-5 py-2 text-sm text-white bg-[rgb(186,191,12)] rounded-lg hover:opacity-90">Issue &amp; Send SMS</button>
            </div>
        </form>
    </div>
</div>

<style>
    .tab-btn.active { background:white; color:#1f2937; box-shadow:0 1px 3px rgba(0,0,0,.1); }
    table.dataTable thead th { background:#f9fafb; font-weight:600; }
    .dataTables_wrapper .dataTables_filter input { border:1px solid #e5e7eb; border-radius:.5rem; padding:.35rem .75rem; font-size:.85rem; }
    .dataTables_wrapper .dataTables_length select { border:1px solid #e5e7eb; border-radius:.5rem; padding:.25rem .5rem; }
</style>

<script>
const CSRF         = '{{ csrf_token() }}';
const STORE_NOTICE = '{{ route("special-assignment.notice.store") }}';
const SECOND_BASE  = '{{ url("special-assignment/notice") }}';
const APP_AJAX     = '{{ route("special-assignment.land-records") }}';

$(document).ready(function () {
    // ── Tab switching ───────────────────────────────────────────────────────
    const NOTICE_HASH = { '#first-serve': 'first-serve', '#second-serve': 'second-serve' };

    function activateNoticeTab(tabKey) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(p => p.classList.add('hidden'));
        const btn = document.querySelector(`.tab-btn[data-tab="${tabKey}"]`);
        if (btn) btn.classList.add('active');
        const content = document.getElementById('tab-' + tabKey);
        if (content) content.classList.remove('hidden');
        setTimeout(() => $.fn.DataTable.tables({ visible:true, api:true }).columns.adjust(), 50);
    }

    activateNoticeTab(NOTICE_HASH[window.location.hash] || 'first-serve');

    window.addEventListener('hashchange', () => {
        const tab = NOTICE_HASH[window.location.hash];
        if (tab) activateNoticeTab(tab);
    });

    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const tab = btn.dataset.tab;
            history.replaceState(null, '', '#' + tab);
            activateNoticeTab(tab);
        });
    });

    // ── DataTables ──────────────────────────────────────────────────────────
    const firstCols = [
        { data:'DT_RowIndex', orderable:false, searchable:false }, { data:'file_number' },
        { data:'recipient' }, { data:'phone' }, { data:'served_date' }, { data:'sms_sent' },
        { data:'days_ago', render: d => d != null ? `<span class="px-2 py-0.5 rounded-full text-xs ${d>=14?'bg-red-100 text-red-700':'bg-green-100 text-green-700'}">${d}d ago</span>` : '—' },
        { data:'status', render: d => `<span class="px-2 py-0.5 rounded-full text-xs font-medium ${d==='served'?'bg-green-100 text-green-700':'bg-amber-100 text-amber-700'}">${d||'—'}</span>` },
        { data:'action', orderable:false, searchable:false },
    ];
    const secondCols = [
        { data:'DT_RowIndex', orderable:false, searchable:false }, { data:'file_number' },
        { data:'recipient' }, { data:'phone' }, { data:'served_date' }, { data:'sms_sent' },
        { data:'status', render: d => `<span class="px-2 py-0.5 rounded-full text-xs font-medium ${d==='served'?'bg-green-100 text-green-700':'bg-amber-100 text-amber-700'}">${d||'—'}</span>` },
    ];

    const firstTable  = $('#first-serve-table').DataTable({ processing:true, serverSide:true, ajax:{ url: window.location.href, data: d=>({...d, ajax:1, type:'first'}) }, columns: firstCols });
    const secondTable = $('#second-serve-table').DataTable({ processing:true, serverSide:true, ajax:{ url: window.location.href, data: d=>({...d, ajax:1, type:'second'}) }, columns: secondCols });

    // ── Trigger 2nd serve ───────────────────────────────────────────────────
    $('#first-serve-table').on('click', '.btn-trigger-second', async function() {
        const id  = $(this).data('id');
        const app = $(this).data('app');
        const ok  = await Swal.fire({ icon:'question', title:'Trigger Second Serve?', text:'An SMS will be sent and a second serve record created.', showCancelButton:true, confirmButtonText:'Yes, trigger' });
        if (!ok.isConfirmed) return;
        const res  = await fetch(`${SECOND_BASE}/${id}/second`, { method:'POST', headers:{'X-CSRF-TOKEN':CSRF,'Content-Type':'application/json'}, body:JSON.stringify({}) });
        const data = await res.json();
        Swal.fire({ icon: data.success?'success':'error', title: data.success?'Done':'Error', text: data.message, timer:2500, showConfirmButton:false });
        if (data.success) { firstTable.ajax.reload(); secondTable.ajax.reload(); }
    });

    // ── Modal ───────────────────────────────────────────────────────────────
    const modal = document.getElementById('modal-notice');
    document.getElementById('btn-issue-notice').addEventListener('click', () => { modal.classList.remove('hidden'); modal.classList.add('flex'); });
    document.querySelectorAll('.modal-close').forEach(b => b.addEventListener('click', () => { modal.classList.add('hidden'); modal.classList.remove('flex'); }));

    // Pre-fill recipient/phone on app selection
    fetch(APP_AJAX + '?ajax=1&length=500&start=0', { headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => {
            const sel = document.getElementById('notice-app-select');
            sel.innerHTML = '<option value="">Select…</option>';
            (d.data || []).forEach(a => {
                sel.innerHTML += `<option value="${a.id}" data-file="${a.file_number}" data-owner="${a.owner_name}" data-phone="${a.phone||''}">${a.file_number} – ${a.owner_name}</option>`;
            });
            if (!d.data || d.data.length === 0) sel.innerHTML = '<option value="">No applications found</option>';
            sel.addEventListener('change', function() {
                const opt = this.options[this.selectedIndex];
                document.getElementById('notice-file-no').value   = opt.dataset.file  || '';
                document.getElementById('notice-recipient').value = opt.dataset.owner || '';
                document.getElementById('notice-phone').value     = opt.dataset.phone || '';
            });
        })
        .catch(() => {
            document.getElementById('notice-app-select').innerHTML = '<option value="">Failed to load</option>';
        });

    // ── Form submit ─────────────────────────────────────────────────────────
    document.getElementById('form-notice').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = this.querySelector('[type=submit]');
        btn.disabled = true; btn.textContent = 'Saving…';
        const body = Object.fromEntries(new FormData(this));
        const res  = await fetch(STORE_NOTICE, { method:'POST', headers:{'X-CSRF-TOKEN':CSRF,'Content-Type':'application/json'}, body: JSON.stringify(body) });
        const data = await res.json();
        if (data.success) {
            modal.classList.add('hidden'); modal.classList.remove('flex'); this.reset();
            firstTable.ajax.reload();
            const msg = data.sms_sent ? 'Notice issued & SMS sent.' : 'Notice issued. SMS could not be sent.';
            Swal.fire({ icon: data.sms_sent ? 'success' : 'warning', title: 'Done', text: msg, timer:3000, showConfirmButton:false });
        } else {
            Swal.fire({ icon:'error', title:'Error', text: data.message||'Save failed.' });
        }
        btn.disabled=false; btn.textContent='Issue & Send SMS';
    });
});
</script>
@endsection

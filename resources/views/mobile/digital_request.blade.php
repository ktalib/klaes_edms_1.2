<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
  <meta name="theme-color" content="#450a0a">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>My File Requests</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background: #fff7f7; color: #4b5563; height: 100vh; overflow: hidden; position: fixed; width: 100%; }
    #app { display: flex; flex-direction: column; height: 100vh; width: 100%; overflow: hidden; }

    /* ── Header ─────────────────────────────────────── */
    .dfr-header { background: linear-gradient(135deg, #450a0a 0%, #6b1010 50%, #450a0a 100%); padding: 16px 16px 20px; flex-shrink: 0; }
    .dfr-header-top { display: flex; align-items: center; justify-content: space-between; }
    .back-btn { background: rgba(255,255,255,0.15); border: none; color: white; width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; }
    .back-btn i { font-size: 16px; }
    .header-title { flex: 1; text-align: center; }
    .header-title h1 { font-size: 17px; font-weight: 700; color: white; }
    .header-title p  { font-size: 11px; color: rgba(255,255,255,0.7); margin-top: 2px; }
    .header-avatar { width: 38px; height: 38px; border-radius: 50%; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; color: white; font-size: 15px; font-weight: 700; flex-shrink: 0; }

    /* ── Toolbar ─────────────────────────────────────── */
    .toolbar { background: white; border-bottom: 1px solid #f3f4f6; padding: 10px 16px; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
    .toolbar-title { font-size: 14px; font-weight: 700; color: #450a0a; display: flex; align-items: center; gap: 6px; }
    .refresh-btn { background: none; border: 1px solid #fca5a5; border-radius: 30px; padding: 5px 14px; font-size: 11px; font-weight: 600; color: #450a0a; cursor: pointer; display: flex; align-items: center; gap: 5px; }
    .refresh-btn:active { background: #fff0f0; }

    /* ── Scroll area ─────────────────────────────────── */
    .main-scroll { flex: 1; overflow-y: auto; padding: 14px 14px 24px; -webkit-overflow-scrolling: touch; }
    .main-scroll::-webkit-scrollbar { width: 3px; }
    .main-scroll::-webkit-scrollbar-thumb { background: #450a0a; border-radius: 10px; }

    /* ── Request cards ─────────────────────────────────── */
    .req-card { background: white; border-radius: 16px; border: 1px solid #f3f4f6; box-shadow: 0 1px 4px rgba(0,0,0,0.04); margin-bottom: 12px; overflow: hidden; }
    .req-card-top { padding: 11px 14px; background: #fafafa; border-bottom: 1px solid #f3f4f6; display: flex; justify-content: space-between; align-items: center; }
    .req-no { font-size: 11px; font-weight: 700; color: #450a0a; }
    .req-type-badge { font-size: 9px; font-weight: 700; padding: 2px 8px; border-radius: 30px; }
    .req-type-physical { background: #fef3c7; color: #92400e; }
    .req-type-digital  { background: #dbeafe; color: #1e40af; }
    .req-body { padding: 12px 14px; }
    .req-file { font-size: 14px; font-weight: 700; color: #1f2937; }
    .req-title { font-size: 11px; color: #6b7280; margin-top: 2px; }
    .req-remarks { font-size: 11px; color: #9ca3af; font-style: italic; margin-top: 4px; }
    .req-status-row { display: flex; align-items: center; justify-content: space-between; margin-top: 10px; }
    .status-pill { font-size: 10px; font-weight: 700; padding: 3px 10px; border-radius: 30px; }
    .status-pending  { background: #fef3c7; color: #92400e; }
    .status-approved { background: #dcfce7; color: #166534; }
    .status-rejected { background: #fee2e2; color: #991b1b; }
    .status-transit  { background: #dbeafe; color: #1e40af; }
    .req-date { font-size: 10px; color: #9ca3af; }

    /* ── Empty / loading ─────────────────────────────── */
    .empty-state { text-align: center; padding: 50px 20px; }
    .empty-state i  { font-size: 48px; color: #fca5a5; margin-bottom: 12px; display: block; }
    .empty-state h3 { font-size: 15px; font-weight: 700; color: #374151; margin-bottom: 6px; }
    .empty-state p  { font-size: 12px; color: #9ca3af; }

    /* ── Spinner ─────────────────────────────────────── */
    .spinner { width: 28px; height: 28px; border: 3px solid rgba(69,10,10,0.15); border-top-color: #450a0a; border-radius: 50%; animation: spin 0.7s linear infinite; margin: 0 auto 12px; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── Toast ─────────────────────────────────────────── */
    #toast { position: fixed; bottom: 24px; left: 16px; right: 16px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; pointer-events: none; }
    .toast-item { padding: 12px 16px; border-radius: 14px; font-size: 13px; font-weight: 600; color: white; box-shadow: 0 4px 12px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 8px; animation: slideUp 0.3s ease; }
    .toast-error { background: #991b1b; }
    .toast-info  { background: #1e40af; }
    @keyframes slideUp { from { transform: translateY(16px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
  </style>
</head>
<body>
<div id="app">

  {{-- Header --}}
  <div class="dfr-header">
    <div class="dfr-header-top">
      <button class="back-btn" onclick="window.location='{{ route('mobile.dashboard') }}'">
        <i class="fas fa-arrow-left"></i>
      </button>
      <div class="header-title">
        <h1>Digital File Request</h1>
        <p>e-Registry · My Requests</p>
      </div>
      <div class="header-avatar">{{ strtoupper(substr($user->first_name ?? $user->name ?? 'U', 0, 1)) }}</div>
    </div>
  </div>

  {{-- Toolbar --}}
  <div class="toolbar">
    <span class="toolbar-title"><i class="fas fa-list-alt"></i> My Requests</span>
    <button class="refresh-btn" onclick="loadMyRequests()">
      <i class="fas fa-sync-alt"></i> Refresh
    </button>
  </div>

  {{-- List --}}
  <div class="main-scroll">
    <div id="requests-list">
      <div class="empty-state">
        <div class="spinner"></div>
        <p style="font-size:12px; color:#9ca3af;">Loading your requests…</p>
      </div>
    </div>
  </div>

</div>

<div id="toast"></div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
const BASE = window.location.origin;

async function loadMyRequests() {
  const list = document.getElementById('requests-list');
  list.innerHTML = '<div class="empty-state"><div class="spinner"></div><p style="font-size:12px;color:#9ca3af;">Loading…</p></div>';

  try {
    const res  = await fetch(`${BASE}/digital-request/list?mine=1`, {
      headers: { 'X-CSRF-TOKEN': CSRF }
    });
    const data = await res.json();

    if (!res.ok) {
      throw new Error(data.error || `Server error ${res.status}`);
    }

    const rows = data.data || data.rows || (Array.isArray(data) ? data : []);

    if (!rows.length) {
      list.innerHTML = `<div class="empty-state">
        <i class="fas fa-inbox"></i>
        <h3>No requests yet</h3>
        <p>Requests you submit will appear here</p>
      </div>`;
      return;
    }

    const statusMap = {
      'Pending':    'status-pending',
      'Approved':   'status-approved',
      'Rejected':   'status-rejected',
      'In Transit': 'status-transit',
    };

    list.innerHTML = rows.map(r => {
      const typeClass  = r.request_type === 'Digital' ? 'req-type-digital' : 'req-type-physical';
      const statusClass = statusMap[r.request_status] || 'status-pending';
      const date = (r.requested_at || r.created_at || '').substring(0, 10);
      return `<div class="req-card">
        <div class="req-card-top">
          <span class="req-no">${r.request_no || '—'}</span>
          <span class="req-type-badge ${typeClass}">${r.request_type || 'Physical'}</span>
        </div>
        <div class="req-body">
          <div class="req-file">${r.file_no || '—'}</div>
          ${r.file_title ? `<div class="req-title">${r.file_title}</div>` : ''}
          ${r.remarks    ? `<div class="req-remarks">"${r.remarks.substring(0,70)}${r.remarks.length>70?'…':''}"</div>` : ''}
          <div class="req-status-row">
            <span class="status-pill ${statusClass}">${r.request_status || 'Pending'}</span>
            <span class="req-date">${date}</span>
          </div>
        </div>
      </div>`;
    }).join('');

  } catch (e) {
    list.innerHTML = `<div class="empty-state">
      <i class="fas fa-exclamation-circle"></i>
      <h3>Could not load requests</h3>
      <p style="color:#dc2626;font-size:11px;margin-top:6px;">${e.message || 'Unknown error'}</p>
    </div>`;
    showToast('Failed to load requests', 'error');
  }
}

function showToast(msg, type = 'info') {
  const el = document.createElement('div');
  el.className = `toast-item toast-${type}`;
  el.innerHTML = `<i class="fas fa-${type==='error'?'times-circle':'info-circle'}"></i> ${msg}`;
  document.getElementById('toast').appendChild(el);
  setTimeout(() => el.remove(), 3500);
}

loadMyRequests();
</script>
</body>
</html>

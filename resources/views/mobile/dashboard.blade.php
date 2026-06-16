<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
  <meta name="theme-color" content="#f4f5fb">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>KLAES File Tracker</title>
  {{-- Set theme before paint to avoid flash --}}
  <script>
    (function(){
      try {
        var t = localStorage.getItem('klaes-theme') ||
                ((window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', t);
      } catch(e){}
    })();
  </script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <style>
    /* ─── Design tokens ─────────────────────────────────────────── */
    :root {
      --bg: #f4f5fb;
      --bg-grad-1: #eef0fb;
      --bg-grad-2: #f7f4fb;
      --surface: #ffffff;
      --surface-2: #f7f8fc;
      --surface-3: #eef0f6;
      --text: #1a1d2b;
      --muted: #6b7185;
      --faint: #99a0b3;
      --border: #ebedf4;
      --border-strong: #e0e3ec;
      --primary: #6366f1;
      --primary-2: #8b5cf6;
      --primary-soft: rgba(99,102,241,0.10);
      --grad: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
      --grad-warm: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
      --shadow-sm: 0 1px 3px rgba(20,23,40,0.05);
      --shadow: 0 6px 22px rgba(20,23,40,0.07);
      --shadow-lg: 0 12px 34px rgba(20,23,40,0.12);
      --tabbar: rgba(255,255,255,0.92);
      --c-sky:#0ea5e9; --c-amber:#f59e0b; --c-emerald:#10b981; --c-rose:#ef4444; --c-indigo:#6366f1;
    }
    [data-theme="dark"] {
      --bg: #0c0e15;
      --bg-grad-1: #11131d;
      --bg-grad-2: #15121f;
      --surface: #171a24;
      --surface-2: #1d212e;
      --surface-3: #242938;
      --text: #e7e9f3;
      --muted: #9aa0b6;
      --faint: #6c7286;
      --border: #262b3a;
      --border-strong: #2f3445;
      --primary: #818cf8;
      --primary-2: #a78bfa;
      --primary-soft: rgba(129,140,248,0.15);
      --grad: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
      --shadow-sm: 0 1px 3px rgba(0,0,0,0.4);
      --shadow: 0 6px 22px rgba(0,0,0,0.45);
      --shadow-lg: 0 12px 34px rgba(0,0,0,0.55);
      --tabbar: rgba(23,26,36,0.88);
    }

    * { margin:0; padding:0; box-sizing:border-box; -webkit-tap-highlight-color:transparent; }
    body {
      font-family:'Inter',sans-serif;
      background:
        radial-gradient(1100px 480px at 100% -10%, var(--bg-grad-2), transparent 60%),
        radial-gradient(900px 460px at -10% 0%, var(--bg-grad-1), transparent 55%),
        var(--bg);
      color:var(--text); height:100vh; overflow:hidden; position:fixed; width:100%;
      transition:background-color .3s ease, color .3s ease;
    }
    #app { display:flex; flex-direction:column; height:100vh; width:100%; position:relative; overflow:hidden; }
    .screen { flex:1; overflow-y:auto; padding:18px 16px 110px; display:none; position:relative; animation:fade .35s ease; }
    .screen.active { display:block; }
    @keyframes fade { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:none; } }
    .screen::-webkit-scrollbar { width:0; }

    .card {
      background:var(--surface); border:1px solid var(--border);
      border-radius:22px; box-shadow:var(--shadow); transition:background-color .3s, border-color .3s;
    }

    /* ─── Top bar ───────────────────────────────────────────────── */
    .topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:22px; }
    .topbar h1 { font-size:25px; font-weight:800; letter-spacing:-.5px; }
    .greeting-text { font-size:13px; color:var(--muted); margin-top:3px; }
    .topbar-actions { display:flex; align-items:center; gap:10px; }
    .icon-circle {
      background:var(--surface); border:1px solid var(--border); border-radius:50%;
      width:44px; height:44px; display:flex; align-items:center; justify-content:center;
      cursor:pointer; transition:.2s; box-shadow:var(--shadow-sm); position:relative; color:var(--text);
    }
    .icon-circle:active { transform:scale(.92); }
    .icon-circle i { font-size:18px; color:var(--primary); }
    .notification-badge {
      position:absolute; top:-3px; right:-3px; background:var(--c-rose); color:#fff;
      font-size:10px; font-weight:700; padding:2px 6px; border-radius:30px; min-width:18px;
      text-align:center; display:none; border:2px solid var(--surface);
    }
    .notification-wrapper { position:relative; }
    .notification-panel {
      position:absolute; top:54px; right:0; width:300px; background:var(--surface);
      border-radius:18px; box-shadow:var(--shadow-lg); z-index:200; display:none; border:1px solid var(--border);
      overflow:hidden;
    }
    .notification-panel.show { display:block; }
    .notification-header { padding:14px 16px; border-bottom:1px solid var(--border); font-weight:700; font-size:14px; display:flex; justify-content:space-between; align-items:center; background:var(--surface-2); }
    .mark-read { font-size:11px; color:var(--primary); cursor:pointer; background:none; border:none; font-weight:600; }
    .notification-list { max-height:350px; overflow-y:auto; }
    .notification-item { padding:12px 16px; border-bottom:1px solid var(--border); cursor:pointer; transition:.15s; }
    .notification-item:hover { background:var(--surface-2); }
    .notification-item.unread { background:var(--primary-soft); }
    .notification-title { font-size:12px; font-weight:600; }
    .notification-desc { font-size:11px; color:var(--muted); margin-top:3px; }
    .notification-time { font-size:9px; color:var(--faint); margin-top:4px; }

    /* ─── Stats ─────────────────────────────────────────────────── */
    .stats-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:12px; margin-bottom:18px; }
    .stat-card { padding:16px; background:var(--surface); border:1px solid var(--border); border-radius:20px; box-shadow:var(--shadow-sm); transition:transform .2s; }
    .stat-card:active { transform:scale(.98); }
    .stat-icon { width:42px; height:42px; border-radius:14px; display:flex; align-items:center; justify-content:center; margin-bottom:10px; font-size:17px; }
    .stat-value { font-size:26px; font-weight:800; letter-spacing:-.5px; }
    .stat-label { font-size:11px; color:var(--muted); margin-top:2px; font-weight:500; }

    /* ─── Quick actions ─────────────────────────────────────────── */
    .section-title { font-size:13px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.6px; margin:4px 2px 12px; }
    .quick-actions-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:12px; margin-bottom:20px; }
    .quick-action-btn {
      background:var(--surface); border:1px solid var(--border); border-radius:20px;
      padding:16px 8px; text-align:center; cursor:pointer; box-shadow:var(--shadow-sm); transition:.2s;
    }
    .quick-action-btn:active { transform:scale(.95); }
    .quick-action-btn .qa-ic {
      width:42px; height:42px; border-radius:14px; margin:0 auto 8px; display:flex; align-items:center; justify-content:center;
      background:var(--grad); color:#fff; font-size:17px; box-shadow:0 6px 14px var(--primary-soft);
    }
    .quick-action-btn.warm .qa-ic { background:var(--grad-warm); box-shadow:0 6px 14px rgba(245,158,11,.25); }
    .quick-action-btn.red .qa-ic  { background:linear-gradient(135deg,#ef4444,#b91c1c); box-shadow:0 6px 14px rgba(239,68,68,.25); }
    .quick-action-btn span { font-size:11px; font-weight:600; color:var(--text); line-height:1.25; display:block; }

    /* ─── Chart / panels ────────────────────────────────────────── */
    .panel { padding:18px; margin-bottom:18px; }
    .panel-title { font-size:14px; font-weight:700; margin-bottom:14px; display:flex; align-items:center; gap:9px; }
    .panel-title i { color:var(--primary); }
    canvas { max-height:170px; width:100% !important; }

    .priority-item { display:flex; align-items:center; gap:10px; margin-bottom:12px; }
    .priority-item:last-child { margin-bottom:0; }
    .priority-label { width:54px; font-size:12px; font-weight:600; }
    .priority-bar { flex:1; height:9px; background:var(--surface-3); border-radius:10px; overflow:hidden; }
    .priority-bar-fill { height:100%; border-radius:10px; transition:width .6s ease; }
    .priority-value { width:34px; font-size:12px; font-weight:700; text-align:right; }
    .fill-high { background:linear-gradient(90deg,#f87171,#ef4444); }
    .fill-medium { background:linear-gradient(90deg,#fbbf24,#f59e0b); }
    .fill-low { background:linear-gradient(90deg,#34d399,#10b981); }

    .activity-item { display:flex; align-items:center; gap:12px; padding:12px 0; border-bottom:1px solid var(--border); }
    .activity-item:last-child { border-bottom:none; }
    .activity-icon { width:38px; height:38px; border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .activity-content { flex:1; min-width:0; }
    .activity-title { font-size:13px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .activity-time { font-size:10px; color:var(--faint); margin-top:2px; }

    /* ─── Page header (sub screens) ─────────────────────────────── */
    .page-header h1 { font-size:23px; font-weight:800; letter-spacing:-.5px; }
    .page-header p { font-size:13px; color:var(--muted); margin-top:3px; }

    /* ─── Forms ─────────────────────────────────────────────────── */
    .form-section { background:var(--surface); border:1px solid var(--border); border-radius:22px; padding:18px; margin-bottom:16px; box-shadow:var(--shadow-sm); }
    .section-header { display:flex; align-items:center; gap:12px; margin-bottom:16px; }
    .section-header .section-icon { font-size:17px; background:var(--primary-soft); padding:10px; border-radius:13px; color:var(--primary); width:40px; height:40px; display:flex; align-items:center; justify-content:center; }
    .section-header h2 { font-size:15px; font-weight:700; }
    .section-header p { font-size:11px; color:var(--faint); margin-top:1px; }
    .field-wrap { margin-bottom:14px; }
    .field-wrap:last-child { margin-bottom:0; }
    .field-wrap label { font-size:12px; font-weight:600; margin-bottom:6px; display:block; color:var(--muted); }
    .field-wrap input, .field-wrap select, .field-wrap textarea {
      background:var(--surface-2); border:1px solid var(--border-strong); border-radius:14px;
      padding:12px 14px; font-size:14px; width:100%; outline:none; font-family:'Inter',sans-serif; color:var(--text);
      transition:.18s;
    }
    .field-wrap input::placeholder, .field-wrap textarea::placeholder { color:var(--faint); }
    .field-wrap input:focus, .field-wrap select:focus, .field-wrap textarea:focus {
      border-color:var(--primary); box-shadow:0 0 0 3px var(--primary-soft); background:var(--surface);
    }
    .field-wrap input[readonly] { background:var(--surface-3); color:var(--muted); cursor:not-allowed; }
    select { -webkit-appearance:none; appearance:none;
      background-image:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="%2399a0b3" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>');
      background-repeat:no-repeat; background-position:right 14px center; padding-right:36px; }
    .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .btn {
      background:var(--grad); border:none; padding:15px; border-radius:16px; font-weight:700; font-size:15px;
      color:#fff; width:100%; cursor:pointer; box-shadow:0 8px 20px var(--primary-soft); transition:.2s;
      display:inline-flex; align-items:center; justify-content:center; gap:8px;
    }
    .btn:active { transform:scale(.98); }
    .btn:disabled { opacity:.6; cursor:not-allowed; }
    .priority-selector { display:flex; gap:8px; }
    .prio-chip { flex:1; text-align:center; background:var(--surface-2); border:1px solid var(--border-strong); padding:10px; border-radius:13px; font-size:13px; font-weight:600; cursor:pointer; user-select:none; transition:.18s; color:var(--muted); }
    .prio-chip.active { background:var(--grad); color:#fff; border-color:transparent; box-shadow:0 6px 14px var(--primary-soft); }

    .success-toast, .error-toast {
      position:fixed; top:18px; left:50%; transform:translateX(-50%); color:#fff; padding:12px 22px;
      border-radius:30px; font-size:13px; font-weight:600; z-index:999; display:none; box-shadow:var(--shadow-lg);
      max-width:90%;
    }
    .success-toast { background:#059669; }
    .error-toast { background:#dc2626; }

    /* ─── Scanner ───────────────────────────────────────────────── */
    .scanner-container { position:relative; width:100%; border-radius:18px; overflow:hidden; background:#000; margin-bottom:14px; min-height:330px; }
    #qr-reader { width:100%; min-height:330px; border:none !important; }
    .camera-controls { display:flex; gap:8px; margin-top:6px; }
    .camera-controls button { flex:1; background:var(--surface-2); border:1px solid var(--border-strong); color:var(--text); padding:11px; border-radius:13px; font-weight:600; font-size:12px; cursor:pointer; }
    .flash-on { background:var(--grad) !important; color:#fff !important; border-color:transparent !important; }
    .scanner-status { padding:13px; margin-top:12px; text-align:center; border-radius:14px; background:var(--surface-2); border:1px solid var(--border); font-size:13px; color:var(--muted); }

    /* ─── Profile ───────────────────────────────────────────────── */
    .avatar-large { background:var(--grad); width:76px; height:76px; border-radius:26px; margin:0 auto 14px; display:flex; align-items:center; justify-content:center; box-shadow:0 10px 24px var(--primary-soft); }
    .profile-name { font-size:20px; font-weight:700; }
    .profile-username { font-size:13px; color:var(--muted); margin-top:2px; }
    .role-pill { background:var(--primary-soft); color:var(--primary); display:inline-block; padding:4px 14px; border-radius:30px; margin-top:10px; font-size:11px; font-weight:700; }
    .menu-item { display:flex; align-items:center; gap:12px; padding:13px 0; cursor:pointer; font-size:14px; font-weight:500; }
    .menu-item i:first-child { color:var(--primary); width:20px; }
    .divider { height:1px; background:var(--border); margin:4px 0; }
    .ghost-btn { background:var(--surface-2); border:1px solid var(--border-strong); color:var(--text); }

    .result-card { background:var(--surface); border:1px solid var(--border); border-radius:18px; box-shadow:var(--shadow-sm); overflow:hidden; margin-top:8px; }

    /* ─── Select2 (themed to match) ──────────────────────────────── */
    #fileSearchSelect + .select2-container { flex:1; }
    .select2-container--default .select2-selection--single { height:46px; background:var(--surface-2); border:1px solid var(--border-strong); border-radius:14px; display:flex; align-items:center; padding:0 14px; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { color:var(--text); font-size:14px; line-height:normal; padding:0; }
    .select2-container--default .select2-selection--single .select2-selection__placeholder { color:var(--faint); }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height:44px; }
    .select2-dropdown { background:var(--surface); border:1px solid var(--border-strong); border-radius:14px; color:var(--text); }
    .select2-search--dropdown .select2-search__field { background:var(--surface-2); border:1px solid var(--border-strong); border-radius:8px; color:var(--text); }
    .select2-results__option { color:var(--text); }
    .select2-container--default .select2-results__option--highlighted[aria-selected] { background:var(--primary); }

    /* ─── Tab bar ───────────────────────────────────────────────── */
    .tab-bar {
      position:fixed; bottom:14px; left:14px; right:14px; background:var(--tabbar);
      backdrop-filter:blur(22px); -webkit-backdrop-filter:blur(22px); border-radius:26px; display:flex;
      justify-content:space-around; align-items:center; padding:8px 8px; border:1px solid var(--border);
      box-shadow:var(--shadow-lg); z-index:100;
    }
    .tab-item { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:3px; background:transparent; border:none; color:var(--faint); font-size:10px; font-weight:600; padding:7px 4px; border-radius:16px; cursor:pointer; transition:.2s; flex:1; }
    .tab-item i { font-size:18px; }
    .tab-item.active { color:var(--primary); }
    .tab-item.active i { transform:translateY(-1px); }

    @media (min-width:768px) {
      .screen { padding:24px 24px 110px; max-width:560px; margin:0 auto; }
      .tab-bar { max-width:540px; margin:0 auto; }
    }
  </style>
</head>
<body>
@include('mobile.partials.preloader')

{{-- Toast messages --}}
<div id="successToast" class="success-toast"></div>
<div id="errorToast"   class="error-toast"></div>

<div id="app">
  <!-- ═══ DASHBOARD SCREEN ═══ -->
  <div id="dashboard-screen" class="screen">
    <div class="topbar">
      <div>
        <h1>Dashboard</h1>
        <div class="greeting-text" id="greetingMsg"></div>
      </div>
      <div class="topbar-actions">
        <button class="icon-circle" id="themeToggle" onclick="toggleTheme()" title="Toggle theme">
          <i id="themeToggleIcon" class="fas fa-moon"></i>
        </button>
        <div class="notification-wrapper">
          <button class="icon-circle" id="notificationBell">
            <i class="fas fa-bell"></i>
            <span class="notification-badge" id="notificationBadge"></span>
          </button>
          <div class="notification-panel" id="notificationPanel">
            <div class="notification-header">
              <span>Notifications</span>
              <button class="mark-read" id="markAllRead">Mark all read</button>
            </div>
            <div class="notification-list" id="notificationList"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="section-title">Quick Actions</div>
    <div class="quick-actions-grid">
      <div class="quick-action-btn" onclick="setActiveTab('scanner')">
        <div class="qa-ic"><i class="fas fa-qrcode"></i></div><span>Scan QR</span>
      </div>
      <div class="quick-action-btn red" onclick="window.location='{{ route('mobile.digital-request') }}'">
        <div class="qa-ic"><i class="fas fa-paper-plane"></i></div><span>Digital File Request</span>
      </div>
    </div>

    <div class="card panel">
      <div class="panel-title"><i class="fas fa-chart-line"></i> Weekly Activity</div>
      <canvas id="weeklyChart"></canvas>
    </div>

    <div class="card panel">
      <div class="panel-title"><i class="fas fa-chart-pie"></i> Priority Distribution</div>
      <div id="priorityDistribution"></div>
    </div>

    <div class="card panel">
      <div class="panel-title"><i class="fas fa-clock-rotate-left"></i> Recent Activity</div>
      <div id="recentActivityList" class="activity-list"></div>
    </div>
  </div>

  <!-- ═══ LOG A FILE SCREEN ═══ -->
  @php
    $deptOptions = $offices->pluck('department')->filter()->unique()->sort()->values();
  @endphp
  <div id="create-screen" class="screen">
    <div class="page-header" style="margin-bottom:18px;"><h1>Log a File</h1><p>Create a new file tracker</p></div>

    <div class="form-section">
      <div class="section-header">
        <span class="section-icon"><i class="fas fa-file-circle-plus"></i></span>
        <div><h2>File Details</h2><p>Enter the file information</p></div>
      </div>
      <div class="field-wrap">
        <label>File Number</label>
        <input type="text" id="createFileNumber" placeholder="e.g. RES-2015-4859" autocomplete="off">
      </div>
      <div class="field-wrap">
        <label>File Title *</label>
        <input type="text" id="createFileName" placeholder="e.g. Alhaji Ibrahim Dantata" autocomplete="off">
      </div>
      <div class="grid-2">
        <div class="field-wrap">
          <label>Tracking ID</label>
          <input type="text" id="trackingIdField" readonly>
        </div>
        <div class="field-wrap">
          <label>Log ID</label>
          <input type="text" id="logIdField" readonly>
        </div>
      </div>
      <div class="field-wrap">
        <label>Priority</label>
        <div class="priority-selector">
          <span class="prio-chip" data-prio="LOW">Low</span>
          <span class="prio-chip active" data-prio="MEDIUM">Medium</span>
          <span class="prio-chip" data-prio="HIGH">High</span>
        </div>
      </div>
      <div class="field-wrap">
        <label>Status</label>
        <select id="fileStatus">
          <option value="Log-in" selected>Log-in</option>
          <option value="Log-out">Log-out</option>
          <option value="Canceled">Canceled</option>
        </select>
      </div>
      <div class="field-wrap">
        <label>Notes / Remarks</label>
        <textarea id="createNotes" rows="2" placeholder="Reason the file is in this office..."></textarea>
      </div>
    </div>

    <div class="form-section">
      <div class="section-header">
        <span class="section-icon"><i class="fas fa-building"></i></span>
        <div><h2>Office Details</h2><p>Select department and offices</p></div>
      </div>
      <div class="field-wrap">
        <label>Department *</label>
        <select id="department">
          <option value="">Select department</option>
          @foreach($deptOptions as $dept)
            <option value="{{ $dept }}">{{ $dept }}</option>
          @endforeach
        </select>
      </div>
      <div class="field-wrap">
        <label>Origin Office *</label>
        <select id="originOffice">
          <option value="">Select origin office</option>
          @foreach($offices as $office)
            <option value="{{ $office->office_code }}" data-name="{{ $office->office_name }}">{{ $office->office_name }}</option>
          @endforeach
        </select>
      </div>
      <div class="field-wrap">
        <label>Receiving Office *</label>
        <select id="receivingOffice">
          <option value="">Select receiving office</option>
          @foreach($offices as $office)
            <option value="{{ $office->office_code }}" data-name="{{ $office->office_name }}">{{ $office->office_name }}</option>
          @endforeach
        </select>
      </div>
      <div class="field-wrap">
        <label>Receiving Officer *</label>
        <select id="receivingOfficer">
          <option value="">Select receiving officer</option>
          @foreach($officers as $officer)
            @php $fullName = trim($officer->first_name.' '.$officer->last_name) ?: $officer->username; @endphp
            <option value="{{ $officer->id }}" data-name="{{ $fullName }}">{{ $fullName }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="form-section">
      <div class="section-header">
        <span class="section-icon"><i class="fas fa-clock"></i></span>
        <div><h2>Movement Log</h2><p>Log in / log out date &amp; time</p></div>
      </div>
      <div class="grid-2">
        <div class="field-wrap"><label>Log In Date</label><input type="date" id="logInDate"></div>
        <div class="field-wrap"><label>Log In Time</label><input type="time" id="logInTime"></div>
      </div>
      <div class="grid-2">
        <div class="field-wrap"><label>Log Out Date</label><input type="date" id="logOutDate"></div>
        <div class="field-wrap"><label>Log Out Time</label><input type="time" id="logOutTime"></div>
      </div>
    </div>

    <button id="submitCreateBtn" class="btn" onclick="createNewFile()">
      <i class="fas fa-file-circle-plus"></i> Log a File
    </button>
  </div>

  <!-- ═══ FILES SCREEN ═══ -->
  <div id="files-screen" class="screen">
    <div class="page-header" style="margin-bottom:16px;"><h1>File Search</h1><p>Look up a file's movement history</p></div>

    <div class="form-section">
      <div class="section-header">
        <span class="section-icon"><i class="fas fa-search"></i></span>
        <div><h2>Search File</h2><p>Enter a file number or tracking ID</p></div>
      </div>
      <div class="field-wrap">
        <label>File No / Tracking ID</label>
        <div style="display:flex;gap:8px;align-items:center;">
          <select id="fileSearchSelect" style="flex:1;"></select>
          <button id="fileSearchBtn" class="btn" style="width:auto;padding:0 18px;flex-shrink:0;">
            <i class="fas fa-search"></i>
          </button>
        </div>
      </div>
    </div>

    <div id="fileSearchResult" style="margin-top:4px;"></div>
  </div>

  <!-- ═══ SCANNER SCREEN ═══ -->
  <div id="scanner-screen" class="screen">
    <div class="page-header" style="margin-bottom:16px;"><h1>QR Scanner</h1><p>Scan a file QR to log movement</p></div>
    <div class="form-section">
      <div class="scanner-container"><div id="qr-reader"></div></div>
      <div class="camera-controls">
        <button id="startCameraBtn"><i class="fas fa-play"></i> Start</button>
        <button id="stopCameraBtn"><i class="fas fa-stop"></i> Stop</button>
        <button id="flashToggleBtn"><i class="fas fa-bolt"></i> Flash</button>
      </div>
      <div id="scannerStatus" class="scanner-status">
        <i class="fas fa-camera"></i> <span>Click Start to begin scanning</span>
      </div>
    </div>

    {{-- Manual entry fallback --}}
    <div class="form-section" style="margin-top:4px;">
      <div class="section-header">
        <span class="section-icon"><i class="fas fa-keyboard"></i></span>
        <div><h2>Manual Entry</h2><p>Type a Tracking ID if camera is unavailable</p></div>
      </div>
      <div class="field-wrap">
        <label>Tracking ID / File Number</label>
        <div style="display:flex;gap:8px;">
          <input type="text" id="manualScanInput" placeholder="e.g. TRK-XXXXXX" style="flex:1;">
          <button id="manualScanBtn" class="btn" style="width:auto;padding:0 16px;flex-shrink:0;white-space:nowrap;">
            <i class="fas fa-search"></i> Look up
          </button>
        </div>
      </div>
      <div id="manualScanResult"></div>
    </div>
  </div>

  @if($isScbMonitor)
  <!-- ═══ SCB MONITOR — FILE REQUESTS SCREEN ═══ -->
  <div id="requests-screen" class="screen">
    <div class="page-header" style="margin-bottom:16px;display:flex;justify-content:space-between;align-items:flex-end;">
      <div><h1>File Requests</h1><p>Physical searches & FSR History</p></div>
      <button class="icon-circle" id="frRefreshBtn" title="Refresh"><i class="fas fa-rotate-right"></i></button>
    </div>
    <div class="fr-seg">
      <button type="button" class="fr-seg-btn active" data-frview="open" onclick="setFrView('open')">Open</button>
      <button type="button" class="fr-seg-btn" data-frview="log" onclick="setFrView('log')">FSR History</button>
    </div>
    <div id="frListContainer"></div>
    <div id="frLogContainer" style="display:none;"></div>
  </div>
  <style>
    .fr-seg { display:flex; gap:6px; background:var(--primary-soft); padding:4px; border-radius:12px; margin-bottom:14px; }
    .fr-seg-btn { flex:1; border:none; background:transparent; padding:9px; border-radius:9px; font-size:12px; font-weight:700; color:var(--muted); cursor:pointer; }
    .fr-seg-btn.active { background:var(--primary); color:#fff; }
  </style>
  @endif

  <!-- ═══ PROFILE SCREEN ═══ -->
  <div id="profile-screen" class="screen">
    <div class="page-header" style="margin-bottom:16px;"><h1>Profile</h1></div>
    <div class="form-section" style="text-align:center;">
      <div class="avatar-large"><i class="fas fa-user fa-2x" style="color:#fff;"></i></div>
      <div class="profile-name">{{ trim(auth()->user()->first_name.' '.auth()->user()->last_name) ?: auth()->user()->username }}</div>
      <div class="profile-username">{{ auth()->user()->username }}</div>
      <div class="role-pill">{{ strtoupper(auth()->user()->type ?? 'USER') }}</div>
    </div>

    <div class="form-section">
      <div class="menu-item" onclick="toggleTheme()">
        <i class="fas fa-circle-half-stroke"></i><span>Toggle Light / Dark</span>
        <i class="fas fa-chevron-right" style="margin-left:auto;color:var(--faint);"></i>
      </div>
      <div class="divider"></div>
      <div class="menu-item" onclick="setActiveTab('dashboard');document.getElementById('notificationBell').click();">
        <i class="fas fa-bell"></i><span>Notifications</span>
        <i class="fas fa-chevron-right" style="margin-left:auto;color:var(--faint);"></i>
      </div>
    </div>

    <form method="POST" action="{{ route('mobile.logout') }}">
      @csrf
      <button type="submit" class="btn ghost-btn">
        <i class="fas fa-right-from-bracket"></i> Logout
      </button>
    </form>
    <div style="text-align:center;margin-top:16px;font-size:10px;color:var(--faint);">KLAES File Tracker v1.0</div>
  </div>

  <!-- Tab Bar -->
  <div class="tab-bar">
    <button class="tab-item" data-tab="dashboard"><i class="fas fa-house"></i><span>Home</span></button>

    <button class="tab-item" data-tab="files"><i class="fas fa-magnifying-glass"></i><span>Search</span></button>
  @if($isScbMonitor)
    <button class="tab-item" data-tab="requests"><i class="fas fa-clipboard-list"></i><span>FSR</span></button>
    @endif
    <button class="tab-item" data-tab="scanner"><i class="fas fa-qrcode"></i><span>Scan</span></button>
    <button class="tab-item" data-tab="dfr" onclick="window.location='{{ route('mobile.digital-request') }}'"><i class="fas fa-paper-plane"></i><span>DFR</span></button>
   
    <button class="tab-item" data-tab="profile"><i class="fas fa-user"></i><span>Profile</span></button>
  </div>
</div>

<script>
// ─── Server-injected data ─────────────────────────────────────────────────
const CSRF_TOKEN  = '{{ csrf_token() }}';
const API_BASE    = '{{ url("/api/file-trackers") }}';
const MOB_BASE    = '{{ url("/api/mobile") }}';
const CURRENT_USER = {
  id:       {{ auth()->id() }},
  name:     '{{ addslashes(trim(auth()->user()->first_name." ".auth()->user()->last_name) ?: auth()->user()->username) }}',
  username: '{{ auth()->user()->username }}',
};
const IS_SCB_MONITOR = {{ $isScbMonitor ? 'true' : 'false' }};

// Quick Search / File Location outcome styling
const LOC_STATUS_META = {
  IN_TRANSIT:                 { label:'In Transit',                 color:'#f59e0b', icon:'fa-truck-fast' },
  IN_ARCHIVE:                 { label:'In Archive',                 color:'#10b981', icon:'fa-box-archive' },
  IN_ARCHIVE_FOUND:           { label:'In Archive — Found',         color:'#10b981', icon:'fa-circle-check' },
  IN_ARCHIVE_NOT_FOUND:       { label:'In Archive — Not Found',     color:'#ef4444', icon:'fa-triangle-exclamation' },
  IN_POOL_OFFICE:             { label:'In Pool Office',             color:'#0ea5e9', icon:'fa-folder-open' },
  IN_POOL_OFFICE_FOUND:       { label:'In Pool Office — Found',     color:'#10b981', icon:'fa-circle-check' },
  IN_POOL_OFFICE_NOT_FOUND:   { label:'In Pool Office — Not Found', color:'#ef4444', icon:'fa-triangle-exclamation' },
  PENDING_FILE:               { label:'Pending (Not Indexed)',      color:'#6b7185', icon:'fa-circle-question' },
  BLIND_REQUEST_SENT:         { label:'Blind Request Sent',         color:'#6366f1', icon:'fa-paper-plane' },
  FILE_NOT_FOUND:             { label:'File Not Found',             color:'#ef4444', icon:'fa-triangle-exclamation' },
  REFER_TO_ORIGINAL_REGISTRY: { label:'Refer to Original Registry', color:'#6b7185', icon:'fa-share-from-square' },
};

// ─── Theme ────────────────────────────────────────────────────────────────
function applyTheme(theme) {
  document.documentElement.setAttribute('data-theme', theme);
  try { localStorage.setItem('klaes-theme', theme); } catch(e) {}
  const ic = document.getElementById('themeToggleIcon');
  if (ic) ic.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
  const mc = document.querySelector('meta[name="theme-color"]');
  if (mc) mc.setAttribute('content', theme === 'dark' ? '#0c0e15' : '#f4f5fb');
}
function toggleTheme() {
  const cur = document.documentElement.getAttribute('data-theme') || 'light';
  applyTheme(cur === 'dark' ? 'light' : 'dark');
  if (document.getElementById('dashboard-screen').classList.contains('active')) renderDashboard();
}

// ─── API helper (session-auth + CSRF) ────────────────────────────────────
async function api(url, opts = {}) {
  const res = await fetch(url, {
    ...opts,
    headers: {
      'Accept':          'application/json',
      'Content-Type':    'application/json',
      'X-CSRF-TOKEN':    CSRF_TOKEN,
      'X-Requested-With':'XMLHttpRequest',
      ...(opts.headers || {}),
    },
    credentials: 'same-origin',
  });
  if (res.status === 401 || res.status === 403) {
    console.warn('API 401/403 on:', url);
    return { success: false, _authError: true };
  }
  return res.json();
}

// ─── Toast ────────────────────────────────────────────────────────────────
function toast(msg, type = 'success') {
  const el = document.getElementById(type === 'success' ? 'successToast' : 'errorToast');
  el.textContent = msg; el.style.display = 'block';
  setTimeout(() => { el.style.display = 'none'; }, 3500);
}

// ─── State ────────────────────────────────────────────────────────────────
let filesDB = [], notifications = [], weeklyChart = null;
let html5QrCode = null, isScannerRunning = false, flashEnabled = false;

// ─── Notifications ────────────────────────────────────────────────────────
function updateNotificationBadge() {
  const unread = notifications.filter(n => !n.is_read).length;
  const badge  = document.getElementById('notificationBadge');
  badge.textContent = unread;
  badge.style.display = unread > 0 ? 'block' : 'none';
}
function renderNotifications() {
  const container = document.getElementById('notificationList');
  if (!notifications.length) { container.innerHTML = '<div style="padding:16px;text-align:center;font-size:12px;color:var(--faint);">No notifications</div>'; return; }
  container.innerHTML = notifications.map(n => `
    <div class="notification-item ${n.is_read ? '' : 'unread'}" data-id="${n.id}">
      <div class="notification-title">${esc(n.title)}</div>
      <div class="notification-desc">${esc(n.body || '')}</div>
      <div class="notification-time">${relTime(n.created_at)}</div>
    </div>`).join('');
  container.querySelectorAll('.notification-item').forEach(item => {
    item.addEventListener('click', async (e) => {
      e.stopPropagation();
      await api(`${MOB_BASE}/notifications/${item.dataset.id}/read`, { method: 'POST' });
      const n = notifications.find(n => String(n.id) === item.dataset.id);
      if (n) n.is_read = true;
      renderNotifications(); updateNotificationBadge();
    });
  });
}
async function loadNotifications() {
  try {
    const res = await api(`${MOB_BASE}/notifications`);
    if (res.success) {
      let list = res.data || [];
      // File Request notifications are for SCB Monitors only on the app.
      if (!IS_SCB_MONITOR) list = list.filter(n => n.type !== 'file_search_request');
      notifications = list;
      renderNotifications(); updateNotificationBadge();
    }
  } catch(e) {}
}

// ─── Utilities ────────────────────────────────────────────────────────────
function esc(s) { return String(s||'').replace(/[&<>]/g,m=>m==='&'?'&amp;':m==='<'?'&lt;':'&gt;'); }
function getPrioCls(p) { p=(p||'').toLowerCase(); return p==='high'?'priority-high':p==='medium'?'priority-medium':'priority-low'; }
function relTime(d) {
  if (!d) return '';
  const diff = Math.floor((new Date()-new Date(d))/60000);
  if (diff<1) return 'Just now'; if (diff<60) return `${diff}m ago`;
  if (diff<1440) return `${Math.floor(diff/60)}h ago`; return `${Math.floor(diff/1440)}d ago`;
}
function genTrackingId() { return 'TRK-'+Math.random().toString(36).substring(2,8).toUpperCase(); }
function genLogId() { const d=new Date(); return `LOG-${d.toISOString().replace(/[-:T]/g,'').split('.')[0]}-${Math.floor(Math.random()*999)}`; }
function fillDateTime() {
  const now = new Date();
  const t = now.toTimeString().substring(0,5);
  const dt= now.toISOString().split('T')[0];
  document.getElementById('logInTime').value  = t;
  document.getElementById('logInDate').value  = dt;
  document.getElementById('logOutTime').value = t;
  document.getElementById('logOutDate').value = dt;
}

// ─── Dashboard ────────────────────────────────────────────────────────────
function chartColors() {
  const dark = document.documentElement.getAttribute('data-theme') === 'dark';
  return {
    grid: dark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.05)',
    tick: dark ? '#9aa0b6' : '#6b7185',
  };
}
async function renderDashboard() {
  const hr = new Date().getHours();
  const greet = hr<12?'Good Morning':hr<18?'Good Afternoon':'Good Evening';
  document.getElementById('greetingMsg').textContent = `${greet}, ${CURRENT_USER.name.split(' ')[0]}!`;
  try {
    const res = await api(`${API_BASE}/dashboard/stats`);
    if (!res || res._authError || (!res.success && !res.data)) return;
    const s = res.data;
    const pb = s.priority_breakdown||{}, h=pb.high||0, m=pb.medium||0, l=pb.low||0, tot=h+m+l||1;
    document.getElementById('priorityDistribution').innerHTML = `
        <div class="priority-item"><span class="priority-label">High</span><div class="priority-bar"><div class="priority-bar-fill fill-high" style="width:${(h/tot)*100}%"></div></div><span class="priority-value">${h}</span></div>
        <div class="priority-item"><span class="priority-label">Medium</span><div class="priority-bar"><div class="priority-bar-fill fill-medium" style="width:${(m/tot)*100}%"></div></div><span class="priority-value">${m}</span></div>
        <div class="priority-item"><span class="priority-label">Low</span><div class="priority-bar"><div class="priority-bar-fill fill-low" style="width:${(l/tot)*100}%"></div></div><span class="priority-value">${l}</span></div>`;
    const recent = s.recent_activity||[];
    document.getElementById('recentActivityList').innerHTML = recent.length
      ? recent.map(f=>`<div class="activity-item"><div class="activity-icon" style="background:${f.status==='ACTIVE'?'rgba(16,185,129,.14)':'rgba(245,158,11,.14)'};color:${f.status==='ACTIVE'?'#10b981':'#f59e0b'};"><i class="fas ${f.status==='ACTIVE'?'fa-play-circle':'fa-clock'}"></i></div><div class="activity-content"><div class="activity-title">${esc(f.file_title)}</div><div class="activity-time">${relTime(f.updated_at)}</div></div></div>`).join('')
      : '<p style="text-align:center;color:var(--faint);font-size:12px;padding:20px;">No recent activity</p>';
    const wl = s.weekly_labels||['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
    const wd = s.weekly_activity||[0,0,0,0,0,0,0];
    const cc = chartColors();
    if (weeklyChart) weeklyChart.destroy();
    weeklyChart = new Chart(document.getElementById('weeklyChart').getContext('2d'), { type:'line', data:{ labels:wl, datasets:[{ label:'Files', data:wd, borderColor:'#8b5cf6', backgroundColor:'rgba(139,92,246,0.12)', borderWidth:2.5, fill:true, tension:0.4, pointBackgroundColor:'#8b5cf6', pointRadius:4 }]}, options:{ responsive:true, maintainAspectRatio:true, plugins:{ legend:{ display:false }}, scales:{ y:{ beginAtZero:true, grid:{ color:cc.grid }, ticks:{ color:cc.tick, font:{ size:9 }}}, x:{ grid:{ display:false }, ticks:{ color:cc.tick, font:{ size:9 }}}}}});
  } catch(e) { console.error(e); }
}

// ─── Files ────────────────────────────────────────────────────────────────
async function loadFiles() {
  const search  = (document.getElementById('searchInput')?.value||'').trim();
  const status  = document.getElementById('statusFilter')?.value||'all';
  const prio    = document.querySelector('.priority-filter-btn.active')?.dataset.priority||'all';
  const params  = new URLSearchParams({ per_page: 50 });
  if (search) params.set('search', search);
  if (prio !== 'all') params.set('priority', prio);
  if (status === 'my-files')  params.set('handler_id',        CURRENT_USER.id);
  if (status === 'awaiting')  params.set('assignment_status', 'PENDING');
  if (status === 'completed') params.set('status',            'COMPLETED');
  try {
    const res = await api(`${API_BASE}?${params}`);
    filesDB = (res.data?.data || res.data || []).map(normalizeTracker);
  } catch(e) { console.error(e); }
  renderFileList();
}
function normalizeTracker(t) {
  const ml = Array.isArray(t.movement_log) ? t.movement_log : (t.movement_log ? JSON.parse(t.movement_log) : []);
  return {
    id: t.id, fileNumber: t.file_number||'—', fileName: t.file_title||'—',
    trackingId: t.tracking_id||'—', status: (t.status||'').toLowerCase(),
    currentLocation: t.current_office_name||t.receiving_office_name||'—',
    priority: (t.priority||'low').toLowerCase(),
    handler: t.receiving_officer_name||'—',
    handlerId: String(t.receiving_officer_id||''),
    createdAt: t.created_at,
    movements: ml.map(m=>({
      logId: m.log_id||m.logId||'—', office: m.office_name||m.office||'—',
      receivingOfficer: m.receiving_officer_name||m.receivingOfficer||'—',
      logInTime: m.log_in_time||m.logInTime||'', logInDate: m.log_in_date||m.logInDate||'',
      logOutTime: m.log_out_time||m.logOutTime||'', logOutDate: m.log_out_date||m.logOutDate||'',
      status: m.status||'active',
    }))
  };
}
function renderFileList() {
  // Guard: the file-list UI is not part of this screen set; skip if absent.
  if (!document.getElementById('fileListContainer')) return;
  const search = (document.getElementById('searchInput')?.value||'').toLowerCase();
  const prio   = document.querySelector('.priority-filter-btn.active')?.dataset.priority||'all';
  let filtered = filesDB;
  if (search) filtered = filtered.filter(f=>(f.fileName||'').toLowerCase().includes(search)||(f.fileNumber||'').toLowerCase().includes(search));
  if (prio !== 'all') filtered = filtered.filter(f=>f.priority===prio.toLowerCase());
  const myId = String(CURRENT_USER.id);
  document.getElementById('myFilesCount').textContent  = filesDB.filter(f=>f.handlerId===myId).length+' Mine';
  document.getElementById('awaitingCount').textContent = filesDB.filter(f=>f.status==='pending_acceptance'||f.status==='pending').length+' Awaiting';
  document.getElementById('othersCount').textContent   = filesDB.filter(f=>f.handlerId!==myId&&f.status!=='completed').length+' Others';
  document.getElementById('completedCount').textContent= filesDB.filter(f=>f.status==='completed').length+' Done';
  const container = document.getElementById('fileListContainer');
  if (!filtered.length) { container.innerHTML = '<div class="card" style="padding:40px;text-align:center;color:var(--faint);"><i class="fas fa-folder-open fa-2x"></i><p style="margin-top:12px;">No files found</p></div>'; return; }
  container.innerHTML = filtered.map(file => `
    <div class="tracker-card">
      <h3>${esc(file.fileName)}</h3>
      <p>${esc(file.fileNumber)} · ${esc(file.currentLocation)}</p>
    </div>`).join('');
}

// ─── Create file ──────────────────────────────────────────────────────────
async function createNewFile() {
  const fileNumber   = document.getElementById('createFileNumber').value.trim();
  const fileName     = document.getElementById('createFileName').value.trim();
  const status       = document.getElementById('fileStatus').value;
  const priority     = document.querySelector('.prio-chip.active')?.dataset.prio||'MEDIUM';
  const notes        = document.getElementById('createNotes').value.trim();
  const department   = document.getElementById('department').value;
  const originSel    = document.getElementById('originOffice');
  const recvOfficeSel= document.getElementById('receivingOffice');
  const recvOfficerSel=document.getElementById('receivingOfficer');
  const logInTime    = document.getElementById('logInTime').value;
  const logInDate    = document.getElementById('logInDate').value;
  const logOutTime   = document.getElementById('logOutTime').value;
  const logOutDate   = document.getElementById('logOutDate').value;

  if (!fileName)                       { toast('File Title is required','error'); return; }
  if (!department)                     { toast('Please select a department','error'); return; }
  if (!originSel.value)                { toast('Please select origin office','error'); return; }
  if (!recvOfficeSel.value)            { toast('Please select receiving office','error'); return; }
  if (!recvOfficerSel.value)           { toast('Please select receiving officer','error'); return; }

  const originCode = originSel.value;
  const originName = originSel.options[originSel.selectedIndex]?.dataset.name || originSel.options[originSel.selectedIndex]?.text || originCode;
  const recvCode   = recvOfficeSel.value;
  const recvName   = recvOfficeSel.options[recvOfficeSel.selectedIndex]?.dataset.name || recvOfficeSel.options[recvOfficeSel.selectedIndex]?.text || recvCode;
  const recvOfficerId   = recvOfficerSel.value;
  const recvOfficerName = recvOfficerSel.options[recvOfficerSel.selectedIndex]?.dataset.name || '';

  const btn = document.getElementById('submitCreateBtn');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';

  try {
    const payload = {
      file_number: fileNumber || null,
      file_title: fileName,
      priority: priority.toUpperCase(),
      status: status === 'Canceled' ? 'Canceled' : (status === 'Log-in' ? null : null),
      department,
      notes,
      origin_office_code: originCode,
      origin_office_name: originName,
      receiving_office_code: recvCode,
      receiving_office_name: recvName,
      receiving_officer_id: recvOfficerId,
      receiving_officer_name: recvOfficerName,
      movement_log: [{
        office_code: originCode,
        office_name: originName,
        log_in_time:  logInTime,
        log_in_date:  logInDate,
        log_out_time: logOutTime,
        log_out_date: logOutDate,
        notes: notes,
      }],
    };
    const res = await api(API_BASE, { method: 'POST', body: JSON.stringify(payload) });
    if (res.success || res.data?.id) {
      toast(`✅ File created! Tracking: ${res.data?.tracking_id||'—'}`);
      // Reset form
      ['createFileNumber','createFileName','createNotes'].forEach(id => { document.getElementById(id).value=''; });
      document.getElementById('trackingIdField').value = genTrackingId();
      document.getElementById('logIdField').value      = genLogId();
      fillDateTime();
      document.getElementById('department').value = '';
      document.getElementById('originOffice').value = '';
      document.getElementById('receivingOffice').value = '';
      document.getElementById('receivingOfficer').value = '';
      setActiveTab('files');
    } else {
      const errs = res.errors ? Object.values(res.errors).flat().join(', ') : (res.message||'Unknown error');
      toast(errs, 'error');
    }
  } catch(e) { toast('Error: '+e.message, 'error'); }

  btn.disabled = false; btn.innerHTML = '<i class="fas fa-file-circle-plus"></i> Log a File';
}

// ─── Scanner ──────────────────────────────────────────────────────────────
async function startScanner() {
  // Camera requires HTTPS in production
  if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
    updateScanStatus('Camera requires HTTPS. Contact your administrator to enable SSL.', 'error');
    return;
  }
  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    updateScanStatus('Camera not supported in this browser or requires HTTPS.', 'error');
    return;
  }
  try {
    const devices = await Html5Qrcode.getCameras();
    if (!devices.length) { updateScanStatus('No camera found on this device.', 'error'); return; }
    html5QrCode = new Html5Qrcode('qr-reader');
    await html5QrCode.start(devices[0].id, { fps:10, qrbox:250 }, onScanSuccess, ()=>{});
    isScannerRunning = true;
    updateScanStatus('Camera ready — scanning', 'success');
    document.getElementById('startCameraBtn').innerHTML = '<i class="fas fa-sync"></i> Restart';
  } catch(e) {
    const msg = e instanceof Error ? e.message : (typeof e === 'string' ? e : (e?.name || JSON.stringify(e)));
    if (msg && (msg.includes('NotAllowed') || msg.includes('Permission'))) {
      updateScanStatus('Camera permission denied. Please allow camera access in your browser.', 'error');
    } else if (msg && msg.includes('NotFound')) {
      updateScanStatus('No camera found on this device.', 'error');
    } else {
      updateScanStatus('Camera error: ' + (msg || 'Could not access camera. HTTPS may be required.'), 'error');
    }
  }
}
async function stopScanner() {
  if (html5QrCode && isScannerRunning) {
    await html5QrCode.stop(); isScannerRunning = false;
    updateScanStatus('Scanner stopped','info');
    document.getElementById('startCameraBtn').innerHTML = '<i class="fas fa-play"></i> Start';
  }
}
async function toggleFlash() {
  if (!html5QrCode || !isScannerRunning) { updateScanStatus('Start camera first','info'); return; }
  flashEnabled = !flashEnabled;
  try { await html5QrCode.applyVideoConstraints({ advanced:[{ torch:flashEnabled }] }); } catch(e) {}
  document.getElementById('flashToggleBtn').classList.toggle('flash-on', flashEnabled);
  document.getElementById('flashToggleBtn').innerHTML = flashEnabled ? '<i class="fas fa-bolt"></i> Flash ON' : '<i class="fas fa-bolt"></i> Flash';
}
async function onScanSuccess(text) {
  const trimmed = text.trim();
  updateScanStatus(`Scanned: ${trimmed.substring(0,35)}`,'success');
  if (isScannerRunning && html5QrCode) html5QrCode.pause(true);
  try {
    const res = await api(`${MOB_BASE}/tracker/scan-and-log`, {
      method:'POST',
      body: JSON.stringify({ qr_code:trimmed, office_code:'MOB', office_name:'Mobile Scan', notes:'Scanned via mobile app' })
    });
    if (res.success) {
      const t = res.tracker;
      toast(`✅ ${t.file_title} — ${t.current_office||'logged'}`);
      await loadFiles();
    } else {
      toast(res.message||'File not found','error');
    }
  } catch(e) { toast('Scan error: '+e.message,'error'); }
  setTimeout(()=>{ if(isScannerRunning&&html5QrCode) html5QrCode.resume(); }, 2500);
}
function updateScanStatus(msg, type) {
  const el = document.getElementById('scannerStatus');
  const icon = type==='success'?'<i class="fas fa-check-circle" style="color:#10b981;"></i>':type==='error'?'<i class="fas fa-exclamation-triangle" style="color:#ef4444;"></i>':'<i class="fas fa-camera"></i>';
  el.innerHTML = `${icon} <span>${msg}</span>`;
}

async function manualLookup() {
  const input = document.getElementById('manualScanInput');
  const resultEl = document.getElementById('manualScanResult');
  const val = input.value.trim();
  if (!val) { resultEl.innerHTML = '<p style="color:#ef4444;font-size:12px;margin-top:6px;">Please enter a Tracking ID or File Number.</p>'; return; }

  const btn = document.getElementById('manualScanBtn');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

  try {
    const res = await api(`${API_BASE}/track/${encodeURIComponent(val)}`);
    if (res && res.success && res.data) {
      const t = res.data;
      resultEl.innerHTML = `
        <div class="result-card" style="padding:14px;margin-top:10px;">
          <div style="font-weight:700;font-size:14px;">${esc(t.file_title||'—')}</div>
          <div style="font-size:12px;color:var(--muted);margin-top:6px;">
            <span style="margin-right:12px;"><i class="fas fa-hashtag" style="color:var(--primary);"></i> ${esc(t.file_number||'—')}</span>
            <span><i class="fas fa-map-marker-alt" style="color:var(--primary);"></i> ${esc(t.current_office_name||t.receiving_office_name||'—')}</span>
          </div>
          <div style="font-size:11px;color:var(--faint);margin-top:6px;">
            Tracking: ${esc(t.tracking_id||'—')} &nbsp;|&nbsp; Priority: ${esc(t.priority||'—')} &nbsp;|&nbsp; Status: ${esc(t.status||'—')}
          </div>
        </div>`;
      input.value = '';
      await loadFiles();
    } else {
      resultEl.innerHTML = `<p style="color:#ef4444;font-size:12px;margin-top:6px;"><i class="fas fa-times-circle"></i> No file found for "<strong>${esc(val)}</strong>".</p>`;
    }
  } catch(e) {
    resultEl.innerHTML = `<p style="color:#ef4444;font-size:12px;margin-top:6px;">Error: ${esc(e.message)}</p>`;
  }

  btn.disabled = false; btn.innerHTML = '<i class="fas fa-search"></i> Look up';
}

// ─── Tab router ───────────────────────────────────────────────────────────
function setActiveTab(tabId) {
  if (tabId !== 'scanner') stopScanner();
  document.querySelectorAll('.screen').forEach(s=>s.classList.remove('active'));
  document.getElementById(`${tabId}-screen`).classList.add('active');
  document.querySelectorAll('.tab-item').forEach(t=>t.classList.remove('active'));
  document.querySelector(`.tab-item[data-tab="${tabId}"]`)?.classList.add('active');
  if (tabId==='dashboard') renderDashboard();
  if (tabId==='files')     $('#fileSearchSelect').select2('open');
  if (tabId==='scanner')   updateScanStatus('Click Start to begin scanning','info');
  if (tabId==='create')    initCreateForm();
  if (tabId==='requests')  setFrView(frView);
}

// ─── Log a File: init defaults when screen opens ───────────────────────────
function initCreateForm() {
  fillDateTime();
  const tid = document.getElementById('trackingIdField');
  const lid = document.getElementById('logIdField');
  if (tid && !tid.value) tid.value = genTrackingId();
  if (lid && !lid.value) lid.value = genLogId();
}

// ─── File Search (5-outcome locator) ───────────────────────────────────────
async function searchFile() {
  const sel    = document.getElementById('fileSearchSelect');
  const result = document.getElementById('fileSearchResult');
  const val    = (sel.value || '').trim();
  if (!val) { result.innerHTML = '<p style="color:#ef4444;font-size:12px;margin-top:4px;">Please select a file number.</p>'; return; }

  const btn = document.getElementById('fileSearchBtn');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  result.innerHTML = '';

  try {
    const res = await api(`${MOB_BASE}/files/search?q=${encodeURIComponent(val)}`);
    if (res && res.success && res.data) {
      const d = res.data;
      const meta = LOC_STATUS_META[d.status] || { label:d.status, color:'#6b7185', icon:'fa-file' };
      const rowsHtml = [
        ['Registry', d.registry],
        ['Current Location', d.current_location],
        ['Rack / Shelf', d.rack_shelf],
      ].filter(r=>r[1]).map(r=>`<div style="display:flex;justify-content:space-between;gap:12px;padding:7px 0;border-bottom:1px solid var(--border);"><span style="font-size:11px;color:var(--muted);">${esc(r[0])}</span><span style="font-size:13px;font-weight:600;text-align:right;">${esc(r[1])}</span></div>`).join('');

      // Mobile File Search is a read-only locator. But if this file has an OPEN
      // File Request waiting on this SCB Monitor, offer a Found/Not-Found shortcut.
      let frShortcut = '';
      if (IS_SCB_MONITOR) {
        const norm = s => String(s || '').trim().toUpperCase();
        const fr = (await getOpenFileRequests()).find(r => norm(r.file_number) === norm(d.file_number));
        if (fr) {
          frShortcut = `
            <div style="margin-top:12px;background:var(--primary-soft);border-radius:12px;padding:10px 12px;" data-fr="${fr.id}">
              <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--primary);">Open File Request · ${esc(fr.request_no)}</div>
              <div style="display:flex;gap:8px;margin-top:10px;align-items:stretch;">
                <button class="btn" style="flex:1;width:auto;padding:11px;font-size:13px;box-shadow:none;background:linear-gradient(135deg,#10b981,#059669);" onclick="respondFrFromSearch(${fr.id}, 'found', this)"><i class="fas fa-check"></i> Found</button>
                <button class="btn ghost-btn" style="flex:1;width:auto;padding:11px;font-size:13px;box-shadow:none;" onclick="respondFrFromSearch(${fr.id}, 'not_found', this)"><i class="fas fa-xmark"></i> Not&nbsp;Found</button>
              </div>
            </div>`;
        }
      }

      result.innerHTML = `
        <div class="result-card">
          <div style="background:var(--surface-2);padding:14px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;gap:10px;">
            <div>
              <div style="font-size:15px;font-weight:800;">${esc(d.file_number)}</div>
              <div style="font-size:11px;color:var(--muted);margin-top:3px;">${esc(d.file_title||'—')}</div>
            </div>
            <span style="white-space:nowrap;font-size:11px;font-weight:700;color:${meta.color};background:${meta.color}1a;border:1px solid ${meta.color}55;padding:5px 10px;border-radius:30px;"><i class="fas ${meta.icon}" style="margin-right:4px;"></i>${esc(meta.label)}</span>
          </div>
          <div style="padding:12px 16px;">
            ${rowsHtml}
            ${frShortcut}
          </div>
        </div>`;
    } else {
      result.innerHTML = `<p style="color:#ef4444;font-size:12px;margin-top:6px;"><i class="fas fa-times-circle"></i> Could not resolve "<strong>${esc(val)}</strong>".</p>`;
    }
  } catch(e) {
    result.innerHTML = `<p style="color:#ef4444;font-size:12px;margin-top:6px;">Error: ${esc(e.message)}</p>`;
  }

  btn.disabled = false; btn.innerHTML = '<i class="fas fa-search"></i>';
}

// Raise an FR straight from a Pool-Office search result (web → SCB pipeline mirror).
async function createFrFromSearch(fileNumber, btn) {
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';
  try {
    const res = await api(`{{ route('create-file-tracker.file-request') }}`, {
      method:'POST',
      body: JSON.stringify({ file_number: fileNumber }),
    });
    if (res.success) { toast(`File Request ${res.data?.request_no||''} created`); if (IS_SCB_MONITOR) loadFileRequests(); }
    else toast(res.message||'Could not create request','error');
  } catch(e) { toast('Error: '+e.message,'error'); }
  btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> Raise File Request';
}

// ─── SCB Monitor: FSR view toggle (Open inbox / full log) ──────────────────
const FSR_STATUS_META = {
  PENDING:   { label:'Pending',   bg:'#fef3c7', fg:'#92400e' },
  SEARCHING: { label:'Searching', bg:'#dbeafe', fg:'#1e40af' },
  FOUND:     { label:'Found',     bg:'#dcfce7', fg:'#166534' },
  NOT_FOUND: { label:'Not Found', bg:'#fee2e2', fg:'#991b1b' },
  CLOSED:    { label:'Closed',    bg:'#e5e7eb', fg:'#374151' },
};

let frView = 'open';
function setFrView(view) {
  frView = view;
  document.querySelectorAll('.fr-seg-btn').forEach(b => b.classList.toggle('active', b.dataset.frview === view));
  const open = document.getElementById('frListContainer');
  const log  = document.getElementById('frLogContainer');
  if (open) open.style.display = view === 'open' ? 'block' : 'none';
  if (log)  log.style.display  = view === 'log'  ? 'block' : 'none';
  if (view === 'open') loadFileRequests();
  else loadFsrLog();
}

// Full FSR History — every request routed to this SCB Monitor, by whom, where, status.
async function loadFsrLog() {
  const container = document.getElementById('frLogContainer');
  if (!container) return;
  container.innerHTML = '<div class="card" style="padding:30px;text-align:center;color:var(--faint);"><i class="fas fa-spinner fa-spin fa-lg"></i></div>';
  try {
    const res = await api(`${MOB_BASE}/file-requests/log`);
    const list = (res && res.success) ? (res.data || []) : [];
    if (!list.length) {
      container.innerHTML = '<div class="card" style="padding:40px;text-align:center;color:var(--faint);"><i class="fas fa-clipboard-list fa-2x"></i><p style="margin-top:12px;">No file requests yet</p></div>';
      return;
    }
    container.innerHTML = list.map(fr => {
      const meta = FSR_STATUS_META[fr.status] || FSR_STATUS_META.PENDING;
      return `
      <div class="result-card" style="margin-bottom:12px;">
        <div style="padding:14px 16px;">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
            <div style="font-size:15px;font-weight:800;">${esc(fr.file_number)}</div>
            <span style="font-size:9px;font-weight:800;padding:3px 9px;border-radius:30px;background:${meta.bg};color:${meta.fg};">${meta.label}</span>
          </div>
          <div style="font-size:12px;color:var(--muted);margin-top:4px;">${esc(fr.file_title||'—')}</div>
          <div style="font-size:11px;color:var(--faint);margin-top:8px;display:flex;flex-direction:column;gap:5px;">
            <span><i class="fas fa-hashtag" style="width:14px;color:var(--primary);"></i> ${esc(fr.request_no)}</span>
            <span><i class="fas ${fr.is_dfr ? 'fa-file-lines' : (fr.is_blind ? 'fa-eye-slash' : 'fa-folder-open')}" style="width:14px;color:var(--primary);"></i> ${esc(fr.request_type || 'Open Request')}</span>
            <span><i class="fas fa-user" style="width:14px;color:var(--primary);"></i> By ${esc(fr.requester||'—')}</span>
            ${fr.current_location ? `<span><i class="fas fa-map-marker-alt" style="width:14px;color:var(--primary);"></i> ${esc(fr.current_location)}</span>` : ''}
            <span><i class="fas fa-clock" style="width:14px;color:var(--primary);"></i> Sent ${esc(fr.created_at||'—')}</span>
            ${fr.responded_at ? `<span><i class="fas fa-reply" style="width:14px;color:var(--primary);"></i> ${meta.label} ${esc(fr.responded_at)}${fr.responder ? ' · '+esc(fr.responder) : ''}</span>` : ''}
            ${fr.feedback_note ? `<span style="font-style:italic;">"${esc(fr.feedback_note)}"</span>` : ''}
          </div>
        </div>
      </div>`;
    }).join('');
  } catch(e) {
    container.innerHTML = `<p style="color:#ef4444;font-size:12px;">Error loading FSR History: ${esc(e.message)}</p>`;
  }
}

// ─── SCB Monitor: File Requests inbox ──────────────────────────────────────
// Cached open-FR list so File Search can offer a Found/Not-Found shortcut.
let openFileRequests = null;
async function getOpenFileRequests() {
  if (openFileRequests !== null) return openFileRequests;
  try {
    const res = await api(`${MOB_BASE}/file-requests`);
    openFileRequests = (res && res.success) ? (res.data || []) : [];
  } catch (e) { openFileRequests = []; }
  return openFileRequests;
}

async function loadFileRequests() {
  const container = document.getElementById('frListContainer');
  if (!container) return;
  container.innerHTML = '<div class="card" style="padding:30px;text-align:center;color:var(--faint);"><i class="fas fa-spinner fa-spin fa-lg"></i></div>';
  try {
    const res = await api(`${MOB_BASE}/file-requests`);
    const list = (res && res.success) ? (res.data || []) : [];
    openFileRequests = list;  // keep the search shortcut in sync
    if (!list.length) {
      container.innerHTML = '<div class="card" style="padding:40px;text-align:center;color:var(--faint);"><i class="fas fa-clipboard-check fa-2x"></i><p style="margin-top:12px;">No open file requests</p></div>';
      return;
    }
    container.innerHTML = list.map(fr => `
      <div class="result-card" style="margin-bottom:12px;" data-fr="${fr.id}">
        <div style="padding:14px 16px;">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
            <div style="font-size:15px;font-weight:800;">${esc(fr.file_number)}</div>
            <span style="font-size:10px;font-weight:700;color:var(--primary);background:var(--primary-soft);padding:3px 9px;border-radius:30px;">${esc(fr.request_no)}</span>
          </div>
          <div style="font-size:12px;color:var(--muted);margin-top:4px;">${esc(fr.file_title||'—')}</div>
          <div style="margin-top:6px;"><span style="display:inline-flex;align-items:center;gap:4px;font-size:9.5px;font-weight:800;padding:2px 8px;border-radius:30px;background:${fr.is_dfr ? '#e5e7eb' : (fr.is_blind ? '#ede9fe' : '#e0f2fe')};color:${fr.is_dfr ? '#374151' : (fr.is_blind ? '#6d28d9' : '#0369a1')};"><i class="fas ${fr.is_dfr ? 'fa-file-lines' : (fr.is_blind ? 'fa-eye-slash' : 'fa-folder-open')}"></i> ${esc(fr.request_type || 'Open Request')}</span></div>
          ${fr.current_location ? `<div style="font-size:11px;color:var(--faint);margin-top:6px;"><i class="fas fa-map-marker-alt" style="margin-right:4px;color:var(--primary);"></i>${esc(fr.current_location)}</div>` : ''}
          <div style="display:flex;gap:8px;margin-top:14px;align-items:stretch;">
            <button class="btn" style="flex:1;width:auto;min-width:0;padding:12px;font-size:13px;box-shadow:none;background:linear-gradient(135deg,#10b981,#059669);" onclick="respondFr(${fr.id}, 'found', this)"><i class="fas fa-check"></i> Found</button>
            <button class="btn ghost-btn" style="flex:1;width:auto;min-width:0;padding:12px;font-size:13px;box-shadow:none;" onclick="respondFr(${fr.id}, 'not_found', this)"><i class="fas fa-xmark"></i> Not&nbsp;Found</button>
            <button class="btn" style="flex:0 0 auto;width:auto;padding:12px 15px;box-shadow:none;background:#fee2e2;color:#991b1b;" onclick="deleteFr(${fr.id}, this)" title="Delete request"><i class="fas fa-trash"></i></button>
          </div>
        </div>
      </div>`).join('');
  } catch(e) {
    container.innerHTML = `<p style="color:#ef4444;font-size:12px;">Error loading requests: ${esc(e.message)}</p>`;
  }
}

async function respondFr(id, result, btn) {
  let note = '';
  const card = document.querySelector(`[data-fr="${id}"]`);
  if (card) card.querySelectorAll('button').forEach(b=>b.disabled=true);
  if (btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  try {
    const res = await api(`${MOB_BASE}/file-requests/${id}/respond`, {
      method:'POST',
      body: JSON.stringify({ result, note }),
    });
    if (res && res.success) {
      const sc = res.data?.second_check;
      if (result === 'found') toast('Marked as Found ✅');
      else if (sc === 'print_missing_slip') toast('Not found — print Missing File slip', 'error');
      else if (sc === 'do_second_physical_search') toast('Not found — file is scanned; do a 2nd physical search', 'error');
      else toast('Feedback recorded');
      loadFileRequests();
    } else {
      toast(res.message || 'Could not record feedback', 'error');
      if (card) card.querySelectorAll('button').forEach(b=>b.disabled=false);
    }
  } catch(e) {
    toast('Error: '+e.message, 'error');
    if (card) card.querySelectorAll('button').forEach(b=>b.disabled=false);
  }
}

// Respond to an open File Request straight from the File Search result.
async function respondFrFromSearch(id, result, btn) {
  const wrap = btn.closest('[data-fr]');
  if (wrap) wrap.querySelectorAll('button').forEach(b=>b.disabled=true);
  if (btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  try {
    const res = await api(`${MOB_BASE}/file-requests/${id}/respond`, {
      method:'POST',
      body: JSON.stringify({ result, note:'' }),
    });
    if (res && res.success) {
      openFileRequests = null;  // invalidate cache; this FR is no longer open
      const found = result === 'found';
      toast(found ? 'Marked as Found ✅' : 'Marked as Not Found');
      if (wrap) {
        wrap.innerHTML = `<div style="font-size:12px;font-weight:700;color:${found ? '#10b981' : '#ef4444'};">
          <i class="fas fa-${found ? 'check-circle' : 'times-circle'}"></i> Response recorded: ${found ? 'Found' : 'Not Found'}</div>`;
      }
    } else {
      toast(res.message || 'Could not record feedback', 'error');
      if (wrap) wrap.querySelectorAll('button').forEach(b=>b.disabled=false);
    }
  } catch(e) {
    toast('Error: '+e.message, 'error');
    if (wrap) wrap.querySelectorAll('button').forEach(b=>b.disabled=false);
  }
}

// Delete a File Search Request from the open inbox.
async function deleteFr(id, btn) {
  if (!confirm('Delete this file request? This cannot be undone.')) return;
  const card = document.querySelector(`[data-fr="${id}"]`);
  if (card) card.querySelectorAll('button').forEach(b=>b.disabled=true);
  if (btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  try {
    const res = await api(`${MOB_BASE}/file-requests/${id}`, { method:'DELETE' });
    if (res && res.success) {
      openFileRequests = null;  // invalidate search shortcut cache
      toast('File request deleted');
      if (card) card.remove();
    } else {
      toast(res.message || 'Could not delete request', 'error');
      if (card) card.querySelectorAll('button').forEach(b=>b.disabled=false);
      if (btn) btn.innerHTML = '<i class="fas fa-trash"></i>';
    }
  } catch(e) {
    toast('Error: '+e.message, 'error');
    if (card) card.querySelectorAll('button').forEach(b=>b.disabled=false);
    if (btn) btn.innerHTML = '<i class="fas fa-trash"></i>';
  }
}

// ─── Event listeners ──────────────────────────────────────────────────────
document.getElementById('fileSearchBtn')?.addEventListener('click', searchFile);
// File-number Select2 search — selecting a file auto-runs the movement lookup.
$('#fileSearchSelect').select2({
  placeholder: 'Search file number…',
  allowClear: true,
  minimumInputLength: 2,
  width: '100%',
  ajax: {
    url: '{{ url("/digital-request/file-numbers") }}',
    dataType: 'json',
    delay: 250,
    data: params => ({ q: params.term }),
    processResults: data => ({ results: data.results || [] }),
    cache: true,
  },
});
$('#fileSearchSelect').on('select2:select', searchFile);

document.getElementById('startCameraBtn')?.addEventListener('click', startScanner);
document.getElementById('stopCameraBtn')?.addEventListener('click', stopScanner);
document.getElementById('flashToggleBtn')?.addEventListener('click', toggleFlash);
document.getElementById('manualScanBtn')?.addEventListener('click', manualLookup);
document.getElementById('manualScanInput')?.addEventListener('keydown', e => { if (e.key === 'Enter') manualLookup(); });
document.getElementById('notificationBell')?.addEventListener('click', function(e) {
  e.stopPropagation(); document.getElementById('notificationPanel').classList.toggle('show');
});
document.addEventListener('click', ()=>document.getElementById('notificationPanel')?.classList.remove('show'));
document.getElementById('markAllRead')?.addEventListener('click', async function(e) {
  e.stopPropagation();
  await api(`${MOB_BASE}/notifications/mark-all-read`, { method:'POST' });
  notifications.forEach(n=>n.is_read=true); renderNotifications(); updateNotificationBadge();
});
document.querySelectorAll('.tab-item[data-tab]').forEach(tab=>{
  const t = tab.getAttribute('data-tab');
  if (t && t !== 'dfr') tab.addEventListener('click', ()=>setActiveTab(t));
});
document.getElementById('frRefreshBtn')?.addEventListener('click', () => (frView === 'log' ? loadFsrLog() : loadFileRequests()));
// Priority chip selector (Log a File)
document.querySelectorAll('#create-screen .prio-chip').forEach(chip=>{
  chip.addEventListener('click', ()=>{
    document.querySelectorAll('#create-screen .prio-chip').forEach(c=>c.classList.remove('active'));
    chip.classList.add('active');
  });
});

// ─── Boot ─────────────────────────────────────────────────────────────────
applyTheme(document.documentElement.getAttribute('data-theme') || 'light');
setActiveTab('dashboard');
loadNotifications();
</script>
</body>
</html>

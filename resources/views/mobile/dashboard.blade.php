<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
  <meta name="theme-color" content="#fefce8">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>KLAES File Tracker</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background-color: #fefce8; color: #4b5563; height: 100vh; overflow: hidden; position: fixed; width: 100%; }
    .glass-card { background: rgba(255,255,255,0.85); backdrop-filter: blur(12px); border-radius: 24px; border: 1px solid rgba(255,255,255,0.6); }
    .glass-card-solid { background: #ffffff; border-radius: 24px; border: 1px solid #f3f4f6; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
    #app { display: flex; flex-direction: column; height: 100vh; width: 100%; position: relative; overflow: hidden; }
    .screen { flex: 1; overflow-y: auto; padding: 20px 16px 80px 16px; display: none; position: relative; }
    .screen.active { display: block; }
    .screen::-webkit-scrollbar { width: 3px; }
    .screen::-webkit-scrollbar-thumb { background: #a16207; border-radius: 10px; }

    .tab-bar { position: fixed; bottom: 10px; left: 12px; right: 12px; background: rgba(255,255,255,0.96); backdrop-filter: blur(20px); border-radius: 40px; display: flex; justify-content: space-around; align-items: center; padding: 6px 10px; border: 1px solid rgba(255,255,255,0.8); box-shadow: 0 4px 12px rgba(0,0,0,0.1); z-index: 100; }
    .tab-item { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; background: transparent; border: none; color: #9ca3af; font-size: 10px; font-weight: 500; padding: 6px 8px; border-radius: 30px; cursor: pointer; transition: all 0.2s; flex: 1; }
    .tab-item i { font-size: 20px; margin-bottom: 2px; }
    .tab-item.active { color: #a16207; background: rgba(161,98,7,0.12); }

    .dashboard-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
    .page-header { margin-bottom: 0; }
    .page-header h1 { font-size: 26px; font-weight: 700; color: #78350f; }
    .greeting-text { font-size: 13px; color: #6b7280; margin-top: 4px; }
    .notification-wrapper { position: relative; }
    .notification-btn { background: white; border: 1px solid #f3f4f6; border-radius: 40px; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; }
    .notification-btn i { font-size: 20px; color: #a16207; }
    .notification-badge { position: absolute; top: -5px; right: -5px; background: #ef4444; color: white; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 30px; min-width: 18px; text-align: center; display: none; }
    .notification-panel { position: absolute; top: 55px; right: 0; width: 300px; background: white; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); z-index: 200; display: none; border: 1px solid #f3f4f6; }
    .notification-panel.show { display: block; }
    .notification-header { padding: 14px 16px; border-bottom: 1px solid #f3f4f6; font-weight: 700; font-size: 14px; color: #78350f; background: #fefce8; display: flex; justify-content: space-between; align-items: center; border-radius: 20px 20px 0 0; }
    .mark-read { font-size: 10px; color: #a16207; cursor: pointer; background: none; border: none; }
    .notification-list { max-height: 350px; overflow-y: auto; }
    .notification-item { padding: 12px 16px; border-bottom: 1px solid #f3f4f6; cursor: pointer; }
    .notification-item:hover { background: #fefce8; }
    .notification-item.unread { background: #fef3c7; }
    .notification-title { font-size: 12px; font-weight: 600; color: #1f2937; }
    .notification-desc { font-size: 11px; color: #6b7280; margin-top: 3px; }
    .notification-time { font-size: 9px; color: #9ca3af; margin-top: 4px; }

    .stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 20px; }
    .stat-card { padding: 14px; }
    .stat-icon { width: 40px; height: 40px; border-radius: 18px; display: flex; align-items: center; justify-content: center; margin-bottom: 8px; }
    .stat-value { font-size: 24px; font-weight: 800; }
    .stat-label { font-size: 11px; }
    .chart-container { margin-bottom: 20px; }
    .chart-card { padding: 16px; }
    .chart-title { font-size: 14px; font-weight: 600; color: #78350f; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
    canvas { max-height: 160px; width: 100% !important; }
    .quick-actions-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 20px; }
    .quick-action-btn { background: white; border: 1px solid #f3f4f6; border-radius: 20px; padding: 12px 8px; text-align: center; cursor: pointer; }
    .quick-action-btn i { font-size: 22px; color: #a16207; margin-bottom: 6px; display: block; }
    .quick-action-btn span { font-size: 10px; font-weight: 500; color: #4b5563; }
    .quick-action-btn:active { transform: scale(0.96); background: #fefce8; }
    .priority-bar-container { margin-bottom: 16px; }
    .priority-item { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
    .priority-label { width: 50px; font-size: 11px; font-weight: 500; }
    .priority-bar { flex: 1; height: 8px; background: #e5e7eb; border-radius: 10px; overflow: hidden; }
    .priority-bar-fill { height: 100%; border-radius: 10px; }
    .priority-value { width: 35px; font-size: 11px; font-weight: 600; text-align: right; }
    .fill-high { background: #ef4444; }
    .fill-medium { background: #f59e0b; }
    .fill-low { background: #10b981; }
    .activity-list { margin-top: 8px; }
    .activity-item { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid #f3f4f6; }
    .activity-icon { width: 36px; height: 36px; border-radius: 18px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .activity-content { flex: 1; }
    .activity-title { font-size: 13px; font-weight: 600; color: #1f2937; }
    .activity-time { font-size: 10px; color: #9ca3af; margin-top: 2px; }

    .control-panel { margin-bottom: 20px; }
    .control-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px; }
    .control-header h3 { font-size: 16px; font-weight: 700; color: #78350f; display: flex; align-items: center; gap: 6px; }
    .control-buttons { display: flex; gap: 8px; }
    .icon-btn { background: white; border: 1px solid #e5e7eb; padding: 6px 12px; border-radius: 30px; font-size: 12px; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
    .filters-wrapper { overflow-x: auto; margin-bottom: 16px; -webkit-overflow-scrolling: touch; }
    .filters-grid { display: flex; gap: 12px; min-width: min-content; padding-bottom: 4px; }
    .filter-card { background: white; border-radius: 20px; padding: 14px; border: 1px solid #f3f4f6; min-width: 260px; flex-shrink: 0; }
    .filter-card h4 { font-size: 13px; font-weight: 700; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
    .status-select { width: 100%; padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 30px; font-size: 13px; }
    .legend { margin-top: 10px; font-size: 10px; color: #6b7280; line-height: 1.4; }
    .legend span { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 4px; }
    .priority-btns { display: flex; flex-wrap: wrap; gap: 6px; }
    .priority-filter-btn { padding: 5px 12px; border-radius: 30px; font-size: 11px; font-weight: 500; cursor: pointer; background: white; border: 1px solid #e5e7eb; }
    .priority-filter-btn.active { background: #a16207; color: white; }
    .search-input { width: 100%; padding: 8px 12px 8px 32px; border: 1px solid #e5e7eb; border-radius: 30px; font-size: 13px; background: white url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="%239ca3af" stroke-width="2"><circle cx="10" cy="10" r="7"/><line x1="21" y1="21" x2="15" y2="15"/></svg>') no-repeat 10px center; }
    .table-header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 10px; }
    .table-header-bar h3 { font-size: 16px; font-weight: 700; color: #78350f; }
    .status-badges { display: flex; gap: 8px; flex-wrap: wrap; }
    .stat-badge { padding: 3px 10px; border-radius: 30px; font-size: 10px; font-weight: 600; }
    .stat-badge.blue { background: #dbeafe; color: #1e40af; }
    .stat-badge.green { background: #dcfce7; color: #166534; }
    .stat-badge.red { background: #fee2e2; color: #991b1b; }
    .stat-badge.gray { background: #f3f4f6; color: #4b5563; }
    .tracker-card { background: white; border-radius: 20px; border: 1px solid #f3f4f6; margin-bottom: 16px; overflow: hidden; }
    .tracker-card-header { padding: 14px; border-bottom: 1px solid #f3f4f6; display: flex; flex-direction: column; gap: 10px; background: #fafafa; }
    .tracker-info h3 { font-size: 15px; font-weight: 700; color: #1f2937; }
    .tracker-meta { display: flex; flex-wrap: wrap; gap: 10px; font-size: 11px; color: #6b7280; margin-top: 6px; }
    .tracker-meta i { width: 12px; margin-right: 3px; color: #a16207; }
    .tracker-badges { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
    .priority-badge { padding: 3px 10px; border-radius: 30px; font-size: 10px; font-weight: 700; }
    .priority-high { background: #fee2e2; color: #dc2626; }
    .priority-medium { background: #fef3c7; color: #d97706; }
    .priority-low { background: #dcfce7; color: #10b981; }
    .status-badge { padding: 3px 10px; border-radius: 30px; font-size: 10px; font-weight: 600; background: #e0e7ff; color: #4338ca; }
    .view-details-btn { background: none; border: 1px solid #e5e7eb; padding: 5px 12px; border-radius: 30px; font-size: 11px; cursor: pointer; }
    .table-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .movement-table { width: 100%; border-collapse: collapse; font-size: 11px; min-width: 700px; }
    .movement-table th { text-align: left; padding: 10px 12px; background: #f9fafb; font-weight: 600; color: #6b7280; border-bottom: 1px solid #e5e7eb; font-size: 10px; }
    .movement-table td { padding: 10px 12px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
    .log-status { display: inline-block; padding: 2px 8px; border-radius: 30px; font-size: 9px; font-weight: 600; background: #dbeafe; color: #1e40af; }
    .movement-footer { padding: 10px 14px; background: #f9fafb; border-top: 1px solid #f3f4f6; font-size: 11px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }

    /* Form */
    .input-field, .input-field select, .input-field textarea { background: white; border: 1px solid #e5e7eb; border-radius: 18px; padding: 12px 14px; font-size: 14px; width: 100%; margin-bottom: 14px; outline: none; font-family: 'Inter',sans-serif; color: #1f2937; }
    .input-field:focus { border-color: #a16207; box-shadow: 0 0 0 2px rgba(161,98,7,0.1); }
    .field-wrap { margin-bottom: 14px; }
    .field-wrap label { font-size: 12px; font-weight: 600; margin-bottom: 5px; display: block; color: #6b7280; }
    .field-wrap input, .field-wrap select, .field-wrap textarea { background: white; border: 1px solid #e5e7eb; border-radius: 18px; padding: 12px 14px; font-size: 14px; width: 100%; outline: none; font-family: 'Inter',sans-serif; color: #1f2937; }
    .field-wrap input:focus, .field-wrap select:focus, .field-wrap textarea:focus { border-color: #a16207; box-shadow: 0 0 0 2px rgba(161,98,7,0.1); }
    .field-wrap input[readonly] { background: #f9fafb; color: #9ca3af; cursor: not-allowed; }
    .btn { background: linear-gradient(105deg, #a16207, #92400e); border: none; padding: 14px; border-radius: 40px; font-weight: 700; font-size: 15px; color: white; width: 100%; cursor: pointer; margin-top: 6px; }
    .btn:disabled { opacity: 0.6; cursor: not-allowed; }
    .priority-selector { display: flex; gap: 8px; flex-wrap: wrap; }
    .prio-chip { background: white; border: 1px solid #e5e7eb; padding: 7px 18px; border-radius: 30px; font-size: 12px; cursor: pointer; user-select: none; }
    .prio-chip.active { background: #a16207; color: white; border-color: #a16207; }
    .section-header { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; }
    .section-header .section-icon { font-size: 20px; background: rgba(161,98,7,0.1); padding: 8px; border-radius: 14px; color: #a16207; }
    .section-header h2 { font-size: 15px; font-weight: 700; color: #1f2937; }
    .section-header p { font-size: 11px; color: #9ca3af; }
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .form-section { background: white; border-radius: 24px; border: 1px solid #f3f4f6; padding: 16px; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
    .success-toast { position: fixed; top: 20px; left: 50%; transform: translateX(-50%); background: #065f46; color: white; padding: 12px 24px; border-radius: 30px; font-size: 13px; font-weight: 600; z-index: 999; display: none; box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
    .error-toast { position: fixed; top: 20px; left: 50%; transform: translateX(-50%); background: #991b1b; color: white; padding: 12px 24px; border-radius: 30px; font-size: 13px; font-weight: 600; z-index: 999; display: none; box-shadow: 0 4px 12px rgba(0,0,0,0.2); }

    /* QR Scanner */
    .scanner-container { position: relative; width: 100%; border-radius: 24px; overflow: hidden; background: #000; margin-bottom: 14px; min-height: 350px; }
    #qr-reader { width: 100%; min-height: 350px; border: none !important; }
    .camera-controls { display: flex; gap: 8px; margin-top: 12px; }
    .camera-controls button { flex: 1; background: white; border: 1px solid #e5e7eb; padding: 10px; border-radius: 30px; font-weight: 500; font-size: 12px; cursor: pointer; }
    .flash-on { background: #a16207 !important; color: white !important; }
    .scanner-status { padding: 12px; margin-top: 12px; text-align: center; border-radius: 20px; }

    /* Profile */
    .profile-info { text-align: center; }
    .avatar-large { background: #a16207; width: 70px; height: 70px; border-radius: 35px; margin: 0 auto 14px; display: flex; align-items: center; justify-content: center; }
    .menu-item { display: flex; align-items: center; gap: 12px; padding: 10px 0; cursor: pointer; font-size: 13px; }
    .divider { height: 1px; background: #f3f4f6; margin: 10px 0; }

    @media (min-width: 768px) {
      .screen { padding: 24px 24px 80px 24px; }
      .filters-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 16px; }
      .filter-card { min-width: auto; }
      .filters-wrapper { overflow-x: visible; }
      .tracker-card-header { flex-direction: row; justify-content: space-between; align-items: flex-start; }
    }
  </style>
</head>
<body>

{{-- Toast messages --}}
<div id="successToast" class="success-toast"></div>
<div id="errorToast"   class="error-toast"></div>

<div id="app">
  <!-- ═══ DASHBOARD SCREEN ═══ -->
  <div id="dashboard-screen" class="screen">
    <div class="dashboard-header">
      <div class="page-header">
        <h1>Dashboard</h1>
        <div class="greeting-text" id="greetingMsg"></div>
      </div>
      <div class="notification-wrapper">
        <button class="notification-btn" id="notificationBell">
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
    <div class="stats-grid" id="statsContainer">
      <div class="glass-card stat-card"><div class="stat-icon" style="background:#a1620720;"><i class="fas fa-folder"></i></div><div class="stat-value">—</div><div class="stat-label">Total Files</div></div>
      <div class="glass-card stat-card"><div class="stat-icon" style="background:#0ea5e920;"><i class="fas fa-play-circle"></i></div><div class="stat-value">—</div><div class="stat-label">Active</div></div>
      <div class="glass-card stat-card"><div class="stat-icon" style="background:#f59e0b20;"><i class="fas fa-clock"></i></div><div class="stat-value">—</div><div class="stat-label">Overdue</div></div>
      <div class="glass-card stat-card"><div class="stat-icon" style="background:#10b98120;"><i class="fas fa-check-circle"></i></div><div class="stat-value">—</div><div class="stat-label">Completed</div></div>
    </div>
    <div class="quick-actions-grid">
      <div class="quick-action-btn" onclick="setActiveTab('scanner')"><i class="fas fa-qrcode"></i><span>Scan QR</span></div>
      <div class="quick-action-btn" onclick="window.location='{{ route('mobile.digital-request') }}'"
           style="border-color:#fca5a5;">
        <i class="fas fa-paper-plane" style="color:#450a0a;"></i>
        <span style="color:#450a0a; font-weight:600; line-height:1.2;">Digital<br>File Request</span>
      </div>
    </div>
    <div class="chart-container">
      <div class="glass-card chart-card">
        <div class="chart-title"><i class="fas fa-chart-line"></i> Weekly Activity</div>
        <canvas id="weeklyChart"></canvas>
      </div>
    </div>
    <div class="chart-container">
      <div class="glass-card chart-card">
        <div class="chart-title"><i class="fas fa-chart-pie"></i> Priority Distribution</div>
        <div id="priorityDistribution"></div>
      </div>
    </div>
  </div>

  <!-- ═══ FILES SCREEN ═══ -->

  <!-- ═══ FILES SCREEN ═══ -->
  <div id="files-screen" class="screen">
    <div class="page-header" style="margin-bottom:16px;"><h1>File Search</h1></div>

    <div class="form-section">
      <div class="section-header">
        <span class="section-icon"><i class="fas fa-search"></i></span>
        <div><h2>Search File</h2><p>Enter a file number or tracking ID</p></div>
      </div>
      <div class="field-wrap" style="position:relative;">
        <label>File No / Tracking ID</label>
        <div style="display:flex;gap:8px;">
          <input type="text" id="fileSearchInput" placeholder="e.g. RES-2015-4859"
            style="flex:1;" autocomplete="off">
          <button id="fileSearchBtn" class="btn" style="width:auto;padding:0 16px;flex-shrink:0;">
            <i class="fas fa-search"></i>
          </button>
        </div>
      </div>
    </div>

    <div id="fileSearchResult" style="margin-top:4px;"></div>
  </div>

  <!-- ═══ SCANNER SCREEN ═══ -->
  <div id="scanner-screen" class="screen">
    <div class="page-header" style="margin-bottom:16px;"><h1>QR Scanner</h1></div>
    <div class="form-section">
      <div class="scanner-container"><div id="qr-reader"></div></div>
      <div class="camera-controls">
        <button id="startCameraBtn"><i class="fas fa-play"></i> Start</button>
        <button id="stopCameraBtn"><i class="fas fa-stop"></i> Stop</button>
        <button id="flashToggleBtn"><i class="fas fa-bolt"></i> Flash</button>
      </div>
      <div id="scannerStatus" class="glass-card scanner-status">
        <i class="fas fa-camera"></i> <span>Click Start to begin scanning</span>
      </div>
    </div>

    {{-- Manual entry fallback --}}
    <div class="form-section" style="margin-top:4px;">
      <div class="section-header">
        <span class="section-icon"><i class="fas fa-keyboard"></i></span>
        <div><h2>Manual Entry</h2><p>Type or paste a Tracking ID if camera is unavailable</p></div>
      </div>
      <div class="field-wrap">
        <label>Tracking ID / File Number</label>
        <div style="display:flex;gap:8px;">
          <input type="text" id="manualScanInput" placeholder="e.g. TRK-XXXXXX or RES-2015-4859"
                 style="flex:1;background:white;border:1px solid #e5e7eb;border-radius:18px;padding:12px 14px;font-size:14px;outline:none;font-family:'Inter',sans-serif;">
          <button id="manualScanBtn" style="background:linear-gradient(135deg,#a16207,#78350f);border:none;padding:12px 18px;border-radius:18px;color:white;font-weight:600;cursor:pointer;white-space:nowrap;">
            <i class="fas fa-search"></i> Look up
          </button>
        </div>
      </div>
      <div id="manualScanResult"></div>
    
    </div>
  </div>

  <!-- ═══ PROFILE SCREEN ═══ -->
  <div id="profile-screen" class="screen">
    <div class="page-header" style="margin-bottom:16px;"><h1>Profile</h1></div>
    <div class="form-section">
      <div class="avatar-large"><i class="fas fa-user fa-2x" style="color:white;"></i></div>
      <div class="profile-info" style="text-align:center;">
        <div style="font-size:20px;font-weight:700;margin-bottom:4px;">{{ trim(auth()->user()->first_name.' '.auth()->user()->last_name) ?: auth()->user()->username }}</div>
        <div style="font-size:13px;color:#6b7280;">{{ auth()->user()->username }}</div>
        <div style="background:#a16207;display:inline-block;padding:3px 12px;border-radius:30px;margin-top:8px;color:white;font-size:11px;">
          {{ strtoupper(auth()->user()->type ?? 'USER') }}
        </div>
      </div>
      <div class="divider"></div>
      <div class="menu-item"><i class="fas fa-bell" style="color:#a16207;width:20px;"></i><span>Notifications</span><i class="fas fa-chevron-right" style="margin-left:auto;color:#9ca3af;"></i></div>
      <div class="divider"></div>
      <form method="POST" action="{{ route('mobile.logout') }}" style="margin-top:12px;">
        @csrf
        <button type="submit" class="btn" style="background:white;border:1.5px solid #a16207;color:#a16207;">
          <i class="fas fa-right-from-bracket"></i> Logout
        </button>
      </form>
    </div>
    <div style="text-align:center;margin-top:16px;font-size:10px;color:#9ca3af;">KLAES File Tracker v1.0</div>
  </div>

  <!-- Tab Bar -->
  <div class="tab-bar">
    <button class="tab-item" data-tab="dashboard"><i class="fas fa-home"></i><span>Home</span></button>
    <button class="tab-item" data-tab="files"><i class="fas fa-folder-open"></i><span>Files</span></button>
    <button class="tab-item" data-tab="scanner"><i class="fas fa-camera"></i><span>Scan</span></button>
    <button class="tab-item" data-tab="dfr" onclick="window.location='{{ route('mobile.digital-request') }}'"><i class="fas fa-paper-plane"></i><span>File Request</span></button>
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
  if (!notifications.length) { container.innerHTML = '<div style="padding:16px;text-align:center;font-size:12px;color:#9ca3af;">No notifications</div>'; return; }
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
    if (res.success) { notifications = res.data || []; renderNotifications(); updateNotificationBadge(); }
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
async function renderDashboard() {
  const hr = new Date().getHours();
  const greet = hr<12?'Good Morning':hr<18?'Good Afternoon':'Good Evening';
  document.getElementById('greetingMsg').textContent = `${greet}, ${CURRENT_USER.name.split(' ')[0]}!`;
  try {
    const res = await api(`${API_BASE}/dashboard/stats`);
    if (!res || res._authError || (!res.success && !res.data)) return;
    const s = res.data;
    document.getElementById('statsContainer').innerHTML = `
      <div class="glass-card stat-card"><div class="stat-icon" style="background:#a1620720;"><i class="fas fa-folder"></i></div><div class="stat-value">${s.total_trackers||0}</div><div class="stat-label">Total Files</div></div>
      <div class="glass-card stat-card"><div class="stat-icon" style="background:#0ea5e920;"><i class="fas fa-play-circle"></i></div><div class="stat-value">${s.active_trackers||0}</div><div class="stat-label">Active</div></div>
      <div class="glass-card stat-card"><div class="stat-icon" style="background:#f59e0b20;"><i class="fas fa-clock"></i></div><div class="stat-value">${s.overdue_trackers||0}</div><div class="stat-label">Overdue</div></div>
      <div class="glass-card stat-card"><div class="stat-icon" style="background:#10b98120;"><i class="fas fa-check-circle"></i></div><div class="stat-value">${s.completed_trackers||0}</div><div class="stat-label">Completed</div></div>`;
    const pb = s.priority_breakdown||{}, h=pb.high||0, m=pb.medium||0, l=pb.low||0, tot=h+m+l||1;
    document.getElementById('priorityDistribution').innerHTML = `
      <div class="priority-bar-container">
        <div class="priority-item"><span class="priority-label">High</span><div class="priority-bar"><div class="priority-bar-fill fill-high" style="width:${(h/tot)*100}%"></div></div><span class="priority-value">${h}</span></div>
        <div class="priority-item"><span class="priority-label">Medium</span><div class="priority-bar"><div class="priority-bar-fill fill-medium" style="width:${(m/tot)*100}%"></div></div><span class="priority-value">${m}</span></div>
        <div class="priority-item"><span class="priority-label">Low</span><div class="priority-bar"><div class="priority-bar-fill fill-low" style="width:${(l/tot)*100}%"></div></div><span class="priority-value">${l}</span></div>
      </div>`;
    const recent = s.recent_activity||[];
    document.getElementById('recentActivityList').innerHTML = recent.length
      ? recent.map(f=>`<div class="activity-item"><div class="activity-icon" style="background:${f.status==='ACTIVE'?'#dcfce7':'#fef3c7'};"><i class="fas ${f.status==='ACTIVE'?'fa-play-circle':'fa-clock'}"></i></div><div class="activity-content"><div class="activity-title">${esc(f.file_title)}</div><div class="activity-time">${relTime(f.updated_at)}</div></div></div>`).join('')
      : '<p style="text-align:center;color:#9ca3af;font-size:12px;padding:20px;">No recent activity</p>';
    const wl = s.weekly_labels||['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
    const wd = s.weekly_activity||[0,0,0,0,0,0,0];
    if (weeklyChart) weeklyChart.destroy();
    weeklyChart = new Chart(document.getElementById('weeklyChart').getContext('2d'), { type:'line', data:{ labels:wl, datasets:[{ label:'Files', data:wd, borderColor:'#a16207', backgroundColor:'rgba(161,98,7,0.1)', borderWidth:2, fill:true, tension:0.3, pointBackgroundColor:'#a16207', pointRadius:4 }]}, options:{ responsive:true, maintainAspectRatio:true, plugins:{ legend:{ position:'top', labels:{ boxWidth:12, font:{ size:10 }}}}, scales:{ y:{ beginAtZero:true, ticks:{ font:{ size:9 }}}, x:{ ticks:{ font:{ size:9 }}}}}});
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
  if (!filtered.length) { container.innerHTML = '<div class="glass-card-solid" style="padding:40px;text-align:center;color:#9ca3af;"><i class="fas fa-folder-open fa-2x"></i><p style="margin-top:12px;">No files found</p></div>'; return; }
  container.innerHTML = filtered.map(file => `
    <div class="tracker-card" style="border-left:3px solid ${file.handlerId===myId?'#3b82f6':(file.status==='pending_acceptance'||file.status==='pending'?'#10b981':'#ef4444')}">
      <div class="tracker-card-header">
        <div class="tracker-info">
          <h3>${esc(file.fileName)}</h3>
          <div class="tracker-meta">
            <span><i class="fas fa-hashtag"></i>${esc(file.fileNumber)}</span>
            <span><i class="fas fa-map-marker-alt"></i>${esc(file.currentLocation)}</span>
            <span><i class="fas fa-user"></i>${esc(file.handler)}</span>
          </div>
        </div>
        <div class="tracker-badges">
          <span class="priority-badge ${getPrioCls(file.priority)}">${(file.priority||'').toUpperCase()}</span>
          <span class="status-badge">${file.status==='active'?'Active':file.status==='pending_acceptance'||file.status==='pending'?'Pending':'Completed'}</span>
          <span style="font-size:10px;color:#9ca3af;">${relTime(file.createdAt)}</span>
        </div>
      </div>
      <div class="table-wrapper">
        <table class="movement-table">
          <thead><tr><th>Log ID</th><th>Office</th><th>Officer</th><th>Log In</th><th>Log Out</th><th>Status</th></tr></thead>
          <tbody>${(file.movements||[]).map(m=>`<tr>
            <td><span class="log-status">${esc(m.logId)}</span></td>
            <td>${esc(m.office)}</td><td>${esc(m.receivingOfficer)}</td>
            <td>${esc(m.logInDate)}<br><small>${esc(m.logInTime)}</small></td>
            <td>${m.logOutDate?esc(m.logOutDate)+'<br><small>'+esc(m.logOutTime)+'</small>':'—'}</td>
            <td><span class="log-status">${m.status==='active'?'Active':'Pending'}</span></td>
          </tr>`).join('')}</tbody>
        </table>
      </div>
      <div class="movement-footer"><span><i class="fas fa-arrow-right"></i> ${esc(file.currentLocation)}</span></div>
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
        <div style="margin-top:10px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:16px;padding:14px;">
          <div style="font-weight:700;font-size:14px;color:#166534;">${esc(t.file_title||'—')}</div>
          <div style="font-size:12px;color:#4b5563;margin-top:4px;">
            <span style="margin-right:12px;"><i class="fas fa-hashtag" style="color:#a16207;"></i> ${esc(t.file_number||'—')}</span>
            <span><i class="fas fa-map-marker-alt" style="color:#a16207;"></i> ${esc(t.current_office_name||t.receiving_office_name||'—')}</span>
          </div>
          <div style="font-size:11px;color:#6b7280;margin-top:4px;">
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
  if (tabId==='files')     document.getElementById('fileSearchInput')?.focus();
  if (tabId==='scanner')   updateScanStatus('Click Start to begin scanning','info');
}

// ─── Event listeners ──────────────────────────────────────────────────────
// ─── File Search ──────────────────────────────────────────────────────────
async function searchFile() {
  const input  = document.getElementById('fileSearchInput');
  const result = document.getElementById('fileSearchResult');
  const val    = input.value.trim();
  if (!val) { result.innerHTML = '<p style="color:#ef4444;font-size:12px;margin-top:4px;">Please enter a file number or tracking ID.</p>'; return; }

  const btn = document.getElementById('fileSearchBtn');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  result.innerHTML = '';

  try {
    const res = await api(`${API_BASE}/track/${encodeURIComponent(val)}`);
    if (res && res.success && res.data) {
      const t   = res.data;
      const logs = (t.log_entries || t.logs || []);
      const logsHtml = logs.length
        ? `<div style="margin-top:12px;">
            <div style="font-size:12px;font-weight:700;color:#78350f;margin-bottom:8px;"><i class="fas fa-history" style="margin-right:5px;"></i>Movement History</div>
            <div style="overflow-x:auto;-webkit-overflow-scrolling:touch;">
              <table style="width:100%;border-collapse:collapse;font-size:11px;min-width:500px;">
                <thead>
                  <tr style="background:#fef3c7;">
                    <th style="padding:8px;text-align:left;border-bottom:1px solid #e5e7eb;">Date &amp; Time</th>
                    <th style="padding:8px;text-align:left;border-bottom:1px solid #e5e7eb;">Office</th>
                    <th style="padding:8px;text-align:left;border-bottom:1px solid #e5e7eb;">Officer</th>
                    <th style="padding:8px;text-align:left;border-bottom:1px solid #e5e7eb;">Status</th>
                  </tr>
                </thead>
                <tbody>
                  ${logs.map(l=>`<tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:8px;">${esc(l.created_at||l.log_date||'—')}</td>
                    <td style="padding:8px;">${esc(l.office_name||l.officeName||'—')}</td>
                    <td style="padding:8px;">${esc(l.receiving_officer_name||l.receivingOfficerName||'—')}</td>
                    <td style="padding:8px;"><span style="background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:30px;font-weight:600;">${esc(l.status||'—')}</span></td>
                  </tr>`).join('')}
                </tbody>
              </table>
            </div>
           </div>`
        : '<p style="font-size:11px;color:#9ca3af;margin-top:8px;">No movement history found.</p>';

      result.innerHTML = `
        <div style="background:white;border-radius:20px;border:1px solid #f3f4f6;box-shadow:0 2px 8px rgba(0,0,0,0.04);overflow:hidden;margin-top:4px;">
          <div style="background:#fefce8;padding:14px 16px;border-bottom:1px solid #f3f4f6;">
            <div style="font-size:15px;font-weight:800;color:#1f2937;">${esc(t.file_title||'—')}</div>
            <div style="font-size:11px;color:#6b7280;margin-top:4px;display:flex;flex-wrap:wrap;gap:10px;">
              <span><i class="fas fa-hashtag" style="color:#a16207;margin-right:3px;"></i>${esc(t.file_number||val)}</span>
              <span><i class="fas fa-map-marker-alt" style="color:#a16207;margin-right:3px;"></i>${esc(t.current_office_name||t.receiving_office_name||'—')}</span>
              <span><i class="fas fa-flag" style="color:#a16207;margin-right:3px;"></i>${esc(t.priority||'—')}</span>
            </div>
          </div>
          <div style="padding:14px 16px;">
            ${logsHtml}
          </div>
        </div>`;
    } else {
      result.innerHTML = `<p style="color:#ef4444;font-size:12px;margin-top:6px;"><i class="fas fa-times-circle"></i> No file found for "<strong>${esc(val)}</strong>".</p>`;
    }
  } catch(e) {
    result.innerHTML = `<p style="color:#ef4444;font-size:12px;margin-top:6px;">Error: ${esc(e.message)}</p>`;
  }

  btn.disabled = false; btn.innerHTML = '<i class="fas fa-search"></i>';
}

document.getElementById('fileSearchBtn')?.addEventListener('click', searchFile);
document.getElementById('fileSearchInput')?.addEventListener('keydown', e => { if (e.key === 'Enter') searchFile(); });

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

// ─── Boot ─────────────────────────────────────────────────────────────────
setActiveTab('dashboard');
loadNotifications();
</script>
</body>
</html>

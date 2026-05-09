<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>VFC Mobile · Field Entry</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --bg: #0b0e14;
            --surface: #151921;
            --surface2: #1e2530;
            --border: rgba(255, 255, 255, 0.08);
            --border-active: #3b82f6;
            --accent: #3b82f6;
            --accent-glow: rgba(59, 130, 246, 0.3);
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --text: #f1f5f9;
            --text-muted: #94a3b8;
            --text-dim: #475569;
            --radius: 16px;
            --radius-sm: 10px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-size: 14px;
            min-height: 100vh;
            overflow-x: hidden;
            overscroll-behavior-y: contain;
        }

        /* ── TOP BAR ── */
        .topbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(11, 14, 20, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px;
            padding-top: max(14px, env(safe-area-inset-top));
        }

        .topbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .brand-text h1 {
            font-size: 14px;
            font-weight: 700;
            letter-spacing: -0.01em;
        }

        .brand-text p {
            font-size: 10px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .sync-status {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--success);
            box-shadow: 0 0 8px var(--success);
        }

        /* ── PROGRESS STRIP ── */
        .nav-strip {
            position: sticky;
            top: 65px; /* adjust based on topbar height */
            z-index: 99;
            background: rgba(21, 25, 33, 0.95);
            backdrop-filter: blur(10px);
            padding: 12px 20px;
            display: flex;
            gap: 8px;
            overflow-x: auto;
            scrollbar-width: none;
            border-bottom: 1px solid var(--border);
        }

        .nav-strip::-webkit-scrollbar { display: none; }

        .nav-item {
            padding: 6px 14px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            white-space: nowrap;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .nav-item.active {
            background: rgba(59, 130, 246, 0.1);
            border-color: var(--accent);
            color: var(--accent);
        }

        .nav-item.done {
            background: rgba(16, 185, 129, 0.1);
            border-color: var(--success);
            color: var(--success);
        }

        /* ── CONTENT ── */
        .main-content {
            padding: 20px;
            padding-bottom: 120px;
            max-width: 500px;
            margin: 0 auto;
        }

        /* ── SUMMARY CARD ── */
        .summary-card {
            margin-top: 16px;
            padding: 16px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border);
            border-radius: 12px;
        }
        .summary-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 12px;
        }
        .summary-label {
            font-size: 10px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .id-badge {
            font-size: 9px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 6px;
            background: rgba(59, 130, 246, 0.15);
            color: var(--accent);
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        .summary-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }
        .summary-icon-box {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
        }
        .summary-value {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            font-weight: 700;
        }
        .stats-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }
        .stat-badge {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 10px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        .stat-badge.success {
            background: rgba(16, 185, 129, 0.1);
            border-color: rgba(16, 185, 129, 0.2);
            color: var(--success);
        }
        .stat-badge span {
            color: var(--text);
            font-weight: 800;
        }
        .stat-badge.success span {
            color: var(--success);
        }
        .mt-3 { margin-top: 12px; }
        .hidden { display: none !important; }

        .section-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            margin-bottom: 16px;
            overflow: hidden;
            animation: slideUp 0.4s ease-out both;
        }

        .bank-logo-img {
            width: 100% !important;
            height: 100% !important;
            object-fit: contain !important;
            display: block !important;
        }

        .selected-logo-wrap {
            width: 28px !important;
            height: 28px !important;
            min-width: 28px !important;
            border-radius: 6px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3px;
            overflow: hidden;
            flex-shrink: 0;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .section-header {
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            border-bottom: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.02);
            cursor: pointer;
            transition: background 0.2s;
        }

        .section-header:active {
            background: rgba(255, 255, 255, 0.05);
        }

        .section-card.collapsed .section-body {
            display: none;
        }

        .section-card.collapsed {
            margin-bottom: 8px;
        }

        .section-card.collapsed .section-header {
            border-bottom: none;
        }

        .section-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .section-header h2 {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
        }

        .section-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        /* ── FORM ELEMENTS ── */
        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .field label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-dim);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .field label .req { color: var(--danger); }

        .inp-wrap {
            position: relative;
        }

        .inp {
            width: 100%;
            background: var(--surface2);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 12px 14px;
            color: var(--text);
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            outline: none;
        }

        .inp:focus {
            border-color: var(--accent);
            background: var(--bg);
            box-shadow: 0 0 0 4px var(--accent-glow);
        }

        .inp-readonly {
            background: rgba(255, 255, 255, 0.03);
            color: var(--text-muted);
            cursor: not-allowed;
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
        }

        select.inp {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23475569' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 40px;
        }

        /* ── SPECIAL WIDGETS ── */
        .amount-card {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(16, 185, 129, 0.05));
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: var(--radius-sm);
            padding: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .amount-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--accent);
            text-transform: uppercase;
        }

        .amount-val {
            font-family: 'JetBrains Mono', monospace;
            font-size: 22px;
            font-weight: 600;
            color: var(--success);
        }

        .items-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .check-item {
            background: var(--surface2);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s;
            cursor: pointer;
        }

        .check-item.active {
            border-color: var(--accent);
            background: rgba(59, 130, 246, 0.1);
        }

        .check-box {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 2px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .check-item.active .check-box {
            background: var(--accent);
            border-color: var(--accent);
        }

        .check-box svg {
            color: white;
            width: 12px;
            height: 12px;
            display: none;
        }

        .check-item.active .check-box svg {
            display: block;
        }

        .check-label {
            font-size: 12px;
            font-weight: 600;
        }

        /* ── FOOTER BAR ── */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(11, 14, 20, 0.9);
            backdrop-filter: blur(20px);
            border-top: 1px solid var(--border);
            padding: 16px 20px;
            padding-bottom: max(16px, env(safe-area-inset-bottom));
            display: flex;
            gap: 12px;
            max-width: 500px;
            margin: 0 auto;
            z-index: 100;
        }

        .btn {
            flex: 1;
            height: 52px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 14px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-ghost {
            background: var(--surface2);
            color: var(--text-muted);
            border: 1px solid var(--border);
        }

        .btn-primary {
            background: var(--accent);
            color: white;
            box-shadow: 0 4px 20px var(--accent-glow);
        }

        .btn:active { transform: scale(0.96); }

        /* ── UTILS ── */
        .hidden { display: none; }
        
        .toast {
            position: fixed;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%) translateY(20px);
            background: var(--surface2);
            border: 1px solid var(--border);
            padding: 12px 20px;
            border-radius: 40px;
            font-size: 13px;
            font-weight: 600;
            color: white;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 10px;
            opacity: 0;
            pointer-events: none;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            white-space: nowrap;
        }

        .toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

    </style>
</head>
<body>


    <!-- TOP BAR -->
    <header class="topbar">
        <div class="topbar-brand">
            <div class="brand-icon" style="background: white; padding: 2px;">
                <img src="http://app.klaes.ng/storage/upload/logo/1.jpeg" alt="Logo" style="width: 100%; height: 100%; object-fit: contain; border-radius: 8px;">
            </div>
            <div class="brand-text">
                <h1>CFV FIELD APP</h1>
                <p>CFV Data Entry</p>
            </div>
        </div>
        <div class="sync-status"></div>
    </header>

    <!-- NAV STRIP -->
    <nav class="nav-strip" id="navStrip">
        <div class="nav-item active" data-target="sec-project">
            <i data-lucide="briefcase" style="width: 12px;"></i>
            <span>PROJECT</span>
        </div>
        <div class="nav-item" data-target="sec-owner">
            <i data-lucide="user" style="width: 12px;"></i>
            <span>OWNER</span>
        </div>
        <div class="nav-item" data-target="sec-building">
            <i data-lucide="home" style="width: 12px;"></i>
            <span>BUILDING</span>
        </div>
        <div class="nav-item" data-target="sec-payment">
            <i data-lucide="credit-card" style="width: 12px;"></i>
            <span>PAYMENT</span>
        </div>
        <div class="nav-item" data-target="sec-location">
            <i data-lucide="map-pin" style="width: 12px;"></i>
            <span>LOCATION</span>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <form id="vfcForm">
            <!-- 1. PROJECT & ASSIGNMENT -->
            <section class="section-card" id="sec-project">
                <div class="section-header">
                    <div class="section-icon" style="background: rgba(59, 130, 246, 0.1); color: var(--accent);">
                        <i data-lucide="layers" style="width: 16px;"></i>
                    </div>
                    <h2>Project Context</h2>
                </div>
                <div class="section-body">
                    <div class="field">
                        <label>Target Project <span class="req">*</span></label>
                        <select name="project_id" id="projectSelect" class="inp" required>
                            <option value="">Loading Projects...</option>
                        </select>

                        <div id="mobile-project-info" class="hidden summary-card">
                            <div class="summary-header">
                                <p class="summary-label">Project Summary</p>
                                <span class="id-badge">ID: <span id="m_proj_id">-</span></span>
                            </div>

                            <div class="summary-body">
                                <div class="summary-item">
                                    <div class="summary-icon-box" style="border-color: rgba(245, 158, 11, 0.2);">
                                        <i data-lucide="hash" style="width: 12px; color: var(--warning);"></i>
                                    </div>
                                    <span id="m_proj_code" class="summary-value" style="color: var(--warning);">-</span>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-icon-box" style="border-color: rgba(59, 130, 246, 0.2);">
                                        <i data-lucide="file-text" style="width: 12px; color: var(--accent);"></i>
                                    </div>
                                    <span id="m_proj_fileno" class="summary-value" style="color: var(--accent);">-</span>
                                </div>

                                <div class="stats-grid">
                                    <div class="stat-badge">
                                        <i data-lucide="users"></i>
                                        WORKERS: <span id="m_proj_workers">0</span>
                                    </div>
                                    <div class="stat-badge success">
                                        <i data-lucide="check-circle"></i>
                                        FORM FILLED: <span id="m_proj_filled">0</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="field">
                        <label>Assigned Worker <span class="req">*</span></label>
                        <select name="worker_id" id="workerSelect" class="inp" required disabled>
                            <option value="">Select Project First</option>
                        </select>
                    </div>
                    <div id="workerBadge" class="hidden">
                        <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.2); border-radius: 10px; padding: 12px; display: flex; align-items: center; gap: 10px;">
                            <i data-lucide="id-card" style="width: 14px; color: var(--warning);"></i>
                            <span id="workerCodeDisplay" style="font-family: 'JetBrains Mono', monospace; font-size: 11px; font-weight: 700; color: var(--warning);">—</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 2. OWNER & FILE -->
            <section class="section-card collapsed" id="sec-owner">
                <div class="section-header">
                    <div class="section-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
                        <i data-lucide="file-text" style="width: 16px;"></i>
                    </div>
                    <h2>Ownership & Dates</h2>
                </div>
                <div class="section-body">
                    <div class="field">
                        <label>Project Code <span class="text-[10px] italic">(From Project)</span></label>
                        <input type="text" id="mobile_project_code" class="inp inp-readonly" placeholder="Select Project First" readonly>
                    </div>
                    <div class="field">
                        <label>Project FileNo <span class="text-[10px] italic">(From Project)</span></label>
                        <input type="text" id="mobile_project_fileno" class="inp inp-readonly" placeholder="Select Project First" readonly>
                    </div>
                    <div class="field">
                        <label>Our Reference <span class="req">*</span></label>
                        <input type="text" name="our_ref" id="mobile_our_ref" class="inp inp-readonly" placeholder="Select Project First" readonly>
                    </div>
                    <div class="field">
                        <label>Your Reference</label>
                        <input type="text" name="your_ref" id="mobile_your_ref" class="inp inp-readonly" placeholder="Select Project First" readonly>
                    </div>
                    <div class="field">
                        <label>Owner Full Name <span class="req">*</span></label>
                        <input type="text" name="owner_name" id="owner_name" class="inp" placeholder="Musa Yakubu" required>
                    </div>
                    <div class="field">
                        <label>Valuation Date <span class="req">*</span></label>
                        <input type="date" name="valuation_date" id="valuation_date" class="inp" value="{{ date('Y-m-d') }}" required>
                    </div>
                </div>
            </section>

            <!-- 3. BUILDING & COST -->
            <section class="section-card collapsed" id="sec-building">
                <div class="section-header">
                    <div class="section-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">
                        <i data-lucide="building" style="width: 16px;"></i>
                    </div>
                    <h2>Building Assessment</h2>
                </div>
                <div class="section-body">
                    <div class="field">
                        <label>Building Type <span class="req">*</span></label>
                        <select name="building_type" id="buildingType" class="inp" required>
                            <option value="">Select Type</option>
                        </select>
                        <input type="text" id="buildingTypeOther" class="inp hidden mt-2" placeholder="Specify Building Type...">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="field">
                            <label>Count</label>
                            <input type="number" name="building_count" id="buildingCount" class="inp" value="1" min="1">
                        </div>
                        <div class="field">
                            <label>Area (m²)</label>
                            <input type="number" name="area_covered" id="areaCovered" class="inp" placeholder="0.00" step="0.01">
                        </div>
                    </div>
                    <div class="field">
                        <label>Rate of Cost (₦)</label>
                        <input type="number" name="rate_of_cost" id="rateOfCost" class="inp" placeholder="0.00" step="0.01">
                    </div>
                    <div class="field">
                        <label>Amount of Compensation <span class="req">*</span></label>
                        <div class="inp-wrap" style="position: relative;">
                            <div style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--success); font-weight: 700;">₦</div>
                            <input type="number" name="compensation_amount" id="compensation_amount" class="inp" placeholder="0.00" step="0.01" style="padding-left: 32px; color: var(--success); font-family: 'JetBrains Mono', monospace; font-size: 18px; font-weight: 700;" required>
                        </div>
                    </div>
                    
                    <div class="field">
                        <label>Compensated Items</label>
                        <div class="items-grid" id="valuationItemsGrid">
                            <div style="padding:10px; font-size:11px; color:var(--text-dim); font-style:italic;">Loading items...</div>
                        </div>
                        <input type="text" id="compItemsOtherText" class="inp hidden mt-3" placeholder="Specify other items...">
                        <input type="hidden" name="compensated_items" id="compItemsVal">
                    </div>
                </div>
            </section>

            <!-- 4. ACCOUNT DETAILS -->
            <section class="section-card collapsed" id="sec-payment">
                <div class="section-header">
                    <div class="section-icon" style="background: rgba(239, 68, 68, 0.1); color: var(--danger);">
                        <i data-lucide="banknote" style="width: 16px;"></i>
                    </div>
                    <h2>Account & Payment</h2>
                </div>
                <div class="section-body">
                    <div class="field">
                        <label>Bank Name</label>
                        <div class="inp-wrap" style="position: relative; display: flex; align-items: center;">
                            <div id="selectedBankLogo" class="hidden" style="position: absolute; left: 12px; z-index: 10; pointer-events: none;">
                                <div class="selected-logo-wrap shadow-lg">
                                    <img src="" alt="Bank" class="bank-logo-img">
                                </div>
                            </div>
                            <input type="text" id="bankSearch" class="inp" placeholder="Search Nigerian Banks..." autocomplete="off">
                            <input type="hidden" name="bank_name" id="bankNameVal">
                            <div id="bankResults" class="hidden absolute z-50 left-0 right-0 top-full mt-1 bg-surface2 border border-border rounded-xl max-h-60 overflow-y-auto shadow-2xl">
                                <!-- JS items -->
                            </div>
                        </div>
                    </div>
                    <div class="field">
                        <label>Account Name <span class="req">*</span></label>
                        <input type="text" name="account_name" class="inp" placeholder="Full Name as on Bank Account" required>
                    </div>
                    <div class="field">
                        <label>Account Number <span class="req">*</span></label>
                        <input type="tel" name="account_number" class="inp" placeholder="10 Digits" maxlength="10" required>
                    </div>
                    <div class="field">
                        <label>Phone Number <span class="req">*</span></label>
                        <input type="tel" name="phone_number" class="inp" placeholder="080..." required>
                    </div>
                    <div class="field">
                        <label>National Identity Number (NIN)</label>
                        <input type="text" name="nin" id="mobile_nin" class="inp" placeholder="11 Digits">
                    </div>
                    <div class="field">
                        <label>Remarks</label>
                        <textarea name="remarks" id="mobile_remarks" class="inp" rows="2" placeholder="Any additional notes..."></textarea>
                    </div>
                </div>
            </section>

            <!-- 5. LOCATION -->
            <section class="section-card collapsed" id="sec-location">
                <div class="section-header">
                    <div class="section-icon" style="background: rgba(139, 92, 246, 0.1); color: #a78bfa;">
                        <i data-lucide="map" style="width: 16px;"></i>
                    </div>
                    <h2>Property Location</h2>
                </div>
                <div class="section-body">
                    <div class="field">
                        <label>Plot No <span class="req">*</span></label>
                        <input type="text" name="plot_no" id="plot_no" class="inp loc-trigger" placeholder="e.g. 101" required>
                    </div>
                    <div class="field">
                        <label>Street Name</label>
                        <select name="street_name" id="streetSelect" class="inp loc-trigger">
                            <option value="">Select Street</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>District</label>
                        <select name="district" id="districtSelect" class="inp loc-trigger">
                            <option value="">Select District</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>LGA <span class="req">*</span></label>
                        <select name="lga" id="lgaSelect" class="inp loc-trigger" required>
                            <option value="">Select LGA</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Full Address <span class="req">*</span></label>
                        <textarea name="location" id="fullLocation" class="inp" rows="3" required placeholder="Generating from selections..."></textarea>
                    </div>
                </div>
            </section>
        </form>
    </main>

    <!-- BOTTOM NAV -->
    <footer class="bottom-nav">
        <button type="button" class="btn btn-ghost" onclick="resetForm()">
            <i data-lucide="rotate-ccw" style="width: 18px;"></i>
            <span>Clear</span>
        </button>
        <button type="button" id="saveBtn" class="btn btn-primary" onclick="submitForm()">
            <i data-lucide="save" style="width: 18px;"></i>
            <span>Save Record</span>
        </button>
    </footer>

    <!-- TOAST -->
    <div id="toast" class="toast">
        <i data-lucide="info" style="width: 16px;"></i>
        <span id="toastMsg">Message here</span>
    </div>

    <script>
        // Initialization
        try {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        } catch (e) {
            console.error('Lucide error:', e);
        }
        
        let lookupData = null;
        let selectedItems = [];
        let allBanks = [];

        // Fetch Lookup Data
        async function loadLookupData() {
            try {
                const res = await fetch("{{ route('valuation-compensations.mobile.lookup') }}");
                const data = await res.json();
                lookupData = data;

                // Populate Projects
                const pSel = document.getElementById('projectSelect');
                pSel.innerHTML = '<option value="">Select Project</option>';
                if (data.projects) {
                    data.projects.forEach(p => {
                        const displayCode = p.fileno || p.code;
                        pSel.innerHTML += `<option value="${p.id}">${p.name} (${displayCode})</option>`;
                    });
                }

                // Populate Building Types
                const btSel = document.getElementById('buildingType');
                btSel.innerHTML = '<option value="">Select Type</option>';
                if (data.buildingTypes) {
                    data.buildingTypes.forEach(t => {
                        btSel.innerHTML += `<option value="${t.name}">${t.name}</option>`;
                    });
                }
                btSel.innerHTML += `<option value="Other">Other (Please specify)</option>`;

                // Populate Streets
                const stSel = document.getElementById('streetSelect');
                stSel.innerHTML = '<option value="">Select Street</option>';
                if (data.streets) {
                    data.streets.forEach(s => {
                        stSel.innerHTML += `<option value="${s.name}">${s.name}</option>`;
                    });
                }
                stSel.innerHTML += `<option value="Other">Other</option>`;

                // Populate LGAs
                const lgaSel = document.getElementById('lgaSelect');
                lgaSel.innerHTML = '<option value="">Select LGA</option>';
                if (data.lgas) {
                    data.lgas.forEach(l => {
                        lgaSel.innerHTML += `<option value="${l.LGAName}">${l.LGAName}</option>`;
                    });
                }

                // Populate Districts
                const dSel = document.getElementById('districtSelect');
                dSel.innerHTML = '<option value="">Select District</option>';
                if (data.districts) {
                    data.districts.forEach(d => {
                        dSel.innerHTML += `<option value="${d.name}">${d.name}</option>`;
                    });
                }
                dSel.innerHTML += `<option value="Other">Other</option>`;

                // Banks
                allBanks = data.banks || [];

                // Populate Valuation Items
                const itemGrid = document.getElementById('valuationItemsGrid');
                itemGrid.innerHTML = '';
                if (data.valuationItems) {
                    data.valuationItems.forEach(item => {
                        itemGrid.innerHTML += `
                            <div class="check-item ${item.name === 'Others' ? 'special-other' : ''}" data-val="${item.name}">
                                <div class="check-box"><i data-lucide="check" style="width:10px;height:10px;"></i></div>
                                <span class="check-label">${item.name}</span>
                            </div>
                        `;
                    });
                    
                    // Re-initialize click listeners for new items
                    initCheckItems();
                    if (window.lucide) window.lucide.createIcons();
                }

            } catch (err) {
                console.error('Initialization Error:', err);
                showToast('Failed to load system data. Check connection.', 'danger');
            }
        }


        // Bank Search Logic
        const bankSearch = document.getElementById('bankSearch');
        const bankResults = document.getElementById('bankResults');
        const selectedBankLogo = document.getElementById('selectedBankLogo');
        const bankNameVal = document.getElementById('bankNameVal');

        bankSearch.addEventListener('input', function() {
            const q = this.value.toLowerCase();
            if (!q) {
                bankResults.classList.add('hidden');
                return;
            }

            const matches = allBanks.filter(b => b.name.toLowerCase().includes(q));
            if (!matches.length) {
                bankResults.innerHTML = '<div style="padding:16px;text-align:center;font-size:12px;color:var(--text-dim);">No banks found</div>';
            } else {
                bankResults.innerHTML = matches.map(b => `
                    <div class="bank-item" onclick="selectBank('${b.name}', '${b.logo}')" style="padding:12px 16px;display:flex;align-items:center;gap:12px;cursor:pointer;border-bottom:1px solid var(--border);">
                        <div class="selected-logo-wrap">
                            <img src="${b.logo}" alt="" class="bank-logo-img" onerror="this.src='https://ui-avatars.com/api/?name=${b.name}&background=random'">
                        </div>
                        <span style="font-size:13px;font-weight:600;">${b.name}</span>
                    </div>
                `).join('');
            }
            bankResults.classList.remove('hidden');
        });

        window.selectBank = function(title, logo) {
            bankSearch.value = title;
            bankNameVal.value = title;
            bankResults.classList.add('hidden');
            
            selectedBankLogo.querySelector('img').src = logo || `https://ui-avatars.com/api/?name=${title}&background=random`;
            selectedBankLogo.classList.remove('hidden');
            bankSearch.style.paddingLeft = '48px';
            bankSearch.style.fontWeight = '700';
            bankSearch.style.color = '#fff';
        };

        // Close bank dropdown on click outside
        document.addEventListener('click', e => {
            if (!e.target.closest('.inp-wrap')) bankResults.classList.add('hidden');
        });

        // Building Type Other Logic
        document.getElementById('buildingType').addEventListener('change', function() {
            const otherInp = document.getElementById('buildingTypeOther');
            if (this.value === 'Other') {
                otherInp.classList.remove('hidden');
                otherInp.required = true;
            } else {
                otherInp.classList.add('hidden');
                otherInp.required = false;
            }
        });

        // Initialize Check Items functionality
        function initCheckItems() {
            document.querySelectorAll('.check-item').forEach(item => {
                item.onclick = function() {
                    this.classList.toggle('active');
                    const val = this.dataset.val;
                    
                    if (this.classList.contains('active')) {
                        if (!selectedItems.includes(val)) selectedItems.push(val);
                        if (val === 'Others' || val === 'Other') {
                            document.getElementById('compItemsOtherText').classList.remove('hidden');
                        }
                    } else {
                        selectedItems = selectedItems.filter(i => i !== val);
                        if (val === 'Others' || val === 'Other') {
                            document.getElementById('compItemsOtherText').classList.add('hidden');
                        }
                    }
                    
                    document.getElementById('compItemsVal').value = selectedItems.join(', ');
                };
            });
        }

        // Street/District Other Logic (if needed)
        ['streetSelect', 'districtSelect'].forEach(id => {
            document.getElementById(id).addEventListener('change', function() {
                // We could add other inputs here but let's keep it simple for now
            });
        });

        // Project Change -> Load Workers
        document.getElementById('projectSelect').addEventListener('change', async function() {
            const pId = this.value;
            const wSel = document.getElementById('workerSelect');
            const wBadge = document.getElementById('workerBadge');
            const ourRef = document.getElementById('mobile_our_ref');
            const yourRef = document.getElementById('mobile_your_ref');
            
            if (!pId) {
                wSel.disabled = true;
                wSel.innerHTML = '<option value="">Select Project First</option>';
                wBadge.classList.add('hidden');
                document.getElementById('mobile-project-info').classList.add('hidden');
                ourRef.value = '';
                yourRef.value = '';
                document.getElementById('mobile_project_code').value = '';
                document.getElementById('mobile_project_fileno').value = '';
                return;
            }

            // Backfill references and update summary
            if (lookupData && lookupData.projects) {
                const proj = lookupData.projects.find(p => p.id == pId);
                if (proj) {
                    ourRef.value = proj.our_reference || '';
                    yourRef.value = proj.your_reference || '';
                    
                    document.getElementById('mobile_project_code').value = proj.code;
                    document.getElementById('mobile_project_fileno').value = proj.fileno;
                    
                    // Update Summary UI
                    const safeSetText = (id, text) => {
                        const el = document.getElementById(id);
                        if (el) el.textContent = text;
                    };

                    safeSetText('m_proj_id', proj.id);
                    safeSetText('m_proj_fileno', proj.fileno);
                    safeSetText('m_proj_code', proj.code);
                    safeSetText('m_proj_workers', proj.workers_count || 0);
                    safeSetText('m_proj_filled', proj.valuations_count || 0);
                    
                    const infoPanel = document.getElementById('mobile-project-info');
                    if (infoPanel) infoPanel.classList.remove('hidden');
                    if (window.lucide) window.lucide.createIcons();
                }
            }

            wSel.disabled = false;
            wSel.innerHTML = '<option value="">Loading Workers...</option>';
            
            try {
                const res = await fetch(`{{ url('valuation-compensations/mobile/workers') }}/${pId}`);
                const data = await res.json();
                wSel.innerHTML = '<option value="">Select Worker</option>';
                data.workers.forEach(w => {
                    wSel.innerHTML += `<option value="${w.id}" data-code="${w.worker_code}">${w.name}</option>`;
                });
            } catch (err) {
                showToast('Failed to load workers', 'danger');
            }
        });

        document.getElementById('workerSelect').addEventListener('change', function() {
            const code = this.options[this.selectedIndex].dataset.code;
            const badge = document.getElementById('workerBadge');
            if (code) {
                document.getElementById('workerCodeDisplay').textContent = code;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        });

        // Calculation
        function calculateTotal() {
            const count = parseFloat(document.getElementById('buildingCount').value) || 0;
            const area = parseFloat(document.getElementById('areaCovered').value) || 0;
            const rate = parseFloat(document.getElementById('rateOfCost').value) || 0;
            const total = count * area * rate;
            
            // Only auto-update if the user hasn't manually modified it or if we want to provide an estimate
            // In most cases for VFC, the auto-calculation is the baseline.
            document.getElementById('compensation_amount').value = total.toFixed(2);
        }

        ['buildingCount', 'areaCovered', 'rateOfCost'].forEach(id => {
            document.getElementById(id).addEventListener('input', calculateTotal);
        });

        // Items Selection
        document.querySelectorAll('.check-item').forEach(item => {
            item.addEventListener('click', function() {
                this.classList.toggle('active');
                const val = this.dataset.val;
                if (this.classList.contains('active')) {
                    selectedItems.push(val);
                } else {
                    selectedItems = selectedItems.filter(i => i !== val);
                }
                document.getElementById('compItemsVal').value = selectedItems.join(', ');
            });
        });

        // Address Builder
        function buildAddress() {
            const plotNo = document.getElementById('plot_no').value;
            const street = document.getElementById('streetSelect').value;
            const district = document.getElementById('districtSelect').value;
            const lga = document.getElementById('lgaSelect').value;
            
            let parts = [];
            if (plotNo) parts.push('Plot ' + plotNo);
            if (street) parts.push(street);
            if (district) parts.push(district + ' District');
            if (lga) parts.push(lga + ' LGA');
            parts.push('Kano State');
            
            document.getElementById('fullLocation').value = parts.join(', ');
        }

        document.querySelectorAll('.loc-trigger').forEach(el => {
            el.addEventListener('change', buildAddress);
            el.addEventListener('input', buildAddress);
        });

        // Toggle Section Collapse
        document.querySelectorAll('.section-header').forEach(header => {
            header.addEventListener('click', () => {
                const card = header.closest('.section-card');
                card.classList.toggle('collapsed');
            });
            // Add Chevron Icon
            const icon = document.createElement('i');
            icon.setAttribute('data-lucide', 'chevron-down');
            icon.style.width = '16px';
            icon.style.color = 'var(--text-dim)';
            header.appendChild(icon);
        });

        // Navigation Functionality
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', () => {
                const targetId = item.dataset.target;
                const targetSection = document.getElementById(targetId);
                
                // Expand the section if collapsed
                targetSection.classList.remove('collapsed');
                
                // Scroll to it
                const offset = 120; // topbar + navstrip height
                const bodyRect = document.body.getBoundingClientRect().top;
                const elementRect = targetSection.getBoundingClientRect().top;
                const elementPosition = elementRect - bodyRect;
                const offsetPosition = elementPosition - offset;

                window.scrollTo({
                    top: offsetPosition,
                    behavior: 'smooth'
                });
            });
        });

        // Navigation Highlight on Scroll
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const id = entry.target.id;
                    document.querySelectorAll('.nav-item').forEach(item => {
                        item.classList.toggle('active', item.dataset.target === id);
                    });
                }
            });
        }, { threshold: 0.2 });

        document.querySelectorAll('section').forEach(section => observer.observe(section));

        // Submit Form
        async function submitForm() {
            const form = document.getElementById('vfcForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const btn = document.getElementById('saveBtn');
            btn.disabled = true;
            btn.innerHTML = '<div class="spinner" style="width:20px;height:20px;border-width:2px;"></div> Saving...';

            const formData = new FormData(form);
            
            // Explicitly add 'Other' specify values if they are visible
            const btOther = document.getElementById('buildingTypeOther');
            if (!btOther.classList.contains('hidden')) {
                formData.set('building_type_other', btOther.value);
            }
            
            const ciOther = document.getElementById('compItemsOtherText');
            if (!ciOther.classList.contains('hidden')) {
                formData.set('compensated_items_other', ciOther.value);
            }

            const data = Object.fromEntries(formData.entries());

            try {
                const res = await fetch("{{ route('valuation-compensations.mobile.save') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(data)
                });

                const result = await res.json();
                if (result.success) {
                    showToast('✅ Record Saved: ' + result.file_number, 'success');
                    resetForm();
                } else {
                    showToast('❌ ' + result.message, 'danger');
                }
            } catch (err) {
                showToast('❌ Server Error', 'danger');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i data-lucide="save" style="width: 18px;"></i> <span>Save Record</span>';
                lucide.createIcons();
            }
        }

        function resetForm() {
            if (!confirm('Clear all entries?')) return;
            document.getElementById('vfcForm').reset();
            selectedItems = [];
            document.querySelectorAll('.check-item').forEach(i => i.classList.remove('active'));
            document.getElementById('totalDisplay').textContent = '₦ 0.00';
            document.getElementById('workerBadge').classList.add('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function showToast(msg, type) {
            const t = document.getElementById('toast');
            const tm = document.getElementById('toastMsg');
            tm.textContent = msg;
            t.style.borderColor = type === 'success' ? 'var(--success)' : 'var(--danger)';
            t.classList.add('show');
            setTimeout(() => t.classList.remove('show'), 3000);
        }

        // Start when DOM is ready
        document.addEventListener('DOMContentLoaded', () => {
            loadLookupData();
        });
    </script>
</body>
</html>

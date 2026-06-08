@extends('phs.layouts.app')

@section('title', 'PHS Portal - Payment Verification')

 @section('content')
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body { font-family: 'DM Sans', sans-serif; background: #f0f2ff; min-height: 100vh; }

    .blob-1 { position: fixed; top: -120px; left: -120px; width: 480px; height: 480px; border-radius: 50%; background: #6c63ff; opacity: .14; pointer-events: none; z-index: 0; }
    .blob-2 { position: fixed; bottom: -160px; right: -100px; width: 520px; height: 520px; border-radius: 50%; background: #06d6a0; opacity: .11; pointer-events: none; z-index: 0; }
    .blob-3 { position: fixed; top: 40%; right: 6%; width: 240px; height: 240px; border-radius: 50%; background: #6c63ff; opacity: .06; pointer-events: none; z-index: 0; }

    .navbar { position: sticky; top: 0; z-index: 50; display: flex; align-items: center; justify-content: space-between; padding: 14px 32px; background: rgba(255,255,255,.88); backdrop-filter: blur(16px); border-bottom: 1px solid rgba(108,99,255,.10); box-shadow: 0 1px 0 rgba(0,0,0,.04); }
    .navbar-brand { display: flex; align-items: center; gap: 10px; }
    .navbar-brand img { height: 36px; width: auto; }
    .navbar-brand span { font-family: 'Sora', sans-serif; font-size: 13px; font-weight: 600; color: #3d3a4e; letter-spacing: .04em; }
    .navbar-actions { display: flex; align-items: center; gap: 10px; }
    .btn-ghost { padding: 8px 18px; border: 1.5px solid #d0cfe8; border-radius: 8px; background: transparent; font-size: 13px; font-weight: 500; color: #3d3a4e; text-decoration: none; cursor: pointer; transition: background .15s; }
    .btn-ghost:hover { background: #f4f3ff; }
    .btn-primary { padding: 8px 18px; border: none; border-radius: 8px; background: #6c63ff; font-size: 13px; font-weight: 500; color: #fff; text-decoration: none; cursor: pointer; transition: background .15s; }
    .btn-primary:hover { background: #5a52e0; }
    .mobile-menu-btn { display: none; border: none; background: transparent; padding: 8px; cursor: pointer; border-radius: 8px; }
    .mobile-menu { display: none; flex-direction: column; gap: 10px; padding: 16px 32px; background: rgba(255,255,255,.96); border-bottom: 1px solid rgba(108,99,255,.10); }
    .mobile-menu a { text-align: center; }
    @media (max-width: 768px) { .navbar-actions { display: none; } .mobile-menu-btn { display: flex; align-items: center; justify-content: center; } .mobile-menu.open { display: flex; } }

    .page-wrapper { position: relative; z-index: 1; max-width: 1060px; margin: 0 auto; padding: 40px 20px 80px; }

    .step-badge { display: inline-flex; align-items: center; gap: 6px; font-family: 'Sora', sans-serif; font-size: 11px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #6c63ff; background: #ededff; border-radius: 20px; padding: 5px 14px; margin-bottom: 28px; }
    .step-badge::before { content: ''; display: inline-block; width: 6px; height: 6px; background: #6c63ff; border-radius: 50%; }

    .main-grid { display: grid; grid-template-columns: 1fr 1.55fr; gap: 24px; align-items: start; }
    @media (max-width: 820px) { .main-grid { grid-template-columns: 1fr; } }

    /* Sidebar */
    .sidebar-card { background: #6c63ff; border-radius: 20px; padding: 32px 28px; color: #fff; position: sticky; top: 84px; }
    .sb-logo { height: 30px; width: auto; margin-bottom: 24px; filter: brightness(0) invert(1); opacity: .85; }
    .sb-eyebrow { font-size: 10px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: rgba(255,255,255,.55); margin-bottom: 10px; }
    .sb-title { font-family: 'Sora', sans-serif; font-size: 22px; font-weight: 700; line-height: 1.25; color: #fff; margin-bottom: 14px; }
    .sb-desc { font-size: 13px; line-height: 1.7; color: rgba(255,255,255,.72); }
    .amount-box { margin-top: 22px; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.18); border-radius: 14px; padding: 18px; }
    .amt-label { font-size: 11px; color: rgba(255,255,255,.6); margin-bottom: 5px; }
    .amt-value { font-family: 'Sora', sans-serif; font-size: 32px; font-weight: 700; color: #fff; letter-spacing: -.5px; }
    .token-tag { display: inline-block; margin-top: 7px; background: rgba(255,255,255,.14); border-radius: 6px; font-size: 11px; font-weight: 500; color: rgba(255,255,255,.8); padding: 3px 9px; }
    .sum-list { margin-top: 20px; display: flex; flex-direction: column; gap: 11px; }
    .sum-row { display: flex; justify-content: space-between; align-items: baseline; }
    .s-lbl { font-size: 11px; color: rgba(255,255,255,.55); }
    .s-val { font-size: 12px; font-weight: 500; color: rgba(255,255,255,.88); text-align: right; max-width: 58%; word-break: break-word; }

    /* Form card */
    .form-card { background: #fff; border-radius: 20px; border: 1px solid rgba(108,99,255,.10); box-shadow: 0 4px 24px rgba(108,99,255,.07); padding: 32px 32px; }
    .form-title { font-family: 'Sora', sans-serif; font-size: 16px; font-weight: 700; color: #1e1b3a; margin-bottom: 4px; }
    .form-sub { font-size: 13px; color: #6b6880; margin-bottom: 22px; }
    .section-divider { height: 1px; background: #eeecfa; margin: 22px 0; }
    .section-title { font-family: 'Sora', sans-serif; font-size: 13px; font-weight: 700; color: #1e1b3a; letter-spacing: .04em; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
    .section-title .icon { width: 28px; height: 28px; border-radius: 8px; background: #ededff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .section-title .icon svg { width: 14px; height: 14px; stroke: #6c63ff; fill: none; stroke-width: 2; }

    /* Payment method selector */
    .method-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 24px; }
    @media (max-width: 540px) { .method-grid { grid-template-columns: 1fr; } }
    .method-card { position: relative; cursor: pointer; border: 1.5px solid #e2e0f0; border-radius: 12px; padding: 14px 14px 12px; background: #fafafa; transition: border-color .2s, background .2s; display: flex; flex-direction: column; align-items: flex-start; gap: 6px; }
    .method-card:hover { border-color: #b0aee0; background: #f7f6ff; }
    .method-card.selected { border-color: #6c63ff; background: #f5f4ff; box-shadow: 0 0 0 3px rgba(108,99,255,.10); }
    .method-card input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
    .method-logo { height: 22px; display: flex; align-items: center; }
    .method-logo img { height: 22px; width: auto; object-fit: contain; }
    .method-logo .logo-text { font-family: 'Sora', sans-serif; font-size: 13px; font-weight: 700; color: #1e1b3a; }
    .method-logo .logo-text.interswitch { color: #e05a00; }
    .method-logo .logo-text.paystack { color: #0ba4db; }
    .method-name { font-size: 11px; color: #8b88aa; font-weight: 500; }
    .method-badge { font-size: 10px; font-weight: 700; color: #6c63ff; background: #ededff; border-radius: 4px; padding: 2px 7px; }
    .mcheck { position: absolute; top: 10px; right: 10px; width: 16px; height: 16px; border-radius: 50%; border: 1.5px solid #d0cfe8; background: #fff; display: flex; align-items: center; justify-content: center; transition: all .15s; }
    .method-card.selected .mcheck { background: #6c63ff; border-color: #6c63ff; }
    .mcheck svg { width: 8px; height: 8px; stroke: #fff; fill: none; stroke-width: 2.5; opacity: 0; transition: opacity .15s; }
    .method-card.selected .mcheck svg { opacity: 1; }

    /* Bank details panel */
    .bank-box { background: #f7f6ff; border: 1.5px solid #e0deff; border-radius: 14px; padding: 18px 20px; margin-bottom: 20px; }
    .bank-box h4 { font-size: 12px; font-weight: 700; color: #1e1b3a; margin-bottom: 14px; display: flex; align-items: center; gap: 6px; }
    .bank-row { display: flex; justify-content: space-between; align-items: center; font-size: 12px; padding: 8px 0; border-bottom: 1px solid #e8e7f5; }
    .bank-row:last-child { border-bottom: none; }
    .bk-lbl { color: #8b88aa; font-weight: 500; }
    .bk-val { color: #1e1b3a; font-weight: 600; }
    .bk-accent { color: #6c63ff; font-size: 13px; letter-spacing: .03em; }
    .copy-btn { display: inline-flex; align-items: center; gap: 4px; font-size: 10px; font-weight: 600; color: #6c63ff; background: #ededff; border: none; border-radius: 6px; padding: 3px 8px; cursor: pointer; }
    .copy-btn:hover { background: #dddcff; }

    /* Online payment notice */
    .online-notice { background: #f7f6ff; border: 1.5px solid #e0deff; border-radius: 14px; padding: 18px 20px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 12px; }
    .online-notice svg { width: 18px; height: 18px; stroke: #6c63ff; flex-shrink: 0; margin-top: 1px; }
    .online-notice p { font-size: 13px; color: #4a4760; line-height: 1.6; }
    .online-notice strong { color: #1e1b3a; font-weight: 600; }

    /* Fields */
    .field-group { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
    @media (max-width: 540px) { .field-group { grid-template-columns: 1fr; } }
    .field-label { display: block; font-size: 12px; font-weight: 600; color: #4a4760; margin-bottom: 7px; letter-spacing: .03em; }
    .field-input { width: 100%; padding: 11px 16px; background: #fafafa; border: 1.5px solid #e2e0f0; border-radius: 10px; font-family: inherit; font-size: 13px; color: #1e1b3a; outline: none; transition: border-color .2s, box-shadow .2s; }
    .field-input:focus { border-color: #6c63ff; box-shadow: 0 0 0 3px rgba(108,99,255,.12); background: #fff; }
    .field-input[readonly] { background: #f2f1fa; color: #6b6880; cursor: not-allowed; }
    .field-input::placeholder { color: #bcbbcc; }
    .field-hint { font-size: 11px; color: #9b98b5; margin-top: 6px; line-height: 1.5; }

    .error-box { background: #fff5f5; border: 1px solid #ffc5c5; border-radius: 12px; padding: 14px 18px; margin-bottom: 20px; }
    .error-box p { font-size: 13px; font-weight: 600; color: #c0392b; margin-bottom: 8px; }
    .error-box ul { padding-left: 18px; }
    .error-box li { font-size: 13px; color: #c0392b; line-height: 1.7; }

    .action-row { display: flex; align-items: center; justify-content: space-between; margin-top: 28px; gap: 12px; flex-wrap: wrap; }
    .btn-back { display: inline-flex; align-items: center; gap: 7px; padding: 11px 22px; border-radius: 10px; border: 1.5px solid #d6d4ee; background: #fff; font-size: 13px; font-weight: 600; color: #4a4760; text-decoration: none; cursor: pointer; transition: background .15s; }
    .btn-back:hover { background: #f4f3ff; }
    .btn-submit { display: inline-flex; align-items: center; gap: 8px; padding: 12px 30px; border-radius: 10px; border: none; background: #6c63ff; font-size: 14px; font-weight: 600; color: #fff; cursor: pointer; transition: background .15s, transform .1s; box-shadow: 0 4px 18px rgba(108,99,255,.35); }
    .btn-submit:hover { background: #5a52e0; }
    .btn-submit:active { transform: scale(.98); }

    .toast { position: fixed; bottom: 24px; right: 24px; z-index: 999; background: #1e1b3a; color: #fff; font-size: 13px; font-weight: 500; padding: 10px 18px; border-radius: 10px; opacity: 0; pointer-events: none; transform: translateY(8px); transition: opacity .25s, transform .25s; }
    .toast.show { opacity: 1; pointer-events: auto; transform: translateY(0); }

    /* Hide irrelevant panels */
    .panel-bank, .panel-interswitch, .panel-paystack { display: none; }
    .panel-bank.active, .panel-interswitch.active, .panel-paystack.active { display: block; }
</style>
 


<div class="blob-1"></div>
<div class="blob-2"></div>
<div class="blob-3"></div>

<nav class="navbar">
    <div class="navbar-brand">
        <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES">
        <span>PHS Portal</span>
    </div>
    <div class="navbar-actions">
        <a href="{{ route('phs.login') }}" class="btn-ghost">Sign In</a>
        <a href="{{ route('phs.request.form') }}" class="btn-primary">Request Access</a>
    </div>
    <button id="mobile-menu-btn" class="mobile-menu-btn" aria-label="Menu">
        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
    </button>
</nav>
<div id="mobile-menu" class="mobile-menu">
    <a href="{{ route('phs.login') }}" class="btn-ghost">Sign In</a>
    <a href="{{ route('phs.request.form') }}" class="btn-primary" style="text-align:center">Request Access</a>
</div>

<div class="page-wrapper">
    <div class="step-badge">Step 2 of 2 — Payment Verification</div>

    <div class="main-grid">

        {{-- Sidebar --}}
        <div class="sidebar-card">
            <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES" class="sb-logo">
            <div class="sb-eyebrow">Application Summary</div>
            <div class="sb-title">Complete Your Payment</div>
            <div class="sb-desc">Choose a payment method below and follow the instructions to finalise your application.</div>

            <div class="amount-box">
                <div class="amt-label">Amount Due</div>
                <div class="amt-value">₦{{ number_format($amount) }}</div>
                <span class="token-tag">{{ $validated['initial_token_package'] }}</span>
            </div>

            <div class="sum-list">
                <div class="sum-row">
                    <span class="s-lbl">Organization</span>
                    <span class="s-val">{{ $validated['organization_name'] }}</span>
                </div>
                <div class="sum-row">
                    <span class="s-lbl">Contact</span>
                    <span class="s-val">{{ $validated['contact_name'] }}</span>
                </div>
                <div class="sum-row">
                    <span class="s-lbl">Email</span>
                    <span class="s-val" style="font-size:11px">{{ $validated['contact_email'] }}</span>
                </div>
            </div>
        </div>

        {{-- Form card --}}
        <div class="form-card">
            <div class="form-title">Select Payment Method</div>
            <div class="form-sub">Choose how you'd like to pay for your token package.</div>

            {{-- Payment method picker --}}
            <div class="method-grid">
                {{-- Bank Transfer (default) --}}
                <label class="method-card selected" data-method="bank">
                    <input type="radio" name="payment_method" value="bank_transfer" checked>
                    <div class="mcheck"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
                    <div class="method-logo">
                        <svg width="18" height="18" fill="none" stroke="#1e1b3a" stroke-width="2" viewBox="0 0 24 24" style="margin-right:5px"><path d="M3 21h18M3 10h18M12 3 2 10h20L12 3z"/></svg>
                        <span class="logo-text">Bank Transfer</span>
                    </div>
                    <div class="method-name">Manual transfer</div>
                    <div class="method-badge">Default</div>
                </label>

                {{-- Interswitch --}}
                <label class="method-card" data-method="interswitch">
                    <input type="radio" name="payment_method" value="interswitch">
                    <div class="mcheck"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
                    <div class="method-logo">
                        <span class="logo-text interswitch">Interswitch</span>
                    </div>
                    <div class="method-name">Card / Webpay</div>
                </label>

                {{-- Paystack --}}
                <label class="method-card" data-method="paystack">
                    <input type="radio" name="payment_method" value="paystack">
                    <div class="mcheck"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
                    <div class="method-logo">
                        <span class="logo-text paystack">Paystack</span>
                    </div>
                    <div class="method-name">Card / Bank / USSD</div>
                </label>
            </div>

            <div class="section-divider"></div>

            @if ($errors->any())
            <div class="error-box">
                <p>Please fix the following:</p>
                <ul>
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
            @endif

            <form method="POST" action="{{ route('phs.request.submit') }}" id="paymentForm">
                @csrf
                <input type="hidden" name="payment_method" id="hiddenMethod" value="bank_transfer">

                {{-- ── Bank Transfer panel ── --}}
                <div class="panel-bank active" id="panel-bank">
                    <div class="section-title">
                        <span class="icon"><svg viewBox="0 0 24 24"><path d="M3 21h18M3 10h18M12 3 2 10h20L12 3z"/></svg></span>
                        Bank Account Details
                    </div>
                    <div class="bank-box">
                        <h4>
                            <svg width="14" height="14" fill="none" stroke="#6c63ff" stroke-width="2.2" viewBox="0 0 24 24"><path d="M3 21h18M3 10h18M12 3 2 10h20L12 3z"/></svg>
                            Transfer ₦{{ number_format($amount) }} to this account
                        </h4>
                        <div class="bank-row">
                            <span class="bk-lbl">Bank Name</span>
                            <span class="bk-val">First Bank</span>
                        </div>
                        <div class="bank-row">
                            <span class="bk-lbl">Account Name</span>
                            <span class="bk-val">KLAES Enterprise</span>
                        </div>
                        <div class="bank-row">
                            <span class="bk-lbl">Account Number</span>
                            <span style="display:flex;align-items:center;gap:9px">
                                <span class="bk-val bk-accent" id="acct-num">1234567890</span>
                                <button type="button" class="copy-btn" onclick="copyAcct()">
                                    <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                    Copy
                                </button>
                            </span>
                        </div>
                    </div>

                    <div class="field-group">
                        <div>
                            <label class="field-label">Payment Amount (NGN)</label>
                            <input type="number" name="payment_amount" value="{{ $amount }}" readonly class="field-input">
                        </div>
                        <div>
                            <label class="field-label">Bank Transfer Reference *</label>
                            <input type="text" name="payment_reference" value="{{ old('payment_reference') }}" required
                                placeholder="e.g. TRF-20260607-001" class="field-input">
                        </div>
                    </div>
                    <p class="field-hint">Find the reference in your bank's transaction receipt, SMS alert, or account statement.</p>
                </div>

                {{-- ── Interswitch panel ── --}}
                <div class="panel-interswitch" id="panel-interswitch">
                    <div class="online-notice">
                        <svg fill="none" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
                        <p>You will be redirected to the <strong>Interswitch Webpay</strong> gateway to complete your payment of <strong>₦{{ number_format($amount) }}</strong> securely. Your application will be activated once payment is confirmed.</p>
                    </div>
                </div>

                {{-- ── Paystack panel ── --}}
                <div class="panel-paystack" id="panel-paystack">
                    <div class="online-notice">
                        <svg fill="none" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
                        <p>You will be redirected to <strong>Paystack</strong> to complete your payment of <strong>₦{{ number_format($amount) }}</strong> via card, bank transfer, or USSD. Your application will be activated once payment is confirmed.</p>
                    </div>
                </div>

                <div class="action-row">
                    <a href="{{ route('phs.request.form') }}" class="btn-back">
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
                        Back
                    </a>
                    <button type="submit" class="btn-submit" id="submitBtn">
                        <span id="submitLabel">Complete Application</span>
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="toast" id="toast">Account number copied!</div>

<script>
    document.getElementById('mobile-menu-btn').addEventListener('click', function() {
        document.getElementById('mobile-menu').classList.toggle('open');
    });

    const methodCards = document.querySelectorAll('.method-card');
    const hiddenMethod = document.getElementById('hiddenMethod');
    const submitLabel = document.getElementById('submitLabel');

    const labels = {
        bank: 'Complete Application',
        interswitch: 'Pay with Interswitch →',
        paystack: 'Pay with Paystack →'
    };

    function selectMethod(method) {
        methodCards.forEach(c => c.classList.toggle('selected', c.dataset.method === method));
        document.querySelectorAll('[id^="panel-"]').forEach(p => p.classList.remove('active'));
        document.getElementById('panel-' + method).classList.add('active');
        hiddenMethod.value = method === 'bank' ? 'bank_transfer' : method;
        submitLabel.textContent = labels[method] || 'Complete Application';

        // bank transfer reference is only required for bank method
        const ref = document.querySelector('input[name="payment_reference"]');
        if (ref) ref.required = method === 'bank';
    }

    methodCards.forEach(card => {
        card.addEventListener('click', () => selectMethod(card.dataset.method));
    });

    function copyAcct() {
        const num = document.getElementById('acct-num').textContent.trim();
        navigator.clipboard.writeText(num).then(() => {
            const t = document.getElementById('toast');
            t.classList.add('show');
            setTimeout(() => t.classList.remove('show'), 2200);
        });
    }
</script>
@endsection
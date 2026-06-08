@extends('phs.layouts.app')

@section('title', 'PHS Portal - Access Request')

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
    .sb-desc { font-size: 13px; line-height: 1.7; color: rgba(255,255,255,.72); margin-bottom: 24px; }

    /* Package cards in sidebar */
    .pkg-list { display: flex; flex-direction: column; gap: 10px; }
    .pkg-item { background: rgba(255,255,255,.10); border: 1px solid rgba(255,255,255,.14); border-radius: 12px; padding: 13px 15px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: background .15s; }
    .pkg-item:hover { background: rgba(255,255,255,.17); }
    .pkg-item.active { background: rgba(255,255,255,.22); border-color: rgba(255,255,255,.45); }
    .pk-name { font-size: 13px; font-weight: 600; color: #fff; }
    .pk-tok { font-size: 10px; color: rgba(255,255,255,.55); margin-top: 2px; }
    .pk-right { display: flex; align-items: center; gap: 8px; }
    .pk-price { font-family: 'Sora', sans-serif; font-size: 14px; font-weight: 700; color: #fff; }
    .pk-dot { width: 8px; height: 8px; border-radius: 50%; border: 2px solid rgba(255,255,255,.35); transition: all .15s; flex-shrink: 0; }
    .pkg-item.active .pk-dot { background: #fff; border-color: #fff; }

    /* Form card */
    .form-card { background: #fff; border-radius: 20px; border: 1px solid rgba(108,99,255,.10); box-shadow: 0 4px 24px rgba(108,99,255,.07); padding: 32px 32px; }
    .section-title { font-family: 'Sora', sans-serif; font-size: 13px; font-weight: 700; color: #1e1b3a; letter-spacing: .04em; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
    .section-title .icon { width: 28px; height: 28px; border-radius: 8px; background: #ededff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .section-title .icon svg { width: 14px; height: 14px; stroke: #6c63ff; fill: none; stroke-width: 2; }
    .section-divider { height: 1px; background: #eeecfa; margin: 24px 0; }

    /* Fields */
    .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
    @media (max-width: 540px) { .field-row { grid-template-columns: 1fr; } }
    .field { display: flex; flex-direction: column; }
    .field-label { font-size: 12px; font-weight: 600; color: #4a4760; margin-bottom: 7px; letter-spacing: .03em; }
    .field-label .req { color: #6c63ff; margin-left: 2px; }
    .field-input, .field-select, .field-textarea { width: 100%; padding: 11px 16px; background: #fafafa; border: 1.5px solid #e2e0f0; border-radius: 10px; font-family: inherit; font-size: 13px; color: #1e1b3a; outline: none; transition: border-color .2s, box-shadow .2s; appearance: none; }
    .field-input:focus, .field-select:focus, .field-textarea:focus { border-color: #6c63ff; box-shadow: 0 0 0 3px rgba(108,99,255,.12); background: #fff; }
    .field-input::placeholder, .field-textarea::placeholder { color: #bcbbcc; }
    .select-wrap { position: relative; }
    .select-wrap::after { content: ''; position: absolute; right: 14px; top: 50%; transform: translateY(-50%); width: 0; height: 0; border-left: 4px solid transparent; border-right: 4px solid transparent; border-top: 5px solid #8b88aa; pointer-events: none; }
    .field-select { padding-right: 36px; }
    .field-textarea { resize: vertical; min-height: 90px; line-height: 1.6; }

    /* Error */
    .error-box { background: #fff5f5; border: 1px solid #ffc5c5; border-radius: 12px; padding: 14px 18px; margin-bottom: 24px; }
    .error-box p { font-size: 13px; font-weight: 600; color: #c0392b; margin-bottom: 8px; }
    .error-box ul { padding-left: 18px; }
    .error-box li { font-size: 13px; color: #c0392b; line-height: 1.7; }

    /* Actions */
    .action-row { display: flex; align-items: center; justify-content: space-between; margin-top: 30px; gap: 12px; flex-wrap: wrap; }
    .btn-cancel { display: inline-flex; align-items: center; gap: 7px; padding: 11px 22px; border-radius: 10px; border: 1.5px solid #d6d4ee; background: #fff; font-size: 13px; font-weight: 600; color: #4a4760; text-decoration: none; cursor: pointer; transition: background .15s; }
    .btn-cancel:hover { background: #f4f3ff; }
    .btn-submit { display: inline-flex; align-items: center; gap: 8px; padding: 12px 30px; border-radius: 10px; border: none; background: #6c63ff; font-size: 14px; font-weight: 600; color: #fff; cursor: pointer; transition: background .15s, transform .1s; box-shadow: 0 4px 18px rgba(108,99,255,.35); }
    .btn-submit:hover { background: #5a52e0; }
    .btn-submit:active { transform: scale(.98); }
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
        <a href="{{ route('phs.landing') }}" class="btn-primary">Home</a>
    </div>
    <button id="mobile-menu-btn" class="mobile-menu-btn" aria-label="Menu">
        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
    </button>
</nav>
<div id="mobile-menu" class="mobile-menu">
    <a href="{{ route('phs.login') }}" class="btn-ghost">Sign In</a>
    <a href="{{ route('phs.landing') }}" class="btn-primary" style="text-align:center">Home</a>
</div>

<div class="page-wrapper">
    <div class="step-badge">Step 1 of 2 — Access Request</div>

    <div class="main-grid">

        {{-- Sidebar --}}
        <div class="sidebar-card">
            <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES" class="sb-logo">
            <div class="sb-eyebrow">Kano State Ministry of Land &amp; Physical Planning</div>
            <div class="sb-title">Property History Search (PHS)</div>
            <div class="sb-desc">Request institutional access to search official property records. Select a token package to continue.</div>

            <div class="pkg-list">
                @foreach ($packages as $pkg)
                <div class="pkg-item" data-pkg="{{ $pkg['name'] }}">
                    <div><div class="pk-name">{{ $pkg['name'] }}</div><div class="pk-tok">{{ number_format($pkg['tokens']) }} tokens</div></div>
                    <div class="pk-right"><span class="pk-price">₦{{ number_format($pkg['price']) }}</span><span class="pk-dot"></span></div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Form card --}}
        <div class="form-card">

            @if ($errors->any())
            <div class="error-box">
                <p>Please fix the following:</p>
                <ul>
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
            @endif

            <form method="POST" action="{{ route('phs.request.confirm') }}" id="requestForm">
                @csrf

                {{-- Hidden select for token package --}}
                <select name="initial_token_package" id="tokenPackage" required
                    style="position:absolute;opacity:0;pointer-events:none;width:0;height:0" aria-hidden="true">
                    <option value="">Select a package</option>
                    @foreach ($packages as $pkg)
                    <option value="{{ $pkg['name'] }}" {{ (old('initial_token_package') ?? $package) === $pkg['name'] ? 'selected' : '' }}>{{ $pkg['name'] }}</option>
                    @endforeach
                </select>

                {{-- Organization --}}
                <div class="section-title">
                    <span class="icon"><svg viewBox="0 0 24 24"><path d="M3 21h18M3 10h18M12 3 2 10h20L12 3z"/></svg></span>
                    Organization Information
                </div>
                <div class="field-row">
                    <div class="field">
                        <label class="field-label">Organization Name <span class="req">*</span></label>
                        <input type="text" name="organization_name" value="{{ old('organization_name') }}" required placeholder="e.g. Acme Law Firm" class="field-input">
                    </div>
                    <div class="field">
                        <label class="field-label">Organization Type <span class="req">*</span></label>
                        <div class="select-wrap">
                            <select name="organization_type" required class="field-select">
                                <option value="">Select type</option>
                                <option value="bank"      {{ old('organization_type') === 'bank'      ? 'selected' : '' }}>Bank</option>
                                <option value="law_firm"  {{ old('organization_type') === 'law_firm'  ? 'selected' : '' }}>Law Firm</option>
                                <option value="corporate" {{ old('organization_type') === 'corporate' ? 'selected' : '' }}>Corporate</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="field-row">
                    <div class="field">
                        <label class="field-label">Phone</label>
                        <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="+234 800 000 0000" class="field-input">
                    </div>
                    <div class="field">
                        <label class="field-label">Address</label>
                        <input type="text" name="address" value="{{ old('address') }}" placeholder="Street, City" class="field-input">
                    </div>
                </div>

                <div class="section-divider"></div>

                {{-- Contact --}}
                <div class="section-title">
                    <span class="icon"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                    Contact Information
                </div>
                <div class="field-row">
                    <div class="field">
                        <label class="field-label">First Name <span class="req">*</span></label>
                        <input type="text" name="contact_first_name" value="{{ old('contact_first_name') }}" required placeholder="First name" class="field-input">
                    </div>
                    <div class="field">
                        <label class="field-label">Last Name <span class="req">*</span></label>
                        <input type="text" name="contact_last_name" value="{{ old('contact_last_name') }}" required placeholder="Last name" class="field-input">
                    </div>
                </div>
                <div class="field-row">
                    <div class="field">
                        <label class="field-label">Email Address <span class="req">*</span></label>
                        <input type="email" name="contact_email" value="{{ old('contact_email') }}" required placeholder="you@organization.com" class="field-input">
                    </div>
                    <div class="field">
                        <label class="field-label">Confirm Email Address <span class="req">*</span></label>
                        <input type="email" name="contact_email_confirmation" value="{{ old('contact_email_confirmation') }}" required placeholder="Retype your email" class="field-input">
                    </div>
                </div>
                <div class="field-row">
                    <div class="field">
                        <label class="field-label">Job Title</label>
                        <input type="text" name="job_title" value="{{ old('job_title') }}" placeholder="e.g. Legal Officer" class="field-input">
                    </div>
                    <div class="field">
                        <label class="field-label">Department</label>
                        <input type="text" name="department" value="{{ old('department') }}" placeholder="e.g. Compliance" class="field-input">
                    </div>
                </div>
               

                <div class="section-divider"></div>

                {{-- Notes --}}
                <div class="section-title">
                    <span class="icon"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></span>
                    Additional Notes
                </div>
                <div class="field">
                    <textarea name="additional_notes" rows="3" placeholder="Any special requirements or questions?" class="field-textarea">{{ old('additional_notes') }}</textarea>
                </div>

                <div class="action-row">
                    <a href="{{ route('phs.landing') }}" class="btn-cancel">
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        Cancel
                    </a>
                    <button type="submit" class="btn-submit">
                        Continue to Payment
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.getElementById('mobile-menu-btn').addEventListener('click', function() {
        document.getElementById('mobile-menu').classList.toggle('open');
    });

    const pkgItems = document.querySelectorAll('.pkg-item');
    const tokenSel = document.getElementById('tokenPackage');
    const preselect = tokenSel.value;

    function selectPkg(value) {
        pkgItems.forEach(i => i.classList.toggle('active', i.dataset.pkg === value));
        tokenSel.value = value;
    }

    if (preselect) selectPkg(preselect);

    pkgItems.forEach(item => {
        item.addEventListener('click', () => selectPkg(item.dataset.pkg));
    });

    // Warn if no package selected on submit
    document.getElementById('requestForm').addEventListener('submit', function(e) {
        if (!tokenSel.value) {
            e.preventDefault();
            alert('Please select a token package from the sidebar before continuing.');
        }
    });
</script>
@endsection
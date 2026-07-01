<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>Online Legal Search Report — KLAES</title>
  <style>
    body { background-color: #525659; font-family: Arial, sans-serif; margin: 0; padding: 10px; display: flex; flex-direction: column; align-items: center; }

    /* Action bar (hidden when printing) */
    .action-bar { width: 11in; max-width: 100%; display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 12px; }
    .action-bar a, .action-bar button { font-family: Arial, sans-serif; font-size: 13px; font-weight: 600; padding: 9px 16px; border-radius: 8px; border: 0; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
    .btn-light { background: #e2e8f0; color: #0f172a; }
    .btn-primary { background: #2563eb; color: #fff; }
    .btn-green { background: #16a34a; color: #fff; }

    /* Payment card */
    .pay-wrap { width: 520px; max-width: 100%; background: #fff; border-radius: 14px; box-shadow: 0 10px 40px rgba(0,0,0,.25); overflow: hidden; }
    .pay-head { background: linear-gradient(135deg,#2563eb,#7c3aed); color: #fff; padding: 24px; }
    .pay-head h1 { margin: 0 0 4px; font-size: 20px; }
    .pay-head p { margin: 0; font-size: 13px; opacity: .9; }
    .pay-body { padding: 24px; color: #1f2937; }
    .pay-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eef2f7; font-size: 14px; }
    .pay-row .lbl { color: #6b7280; }
    .pay-row .val { font-weight: 700; }
    .pay-total { font-size: 26px; font-weight: 800; color: #16a34a; }
    .pay-btn { width: 100%; margin-top: 18px; padding: 13px; font-size: 16px; font-weight: 700; color: #fff; background: #16a34a; border: 0; border-radius: 10px; cursor: pointer; }
    .pay-btn:disabled { opacity: .6; cursor: not-allowed; }
    .pay-note { font-size: 12px; color: #6b7280; margin-top: 14px; text-align: center; }
    .pay-error { display:none; margin-top: 12px; background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; padding:10px; border-radius:8px; font-size:13px; }

    /* Report page (from ONLINE-demo.html) */
    .page { background-color: #fff; width: 11in; min-height: 8.5in; padding: 0.2in 0.4in; position: relative; box-sizing: border-box; display: flex; flex-direction: column; overflow: hidden; }
    .watermark { position: absolute; left: 50%; transform: translate(-50%, -50%) rotate(-30deg); white-space: nowrap; font-size: 52px; font-weight: 900; letter-spacing: 2px; color: rgba(0,0,0,.14); text-transform: uppercase; text-align: center; pointer-events: none; z-index: 0; }
    .watermark.wm-top { top: 50%; }
    .bg-watermark { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); width: 45%; max-width: 460px; opacity: .06; pointer-events: none; z-index: 0; }
    header { width: 100%; border-bottom: 2px solid #000; padding-bottom: 2px; margin-bottom: 6px; position: relative; z-index: 1; }
    .header-top { display: flex; justify-content: space-between; align-items: center; }
    .logo-box img { width: 75px; height: auto; }
    .title-block { text-align: center; flex: 1; }
    .title-block h1 { font-size: 19px; color: #007a33; margin: 0; font-weight: bold; }
    .title-block h2 { font-size: 15px; margin: 2px 0; font-weight: bold; }
    .title-block h3 { font-size: 13px; margin: 0; text-decoration: underline; font-weight: bold; }
    .date-line { text-align: right; font-size: 13px; font-weight: bold; margin-top: 2px; position: relative; z-index: 1; }
    .section-label { border: 1px solid #000; display: inline-block; padding: 1px 6px; font-weight: bold; font-size: 11px; margin: 3px 0; background: #fff; position: relative; z-index: 1; }
    .prop-details { font-size: 12px; margin-bottom: 5px; border-collapse: collapse; width: 100%; table-layout: fixed; position: relative; z-index: 1; }
    .prop-details td { padding: 1px 0; vertical-align: top; }
    .bold-lbl-left { font-weight: bold; width: 125px; }
    .bold-lbl-right { font-weight: bold; width: 110px; padding-left: 35px; }
    .transaction-table { width: 100%; border-collapse: collapse; font-size: 10px; table-layout: fixed; margin-bottom: 6px; position: relative; z-index: 1; }
    .transaction-table th, .transaction-table td { padding: 4px; vertical-align: top; border-bottom: .5px solid #eee; word-wrap: break-word; }
    .transaction-table .header-row th { font-weight: bold; border-bottom: 1.5px solid #000; text-align: left; }
    .transaction-table .instrument-cell { white-space: nowrap; padding-right: 10px; }
    .footer-wrapper { margin-top: 16px; width: 100%; position: relative; z-index: 1; }
    .remarks-container { border-top: 1px solid #000; width: 100%; padding-top: 8px; margin-top: 10px; display: flex; align-items: flex-start; }
    .remarks-label { border: 1px solid #000; padding: 2px 8px; font-weight: bold; font-size: 13px; background: #fff; margin-right: 12px; flex-shrink: 0; }
    .remarks-content { margin: 0; flex-grow: 1; }
    .remarks-text { color: #0f766e; font-size: 13px; font-weight: bold; margin: 8px 0; line-height: 1.4; display: block; }
    .disclaimer-nb { font-size: 9.5px; font-style: italic; font-weight: bold; text-align: center; margin: 8px 0 4px; color: #333; }
    .footer-bottom { display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #000; padding-top: 4px; margin-top: 10px; position: relative; z-index: 1; }
    .gen-text { font-size: 9px; color: #333; text-align: center; flex: 1; }
    .footer-logo-left img, .footer-logo-right img { height: 26px; }

    @media print {
      @page { size: landscape; margin: 0.2in; }
      body { background: none; padding: 0; display: block; }
      .no-print { display: none !important; }
      .page { box-shadow: none; width: 100%; padding: 0.1in 0.2in; min-height: 95vh; }
      .transaction-table { page-break-inside: auto; }
      .transaction-table tr { page-break-inside: avoid; }
      .transaction-table thead { display: table-header-group; }
      .footer-wrapper { page-break-inside: avoid; }
      .watermark { position: fixed; left: 50%; transform: translate(-50%,-50%) rotate(-30deg); }
    }
  </style>
</head>
<body>

@if($mode === 'payment')
  <div class="action-bar no-print">
    <a href="{{ route('ols.landing', ['query' => $fileNumber]) }}" class="btn-light">&larr; Back to Search</a>
  </div>

  <div class="pay-wrap">
    <div class="pay-head">
      <h1>Unlock Full Legal Search Result</h1>
      <p>Secure payment required to access the complete report. No account needed.</p>
    </div>
    <div class="pay-body">
      <div class="pay-row"><span class="lbl">File Number</span><span class="val">{{ $fileNumber }}</span></div>
      <div class="pay-row"><span class="lbl">Total</span><span class="val pay-total">&#8358;{{ number_format($amount / 100) }}</span></div>

      <label for="payEmail" style="display:block;margin-top:16px;margin-bottom:6px;font-size:13px;font-weight:600;color:#374151;">Email address</label>
      <input id="payEmail" type="email" inputmode="email" autocomplete="email" placeholder="you@example.com"
             style="width:100%;box-sizing:border-box;padding:11px 12px;font-size:14px;border:1px solid #d1d5db;border-radius:10px;color:#1f2937;" />

      <label for="payEmailConfirm" style="display:block;margin-top:12px;margin-bottom:6px;font-size:13px;font-weight:600;color:#374151;">Confirm email address</label>
      <input id="payEmailConfirm" type="email" inputmode="email" autocomplete="email" placeholder="Re-enter your email" onpaste="return false;" ondrop="return false;"
             style="width:100%;box-sizing:border-box;padding:11px 12px;font-size:14px;border:1px solid #d1d5db;border-radius:10px;color:#1f2937;" />

      <p class="pay-note" style="margin-top:6px;text-align:left;">We email your receipt to this address and use it for your payment reference.</p>

      <button id="payBtn" class="pay-btn">Pay &#8358;{{ number_format($amount / 100) }} with Paystack</button>
      <div id="payError" class="pay-error"></div>
      <p class="pay-note">You will be charged once. After payment is verified, the full report opens automatically.</p>
    </div>
  </div>

  <script src="https://js.paystack.co/v1/inline.js"></script>
  <script>
    (function () {
      const btn = document.getElementById('payBtn');
      const emailInput = document.getElementById('payEmail');
      const emailConfirmInput = document.getElementById('payEmailConfirm');
      const errBox = document.getElementById('payError');
      const VERIFY_URL = @json(route('ols.payment.verify'));
      const RESULT_URL = @json(route('ols.result'));
      const CSRF = document.querySelector('meta[name="csrf-token"]').content;
      const fileNumber = @json($fileNumber);
      const searchParams = @json($searchParams);
      const PAY_LABEL = @json('Pay ₦' . number_format($amount / 100) . ' with Paystack');

      function showError(msg) { errBox.textContent = msg; errBox.style.display = 'block'; btn.disabled = false; btn.textContent = PAY_LABEL; }

      // Send the verified buyer to the gated report, unlocked by the payment reference.
      function openReport(reference) {
        const qs = new URLSearchParams();
        Object.entries(searchParams || {}).forEach(([k, v]) => { if (v) qs.append(k, v); });
        qs.set('query', fileNumber);
        qs.set('ref', reference);
        window.location.href = RESULT_URL + '?' + qs.toString();
      }

      btn.addEventListener('click', function () {
        const email = (emailInput.value || '').trim();
        const emailConfirm = (emailConfirmInput.value || '').trim();
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
          showError('Please enter a valid email address to continue.');
          emailInput.focus();
          return;
        }
        if (email.toLowerCase() !== emailConfirm.toLowerCase()) {
          showError('The email addresses do not match. Please re-enter to confirm.');
          emailConfirmInput.focus();
          return;
        }

        btn.disabled = true; btn.textContent = 'Opening secure checkout…'; errBox.style.display = 'none';
        const handler = PaystackPop.setup({
          key: @json($paystackPublicKey),
          email: email,
          amount: {{ (int) $amount }},
          currency: 'NGN',
          metadata: { custom_fields: [{ display_name: 'File Number', variable_name: 'file_number', value: fileNumber }] },
          callback: function (response) {
            btn.textContent = 'Verifying payment…';
            fetch(VERIFY_URL, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
              body: JSON.stringify({ reference: response.reference, email: email, file_number: fileNumber, search_params: searchParams }),
            })
            .then(r => r.json().then(j => ({ ok: r.ok, j })))
            .then(({ ok, j }) => {
              if (ok && j.success) { openReport(j.reference || response.reference); }
              else { showError((j && j.message) || 'Payment verification failed. Please contact support if you were charged.'); }
            })
            .catch(() => showError('A network error occurred while verifying your payment.'));
          },
          onClose: function () { btn.disabled = false; btn.textContent = PAY_LABEL; },
        });
        handler.openIframe();
      });
    })();
  </script>

@else
  {{-- Report mode --}}
  <div class="action-bar no-print">
    <a href="{{ route('ols.landing') }}" class="btn-light">&larr; New Search</a>
    <span style="color:#e2e8f0;font-size:12px;">Ref: {{ $payment->tracking_id ?? $payment->reference }}</span>
    <button onclick="window.print()" class="btn-primary">&#128424; Print / Save PDF</button>
  </div>

  @if(!$report)
    <div class="pay-wrap"><div class="pay-body"><p style="text-align:center;color:#b91c1c;font-weight:600;">The report for file <strong>{{ $fileNumber }}</strong> could not be generated. Please return to search and try again.</p></div></div>
  @else
    <div class="page">
      <img class="bg-watermark" src="{{ asset('assets/logo/ministry2.jpeg') }}" alt="" aria-hidden="true" onerror="this.style.display='none'">
      <div class="watermark wm-top" aria-hidden="true">Online Legal Search <br> &nbsp;&bull;&nbsp; Not For Sale</div>

      <div class="main-content">
        <header>
          <div class="header-top">
            <div class="logo-box"><img src="{{ asset('assets/logo/ministry1.jpg') }}" alt="Logo" onerror="this.style.visibility='hidden'"></div>
            <div class="title-block">
              <h1>KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
              <h2>LEGAL SEARCH REPORT</h2>
              <h3>Online Pay-per Search</h3>
            </div>
            <div class="logo-box"><img src="{{ asset('assets/logo/ministry2.jpeg') }}" alt="Logo" onerror="this.style.visibility='hidden'"></div>
          </div>
          <div class="date-line">{{ $report['date_line'] ?? 'Date: ' . now()->format('F j, Y') }}</div>
        </header>

        <div class="section-label">Property Details</div>
        <table class="prop-details">
          <tr>
            <td class="bold-lbl-left">File Number:</td><td><strong>{{ $report['file_number'] ?: '-' }}</strong></td>
            <td class="bold-lbl-right">District/LGA:</td><td><strong>{{ $report['district_lga'] ?: '-' }}</strong></td>
          </tr>
          <tr>
            <td class="bold-lbl-left">File Title:</td><td><strong>{{ $report['file_title'] ?: '-' }}</strong></td>
            <td class="bold-lbl-right">Plot No:</td><td><strong>{{ $report['plot_no'] ?: '-' }}</strong></td>
          </tr>
          <tr>
            <td class="bold-lbl-left">Land Use:</td><td><strong>{{ $report['land_use'] ?: '-' }}</strong></td>
            <td class="bold-lbl-right">Size:</td><td><strong>{{ $report['size'] ?: '-' }}</strong></td>
          </tr>
          <tr>
            <td class="bold-lbl-left">Plot Description:</td><td><strong>{{ $report['plot_description'] ?: '-' }}</strong></td>
            <td class="bold-lbl-right">TP No:</td><td><strong>{{ $report['tpno'] ?: '-' }}</strong></td>
          </tr>
        </table>

        <div class="section-label">File History</div>
        <table class="transaction-table">
          <thead>
            <tr class="header-row">
              <th style="width:3%">S/N</th>
              <th style="width:9%">File No</th>
              <th class="instrument-cell" style="width:18%">Instrument/Transaction Type</th>
              <th style="width:13%">Party 1</th>
              <th style="width:13%">Party 2</th>
              <th style="width:10%">Party 3</th>
              <th style="width:10%">Reg. Time/Date</th>
              <th style="width:9%">Particulars</th>
              <th style="width:5%">Caveat</th>
              <th style="width:10%">Comments</th>
            </tr>
          </thead>
          <tbody>
            @forelse($report['rows'] ?? [] as $row)
              <tr>
                <td>{{ $row['sn'] ?? $loop->iteration }}</td>
                <td>{{ $row['file_no'] ?? '-' }}</td>
                <td class="instrument-cell">{{ $row['instrument_type'] ?? '-' }}</td>
                <td>{{ $row['grantor'] ?? '-' }}</td>
                <td>{{ $row['grantee'] ?? '-' }}</td>
                <td>{{ $row['party_3'] ?? '-' }}</td>
                <td>
                  @if(!empty($row['reg_time']))<strong>{{ $row['reg_time'] }}</strong><br />@else-<br />@endif
                  {{ $row['reg_date'] ?? '-' }}
                </td>
                <td>{{ $row['reg_no'] ?? '0/0/0' }}</td>
                <td>{{ $row['caveat'] ?? '-' }}</td>
                <td>{{ $row['comments'] ?? '-' }}</td>
              </tr>
            @empty
              <tr><td colspan="10" style="text-align:center;padding:14px;">No transactions on record for this file.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="footer-wrapper">
        <div class="remarks-container">
          <div class="remarks-label">Remarks</div>
          <div class="remarks-content">
            <span class="remarks-text">
              {{ $report['caveat_note'] ?: ($report['is_caveated'] ?? false ? 'A caveat exists on this title. Please consult the Ministry before proceeding.' : 'Based on our available records, the title is free from encumbrances.') }}
            </span>
          </div>
        </div>

        <p class="disclaimer-nb" style="text-align:left;">
          N.B: This search report is deduced based on the available records from the file and does not represent any document in possession of any body.
          @if(!empty($report['remarks'])) {{ $report['remarks'] }} @endif
        </p>

        <div class="footer-bottom">
          <div class="footer-logo-left"><img src="{{ asset('storage/upload/logo/logo.png') }}" alt="KLAES" onerror="this.style.display='none'"></div>
          <div class="gen-text">Generated online for {{ $payment->email }} ({{ $payment->tracking_id ?? $payment->reference }}) at {{ now()->format('g:i A & d/m/Y') }}</div>
          <div class="footer-logo-right"><img src="{{ asset('assets/logo/las.jpg') }}" alt="LAS" onerror="this.style.display='none'"></div>
        </div>
      </div>
    </div>
  @endif
@endif

</body>
</html>

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
    .table-end-notice { text-align: center; font-size: 9.5px; font-weight: bold; font-style: italic; margin: 6px 0 8px; color: #000; page-break-inside: avoid; }
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
      <h1>Submit Your Legal Search Request</h1>
      <p>Secure payment submits your request for approval. No account needed.</p>
    </div>
    <div class="pay-body">
      <div class="pay-row"><span class="lbl">File Number</span><span class="val">{{ $fileNumber }}</span></div>
      <div class="pay-row"><span class="lbl">Total</span><span class="val pay-total">&#8358;{{ number_format($amount / 100) }}</span></div>

      <label for="paySearchPurpose" style="display:block;margin-top:16px;margin-bottom:6px;font-size:13px;font-weight:600;color:#374151;">Purpose of search <span style="color:#dc2626;">*</span></label>
      <select id="paySearchPurpose" required
              style="width:100%;box-sizing:border-box;padding:11px 12px;font-size:14px;border:1px solid #d1d5db;border-radius:10px;color:#1f2937;background:#fff;">
        <option value="">— Select a purpose —</option>
        @foreach($purposes as $purpose)
          <option value="{{ $purpose->id }}">{{ $purpose->name }}</option>
        @endforeach
      </select>
      <p class="pay-note" style="margin-top:6px;text-align:left;">A search can only be carried out for one of the listed purposes.</p>

      <label for="payEmail" style="display:block;margin-top:16px;margin-bottom:6px;font-size:13px;font-weight:600;color:#374151;">Email address</label>
      <input id="payEmail" type="email" inputmode="email" autocomplete="email" placeholder="you@example.com"
             style="width:100%;box-sizing:border-box;padding:11px 12px;font-size:14px;border:1px solid #d1d5db;border-radius:10px;color:#1f2937;" />

      <label for="payEmailConfirm" style="display:block;margin-top:12px;margin-bottom:6px;font-size:13px;font-weight:600;color:#374151;">Confirm email address</label>
      <input id="payEmailConfirm" type="email" inputmode="email" autocomplete="email" placeholder="Re-enter your email" onpaste="return false;" ondrop="return false;"
             style="width:100%;box-sizing:border-box;padding:11px 12px;font-size:14px;border:1px solid #d1d5db;border-radius:10px;color:#1f2937;" />

      <p class="pay-note" style="margin-top:6px;text-align:left;"><strong>Your report will be emailed to this address</strong> once approved, so please check it carefully. We also send your receipt here.</p>

      <button id="payBtn" class="pay-btn">Pay &#8358;{{ number_format($amount / 100) }} with Paystack</button>
      <div id="payError" class="pay-error"></div>
      <p class="pay-note">You will be charged once. Your request is then reviewed by the Director / Deputy Director, and the approved report is emailed to you as a PDF.</p>
    </div>
  </div>

  <script src="https://js.paystack.co/v1/inline.js"></script>
  <script>
    (function () {
      const btn = document.getElementById('payBtn');
      const emailInput = document.getElementById('payEmail');
      const emailConfirmInput = document.getElementById('payEmailConfirm');
      const purposeInput = document.getElementById('paySearchPurpose');
      const errBox = document.getElementById('payError');
      const VERIFY_URL = @json(route('ols.payment.verify'));
      const RESULT_URL = @json(route('ols.result'));
      const CSRF = document.querySelector('meta[name="csrf-token"]').content;
      const fileNumber = @json($fileNumber);
      const searchParams = @json($searchParams);
      const PAY_LABEL = @json('Pay ₦' . number_format($amount / 100) . ' with Paystack');

      function showError(msg) { errBox.textContent = msg; errBox.style.display = 'block'; btn.disabled = false; btn.textContent = PAY_LABEL; }

      // Send the verified buyer to their request-status page. The report itself
      // is emailed once a Director / Deputy Director approves the request.
      function openStatus(reference) {
        const qs = new URLSearchParams();
        Object.entries(searchParams || {}).forEach(([k, v]) => { if (v) qs.append(k, v); });
        qs.set('query', fileNumber);
        qs.set('ref', reference);
        window.location.href = RESULT_URL + '?' + qs.toString();
      }

      btn.addEventListener('click', function () {
        // A search cannot proceed without one of the defined purposes.
        const purposeId = (purposeInput.value || '').trim();
        if (!purposeId) {
          showError('Please select the purpose of your search to continue.');
          purposeInput.focus();
          return;
        }

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
          metadata: {
            custom_fields: [
              { display_name: 'File Number', variable_name: 'file_number', value: fileNumber },
              { display_name: 'Purpose of Search', variable_name: 'purpose', value: purposeInput.options[purposeInput.selectedIndex].text },
            ],
          },
          callback: function (response) {
            btn.textContent = 'Verifying payment…';
            fetch(VERIFY_URL, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
              body: JSON.stringify({ reference: response.reference, email: email, file_number: fileNumber, search_params: searchParams, purpose_id: purposeId }),
            })
            .then(r => r.json().then(j => ({ ok: r.ok, j })))
            .then(({ ok, j }) => {
              if (ok && j.success) { openStatus(j.reference || response.reference); }
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
  {{-- Status mode: the report is delivered by email after approval, never rendered here. --}}
  @php
    $statusKey = $searchRequest->status ?? 'pending';
    $badge = [
      'pending'  => ['#fef3c7', '#92400e', 'Awaiting Approval'],
      'approved' => ['#dcfce7', '#166534', 'Approved'],
      'rejected' => ['#fee2e2', '#991b1b', 'Not Approved'],
    ][$statusKey] ?? ['#e2e8f0', '#334155', ucfirst($statusKey)];
  @endphp

  <div class="action-bar no-print">
    <a href="{{ route('ols.landing') }}" class="btn-light">&larr; New Search</a>
    <span style="color:#e2e8f0;font-size:12px;">Ref: {{ $payment->tracking_id ?? $payment->reference }}</span>
  </div>

  <div class="pay-wrap">
    <div class="pay-head">
      <h1>Request {{ $searchRequest->request_no }}</h1>
      <p>Your payment was received and your Legal Search request has been submitted.</p>
    </div>
    <div class="pay-body">
      <div class="pay-row">
        <span class="lbl">Status</span>
        <span class="val" style="background:{{ $badge[0] }};color:{{ $badge[1] }};padding:4px 10px;border-radius:999px;font-size:12px;">{{ $badge[2] }}</span>
      </div>
      <div class="pay-row"><span class="lbl">Request No.</span><span class="val">{{ $searchRequest->request_no }}</span></div>
      <div class="pay-row"><span class="lbl">File Number</span><span class="val">{{ $fileNumber }}</span></div>
      <div class="pay-row"><span class="lbl">Purpose</span><span class="val">{{ $searchRequest->purpose ?: '—' }}</span></div>
      <div class="pay-row"><span class="lbl">Payment Ref</span><span class="val">{{ $payment->tracking_id ?? $payment->reference }}</span></div>
      <div class="pay-row"><span class="lbl">Report goes to</span><span class="val">{{ $payment->email }}</span></div>
      <div class="pay-row"><span class="lbl">Submitted</span><span class="val">{{ optional($searchRequest->submitted_at)->format('d M Y, g:i A') }}</span></div>

      @if($statusKey === 'approved')
        <div style="margin-top:18px;background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;padding:14px;border-radius:10px;font-size:13px;">
          <strong>Approved{{ $searchRequest->reviewer_name ? ' by ' . $searchRequest->reviewer_name : '' }}.</strong>
          Your Legal Search report has been emailed to <strong>{{ $payment->email }}</strong> as a PDF attachment.
          If it has not arrived, please check your spam folder before contacting the Ministry.
        </div>
      @elseif($statusKey === 'rejected')
        <div style="margin-top:18px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:14px;border-radius:10px;font-size:13px;">
          <strong>This request was not approved.</strong>
          @if($searchRequest->rejection_reason)<br>Reason: {{ $searchRequest->rejection_reason }}@endif
          <br>Please contact the Ministry quoting request number {{ $searchRequest->request_no }}.
        </div>
      @else
        <div style="margin-top:18px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;padding:14px;border-radius:10px;font-size:13px;">
          <strong>Your request is with the Director / Deputy Director for approval.</strong>
          Once approved, the full Legal Search report is emailed to <strong>{{ $payment->email }}</strong> as a PDF attachment.
          You do not need to keep this page open.
        </div>
      @endif

      <p class="pay-note">Keep request number <strong>{{ $searchRequest->request_no }}</strong> for any correspondence about this search.</p>
    </div>
  </div>
@endif

</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #1f2937; font-size: 12px; margin: 0; padding: 0; }
        .wrap { padding: 20px 36px; }
        .head { width: 100%; }
        .head td { vertical-align: middle; }
        .head .logo-cell { width: 64px; }
        .head .logo-cell img { height: 50px; width: auto; }
        .head .logo-cell.r { text-align: right; }
        .brand { text-align: center; padding: 0 8px; }
        .brand h1 { color: #166534; font-size: 16px; margin: 0; white-space: nowrap; }
        .brand p { color: #6b7280; font-size: 11px; margin: 2px 0 0; }
        .head-rule { border-bottom: 3px solid #166534; margin-top: 10px; }
        .title-row { width: 100%; margin-top: 10px; }
        .title-row td { vertical-align: top; }
        .title-row .qr-cell { width: 84px; }
        .doc-title { text-align: right; }
        .doc-title h2 { font-size: 22px; letter-spacing: 2px; color: #111827; margin: 0; }
        .doc-title p { margin: 3px 0 0; color: #6b7280; font-size: 11px; }
        .doc-title .num { white-space: nowrap; font-weight: 700; color: #374151; }
        .doc-title .ref { white-space: nowrap; }
        .doc-title .ref span { font-weight: 600; color: #374151; }
        .meta { width: 100%; margin-top: 12px; }
        .meta td { vertical-align: top; width: 50%; }
        .meta h3 { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: #166534; margin: 0 0 4px; }
        .meta p { margin: 2px 0; }
        .meta .muted { color: #6b7280; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 14px; }
        table.items th { background: #166534; color: #fff; text-align: left; padding: 7px 12px; font-size: 11px; text-transform: uppercase; }
        table.items th.r, table.items td.r { text-align: right; }
        table.items td { padding: 6px 12px; border-bottom: 1px solid #e5e7eb; }
        .totals { width: 100%; margin-top: 4px; }
        .totals td { padding: 4px 12px; }
        .totals .label { text-align: right; color: #6b7280; }
        .totals .val { text-align: right; width: 160px; font-weight: bold; }
        .totals .grand { font-size: 15px; color: #166534; border-top: 2px solid #166534; }
        .pay { margin-top: 14px; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px 16px; background: #f9fafb; }
        .pay h3 { margin: 0 0 6px; font-size: 12px; color: #166534; }
        .pay p { margin: 2px 0; }
        .qr-block { text-align: left; }
        .qr-block .qr-img { width: 72px; height: 72px; }
        .status { display: inline-block; padding: 3px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; }
        .status-paid { background: #dcfce7; color: #166534; }
        .status-due { background: #fef3c7; color: #92400e; }
        .status-short { background: #fef3c7; color: #92400e; }
        .status-pending { background: #f3f4f6; color: #4b5563; }
        .foot { width: 100%; margin-top: 16px; border-top: 1px solid #e5e7eb; padding-top: 10px; }
        .foot td { vertical-align: middle; }
        .foot-logo { width: 70px; text-align: right; }
        .foot-logo img { height: 50px; width: auto; }
        .foot-text { color: #9ca3af; font-size: 10px; text-align: center; }
        .watermark { position: fixed; left: 0; top: 300px; width: 100%; text-align: center; z-index: 0; }
        .watermark img { width: 340px; opacity: 0.06; }
    </style>
</head>
<body>
@php
    $ministryRight = 'http://app.klaes.ng/assets/logo/ministry1.jpg';
    $ministryLeft  = 'http://app.klaes.ng/assets/logo/ministry2.jpeg';
    $watermarkLogo = 'http://app.klaes.ng/assets/logo/ministry2.jpeg';
    $footerLogo    = 'http://app.klaes.ng/storage/upload/logo/1.jpeg';
@endphp

<div class="watermark"><img src="{{ $watermarkLogo }}" alt=""></div>

<div class="wrap">
    <table class="head">
        <tr>
            <td class="logo-cell"><img src="{{ $ministryLeft }}" alt=""></td>
            <td class="brand">
                <h1>Kano State Ministry of Land &amp; Physical Planning</h1>
                <p>Property History Search (PHS) Portal Payment Invoice</p>
            </td>
            <td class="logo-cell r"><img src="{{ $ministryRight }}" alt=""></td>
        </tr>
    </table>
    <div class="head-rule"></div>

    <table class="title-row">
        <tr>
            <td class="qr-cell">
                @if (!empty($qr_code))
                    <div class="qr-block">
                        <img src="{{ $qr_code }}" alt="QR" class="qr-img">
                    </div>
                @endif
            </td>
            <td class="doc-title">
                <h2>INVOICE</h2>
                <p class="num">{{ $invoice_number }}</p>
                @if ($request->payment_reference)<p class="ref">Ref: <span>{{ $request->payment_reference }}</span></p>@endif
                <p>Date: {{ $invoice_date->format('F j, Y') }}</p>
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td>
                <h3>Billed To</h3>
                <p><strong>{{ $request->organization_name }}</strong></p>
                <p class="muted">{{ ucfirst(str_replace('_', ' ', $request->organization_type)) }}</p>
                <p>{{ $request->contact_name }}</p>
                <p class="muted">{{ $request->contact_email }}</p>
                @if ($request->phone)<p class="muted">{{ $request->phone }}</p>@endif
                @if ($request->address)<p class="muted">{{ $request->address }}</p>@endif
            </td>
            <td>
                <h3>Payment Status</h3>
                @php
                    $ps = $request->payment_status ?: 'not_paid';
                    $out = (float) ($request->outstanding_amount ?? 0);
                @endphp
                @if ($ps === 'completed' || $ps === 'overpaid')
                    <p><span class="status status-paid">PAID</span></p>
                    @if ($request->payment_verified_at)<p class="muted">Confirmed: {{ $request->payment_verified_at->format('F j, Y') }}</p>@endif
                @elseif ($ps === 'incomplete')
                    <p><span class="status status-short">INCOMPLETE</span></p>
                    <p class="muted">Outstanding: NGN {{ number_format($out, 2) }}</p>
                @else
                    <p><span class="status status-pending">PENDING</span></p>
                @endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Description</th>
                <th class="r">Tokens</th>
                <th class="r">Team Members</th>
                <th class="r">Amount (NGN)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>Subscription</strong>
                    @if (!empty($package['description']))<br><span style="color:#6b7280">{{ $package['description'] }}</span>@endif
                </td>
                <td class="r">&mdash;</td>
                <td class="r">&mdash;</td>
                <td class="r">{{ number_format($amount, 2) }}</td>
            </tr>
            <tr>
                <td><strong>Token</strong></td>
                <td class="r">{{ number_format($package['tokens'] ?? 0) }}</td>
                <td class="r">&mdash;</td>
                <td class="r">Included</td>
            </tr>
            @php
                $teamSeats = (int) ($package['team_members'] ?? 0);
                $viewerSeats = max($teamSeats - 1, 0);
            @endphp
            <tr>
                <td><strong>User License</strong></td>
                <td class="r">&mdash;</td>
                <td class="r">{{ $teamSeats }}</td>
                <td class="r">Included</td>
            </tr>
            <tr>
                <td>Administrator</td>
                <td class="r">&mdash;</td>
                <td class="r">1</td>
                <td class="r">Included</td>
            </tr>
            <tr>
                <td>Viewer</td>
                <td class="r">&mdash;</td>
                <td class="r">{{ $viewerSeats }}</td>
                <td class="r">Included</td>
            </tr>
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">Subtotal</td>
            <td class="val">NGN {{ number_format($amount, 2) }}</td>
        </tr>
        <tr>
            <td class="label grand">Total Due</td>
            <td class="val grand">NGN {{ number_format($amount, 2) }}</td>
        </tr>
    </table>

    <div class="pay">
        <h3>Payment Instructions</h3>
        <p>Account Name: <strong>Kano State Ministry of Land &amp; Physical Planning</strong></p>
        <p>Bank: <strong>{{ config('phs.bank_name', 'Zenith Bank Plc') }}</strong></p>
        <p>Account No: <strong>{{ config('phs.bank_account', '1010101010') }}</strong></p>
        <p>Use payment reference: <strong>{{ $request->payment_reference ?: $invoice_number }}</strong></p>
    </div>

    <table class="foot">
        <tr>
            <td class="foot-text">
                <p>This invoice was generated electronically by the KLAES PHS Portal. For enquiries contact the ministry's finance office.</p>
            </td>
            <td class="foot-logo"><img src="{{ $footerLogo }}" alt=""></td>
        </tr>
    </table>
</div>
</body>
</html>

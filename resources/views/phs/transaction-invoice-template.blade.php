<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #1f2937; font-size: 12px; margin: 0; padding: 0; }
        .wrap { padding: 32px 36px; }
        .head { width: 100%; border-bottom: 3px solid #166534; padding-bottom: 14px; }
        .head td { vertical-align: middle; }
        .brand h1 { color: #166534; font-size: 18px; margin: 0; }
        .brand p { color: #6b7280; font-size: 11px; margin: 2px 0 0; }
        .doc-title { text-align: right; }
        .doc-title h2 { font-size: 26px; letter-spacing: 2px; color: #111827; margin: 0; }
        .doc-title p { margin: 4px 0 0; color: #6b7280; font-size: 11px; }
        .meta { width: 100%; margin-top: 22px; }
        .meta td { vertical-align: top; width: 50%; }
        .meta h3 { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: #166534; margin: 0 0 6px; }
        .meta p { margin: 2px 0; }
        .meta .muted { color: #6b7280; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 26px; }
        table.items th { background: #166534; color: #fff; text-align: left; padding: 9px 12px; font-size: 11px; text-transform: uppercase; }
        table.items th.r, table.items td.r { text-align: right; }
        table.items td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; }
        .totals { width: 100%; margin-top: 6px; }
        .totals td { padding: 6px 12px; }
        .totals .label { text-align: right; color: #6b7280; }
        .totals .val { text-align: right; width: 170px; font-weight: bold; }
        .totals .grand { font-size: 15px; color: #166534; border-top: 2px solid #166534; }
        .status { display: inline-block; padding: 3px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; }
        .status-paid { background: #dcfce7; color: #166534; }
        .status-due { background: #fef3c7; color: #92400e; }
        .status-short { background: #fef3c7; color: #92400e; }
        .status-over { background: #e0f2fe; color: #075985; }
        .foot { margin-top: 30px; border-top: 1px solid #e5e7eb; padding-top: 12px; color: #9ca3af; font-size: 10px; text-align: center; }
    </style>
</head>
@php
    $status = $txn->status === 'completed' ? 'completed' : 'pending';
    $validation = $txn->validation_status; // verified | incomplete | overpaid | null
    $expected = (float) ($txn->expected_amount ?? $txn->amount);
    $paid = $txn->paid_amount !== null ? (float) $txn->paid_amount : null;
    $outstanding = ($paid !== null) ? max(0, round($expected - $paid, 2)) : null;
@endphp
<body>
<div class="wrap">
    <table class="head">
        <tr>
            <td class="brand">
                <h1>Kano State Ministry of Land &amp; Physical Planning</h1>
                <p>Property History Search (PHS) Portal</p>
            </td>
            <td class="doc-title">
                <h2>INVOICE</h2>
                <p>{{ $invoice_number }}</p>
                <p>Date: {{ $invoice_date->format('F j, Y') }}</p>
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td>
                <h3>Billed To</h3>
                <p><strong>{{ optional($institution)->name ?? '—' }}</strong></p>
                @if (optional($institution)->type)<p class="muted">{{ ucfirst(str_replace('_', ' ', $institution->type)) }}</p>@endif
                @if (optional($institution)->email)<p class="muted">{{ $institution->email }}</p>@endif
                @if (optional($institution)->phone)<p class="muted">{{ $institution->phone }}</p>@endif
            </td>
            <td>
                <h3>Payment Status</h3>
                @if ($validation === 'incomplete')
                    <p><span class="status status-short">INCOMPLETE</span></p>
                    <p class="muted">Outstanding: NGN {{ number_format((float) $outstanding, 2) }}</p>
                @elseif ($validation === 'overpaid')
                    <p><span class="status status-over">OVERPAID</span></p>
                    <p class="muted">Excess: NGN {{ number_format(abs((float) $txn->payment_variance), 2) }}</p>
                @elseif ($status === 'completed' || $validation === 'verified')
                    <p><span class="status status-paid">PAID</span></p>
                @else
                    <p><span class="status status-due">PENDING</span></p>
                @endif
                @if ($txn->bank_reference)<p class="muted">Bank ref: {{ $txn->bank_reference }}</p>@endif
                @if ($txn->payment_date)<p class="muted">Paid: {{ \Illuminate\Support\Carbon::parse($txn->payment_date)->format('F j, Y') }}</p>@endif
                @if ($txn->reference_no)<p class="muted">Ref: {{ $txn->reference_no }}</p>@endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Description</th>
                <th class="r">Tokens</th>
                <th class="r">Amount (NGN)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Subscription Licence</strong>@if ($txn->package_name) &mdash; {{ $txn->package_name }} plan @endif</td>
                <td class="r">&mdash;</td>
                <td class="r">{{ number_format($expected, 2) }}</td>
            </tr>
            <tr>
                <td><strong>Subscription Tokens</strong></td>
                <td class="r">{{ number_format(abs((int) $txn->tokens)) }}</td>
                <td class="r">Included</td>
            </tr>
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">Subtotal</td>
            <td class="val">NGN {{ number_format($expected, 2) }}</td>
        </tr>
        @if ($paid !== null)
            <tr><td class="label">Amount paid</td><td class="val">NGN {{ number_format($paid, 2) }}</td></tr>
            @if ($outstanding > 0)
                <tr><td class="label">Outstanding</td><td class="val" style="color:#92400e">NGN {{ number_format((float) $outstanding, 2) }}</td></tr>
            @endif
        @endif
        <tr>
            <td class="label grand">Total Due</td>
            <td class="val grand">NGN {{ number_format($expected, 2) }}</td>
        </tr>
    </table>

    <div class="foot">
        <p>This invoice was generated electronically by the KLAES PHS Portal. For enquiries contact the ministry's finance office.</p>
    </div>
</div>
</body>
</html>

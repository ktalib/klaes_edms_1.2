<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KLAES Official Search Slip</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #f3f4f6; color: #111827; font-family: Georgia, "Times New Roman", Times, serif; padding: 32px; }
        .toolbar { margin: 0 auto 16px; max-width: 920px; text-align: right; }
        .toolbar button { background: #14532d; border: 0; border-radius: 6px; color: white; cursor: pointer; font: 700 13px Arial, sans-serif; padding: 10px 14px; }
        .slip { background: white; border: 1px solid #e5e7eb; box-shadow: 0 10px 40px rgba(15, 23, 42, .08); margin: 0 auto; max-width: 920px; }
        .header { border-bottom: 3px solid #166534; padding: 20px 30px 16px; }
        .logo-container { align-items: center; display: flex; gap: 20px; justify-content: space-between; }
        .logo-box { align-items: center; border: 1px solid #d1d5db; border-radius: 10px; display: flex; height: 64px; justify-content: center; overflow: hidden; width: 64px; }
        .logo-box img { height: 100%; object-fit: contain; width: 100%; }
        .title-container { flex: 1; text-align: center; }
        .title-container h1 { color: #166534; font-size: 21px; letter-spacing: .5px; line-height: 1.25; }
        .title-container h3 { color: #374151; font-size: 13px; font-weight: normal; margin-top: 6px; }
        .content { padding: 24px 30px; }
        .info-section { display: grid; gap: 18px; grid-template-columns: repeat(2, minmax(0, 1fr)); margin-bottom: 24px; }
        .info-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 15px; }
        .info-box h4 { border-bottom: 2px solid #166534; color: #166534; display: inline-block; font-size: 13px; margin-bottom: 12px; padding-bottom: 7px; }
        .info-row { display: flex; font-size: 12px; gap: 12px; margin-bottom: 8px; }
        .info-label { color: #6b7280; flex: 0 0 120px; }
        .info-value { color: #111827; flex: 1; font-weight: 600; }
        .section-title { border-bottom: 2px solid #166534; color: #166534; display: inline-block; font-size: 15px; font-weight: bold; margin: 8px 0 14px; padding-bottom: 8px; }
        .timeline { border-left: 2px solid #d1d5db; margin-left: 8px; padding-left: 18px; }
        .timeline-row { margin-bottom: 14px; position: relative; }
        .timeline-row::before { background: #166534; border: 2px solid white; border-radius: 999px; box-shadow: 0 0 0 2px #d1d5db; content: ""; height: 10px; left: -24px; position: absolute; top: 2px; width: 10px; }
        .timeline-head { margin-bottom: 8px; }
        .tl-badge { background: #e8f5e9; border-radius: 20px; color: #166534; display: inline-block; font-size: 12px; font-weight: 600; padding: 2px 12px; }
        .tl-date { color: #9ca3af; font-size: 11px; margin-left: 10px; }
        .timeline-card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; }
        .tl-parties { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; }
        .tl-party { flex: 1; min-width: 130px; }
        .tl-party .lbl { color: #6b7280; font-size: 10px; letter-spacing: .4px; text-transform: uppercase; }
        .tl-party .val { color: #111827; font-size: 13px; font-weight: 600; }
        .tl-arrow { color: #9ca3af; font-size: 16px; padding: 0 12px; }
        .tl-desc { border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 11px; line-height: 1.55; margin-top: 10px; padding-top: 8px; }
        .note { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; color: #78350f; font-size: 12px; line-height: 1.6; margin-top: 22px; padding: 12px; }
        .footer { background: #f9fafb; border-top: 1px solid #e5e7eb; color: #6b7280; font-family: Arial, sans-serif; font-size: 11px; line-height: 1.6; padding: 15px 30px; text-align: center; }
        .footer-logos { align-items: center; display: flex; justify-content: space-between; gap: 20px; margin-top: 56px; padding: 16px 30px 32px; }
        .footer-logos .logo-box { border: 0; height: 110px; width: 110px; }
        .signature { display: flex; justify-content: flex-end; padding: 26px 30px 30px; }
        .sig-line { text-align: center; width: 250px; }
        .sig-line hr { border: 0; border-top: 1px solid #111827; margin-bottom: 8px; }
        @media print {
            @page { size: A4 portrait; margin: 8mm; }
            html, body { background: white; padding: 0; margin: 0; }
            .toolbar { display: none; }
            .slip { border: 0; box-shadow: none; max-width: none; }

            /* Compact everything so short results stay on a single page */
            .header { padding: 10px 18px 8px; border-bottom-width: 2px; }
            .logo-box { height: 46px; width: 46px; }
            .footer-logos .logo-box { height: 96px; width: 96px; }
            .title-container h1 { font-size: 16px; }
            .title-container h3 { font-size: 11px; margin-top: 3px; }
            .content { padding: 12px 18px; }
            .info-section { gap: 12px; margin-bottom: 12px; }
            .info-box { padding: 10px; }
            .info-box h4 { font-size: 12px; margin-bottom: 7px; padding-bottom: 4px; }
            .info-row { font-size: 11px; margin-bottom: 4px; }
            .section-title { font-size: 13px; margin: 4px 0 8px; padding-bottom: 5px; }
            .timeline { padding-left: 16px; }
            .timeline-row { margin-bottom: 9px; }
            .timeline-head { margin-bottom: 5px; }
            .tl-badge { font-size: 11px; padding: 1px 10px; }
            .timeline-card { padding: 9px; }
            .tl-party .val { font-size: 12px; }
            .tl-desc { font-size: 10px; margin-top: 7px; padding-top: 6px; }
            .note { font-size: 11px; line-height: 1.45; margin-top: 12px; padding: 9px; }
            .slip { min-height: auto; padding-bottom: 0; }
            .footer-logos { margin-top: 40px; padding: 14px 18px 6px; }
            .footer { font-size: 10px; line-height: 1.45; padding: 10px 18px; }
            .signature { padding: 14px 18px 16px; }

            /* Keep blocks intact across page breaks */
            .timeline-row, .info-box, .note, .footer, .footer-logos, .signature { break-inside: avoid; page-break-inside: avoid; }
        }
        @media (max-width: 760px) {
            body { padding: 12px; }
            .logo-container { align-items: flex-start; }
            .logo-box { height: 48px; width: 48px; }
            .title-container h1 { font-size: 15px; }
            .info-section { grid-template-columns: 1fr; }
            .content, .header, .footer { padding-left: 16px; padding-right: 16px; }
        }
    </style>
</head>
<body>
    <div class="toolbar"><button type="button" onclick="window.print()">Print Slip</button></div>

    <article class="slip">
        <header class="header">
            <div class="logo-container">
                <div class="logo-box"><img src="http://app.klaes.ng/assets/logo/ministry2.jpeg" alt="Ministry Logo"></div>
                <div class="title-container">
                    <h1>KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
                    <h3>OFFICIAL SEARCH SLIP (CERTIFIED COPY)</h3>
                </div>
                <div class="logo-box"><img src="http://app.klaes.ng/assets/logo/ministry1.jpg" alt="Ministry Logo"></div>
            </div>
        </header>

        <section class="content">
            <div class="info-section">
                <div class="info-box">
                    <h4>PROPERTY INFORMATION</h4>
                    <div class="info-row"><div class="info-label">File Number:</div><div class="info-value">{{ $data['file_number'] ?? '-' }}</div></div>
                    <div class="info-row"><div class="info-label">Title Holder:</div><div class="info-value">{{ $data['file_title'] ?? '-' }}</div></div>
                    <div class="info-row"><div class="info-label">District/LGA:</div><div class="info-value">{{ $data['district_lga'] ?? '-' }}</div></div>
                    <div class="info-row"><div class="info-label">Land Use:</div><div class="info-value">{{ $data['land_use'] ?? '-' }}</div></div>
                    <div class="info-row"><div class="info-label">Plot Number:</div><div class="info-value">{{ $data['plot_no'] ?? '-' }}</div></div>
                    <div class="info-row"><div class="info-label">TP Number:</div><div class="info-value">{{ $data['tpno'] ?? '-' }}</div></div>
                    <div class="info-row"><div class="info-label">Size:</div><div class="info-value">{{ $data['size'] ?? '-' }}</div></div>
                </div>

                <div class="info-box">
                    <h4>SEARCH DETAILS</h4>
                    <div class="info-row"><div class="info-label">Reference:</div><div class="info-value">{{ $reference_no ?: ($data['qr_data'] ?? '-') }}</div></div>
                    <div class="info-row"><div class="info-label">Search Date:</div><div class="info-value">{{ $data['date_line'] ?? now()->format('F j, Y') }}</div></div>
                    <div class="info-row"><div class="info-label">Institution:</div><div class="info-value">{{ $institution->name }}</div></div>
                    <div class="info-row"><div class="info-label">Generated By:</div><div class="info-value">{{ $member->name }}</div></div>
                    <div class="info-row"><div class="info-label">Tokens Consumed:</div><div class="info-value">1</div></div>
                    <div class="info-row"><div class="info-label">Verification:</div><div class="info-value">{{ $data['qr_data'] ?? '-' }}</div></div>
                </div>
            </div>

            <div class="section-title">TRANSACTION TIMELINE</div>
            <div class="timeline">
                @forelse(($data['rows'] ?? []) as $row)
                    @php
                        $type = $row['transaction_type'] ?? $row['instrument_type'] ?? '-';
                        $date = $row['transaction_date'] ?? $row['reg_date'] ?? $row['deeds_date'] ?? '-';
                        $grantor = $row['grantor'] ?? $row['party_1'] ?? '-';
                        $grantee = $row['grantee'] ?? $row['party_2'] ?? '-';
                        $reg = $row['registration_particulars'] ?? $row['reg_no'] ?? null;
                        $comments = ($row['comments'] ?? null) && $row['comments'] !== '-' ? $row['comments'] : null;
                        $descParts = [];
                        if ($reg && $reg !== '-') { $descParts[] = 'Registration Particulars: ' . $reg; }
                        if ($comments) { $descParts[] = $comments; }
                    @endphp
                    <div class="timeline-row">
                        <div class="timeline-head">
                            <span class="tl-badge">{{ $type }}</span>
                            <span class="tl-date">{{ $date }}</span>
                        </div>
                        <div class="timeline-card">
                            <div class="tl-parties">
                                <div class="tl-party">
                                    <div class="lbl">From</div>
                                    <div class="val">{{ $grantor ?: '-' }}</div>
                                </div>
                                <div class="tl-arrow">&rarr;</div>
                                <div class="tl-party">
                                    <div class="lbl">To</div>
                                    <div class="val">{{ $grantee ?: '-' }}</div>
                                </div>
                            </div>
                            @if(count($descParts))
                                <div class="tl-desc">{{ implode(' · ', $descParts) }}</div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="timeline-row"><div class="timeline-card"><div class="tl-party"><div class="val">No timeline rows available.</div></div></div></div>
                @endforelse
            </div>

            <div class="note hidden" style='display:none'>
                <strong>Encumbrance note:</strong> {{ $data['caveat_note'] ?? 'No caveat note available.' }}
                @if(!empty($data['ground_rent']))<br><strong>Ground rent:</strong> {{ $data['ground_rent'] }}@endif
                @if(!empty($data['encumbrance_comment']))<br><strong>Encumbrance:</strong> {{ $data['encumbrance_comment'] }}@endif
                @if(!empty($data['litigation_comment']))<br><strong>Litigation:</strong> {{ $data['litigation_comment'] }}@endif
            </div>
        </section>

        <footer class="footer">
            <p>This is an electronically generated official search slip. Verification can be made at www.klaes.gov.ng/verify</p>
            <p><strong>STATUS: CERTIFIED VERIFIED ✓</strong></p>
            <p>{{ $data['generated_by'] ?? '' }}</p>
        </footer>

        <div class="signature">
            <div class="sig-line">
                <hr>
                <p style="font-size:11px;">Authorized Signatory<br>Kano State Land Administration</p>
            </div>
        </div>

        <div class="footer-logos">
            <div class="logo-box"><img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES Logo"></div>
            <div class="logo-box"><img src="http://app.klaes.ng/assets/logo/las.jpg" alt="Land Administration Logo"></div>
        </div>
    </article>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate – {{ $cert->cert_number }}</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: Georgia, 'Times New Roman', serif; background:#fff; color:#1a1a1a; }
        .page { width:210mm; min-height:297mm; margin:0 auto; padding:20mm 25mm; position:relative; }
        .border-outer { border:6px double #8B7A2A; padding:10mm; min-height:277mm; }
        .border-inner { border:2px solid #8B7A2A; padding:8mm; min-height:257mm; }
        .header { text-align:center; margin-bottom:6mm; }
        .header .state-name { font-size:13pt; font-weight:bold; text-transform:uppercase; letter-spacing:2px; color:#2c3e50; }
        .header .ministry { font-size:10pt; color:#555; margin-top:2mm; }
        .header .dept { font-size:9pt; color:#666; margin-top:1mm; }
        .cert-title { text-align:center; margin:6mm 0 3mm; }
        .cert-title h1 { font-size:18pt; font-weight:bold; text-transform:uppercase; letter-spacing:3px; color:#8B7A2A; border-bottom:2px solid #8B7A2A; border-top:2px solid #8B7A2A; padding:3mm 0; display:inline-block; }
        .cert-no { text-align:center; margin-bottom:6mm; font-size:10pt; color:#555; }
        .cert-no span { font-weight:bold; color:#1a1a1a; }
        .body-text { font-size:10.5pt; line-height:1.8; text-align:justify; margin-bottom:4mm; }
        .info-table { width:100%; border-collapse:collapse; margin:4mm 0; }
        .info-table td { padding:2mm 3mm; font-size:10pt; vertical-align:top; }
        .info-table td:first-child { font-weight:bold; width:40%; color:#333; }
        .info-table td:last-child { border-bottom:1px dotted #aaa; }
        .seal-section { display:flex; justify-content:space-between; align-items:flex-end; margin-top:12mm; }
        .sig-box { text-align:center; width:45%; }
        .sig-box .sig-line { border-top:1px solid #333; margin-bottom:2mm; }
        .sig-box .sig-label { font-size:9pt; color:#333; }
        .sig-box .sig-title { font-size:8pt; color:#666; margin-top:1mm; }
        .seal-placeholder { text-align:center; width:80px; height:80px; border:2px dashed #ccc; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:8pt; color:#aaa; margin:0 auto; }
        .footer { text-align:center; margin-top:8mm; font-size:8pt; color:#888; border-top:1px solid #ddd; padding-top:3mm; }
        .watermark { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%) rotate(-45deg); font-size:72pt; color:rgba(139,122,42,.06); font-weight:bold; text-transform:uppercase; white-space:nowrap; pointer-events:none; z-index:0; }
        .content-wrap { position:relative; z-index:1; }
        @media print {
            body { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
            .no-print { display:none; }
            .page { padding:15mm 20mm; }
        }
    </style>
</head>
<body>

<div class="no-print" style="padding:12px;background:#f3f4f6;display:flex;gap:8px;align-items:center;">
    <button onclick="window.print()" style="padding:8px 20px;background:rgb(186,191,12);color:#fff;border:none;border-radius:6px;font-size:13px;cursor:pointer;">Print Certificate</button>
    <button onclick="window.close()" style="padding:8px 16px;background:#6b7280;color:#fff;border:none;border-radius:6px;font-size:13px;cursor:pointer;">Close</button>
</div>

<div class="page">
    <div class="border-outer">
        <div class="border-inner">
            <div class="watermark">OFFICIAL</div>
            <div class="content-wrap">

                <div class="header">
                    <div class="state-name">Kano State Government</div>
                    <div class="ministry">Ministry of Lands and Physical Planning</div>
                    <div class="dept">Department of Land Administration</div>
                </div>

                <div class="cert-title">
                    <h1>Certificate of Change of Purpose</h1>
                </div>

                <div class="cert-no">
                    Certificate No: <span>{{ $cert->cert_number }}</span>
                </div>

                <p class="body-text">
                    This is to certify that, having satisfied the requirements of the Kano State Ministry of Lands and Physical
                    Planning under the Special Assignment (Land Use Compliance) Programme, and pursuant to the approval
                    granted by the Honourable Commissioner for Lands and Physical Planning, the land described herein has been
                    approved for change of purpose as follows:
                </p>

                <table class="info-table">
                    <tr>
                        <td>File Number</td>
                        <td>{{ $cert->file_number ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td>Holder / Owner Name</td>
                        <td>{{ $cert->holder_name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td>Previous (Approved) Use</td>
                        <td>{{ $cert->from_use ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td>New (Approved) Purpose</td>
                        <td>{{ $cert->to_use ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td>Date of Issue</td>
                        <td>{{ $cert->issue_date?->format('d F Y') ?? '—' }}</td>
                    </tr>
                    @if($cert->expiry_date)
                    <tr>
                        <td>Expiry Date</td>
                        <td>{{ $cert->expiry_date->format('d F Y') }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td>Issued By</td>
                        <td>{{ optional($cert->issuedBy)->name ?? '—' }}</td>
                    </tr>
                </table>

                <p class="body-text" style="margin-top:5mm;">
                    This certificate is issued in accordance with the Land Use Act and relevant Kano State land administration
                    regulations. Any development on this land must conform to the approved purpose stated herein and all
                    applicable planning regulations.
                </p>

                <div class="seal-section">
                    <div class="sig-box">
                        <div style="height:18mm;"></div>
                        <div class="sig-line"></div>
                        <div class="sig-label">Authorised Signatory</div>
                        <div class="sig-title">Director, Land Administration</div>
                    </div>
                    <div style="text-align:center;">
                        <div class="seal-placeholder">SEAL</div>
                    </div>
                    <div class="sig-box">
                        <div style="height:18mm;"></div>
                        <div class="sig-line"></div>
                        <div class="sig-label">Commissioner for Lands</div>
                        <div class="sig-title">Kano State Ministry of Lands &amp; Physical Planning</div>
                    </div>
                </div>

                <div class="footer">
                    This certificate is issued by the Kano State Ministry of Lands and Physical Planning.
                    For verification contact: Ministry of Lands, Kano State. | Cert. No: {{ $cert->cert_number }}
                </div>

            </div>
        </div>
    </div>
</div>

</body>
</html>

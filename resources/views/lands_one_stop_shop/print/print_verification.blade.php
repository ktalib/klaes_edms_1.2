{{-- Print template based on docs/templates/landsoss/one-stop2.html --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Form - FORM LN-03A</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
            margin: 0;
        }

        .print-bar {
            margin-bottom: 20px;
            text-align: center;
        }
        .print-bar button {
            padding: 10px 25px;
            background-color: #0f766e;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            margin: 0 5px;
        }
        .print-bar button:hover { background-color: #115e59; }
        .print-bar button.secondary { background-color: #64748b; }
        .print-bar button.secondary:hover { background-color: #475569; }

        .form-container {
            background-color: white;
            width: 210mm;
            min-height: 297mm;
            padding: 15mm 20mm;
            box-sizing: border-box;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .qr-placeholder {
            width: 85px;
            height: 85px;
            border: 1px solid #ccc;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
        }

        .center-logo {
            text-align: center;
            flex-grow: 1;
        }
        .center-logo img { width: 75px; }

        .form-label-top {
            font-size: 13px;
            font-weight: bold;
            text-align: right;
        }

        .ministry-title {
            text-align: center;
            margin-top: 5px;
        }
        .ministry-title h1 {
            color: #0f766e;
            font-size: 22px;
            margin: 0;
            text-transform: uppercase;
        }
        .ministry-title p {
            font-size: 10px;
            margin: 2px 0;
            color: #333;
        }

        .badge-heading {
            text-align: center;
            margin: 15px 0;
        }
        .badge-text {
            display: inline-block;
            text-align: center;
        }
        .badge-top {
            color: #0f766e;
            font-weight: bold;
            font-size: 20px;
            padding: 5px 20px;
            display: block;
        }
        .badge-bottom {
            background-color: #0f766e;
            color: white;
            font-weight: bold;
            font-size: 16px;
            padding: 5px 20px;
            display: block;
            text-transform: uppercase;
        }

        .passport-box-area {
            text-align: right;
            margin: 15px 0;
        }
        .passport-box {
            width: 110px;
            height: 130px;
            border: 1px solid #999;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: 12px;
            color: #888;
        }

        .part-heading {
            font-weight: bold;
            font-size: 16px;
            margin: 20px 0 10px 0;
            padding-bottom: 3px;
            border-bottom: 2px solid #0f766e;
            color: #0f766e;
        }

        .field-row {
            margin-bottom: 12px;
            font-size: 15px;
            line-height: 1.4;
        }

        .line-input {
            border-bottom: 1px solid #000;
            display: inline-block;
            min-height: 1.2em;
            min-width: 50px;
        }

        .sub-fields {
            margin-left: 25px;
            margin-top: 5px;
        }

        .checkbox-row-inline {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 8px;
        }
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
        }
        .check-box-sm {
            width: 20px;
            height: 20px;
            border: 1px solid #000;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: bold;
        }

        .official-box {
            border: 2px solid #333;
            padding: 20px;
            margin-top: 30px;
        }
        .official-box h3 {
            text-align: center;
            margin-top: 0;
            font-size: 16px;
            text-decoration: underline;
        }

        .rec-row {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 12px;
            font-size: 15px;
        }
        .rec-checkbox {
            width: 25px;
            height: 25px;
            border: 1px solid #000;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: bold;
        }

        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
        }
        .sig-block {
            width: 45%;
            text-align: center;
            border-top: 1px solid #000;
            padding-top: 5px;
            font-size: 14px;
        }

        @media print {
            .print-bar { display: none !important; }
            body { background-color: white; padding: 0; }
            .form-container { box-shadow: none; border: none; width: 100%; }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body>
    <div class="print-bar">
        <button onclick="window.print()">&#128424; Print Verification Form</button>
        <button class="secondary" onclick="window.close()">Close</button>
    </div>

    <div class="form-container">
        <div class="header-top">
            <div class="qr-placeholder">QR CODE</div>
            <div class="center-logo">
                <img src="{{ asset('assets/logo/logo1.jpg') }}" alt="Coat of Arms" onerror="this.style.display='none'">
            </div>
            <div class="form-label-top">FORM LN-03A</div>
        </div>

        <div class="ministry-title">
            <h1>MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
            <p>No. 2 Dr. Bala Mohd. Road, Nassarawa GRA, Kano P.M.B. 3083, Kano-Nigeria</p>
        </div>

        <div class="badge-heading">
            <div class="badge-text">
                <span class="badge-top">OCCUPANCY PERMIT</span>
                <span class="badge-bottom">VERIFICATION FORM</span>
            </div>
        </div>

        <div class="passport-box-area">
            @if(!empty($data['passport_photo_url']))
                <img src="{{ $data['passport_photo_url'] }}" alt="Passport" style="width:110px;height:130px;object-fit:cover;border:1px solid #999;">
            @else
                <div class="passport-box">Passport<br>Photograph</div>
            @endif
        </div>

        {{-- PART A --}}
        <div class="part-heading">Part A - Applicant Details</div>
        <div class="field-row">
            1. Name of Applicant: <div class="line-input" style="width:450px;">{{ $data['applicant_name'] ?? '' }}</div>
        </div>
        <div class="field-row">
            2. Address: <div class="line-input" style="width:510px;">{{ $data['applicant_address'] ?? '' }}</div>
        </div>
        <div class="field-row">
            3. Phone No.: <div class="line-input" style="width:250px;">{{ $data['applicant_phone'] ?? '' }}</div>
            Email: <div class="line-input" style="width:250px;">{{ $data['applicant_email'] ?? '' }}</div>
        </div>
        <div class="field-row">
            4. Means of Identification:
            @php $idType = $data['id_type'] ?? ''; @endphp
            <div class="checkbox-row-inline">
                <div class="checkbox-item"><div class="check-box-sm">{{ $idType === 'NIN' ? '✓' : '' }}</div> NIN</div>
                <div class="checkbox-item"><div class="check-box-sm">{{ $idType === 'Voters Card' ? '✓' : '' }}</div> Voters Card</div>
                <div class="checkbox-item"><div class="check-box-sm">{{ $idType === 'Passport' ? '✓' : '' }}</div> Passport</div>
                <div class="checkbox-item"><div class="check-box-sm">{{ $idType === 'Drivers License' ? '✓' : '' }}</div> Drivers License</div>
            </div>
        </div>

        {{-- PART B --}}
        <div class="part-heading">Part B - OP Details</div>
        <div class="field-row">
            5. Name of Original Allottee: <div class="line-input" style="width:400px;">{{ $data['original_allottee'] ?? '' }}</div>
        </div>
        <div class="field-row">
            6. OP Number: <div class="line-input" style="width:480px;">{{ $data['op_number'] ?? '' }}</div>
        </div>
        <div class="field-row">
            7. Plot/Plan No.: <div class="line-input" style="width:200px;">{{ $data['plot_no'] ?? '' }}</div> / <div class="line-input" style="width:200px;">{{ $data['plan_no'] ?? '' }}</div>
        </div>
        <div class="field-row">
            8. Location: <div class="line-input" style="width:500px;">{{ $data['location'] ?? '' }}</div>
        </div>

        {{-- OFFICIAL USE ONLY --}}
        <div class="official-box">
            <h3>FOR OFFICIAL USE ONLY</h3>
            @php $recommendation = $data['recommendation'] ?? ''; @endphp
            <div class="rec-row">
                <div class="rec-checkbox">{{ $recommendation === 'Recommended' ? '✓' : '' }}</div> Recommended
                <div class="rec-checkbox" style="margin-left: 30px;">{{ $recommendation === 'Not Recommended' ? '✓' : '' }}</div> Not Recommended
            </div>
            <div class="field-row" style="margin-top: 30px;">
                Chairman: <div class="line-input" style="width:450px;">{{ $data['chairman_name'] ?? '' }}</div>
            </div>

            <div class="signature-section">
                <div class="sig-block">
                    Signature of Chairman
                </div>
                <div class="sig-block">
                    Date
                </div>
            </div>
        </div>
    </div>
</body>
</html>

{{-- Print template based on docs/templates/landsoss/one-stop3.html --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OP Change of Ownership Form</title>
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
            background-color: #2e7d32;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            margin: 0 5px;
        }
        .print-bar button:hover { background-color: #1b5e20; }
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

        .ministry-title {
            text-align: center;
            margin-top: 5px;
        }
        .ministry-title h1 {
            color: #2e7d32;
            font-size: 22px;
            margin: 0;
            text-transform: uppercase;
        }
        .ministry-title p {
            font-size: 10px;
            margin: 2px 0;
            color: #333;
        }

        .ref-box {
            border: 1px solid #999;
            margin-top: 15px;
            padding: 8px;
            display: flex;
            justify-content: space-between;
            font-size: 13px;
        }

        .badge-heading {
            text-align: center;
            margin: 20px 0;
        }
        .badge-text {
            padding: 0;
            display: inline-block;
            text-align: center;
        }
        .badge-top {
            color: #2e7d32;
            font-weight: bold;
            font-size: 20px;
            padding: 5px 20px;
            display: block;
        }
        .badge-bottom {
            background-color: #f28d8d;
            color: white;
            font-weight: bold;
            font-size: 16px;
            padding: 5px 20px;
            display: block;
            text-transform: uppercase;
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

        .checkbox-row {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            width: 300px;
        }

        .check-box {
            width: 25px;
            height: 25px;
            border: 1px solid #000;
            margin-left: auto;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: bold;
        }

        .declaration-box {
            border: 1px solid #333;
            padding: 15px;
            margin-top: 30px;
            font-size: 14px;
            line-height: 1.4;
            background-color: #f9f9f9;
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
        <button onclick="window.print()">&#128424; Print Ownership Form</button>
        <button class="secondary" onclick="window.close()">Close</button>
    </div>

    <div class="form-container">
        <div class="header-top">
            <div class="qr-placeholder">QR CODE</div>
            <div class="center-logo">
                <img src="{{ asset('assets/logo/logo1.jpg') }}" alt="Coat of Arms" onerror="this.style.display='none'">
            </div>
            <div style="width: 85px;"></div>
        </div>

        <div class="ministry-title">
            <h1>MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
            <p>No. 2 Dr. Bala Mohd. Road, Nassarawa GRA, Kano P.M.B. 3083, Kano-Nigeria</p>
        </div>

        <div class="ref-box">
            <span>In case of reply please quote</span>
            <span>Our ref: <div class="line-input" style="width: 200px;">{{ $data['file_no'] ?? '' }}</div></span>
        </div>

        <div class="badge-heading">
            <div class="badge-text">
                <span class="badge-top">OCCUPANCY PERMIT</span>
                <span class="badge-bottom">CHANGE OF OWNERSHIP FORM</span>
            </div>
        </div>

        <div class="field-row">
            <b>1.</b> OP Number <div class="line-input" style="width: 200px;">{{ $data['op_number'] ?? '' }}</div>
            Location <div class="line-input" style="width: 300px;">{{ $data['location'] ?? '' }}</div>
            <div class="sub-fields">
                Plot No <div class="line-input" style="width: 100px;">{{ $data['plot_no'] ?? '' }}</div>
                Plan No <div class="line-input" style="width: 150px;">{{ $data['plan_no'] ?? '' }}</div>
                Date of Issuance <div class="line-input" style="width: 150px;">{{ $data['date_of_issuance'] ?? '' }}</div>
            </div>
        </div>

        <div class="field-row">
            <b>2. Original Allottee</b>
            <div class="sub-fields">
                a. Name <div class="line-input" style="width: 530px;">{{ $data['original_name'] ?? '' }}</div><br>
                b. Address: <div class="line-input" style="width: 515px;">{{ $data['original_address'] ?? '' }}</div><br>
                c. Phone No. <div class="line-input" style="width: 505px;">{{ $data['original_phone'] ?? '' }}</div>
            </div>
        </div>

        <div class="field-row">
            <b>3. Current Owner</b>
            <div class="sub-fields">
                a. Name <div class="line-input" style="width: 530px;">{{ $data['current_name'] ?? '' }}</div><br>
                b. Address: <div class="line-input" style="width: 515px;">{{ $data['current_address'] ?? '' }}</div><br>
                c. Phone No. <div class="line-input" style="width: 505px;">{{ $data['current_phone'] ?? '' }}</div>
            </div>
        </div>

        <div class="field-row">
            <b>4. How ownership was obtained</b>
            <div class="sub-fields">
                @php $method = $data['ownership_method'] ?? ''; @endphp
                <div class="checkbox-row">a. Direct <div class="check-box">{{ $method === 'Direct' ? '✓' : '' }}</div></div>
                <div class="checkbox-row">b. Resettlement <div class="check-box">{{ $method === 'Resettlement' ? '✓' : '' }}</div></div>
                <div class="checkbox-row">c. Gift <div class="check-box">{{ $method === 'Gift' ? '✓' : '' }}</div></div>
                <div class="checkbox-row">d. Purchase <div class="check-box">{{ $method === 'Purchase' ? '✓' : '' }}</div></div>
                <div class="checkbox-row">e. Inheritance <div class="check-box">{{ $method === 'Inheritance' ? '✓' : '' }}</div></div>
            </div>
        </div>

        <div class="declaration-box">
            I, the undersigned declares that all the information given above is true and correct and I also understand that if any part of the information is false, it may / shall jeopardize the validity of the application or document or any registration resulting there-from.
        </div>

        <div class="signature-section">
            <div class="sig-block">
                Signature of Applicant
            </div>
            <div class="sig-block">
                Date
            </div>
        </div>
    </div>
</body>
</html>

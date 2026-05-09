<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compensation Valuation - {{ $record->owner_name }}</title>
    <style>
        body {
            font-family: "Times New Roman", Times, serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            background-color: white;
            padding: 40px;
            border: 2px solid #00008B;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        h1 {
            text-align: center;
            text-decoration: underline;
            font-size: 28px;
            margin-top: 0;
            margin-bottom: 25px;
            text-transform: uppercase;
        }

        .header-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            line-height: 1.8;
            font-size: 16px;
        }

        .underline-field {
            border-bottom: 1px solid black;
            display: inline-block;
            min-width: 150px;
            padding-left: 5px;
            font-weight: bold;
        }

        .location-banner {
            text-align: center;
            font-weight: bold;
            font-size: 18px;
            text-transform: uppercase;
            border-top: 2px solid black;
            border-bottom: 2px solid black;
            padding: 15px 0;
            margin-bottom: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 40px;
        }

        th, td {
            border: 1px solid black;
            padding: 12px 8px;
            text-align: center;
            font-size: 14px;
            word-wrap: break-word;
        }

        th {
            background-color: #f8fafc;
            height: 50px;
            text-transform: uppercase;
            font-size: 12px;
        }

        .footer-signatures {
            margin-top: 60px;
        }

        .sig-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 80px;
        }

        .sig-line {
            width: 280px;
            border-top: 1px solid black;
            padding-top: 8px;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            font-size: 14px;
        }

        .perm-sec-wrap {
            display: flex;
            justify-content: center;
        }

        /* PRINT SETTINGS */
        @media print {
            @page {
                size: landscape;
                margin: 1cm;
            }
            
            body {
                background-color: white;
                padding: 0;
            }

            .container {
                width: 100%;
                max-width: none;
                box-shadow: none;
                border: 2px solid #00008B;
                padding: 20px;
            }

            .no-print {
                display: none;
            }
        }

        /* Action Bar */
        .no-print-bar {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            items-center: center;
            gap: 8px;
            border: none;
            transition: all 0.2s;
        }

        .btn-print { background: #0d9488; color: white; }
        .btn-back { background: #64748b; color: white; }
        .btn:hover { opacity: 0.9; transform: translateY(-1px); }
    </style>
</head>
<body>

<div class="no-print-bar no-print">
    <a href="{{ route('valuation-compensations.index') }}" class="btn btn-back">
        <span>Back to List</span>
    </a>
    <button onclick="window.print(); logPrint();" class="btn btn-print">
        <span>Print Document</span>
    </button>
</div>

<div class="container">
    <h1>COMPENSATION VALUATION</h1>

    <div class="header-meta">
        <div>
            To: <span class="underline-field">The Honourable Commissioner</span><br>
            <span style="margin-left: 25px;"></span><span class="underline-field">Ministry of Land and Physical</span><br>
            <span style="margin-left: 25px;"></span><span class="underline-field">Planning, Kano State</span>
        </div>
        <div style="text-align: right;">
            Our Ref: <span class="underline-field">{{ $record->our_ref ?? 'LS/VAL/FGE/5KM' }}</span><br>
            Your Ref: <span class="underline-field">{{ $record->your_ref ?? 'N/A' }}</span><br>
            Date: <span class="underline-field">{{ $record->valuation_date->format('jS F, Y') }}</span>
        </div>
    </div>

    <div class="location-banner">
        LOCATION: <span style="text-decoration: underline;">{{ $record->location }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 4%;">S/N</th>
                <th style="width: 15%;">Name of Owner</th>
                <th style="width: 12%;">Type of Building</th>
                <th style="width: 8%;">No. of Building</th>
                <th style="width: 10%;">Area Covered in M<sup>2</sup></th>
                <th style="width: 10%;">Rate of Cost ₦</th>
                <th style="width: 12%;">Amount of Compensation ₦</th>
                <th style="width: 10%;">Account Number</th>
                <th style="width: 10%;">Phone Number</th>
                <th style="width: 9%;">Remarks</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td style="font-weight: bold;">{{ $record->owner_name }}</td>
                <td>{{ $record->building_type }}</td>
                <td>{{ $record->building_count }}</td>
                <td>{{ number_format($record->area_covered, 2) }}</td>
                <td>{{ number_format($record->rate_of_cost, 2) }}</td>
                <td style="font-weight: bold;">{{ number_format($record->compensation_amount, 2) }}</td>
                <td>{{ $record->account_number }}</td>
                <td>{{ $record->phone_number }}</td>
                <td style="font-size: 11px;">{{ $record->remarks ?? '-' }}</td>
            </tr>
            <!-- Empty rows for spacing as in template -->
            @for($i=0; $i<5; $i++)
            <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
            @endfor
        </tbody>
    </table>

    <div class="footer-signatures">
        <div class="sig-row">
            <div class="sig-line">Head of Valuation</div>
            <div class="sig-line">Director Deeds</div>
        </div>
        <div class="perm-sec-wrap">
            <div class="sig-line" style="width: 350px;">Permanent Secretary</div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    function logPrint() {
        $.ajax({
            url: "{{ route('valuation-compensations.log-print', $record->id) }}",
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) { console.log('Print logged'); }
        });
    }
</script>

</body>
</html>

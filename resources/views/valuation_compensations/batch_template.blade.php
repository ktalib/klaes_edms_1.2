<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batch Compensation Valuation - {{ $project->project_name ?? 'Project' }}</title>
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
            max-width: 1400px;
            background-color: white;
            padding: 40px;
            border: 2px solid #00008B;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
        }

        .logo-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .logo-header img {
            height: 100px;
            object-contain: contain;
        }

        h1 {
            text-align: center;
            text-decoration: underline;
            font-size: 28px;
            margin-top: 10px;
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
            padding: 8px 4px;
            text-align: center;
            font-size: 12px;
            word-wrap: break-word;
        }

        th {
            background-color: #f8fafc;
            height: 40px;
            text-transform: uppercase;
            font-size: 10px;
        }

        .footer-signatures {
            margin-top: 40px;
        }

        .sig-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 60px;
        }

        .sig-line {
            width: 250px;
            border-top: 1px solid black;
            padding-top: 8px;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            font-size: 12px;
        }

        .perm-sec-wrap {
            display: flex;
            justify-content: center;
        }

        .logo-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 40px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }

        .logo-footer img {
            height: 40px;
            width: auto;
            max-width: 120px;
            object-contain: contain;
        }

        /* PRINT SETTINGS */
        @media print {
            @page {
                size: landscape;
                margin: 0.5cm;
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
                padding: 15px;
            }

            .no-print {
                display: none;
            }
        }

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
    </style>
</head>
<body>

<div class="no-print-bar no-print">
    <a href="{{ route('valuation-compensations.index') }}" class="btn btn-back">
        <span>Back to List</span>
    </a>
    <button onclick="window.print();" class="btn btn-print">
        <span>Print Batch Report</span>
    </button>
</div>

<div class="container">
    <div class="logo-header">
        <img src="http://app.klaes.ng/assets/logo/ministry1.jpg" alt="Header Left">
        <img src="http://app.klaes.ng/assets/logo/ministry2.jpeg" alt="Header Right">
    </div>

    <h1>COMPENSATION VALUATION</h1>

    <div class="header-meta">
        <div>
            @php
                $addressedTo = ($project && $project->addressed_to) 
                    ? $project->addressed_to 
                    : "The Honourable Commissioner\nMinistry of Land and Physical\nPlanning, Kano State";
                $addrLines = explode("\n", $addressedTo);
            @endphp
            To: <span class="underline-field">{{ $addrLines[0] ?? '' }}</span><br>
            @foreach(array_slice($addrLines, 1) as $line)
                <span style="margin-left: 25px;"></span><span class="underline-field">{{ $line }}</span><br>
            @endforeach
        </div>
        <div style="text-align: right;">
            Our Ref: <span class="underline-field">{{ $project->our_reference ?? ($project->project_fileno ?? 'N/A') }}</span><br>
            Your Ref: <span class="underline-field">{{ $project->your_reference ?? 'N/A' }}</span><br>
            Date: <span class="underline-field">{{ now()->format('jS F, Y') }}</span>
        </div>
    </div>

    <div class="location-banner">
        @php
            $projLocation = "";
            if ($project->project_name) $projLocation .= $project->project_name;
            
            $addr = "";
            if ($project->street) $addr .= $project->street;
            if ($project->district) $addr .= ($addr ? ", " : "") . $project->district;
            if ($project->lga) $addr .= ($addr ? ", " : "") . $project->lga;
            if ($project->state) $addr .= ($addr ? ", " : "") . $project->state . " State";
            
            if ($addr) {
                $projLocation .= ($projLocation ? " - " : "") . $addr;
            }
        @endphp
        LOCATION SCOPE: <span style="text-decoration: underline;">{{ $projLocation ?: ($records->first()->location ?? 'N/A') }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 3%;">S/N</th>
                <th style="width: 15%;">Name of Owner</th>
                <th style="width: 10%;">Type of Building</th>
                <th style="width: 6%;">No. of Building</th>
                <th style="width: 8%;">Area in M<sup>2</sup></th>
                <th style="width: 8%;">Rate ₦</th>
                <th style="width: 12%;">Amount ₦</th>
                <th style="width: 10%;">Account No</th>
                <th style="width: 10%;">Phone</th>
                <th style="width: 10%;">Ref No</th>
                <th style="width: 8%;">Remarks</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $index => $record)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td style="font-weight: bold; text-align: left; padding-left: 5px;">{{ $record->owner_name }}</td>
                <td>{{ $record->building_type }}</td>
                <td>{{ $record->building_count }}</td>
                <td>{{ number_format($record->area_covered, 2) }}</td>
                <td>{{ number_format($record->rate_of_cost, 2) }}</td>
                <td style="font-weight: bold;">{{ number_format($record->compensation_amount, 2) }}</td>
                <td>{{ $record->account_number }}</td>
                <td>{{ $record->phone_number }}</td>
                <td style="font-size: 10px;">{{ $record->our_ref }}</td>
                <td style="font-size: 10px;">{{ Str::limit($record->remarks, 20) }}</td>
            </tr>
            @endforeach
            
            <tr style="background-color: #f8fafc; font-weight: bold;">
                <td colspan="6" style="text-align: right; padding-right: 10px;">TOTAL COMPENSATION</td>
                <td colspan="5" style="text-align: left; padding-left: 10px; color: #0d9488; font-size: 14px;">
                    ₦{{ number_format($records->sum('compensation_amount'), 2) }}
                </td>
            </tr>
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

    <div class="logo-footer">
        <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="Footer Left">
        <img src="http://app.klaes.ng/assets/logo/las.jpg" alt="Footer Right">
    </div>
</div>

</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VFC Project Template - {{ $project->project_name }}</title>
    <style>
        body {
            font-family: "Times New Roman", Times, serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            background-color: white;
            padding: 40px;
            border: 2px solid #00008B;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            position: relative;
        }

        h1 {
            text-align: center;
            text-decoration: underline;
            font-size: 28px;
            margin-top: 0;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .project-banner {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 25px;
            color: #00008B;
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

        .worker-watermark {
            position: absolute;
            top: 20px;
            right: 40px;
            border: 2px dashed #ccc;
            padding: 10px;
            transform: rotate(5deg);
            color: #666;
            text-align: right;
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
                page-break-after: always;
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
            align-items: center;
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
    <a href="{{ route('valuation-compensations.projects.index') }}" class="btn btn-back">
        <span>Back to Console</span>
    </a>
    <button onclick="window.print()" class="btn btn-print">
        <span>Print All Templates</span>
    </button>
</div>

@foreach($project->workers as $worker)
<div class="container">
    <div class="worker-watermark">
        <strong>ASSIGNED WORKER</strong><br>
        Name: {{ $worker->user->first_name }} {{ $worker->user->last_name }}<br>
        ID: {{ $worker->worker_code }}
    </div>

    <h1>COMPENSATION VALUATION</h1>
    <div class="project-banner">PROJECT: {{ $project->project_name }} ({{ $project->project_code }})</div>

    <div class="header-meta">
        <div>
            To: <span class="underline-field">The Honourable Commissioner</span><br>
            <span style="margin-left: 25px;"></span><span class="underline-field">Ministry of Land and Physical</span><br>
            <span style="margin-left: 25px;"></span><span class="underline-field">Planning, Kano State</span>
        </div>
        <div style="text-align: right;">
            Our Ref: <span class="underline-field">LS/VAL/FGE/5KM</span><br>
            Worker ID: <span class="underline-field">{{ $worker->worker_code }}</span><br>
            Date: <span class="underline-field">____________________</span>
        </div>
    </div>

    <div class="location-banner">
        LOCATION SCOPE: <span style="text-decoration: underline;">{{ $project->street ?? $project->district ?? $project->lga }}, {{ $project->state }}</span>
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
            @for($i=1; $i<=$project->number_of_items; $i++)
            <tr>
                <td>{{ $i }}</td>
                <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
            </tr>
            @if($i % 15 == 0 && $i < $project->number_of_items)
            </tbody>
            </table>
            <div style="page-break-after: always;"></div>
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
            @endif
            @endfor
        </tbody>
    </table>

    <div class="footer-signatures">
        <div class="sig-row">
            <div class="sig-line">Head of Valuation</div>
            <div class="sig-line">Director Deeds</div>
        </div>
    </div>
</div>
@endforeach

</body>
</html>

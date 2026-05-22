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

        .generation-info {
            font-size: 10px;
            color: #666;
            margin-top: 10px;
            text-align: center;
            font-style: italic;
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
                min-height: 95vh;
                display: flex;
                flex-direction: column;
            }

            .footer-wrap {
                margin-top: auto;
                padding-top: 20px;
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

    <h1>COMPENSATION VALUATION ({{ $project->project_fileno ?? 'N/A' }})</h1>

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
                <th style="width: 4%;">S/N</th>
                <th style="width: 15%;">Name of Owner</th>
                <th style="width: 12%;">Type of Building</th>
                <th style="width: 6%;">No. of Building</th>
                <th style="width: 8%;">Area Covered in M<sup>2</sup></th>
                <th style="width: 9%;">Rate of Cost ₦</th>
                <th style="width: 12%;">Amount of Compensation ₦</th>
                <th style="width: 10%;">Account Number</th>
                <th style="width: 10%;">Phone Number</th>
               
                <th style="width: 6%;">Remarks</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $globalIndex = 1;
                $projectWorkers = $project->workers()->with('user')->get()->keyBy('worker_code');
                
                // Group by sub-project first
                $recordsBySubProject = $records->groupBy('sub_project_id');
                $totalValuation = 0;
            @endphp

            @foreach($recordsBySubProject as $subProjectId => $subProjectRecords)
                @php
                    $subProject = $subProjectRecords->first()->subProject;
                    $subProjectName = $subProject ? $subProject->name : 'General';
                    $subProjectTotal = 0;
                @endphp

                <!-- Sub-Project Header Row -->
                <tr style="border-top: 2px solid #000;">
                    <td colspan="1" style="font-weight: bold;"></td>
                    <td colspan="9" style="text-align: left; padding-left: 15px; font-weight: bold; text-transform: uppercase; background-color: #f8fafc;">
                        {{ $subProjectName }}
                    </td>
                </tr>

                @php
                    // Group by worker within this sub-project
                    $recordsByWorker = $subProjectRecords->groupBy('worker_id');
                @endphp

                @foreach($recordsByWorker as $workerId => $workerRecords)
                    @php
                        $worker = $workerRecords->first()->worker;
                        $workerName = ($worker && $worker->user) ? $worker->user->first_name : 'Unknown';
                        $workerCode = $worker ? $worker->worker_code : 'N/A';
                        $workerTotal = 0;
                    @endphp

                    <!-- Worker Header Row -->
                    <tr style="border-bottom: 2px solid #000;">
                        <td colspan="1" style="font-weight: bold;"></td>
                        <td colspan="9" style="text-align: left; padding-left: 15px; font-weight: bold; text-transform: uppercase; font-size: 10px; color: #475569;">
                            WORKER: {{ $workerName }} ({{ $workerCode }})
                        </td>
                    </tr>

                    @php
                        // Group by owner within this worker
                        $recordsByOwner = $workerRecords->groupBy('owner_name');
                    @endphp

                    @foreach($recordsByOwner as $ownerName => $ownerRecords)
                        @php
                            $ownerSubTotal = 0;
                            $ownerRecordCount = count($ownerRecords);
                        @endphp

                        @foreach($ownerRecords as $record)
                            @php
                                $recordTotal = (float)$record->compensation_amount;
                                $ownerSubTotal += $recordTotal;

                                // Parse compensated_items — supports JSON (new) and legacy string (old)
                                $buildings = [];
                                $subItems = [];
                                if ($record->compensated_items) {
                                    $decoded = json_decode($record->compensated_items, true);
                                    if (json_last_error() === JSON_ERROR_NONE && isset($decoded['buildings'])) {
                                        $buildings = $decoded['buildings'] ?? [];
                                        $subItems  = $decoded['items'] ?? [];
                                    } else {
                                        // Legacy string format
                                        $legacyItems = explode(', ', $record->compensated_items);
                                        foreach ($legacyItems as $itemStr) {
                                            if (strpos($itemStr, '[') === 0) continue;
                                            if (strpos($itemStr, ' (₦') !== false) {
                                                $parts = explode(' (₦', $itemStr);
                                                $name  = trim($parts[0]);
                                                $amt   = (float) str_replace([')', ','], '', $parts[1]);
                                                $subItems[] = ['name' => $name, 'amount' => $amt];
                                            }
                                        }
                                    }
                                }
                                $mainAmount = $record->compensation_amount - collect($subItems)->sum('amount');
                            @endphp

                            @if(count($buildings) > 0)
                                {{-- JSON format: one row per building --}}
                                @foreach($buildings as $bIdx => $building)
                                <tr>
                                    <td>{{ $bIdx === 0 ? $globalIndex++ : '' }}</td>
                                    <td style="font-weight: bold; text-align: left; padding-left: 8px;">
                                        {{ $bIdx === 0 ? $record->owner_name : '' }}
                                    </td>
                                    <td style="text-align: left; padding-left: {{ $bIdx > 0 ? '20px' : '8px' }};">
                                        {{ $bIdx > 0 ? '• ' : '' }}{{ $building['building_type'] ?? '' }}
                                    </td>
                                    <td>1</td>
                                    <td>{{ ($building['area'] ?? 0) > 0 ? number_format($building['area'], 2) : 'Allow' }}</td>
                                    <td style="text-align: right; padding-right: 8px;">{{ number_format($building['rate'] ?? 0, 2) }}</td>
                                    <td style="text-align: right; padding-right: 8px;">{{ number_format($building['amount'] ?? 0, 2) }}</td>
                                    <td style="font-size: 11px;">
                                        @if($bIdx === 0)
                                            <div style="font-weight: bold;">{{ $record->account_number }}</div>
                                            <div style="color: #666;">{{ $record->bank_name }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $bIdx === 0 ? $record->phone_number : '' }}</td>
                                    <td style="font-size: 9px; text-align: left;">{{ $bIdx === 0 ? Str::limit($record->remarks, 50) : '' }}</td>
                                </tr>
                                @endforeach
                            @else
                                {{-- Legacy / fallback: single aggregated row --}}
                                <tr>
                                    <td>{{ $globalIndex++ }}</td>
                                    <td style="font-weight: bold; text-align: left; padding-left: 8px;">{{ $record->owner_name }}</td>
                                    <td style="text-align: left; padding-left: 8px;">{{ $record->building_type }}</td>
                                    <td>{{ $record->building_count }}</td>
                                    <td>{{ $record->area_covered > 0 ? number_format($record->area_covered, 2) : 'Allow' }}</td>
                                    <td style="text-align: right; padding-right: 8px;">{{ number_format($record->rate_of_cost, 2) }}</td>
                                    <td style="text-align: right; padding-right: 8px;">{{ number_format($mainAmount, 2) }}</td>
                                    <td style="font-size: 11px;">
                                        <div style="font-weight: bold;">{{ $record->account_number }}</div>
                                        <div style="color: #666;">{{ $record->bank_name }}</div>
                                    </td>
                                    <td>{{ $record->phone_number }}</td>
                                    <td style="font-size: 9px; text-align: left;">{{ Str::limit($record->remarks, 50) }}</td>
                                </tr>
                            @endif

                            {{-- Compensated items sub-rows --}}
                            @foreach($subItems as $sub)
                            <tr style="font-size: 10px; color: #444; font-style: italic;">
                                <td></td>
                                <td></td>
                                <td style="text-align: left; padding-left: 20px;">• {{ $sub['name'] }}</td>
                                <td>1</td>
                                <td>Allow</td>
                                <td style="text-align: right; padding-right: 8px;">{{ number_format($sub['amount'], 2) }}</td>
                                <td style="text-align: right; padding-right: 8px;">{{ number_format($sub['amount'], 2) }}</td>
                                <td colspan="3"></td>
                            </tr>
                            @endforeach

                            <!-- Owner Total Row -->
                            @if($ownerRecordCount > 1 || count($subItems) > 0)
                            <tr>
                                <td colspan="6" style="text-align: right; padding-right: 15px; font-weight: bold; font-size: 10px; color: #555;">
                                    Sub Total {{ $ownerName }} ({{ $subProjectName }})
                                </td>
                                <td style="font-weight: bold; text-align: right; padding-right: 8px; border-top: 1px solid #999; font-size: 11px;">
                                    {{ number_format($ownerSubTotal, 2) }}
                                </td>
                                <td colspan="3"></td>
                            </tr>
                            @endif
                        @endforeach
                        
                        @php $workerTotal += $ownerSubTotal; @endphp
                    @endforeach

                    <!-- Worker Sub-total row -->
                    @if(count($recordsByWorker) > 1)
                    <tr style="border-top: 1.5px solid #000; background-color: #f1f5f9;">
                        <td colspan="6" style="text-align: right; padding-right: 15px; height: 30px; font-weight: bold; text-transform: uppercase; font-size: 10px;">
                            Sub Total {{ $workerName }} ({{ count($recordsByOwner) }})
                        </td>
                        <td style="text-align: right; padding-right: 8px; font-size: 12px; font-weight: bold; border-bottom: 2px solid #000;">
                            {{ number_format($workerTotal, 2) }}
                        </td>
                        <td colspan="3"></td>
                    </tr>
                    @endif

                    @php $subProjectTotal += $workerTotal; @endphp
                @endforeach

                <!-- Sub-Project Sub-total row -->
                <tr style="border-top: 1.5px solid #000;">
                    <td colspan="6" style="text-align: right; padding-right: 15px; height: 35px; font-weight: bold; text-transform: uppercase; font-size: 11px;">
                        Sub Total {{ $subProjectName }}
                    </td>
                    <td style="text-align: right; padding-right: 8px; font-size: 14px; font-weight: bold; border-bottom: 3px double #000;">
                        {{ number_format($subProjectTotal, 2) }}
                    </td>
                    <td colspan="3"></td>
                </tr>

                @php $totalValuation += $subProjectTotal; @endphp
            @endforeach
            
            @php
                $percent = $customPercentage ?? ($project->apply_percentage ?? 0);
                $appliedAmount = ($totalValuation * $percent) / 100;
                $finalTotal = $totalValuation + $appliedAmount;
            @endphp
            
            <tr style="font-weight: bold; border-top: 2px solid #000;">
                <td colspan="6" style="text-align: right; padding-right: 15px; height: 45px; font-size: 14px;">Sub Total</td>
                <td style="text-align: right; padding-right: 8px; font-size: 16px;">
                    ₦{{ number_format($totalValuation, 2) }}
                </td>
                <td colspan="3"></td>
            </tr>

            @if($percent > 0)
            <tr style="font-weight: bold; border-top: 1px solid #000;">
                <td colspan="6" style="text-align: right; padding-right: 15px; height: 40px; font-size: 14px;">{{ $percent }}%</td>
                <td style="text-align: right; padding-right: 8px; font-size: 16px;">
                    ₦{{ number_format($appliedAmount, 2) }}
                </td>
                <td colspan="3"></td>
            </tr>
            @endif

            <tr style="font-weight: bold; border: 2px solid #000;">
                <td colspan="6" style="text-align: right; padding-right: 15px; height: 60px; font-size: 16px;">Grand Total</td>
                <td style="text-align: right; padding-right: 8px; font-size: 20px;">
                    ₦{{ number_format($finalTotal, 2) }}
                </td>
                <td colspan="3"></td>
            </tr>
        </tbody>
    </table>

    <div class="footer-wrap">
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
            <div class="generation-info">
                Generated by {{ Auth::user()->first_name ?? 'Klaes' }} {{ Auth::user()->last_name ?? 'Admin' }} 
                at {{ now()->format('g:i A') }} & {{ now()->format('d/m/Y') }}
            </div>
            <img src="http://app.klaes.ng/assets/logo/las.jpg" alt="Footer Right">
        </div>
    </div>
</div>

</body>
</html>

{{--
    The Master JSI sheet, as the Ministry writes it by hand.

    One page for all five parcel updates: the heading, the narrative and the rows in
    the S/N | PORTION | DIMENSION | MEASUREMENT table all come from
    MasterJsiPortionTemplates, so nothing here branches per type.

    Browser print, not dompdf — this codebase uses dompdf only for invoices and
    legal-search exports, and every land memo is an A4 HTML page printed from the
    browser (see deeds/parcel_update/print/subdivision_recommendation.blade.php).
--}}
@php
    use App\Support\ParcelSizeSummary;

    $heading = $template['heading'] ?? ucwords(str_replace('_', ' ', $report->parcel_update_type));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report->jsi_ref }} — Joint Site Inspection</title>
    <style>
        @page { size: A4; margin: 0; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "Times New Roman", Times, serif;
            font-size: 12pt;
            color: #000;
            background: #f1f5f9;
        }

        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 18mm 16mm;
            background: #fff;
        }

        .head { text-align: center; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 14px; }
        .head h1 { font-size: 15pt; margin: 0; text-transform: uppercase; letter-spacing: .5px; }
        .head h2 { font-size: 12pt; margin: 4px 0 0; font-weight: normal; text-transform: uppercase; }
        .head .ref { font-size: 10pt; margin-top: 6px; }

        .meta { width: 100%; border-collapse: collapse; margin-bottom: 14px; font-size: 11pt; }
        .meta td { padding: 3px 0; vertical-align: top; }
        .meta .k { width: 34mm; font-weight: bold; }

        .narrative { line-height: 1.7; text-align: justify; margin-bottom: 6px; }
        .points { line-height: 1.9; margin: 0 0 14px; padding-left: 18px; }
        .points li { margin-bottom: 2px; }

        table.grid { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.grid th, table.grid td { border: 1px solid #000; padding: 7px 8px; font-size: 11pt; }
        table.grid th { background: #f3f4f6; text-align: left; text-transform: uppercase; font-size: 9.5pt; letter-spacing: .4px; }
        table.grid td.sn { width: 12mm; text-align: center; }
        table.grid td.num { white-space: nowrap; }

        /* The recommended line is the answer the sheet gives, not another
           observation, so it is set apart the way the handwritten sheets underline it. */
        tr.derived td { font-weight: bold; background: #fafafa; }

        .sign { margin-top: 26mm; display: flex; justify-content: space-between; gap: 14mm; }
        .sign div { flex: 1; text-align: center; }
        .sign .line { border-top: 1px solid #000; padding-top: 5px; font-size: 10pt; }

        .foot { margin-top: 10mm; font-size: 8.5pt; color: #555; text-align: center; }

        @media print {
            body { background: #fff; }
            .sheet { margin: 0; padding: 16mm 14mm; }
            .no-print { display: none !important; }
        }

        .no-print { text-align: center; padding: 12px; }
        .no-print button {
            padding: 9px 22px; font-size: 13px; font-weight: bold; cursor: pointer;
            background: #dc2626; color: #fff; border: 0; border-radius: 8px;
        }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()">Print this sheet</button>
</div>

<div class="sheet">
    <div class="head">
        <h1>Joint Site Inspection Report</h1>
        <h2>{{ $heading }}</h2>
        <div class="ref">
            <strong>{{ $report->jsi_ref }}</strong>
            &nbsp;·&nbsp; {{ $report->category }}
            @if ($report->inspection_date) &nbsp;·&nbsp; {{ $report->inspection_date->format('d F Y') }} @endif
        </div>
    </div>

    <table class="meta">
        @foreach ([
            ['File Number', $report->file_number],
            ['File Title', $report->file_title],
            ['Applicant', \App\Support\PersonName::display($report->applicant_name)],
            ['Plot Number', $report->plot_number],
            ['Location', $report->location],
            ['District / LGA', trim(collect([$report->district, $report->lga])->filter()->implode(' / '))],
        ] as [$k, $v])
            @if ($v !== null && $v !== '')
            <tr><td class="k">{{ $k }}:</td><td>{{ $v }}</td></tr>
            @endif
        @endforeach
    </table>

    <p class="narrative">{{ $template['narrative'] ?? '' }}</p>

    {{-- The observations the sheet states in words before it states them in the
         table. Only the ones actually recorded are printed — a blank line on a
         signed inspection reads as a question that was never asked. --}}
    <ul class="points">
        @if ($report->available_on_ground)
            <li>The site was found to be <strong>{{ strtolower($report->available_on_ground) }}</strong> on ground.</li>
        @endif
        @if ($report->boundary_description)
            <li>The site was bounded by — {{ $report->boundary_description }}</li>
        @endif
        @if ($report->number_of_units)
            <li>The site will be divided into <strong>{{ $report->number_of_units }}</strong> number of plots.</li>
        @endif
        @if ($report->average_size)
            <li>The site has an average size of <strong>{{ $report->average_size }}</strong>.</li>
        @endif
        @if ($report->prevailing_land_use)
            <li>The prevailing land use is <strong>{{ $report->prevailing_land_use }}</strong>.</li>
        @endif
        @if ($report->existing_land_use || $report->recommended_land_use)
            <li>
                Change of purpose from <strong>{{ $report->existing_land_use ?: '—' }}</strong>
                to <strong>{{ $report->recommended_land_use ?: '—' }}</strong> land use.
            </li>
        @endif
        @if ($report->conformity !== null)
            <li>
                The site was found
                <strong>{{ $report->conformity ? 'to conform' : 'not to conform' }}</strong>
                with the surrounding land use.
            </li>
        @endif
        @if ($report->road_reservation)
            <li>Road reservation: {{ $report->road_reservation }}</li>
        @endif
        @if ($report->additional_observations)
            <li>{{ $report->additional_observations }}</li>
        @endif
    </ul>

    <table class="grid">
        <thead>
            <tr>
                <th style="width:12mm;text-align:center;">S/N</th>
                <th>Portion</th>
                <th style="width:50mm;">Dimension</th>
                <th style="width:52mm;">Measurement (m²/ha)</th>
            </tr>
        </thead>
        <tbody>
            @php
                // Which roles the sheet marks as the recommendation, so the printed
                // table sets the same line apart the capture form does.
                $derivedRoles = collect($template['rows'] ?? [])
                    ->filter(fn ($r) => $r['derived'] ?? false)
                    ->pluck('role')
                    ->all();
            @endphp
            @forelse ($report->portions as $portion)
            <tr class="{{ in_array($portion->role, $derivedRoles, true) ? 'derived' : '' }}">
                <td class="sn">{{ $portion->sn }}</td>
                <td>{{ $portion->label }}</td>
                <td>
                    {{ $portion->dimensions
                        ?: ($portion->land_use ?: ($portion->unit_count !== null ? $portion->unit_count : '')) }}
                </td>
                <td class="num">{{ $portion->measurementText() }}</td>
            </tr>
            @empty
            <tr><td colspan="4" style="text-align:center;padding:18px;">No measurements recorded.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="sign">
        <div>
            <div class="line">
                {{ $report->inspection_officer ?: 'Physical Planning Officer' }}<br>
                <span style="font-size:9pt;">Inspection Officer</span>
            </div>
        </div>
        <div>
            <div class="line">
                <span style="font-size:9pt;">Director, Physical Planning</span>
            </div>
        </div>
    </div>

    <div class="foot">
        {{ $report->jsi_ref }} · generated {{ now()->format('d M Y H:i') }}
        @if ($report->sent_to_deeds_at) · sent to Deeds {{ $report->sent_to_deeds_at->format('d M Y') }} @endif
    </div>
</div>

</body>
</html>

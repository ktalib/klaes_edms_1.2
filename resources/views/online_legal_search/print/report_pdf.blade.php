{{--
    PDF rendition of the Online Legal Search report, emailed to the requester
    once a Director / Deputy Director approves the request.

    Kept deliberately table-based (no flexbox) because DomPDF does not lay out
    flex containers — the on-screen template at online_legal_search/result.blade.php
    is the flex version and must not be reused here.
--}}
@php
    // DomPDF reads local files directly; skip any logo that is not deployed.
    $logo = function (string $relative) {
        $path = public_path($relative);
        return is_file($path) ? $path : null;
    };

    $logoLeft   = $logo('assets/logo/ministry1.jpg');
    $logoRight  = $logo('assets/logo/ministry2.jpeg');
    $logoKlaes  = $logo('storage/upload/logo/logo.png');
    $logoLas    = $logo('assets/logo/las.jpg');

    $rows = $report['rows'] ?? [];

    // Only show the File No column when the history spans more than one file,
    // matching the on-screen report.
    $uniqueFileNumbers = collect($rows)
        ->pluck('file_no')
        ->filter(fn ($fn) => $fn && $fn !== '-')
        ->unique();
    $showFileNo = $uniqueFileNumbers->count() > 1;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Legal Search Report — {{ $report['file_number'] ?? '' }}</title>
    <style>
        @page { margin: 18px 22px; }
        /* Helvetica is a DomPDF core font, so nothing is embedded and the
           attachment stays small; the report is plain Latin text throughout. */
        body { font-family: Helvetica, Arial, sans-serif; color: #000; font-size: 10px; margin: 0; }

        .hdr { width: 100%; border-bottom: 2px solid #000; padding-bottom: 3px; margin-bottom: 6px; }
        .hdr td { vertical-align: middle; }
        .hdr .logo { width: 80px; }
        .hdr .logo img { width: 68px; }
        .hdr .title { text-align: center; }
        .hdr .title h1 { font-size: 14px; color: #007a33; margin: 0; }
        .hdr .title h2 { font-size: 12px; margin: 2px 0; }
        .hdr .title h3 { font-size: 10px; margin: 0; text-decoration: underline; }
        .date-line { text-align: right; font-size: 10px; font-weight: bold; margin-bottom: 4px; }

        .section-label { border: 1px solid #000; display: inline-block; padding: 1px 6px; font-weight: bold; font-size: 9px; margin: 4px 0; }

        .prop { width: 100%; border-collapse: collapse; font-size: 10px; margin-bottom: 6px; }
        .prop td { padding: 1px 0; vertical-align: top; }
        .prop .lbl-l { font-weight: bold; width: 105px; }
        .prop .lbl-r { font-weight: bold; width: 95px; padding-left: 26px; }

        .tx { width: 100%; border-collapse: collapse; font-size: 8px; table-layout: fixed; }
        .tx th, .tx td { padding: 3px; vertical-align: top; border-bottom: 0.5px solid #e5e5e5; word-wrap: break-word; text-align: left; }
        .tx thead th { font-weight: bold; border-bottom: 1.2px solid #000; }
        .tx tr { page-break-inside: avoid; }

        .end-notice { text-align: center; font-size: 8.5px; font-weight: bold; font-style: italic; margin: 6px 0; }
        .remarks { width: 100%; border-collapse: collapse; border-top: 1px solid #000; margin-top: 10px; padding-top: 6px; }
        .remarks .rl { border: 1px solid #000; padding: 2px 8px; font-weight: bold; font-size: 10px; width: 70px; }
        .remarks .rt { color: #0f766e; font-size: 10px; font-weight: bold; padding-left: 10px; }
        .disclaimer { font-size: 8px; font-style: italic; font-weight: bold; margin: 8px 0 4px; color: #333; }
        .foot { width: 100%; border-collapse: collapse; border-top: 1px solid #000; padding-top: 4px; margin-top: 8px; font-size: 7.5px; color: #333; }
        .foot td { vertical-align: middle; }
        .foot img { height: 22px; }
        .foot .mid { text-align: center; }
        .foot .r { text-align: right; }
    </style>
</head>
<body>

<table class="hdr">
    <tr>
        <td class="logo">@if($logoLeft)<img src="{{ $logoLeft }}" alt="">@endif</td>
        <td class="title">
            <h1>KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
            <h2>LEGAL SEARCH REPORT</h2>
            <h3>Online Pay-per Search</h3>
        </td>
        <td class="logo" style="text-align:right;">@if($logoRight)<img src="{{ $logoRight }}" alt="">@endif</td>
    </tr>
</table>

<div class="date-line">{{ $report['date_line'] ?? 'Date: ' . now()->format('F j, Y') }}</div>

<div class="section-label">Property Details</div>
<table class="prop">
    <tr>
        <td class="lbl-l">File Number:</td><td><strong>{{ $report['file_number'] ?: '-' }}</strong></td>
        <td class="lbl-r">District/LGA:</td><td><strong>{{ $report['district_lga'] ?: '-' }}</strong></td>
    </tr>
    <tr>
        <td class="lbl-l">File Title:</td><td><strong>{{ $report['file_title'] ?: '-' }}</strong></td>
        <td class="lbl-r">Plot No:</td><td><strong>{{ $report['plot_no'] ?: '-' }}</strong></td>
    </tr>
    <tr>
        <td class="lbl-l">Land Use:</td><td><strong>{{ $report['land_use'] ?: '-' }}</strong></td>
        <td class="lbl-r">Size:</td><td><strong>{{ $report['size'] ?: '-' }}</strong></td>
    </tr>
    <tr>
        <td class="lbl-l">Plot Description:</td><td><strong>{{ $report['plot_description'] ?: '-' }}</strong></td>
        <td class="lbl-r">TP No:</td><td><strong>{{ $report['tpno'] ?: '-' }}</strong></td>
    </tr>
</table>

<div class="section-label">File History</div>
<table class="tx">
    <thead>
        <tr>
            <th style="width:3%">S/N</th>
            @if($showFileNo)<th style="width:9%">File No</th>@endif
            <th style="width:{{ $showFileNo ? '18%' : '27%' }}">Instrument/Transaction Type</th>
            <th style="width:13%">Party 1</th>
            <th style="width:13%">Party 2</th>
            <th style="width:10%">Party 3</th>
            <th style="width:10%">Reg. Time/Date</th>
            <th style="width:9%">Particulars</th>
            <th style="width:5%">Caveat</th>
            <th style="width:10%">Comments</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $row)
            <tr>
                <td>{{ $row['sn'] ?? $loop->iteration }}</td>
                @if($showFileNo)<td>{{ $row['file_no'] ?? '-' }}</td>@endif
                <td>{{ $row['instrument_type'] ?? '-' }}</td>
                <td>{{ $row['grantor'] ?? '-' }}</td>
                <td>{{ $row['grantee'] ?? '-' }}</td>
                <td>{{ $row['party_3'] ?? '-' }}</td>
                <td>
                    @if(!empty($row['reg_time']))<strong>{{ $row['reg_time'] }}</strong><br>@else-<br>@endif
                    {{ $row['reg_date'] ?? '-' }}
                </td>
                <td>{{ $row['reg_no'] ?? '0/0/0' }}</td>
                <td>{{ $row['caveat'] ?? '-' }}</td>
                <td>{{ $row['comments'] ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="{{ $showFileNo ? 10 : 9 }}" style="text-align:center;padding:12px;">No transactions on record for this file.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="end-notice">*** END OF TRANSACTION HISTORY ***</div>

<table class="remarks">
    <tr>
        <td class="rl">Remarks</td>
        <td class="rt">
            {{ $report['caveat_note'] ?: (($report['is_caveated'] ?? false)
                ? 'A caveat exists on this title. Please consult the Ministry before proceeding.'
                : 'Based on our available records, the title is free from encumbrances.') }}
        </td>
    </tr>
</table>

<p class="disclaimer">
    N.B: This search report is deduced based on the available records from the file and does not represent any document in possession of any body.
    @if(!empty($report['remarks'])) {{ $report['remarks'] }} @endif
</p>

<table class="foot">
    <tr>
        <td style="width:80px;">@if($logoKlaes)<img src="{{ $logoKlaes }}" alt="">@endif</td>
        <td class="mid">
            Request {{ $searchRequest->request_no }} — approved by {{ $searchRequest->reviewer_name ?: 'the Ministry' }}@if($searchRequest->reviewer_rank) ({{ $searchRequest->reviewer_rank }})@endif
            on {{ optional($searchRequest->reviewed_at)->format('g:i A & d/m/Y') ?: now()->format('g:i A & d/m/Y') }}.
            Issued to {{ $searchRequest->requester_email }}.
        </td>
        <td class="r" style="width:80px;">@if($logoLas)<img src="{{ $logoLas }}" alt="">@endif</td>
    </tr>
</table>

</body>
</html>

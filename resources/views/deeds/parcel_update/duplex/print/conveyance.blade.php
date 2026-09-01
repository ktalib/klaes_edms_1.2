{{--
    Conveyance letter — the official Ministry format.

    Deliberately NOT the tabular sheet the application and memo use: this is the letter
    that goes out to the applicant, so it follows the house layout exactly — title number
    centred at the top, date right, addressee block, an underlined RE line, then prose.

    A duplex can carry several updates, so the RE line and the body are composed from the
    stages in execution order rather than hard-coded to one workflow.
--}}
@php
    /** The registry writes CON-AG-2021-171; the letter reads CON/AG/2021/171. */
    $slash = fn ($no) => str_replace('-', '/', (string) $no);

    $sources = array_values(array_filter((array) ($duplex->source_file_nos ?? [])));
    $title   = $slash($sources[0] ?? '');

    $landUseName = [
        'RES' => 'residential', 'COM' => 'commercial', 'IND' => 'industrial',
        'AGR' => 'agricultural', 'AG' => 'agricultural', 'AGRIC' => 'agricultural',
        'MIX' => 'mixed use',
    ];

    $currentUse = $landUseName[strtoupper((string) $duplex->land_use)] ?? strtolower((string) $duplex->land_use);

    /** Sizes as the letter reads them: "1,500 + 2,000 + 3,000 m²", blanks skipped. */
    // Square metres run to five figures, so they carry a thousands separator: a plot
    // reads "1,410" not "1410". Fractional metres are kept where a survey gives them.
    $num = function ($v) {
        $f = (float) $v;
        return $f == floor($f)
            ? number_format($f, 0, '.', ',')
            : rtrim(rtrim(number_format($f, 2, '.', ','), '0'), '.');
    };

    $sizesOf = function ($stage) use ($num) {
        $list = collect((array) data_get($stage->payload, 'plots', []))
            ->pluck('size')
            ->filter(fn ($v) => $v !== null && $v !== '' && (float) $v > 0)
            ->map(fn ($v) => (float) $v)
            ->values();

        return $list->isEmpty()
            ? ['list' => '', 'total' => '', 'has' => false]
            : ['list' => $list->map($num)->implode(' + '), 'total' => $num($list->sum()), 'has' => true];
    };

    /**
     * The new purpose, in words, for the conveyance.
     *
     * Where every file in the stage changes to the same purpose this is one word.
     * Where they differ the letter must name each file and what it becomes — "RES /
     * COM" is legible on a screen but says nothing on an instrument, and picking one
     * of them would misstate the others.
     */
    $words = fn ($code) => $landUseName[strtoupper((string) $code)] ?? strtolower((string) $code);

    /**
     * The purpose being changed FROM, for one stage.
     *
     * It is the CHANGING file's own land use, not the duplex's. The duplex takes its
     * land use from the first source file, which is very often not the file being
     * changed — this letter's own case is a COM file changing inside a duplex whose
     * first source is RES, and reading it from the duplex printed "from residential
     * to residential".
     *
     * Null when the files disagree, which is the caller's signal to name them one by one.
     */
    $copCurrentUse = function ($stage) {
        $codes = array_values(array_unique(array_filter(array_map(
            fn ($r) => strtoupper(trim((string) ($r['current_land_use'] ?? ''))),
            $stage->copRows()
        ))));

        if (count($codes) === 1) {
            return $codes[0];
        }

        // A LATER Change of Purpose has no file rows — its parcels do not exist yet
        // when it is planned. The stage answers for them: the purpose recorded on it,
        // or failing that the one the previous Change of Purpose left them with.
        return $stage->currentLandUseLabel();
    };

    /**
     * The whole change-of-purpose clause. Three shapes, in order of preference:
     *
     *   no per-file rows      "from residential to commercial use"   (as captured before
     *                                                                 per-file purposes)
     *   one from, one to      "from commercial to residential use"
     *   anything else         "(COM/RC/82/420 from commercial to residential, ...)"
     */
    $copClause = function ($stage, $of) use ($words, $currentUse, &$copCurrentUse, &$newUseWords) {
        if (empty($stage->copRows())) {
            $from = $copCurrentUse($stage);

            return 'change of purpose' . $of . ' from ' . ($from ? $words($from) : $currentUse)
                . ' to ' . $words($stage->newLandUseLabel()) . ' use';
        }

        $from = $copCurrentUse($stage);

        if ($from && !$stage->hasMixedNewLandUses()) {
            return 'change of purpose' . $of . ' from ' . $words($from)
                . ' to ' . $words($stage->newLandUseLabel()) . ' use';
        }

        return 'change of purpose' . $of . ' (' . $newUseWords($stage) . ')';
    };

    $newUseWords = function ($stage) use ($words) {
        $parts = [];

        foreach ($stage->copRows() as $row) {
            $file = trim((string) ($row['file_no'] ?? ''));
            $to   = $words($row['new_land_use'] ?? '');
            $from = trim((string) ($row['current_land_use'] ?? ''));

            if ($file === '' || $to === '') {
                continue;
            }

            $parts[] = str_replace('-', '/', $file)
                . ($from !== '' ? ' from ' . $words($from) : '')
                . ' to ' . $to;
        }

        return $parts ? implode(', ', $parts) : $words($stage->newLandUseLabel());
    };

    // One clause per stage, in the order the duplex runs them. These read as prose in
    // the RE line and again in the body, so they are phrased as noun phrases — with the
    // parcel sizes named, which is what makes the letter checkable against the plan.
    $clauses = [];
    foreach ($duplex->stageRows as $stage) {
        $plots = $stage->plot_count ?: count(array_filter((array) data_get($stage->payload, 'plots', [])));
        $size  = $sizesOf($stage);
        $of    = $size['has'] ? ' of ' . $size['list'] . ' m²' : '';

        $clauses[] = match ($stage->type) {
            'merger' => 'merger of ' . count($sources) . ' titles'
                . ($size['has'] ? ' measuring ' . $size['list'] . ' m²' : '')
                . ' into one',
            'subdivision' => 'subdivision into ' . $plots . ' plots' . $of,
            'separation'  => 'separation into ' . $plots . ' plots' . $of,
            'extension'   => 'extension of the plot boundary' . $of,
            'change_of_purpose' => $copClause($stage, $of),
            default => $stage->label() . $of,
        };
    }

    $reLine = strtoupper(implode(', ', $clauses));

    $applied = $duplex->created_at ? $duplex->created_at->format('jS F, Y') : '';
    $issued  = ($duplex->conveyance_generated_at ?: now())->format('jS F, Y');

    /**
     * The letter prints on the ministry's letterhead artwork.
     *
     * ?plain=1 prints exactly the same letter without it — for a comparison, and
     * for the case where it is being run onto pre-printed letterhead paper, where
     * printing the artwork again would double it.
     */
    $letterhead = ! request()->boolean('plain');
@endphp

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Conveyance — {{ $duplex->duplex_id }}</title>
    <style>
        /* No page margin: the letterhead artwork has to print edge to edge. The
           text insets that used to be the @page margin are .a4-page padding now. */
        @page { size: A4; margin: 0; }

        body {
            font-family: "Times New Roman", Times, serif;
            font-size: 12.5pt;
            line-height: 1.55;
            color: #000;
            margin: 0;
            padding: 0;
        }

        /*
         * The sheet. On the ministry's letterhead the reply box's bottom rule sits
         * 62mm down the page (measured off the scan: 4798x6735px = 210x297mm), so
         * the letter opens at 66mm — below it, not across it. Left and right keep
         * the 22mm measure the @page margin used to give, and the bottom padding
         * reserves the strip the fixed footer occupies.
         */
        .a4-page {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 66mm 22mm 96px;
            background-color: #fff;
        }
@if ($letterhead)
        /*
         * Sized to the exact A4 rectangle, NOT 100% 100%: a letter that runs long
         * grows the sheet past 297mm, and a stretched background would drag the
         * ministry's ref box down the page with it. no-repeat, so a second page
         * comes out as a plain continuation sheet.
         */
        .a4-page {
            background-image: url('{{ asset('assets/letterhead/bg.png') }}');
            background-size: 210mm 297mm;
            background-repeat: no-repeat;
            background-position: left top;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
@endif

        @media screen {
            body { background: #525659; padding: 18px 0; }
            .a4-page { box-shadow: 0 0 10px rgba(0, 0, 0, .5); }
        }

        @media print {
            body { background: #fff; }
            .a4-page { box-shadow: none; margin: 0; }
        }

        .title-no   { text-align: center; font-weight: bold; margin-bottom: 26px; }
        .date       { text-align: right; margin-bottom: 30px; }

        .addressee  { margin-bottom: 26px; }
        .addressee div { line-height: 1.35; }

        /* The RE line is the one thing a reader scans for, hence bold + underlined,
           and justified so it fills the measure like the printed original. */
        .re {
            font-weight: bold;
            text-decoration: underline;
            text-align: justify;
            text-justify: inter-word;
            margin-bottom: 22px;
        }

        p { text-align: justify; text-justify: inter-word; margin: 0 0 18px; }

        .sign-off  { margin-top: 34px; }
        .signature { margin-top: 46px; font-style: italic; font-weight: bold; line-height: 1.4; }

        /*
         * The marks belong at the FOOT OF THE PAGE, not after the last line of text —
         * a short letter left them floating halfway up the sheet. `position: fixed`
         * pins them to the bottom of the page box (inside the @page margins) and
         * repeats them if the letter ever runs to a second page.
         *
         * body gets matching bottom padding so a long letter cannot run underneath it.
         */
        .page-footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            /* The @page margin that used to inset it is gone, so it carries the
               sheet's own 22mm measure and lifts itself off the paper edge. */
            padding: 0 22mm 10mm;
        }

        .footer-logo {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
        }
        .footer-logo img { height: 58px; width: auto; }

        /* Ask the browser to print it — background images and colour are stripped by
           default, and a logo that vanishes on paper is worse than none. */
        @media print {
            .footer-logo img {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        @media print { .no-print { display: none; } }
        .no-print { text-align: center; margin-bottom: 18px; }
        .no-print button {
            padding: 8px 18px; font-family: system-ui, sans-serif; font-size: 13px;
            font-weight: 600; border: 1px solid #cbd5e1; border-radius: 8px;
            background: #2563eb; color: #fff; cursor: pointer;
        }
    </style>
</head>
{{-- Opens the print dialog on load, the same as the application and recommendation
     sheets (duplex/print/_layout.blade.php). This route is only ever reached by
     clicking Print on the register, so the click has already been made - asking for a
     second one on arrival was a step with nothing behind it. The button stays for the
     reprint: cancelling the dialog must not leave the page with no way to print. --}}
<body onload="window.print()">

<div class="no-print">
    <button onclick="window.print()">Print this conveyance</button>
</div>

<div class="a4-page">

<div class="title-no">{{ $title ?: '—' }}</div>

<div class="date">{{ $issued }}</div>

<div class="addressee">
    <div>{{ strtoupper($duplex->applicant_name ?: $duplex->file_title) }}</div>
    @if ($duplex->address)
        <div>{{ strtoupper($duplex->address) }}</div>
    @else
        {{-- The postal address is not captured on the duplex; the parcel's location is
             printed instead so the letter is not left blank. --}}
        <div>{{ strtoupper(collect([$duplex->plot_no ? 'PLOT ' . $duplex->plot_no : null, $duplex->street_name, $duplex->district, $duplex->lga])->filter()->implode(', ')) }}</div>
    @endif
    <div>{{ strtoupper($duplex->state ?: 'KANO') }} STATE.</div>
</div>

<div class="re">
    RE: APPLICATION FOR {{ $reLine }} OVER TITLE NO {{ $title }}.
</div>

<p>
    Reference to your application{{ $applied ? ' dated ' . $applied : '' }} in connection with the above
    captioned, I am directed to inform you that, your application for
    {{ implode(', ', $clauses) }} has been recommended by Kano State Urban Planning and
    Development Authority (KNUPDA) in view of the fact that, the site is adequate in size
    requirement, accessible and conforms with the existing land use in the area.
</p>

<p>Above is for your kind acceptance or otherwise, please.</p>

<div class="sign-off">Yours Faithfully</div>

<div class="signature">
    <div>{{ $duplex->approvedBy->name ?? '__________________________' }}</div>
    <div>Senior Land Officer</div>
    <div>For: Honourable Commission.</div>
</div>

</div>{{-- /.a4-page --}}

<div class="page-footer">
    <div class="footer-logo">
        {{-- Absolute URL on purpose: this sheet is printed and shared from environments
             that are not always the app host. --}}
        <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES">
        <img src="http://app.klaes.ng/assets/logo/Left_Logo.png" alt="LAnd ADmin Enterprise System">
    </div>
</div>

</body>
</html>

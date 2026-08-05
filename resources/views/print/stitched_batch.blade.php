{{--
    One printable document assembled from several single-record print views.

    $head / $bodies come from App\Services\StitchedBatchPrint, which renders each
    record through its own print template — so this file holds no letter markup of
    its own and the batch can never drift from what a single print produces.

    Expects: $head, $bodies, $title, $subtitle, $logUrls (may be empty).
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>

    {{-- The head of the record template: its styles, fonts and metadata. --}}
    {!! $head !!}

    <style>
        /* A zero-height marker placed BETWEEN records. Wrapping each letter in a box
           instead would add a block to the record's own page flow and can push its
           last lines onto an extra sheet. */
        .batch-print-break {
            height: 0;
            margin: 0;
            border: 0;
            break-before: page;
            page-break-before: always;
        }

        .batch-print-bar {
            position: sticky; top: 0; z-index: 999999;
            display: flex; align-items: center; gap: 12px;
            padding: 10px 16px;
            background: #4c1d95; color: #fff;
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
            font-size: 13px; line-height: 1.4;
        }
        .batch-print-bar strong { font-weight: 700; }
        .batch-print-bar button {
            margin-left: auto; padding: 6px 14px; border: 0; border-radius: 6px;
            background: #fff; color: #4c1d95; font-weight: 700; font-size: 12px; cursor: pointer;
        }
        @media print {
            .batch-print-bar { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="batch-print-bar no-print">
        <span>{{ $subtitle }}</span>
        <button type="button" onclick="window.print()">Print all</button>
    </div>

    {{-- $breakBetween: false when the record template already forces a page break
         between its own top-level pages. rofo_print does, via the general-sibling
         rule `.page-container ~ .page-container { page-break-before: always }` —
         once stitched, every page-container is a sibling, so record 2 already
         starts on a fresh sheet and a marker here would add a blank one. --}}
    @foreach($bodies as $i => $body)
        @if(($breakBetween ?? true) && $i > 0)<div class="batch-print-break"></div>@endif
        {!! $body !!}
    @endforeach

    <script>
        // The per-record scripts are stripped server-side, so printing and logging
        // happen once here for the whole batch.
        var LOG_URLS   = @json($logUrls ?? []);
        var CSRF_TOKEN = '{{ csrf_token() }}';
        var logged     = false;

        window.addEventListener('afterprint', function () {
            // The opener refreshes either way — a caller that logged the batch before
            // opening this page passes no URLs, and its list still needs to update.
            try { window.opener.location.reload(); } catch (e) {}

            if (logged || !LOG_URLS.length) return;
            logged = true;

            LOG_URLS.forEach(function (url) {
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                }).catch(function (e) { console.error('Failed to log print: ' + url, e); });
            });
        });
    </script>
</body>
</html>

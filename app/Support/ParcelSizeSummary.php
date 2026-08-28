<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * The parcel sizes a parcel-update application recorded, as a memo reads them:
 * "1,500 + 2,000 + 3,000 m² (total 6,500 m²)".
 *
 * Lifted out of duplex/print/recommendation.blade.php, which is the sheet the
 * Ministry accepted, so the four single-workflow memos (subdivision, merger,
 * separation, extension) print sizes the same way rather than each growing its own
 * formatter — or, as they did until now, omitting the sizes altogether while the
 * controller had already loaded them.
 *
 * Square metres run to five figures, so they carry a thousands separator: a plot
 * reads "1,410", not "1410". Fractional metres are kept where a survey gives them
 * and dropped where it does not, so a whole number never prints as "1,410.00".
 *
 * A blank or zero size is skipped rather than printed as 0 — a partly-filled
 * application still produces a sensible line, and an application with no sizes at
 * all produces no phrase instead of "of  m²".
 */
class ParcelSizeSummary
{
    /**
     * @param  iterable|null  $sizes  PlotApplicationSize rows (any `type`).
     * @return array{list:string,total:string,has:bool,phrase:string,total_phrase:string}
     */
    public static function of($sizes): array
    {
        $list = Collection::make($sizes ?? [])
            ->map(fn ($row) => is_array($row) ? ($row['plot_size'] ?? null) : ($row->plot_size ?? null))
            ->filter(fn ($v) => $v !== null && $v !== '' && (float) $v > 0)
            ->map(fn ($v) => (float) $v)
            ->values();

        if ($list->isEmpty()) {
            return ['list' => '', 'total' => '', 'has' => false, 'phrase' => '', 'total_phrase' => ''];
        }

        $formatted = $list->map(fn ($v) => self::number($v))->implode(' + ');
        $total     = self::number($list->sum());

        return [
            'list'  => $formatted,
            'total' => $total,
            'has'   => true,
            // " of 1,500 + 2,000 + 3,000 m²" — appended wherever a parcel is named.
            'phrase' => ' of ' . $formatted . ' m²',
            // The same, with the sum spelled out. Used where one number is clearer
            // than the run — a merger states what the merged parcel comes to.
            'total_phrase' => $list->count() > 1
                ? ' measuring ' . $formatted . ' m² (total ' . $total . ' m²)'
                : ' measuring ' . $formatted . ' m²',
        ];
    }

    /** 1410 -> "1,410"; 1410.5 -> "1,410.5"; 1410.00 -> "1,410". */
    public static function number($value): string
    {
        $f = (float) $value;

        return $f == floor($f)
            ? number_format($f, 0, '.', ',')
            : rtrim(rtrim(number_format($f, 2, '.', ','), '0'), '.');
    }
}

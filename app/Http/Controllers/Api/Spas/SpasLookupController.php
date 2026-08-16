<?php

namespace App\Http\Controllers\Api\Spas;

use App\Http\Controllers\Controller;
use App\Support\LgaNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Reference data the SPAS offline app caches on device.
 *
 * These mirror the /api/reference/* endpoints the web forms already call. The
 * app points at these copies instead so everything it needs sits behind one
 * token guard and one caching convention.
 *
 * Conflict rule for all of it: server always wins, simple overwrite. None of
 * this is ever edited on the device.
 *
 * @see docs/plans/SPAS_MOBILE_OFFLINE_CAPACITOR_SYNC_PLAN.md §5, §6.2
 */
class SpasLookupController extends Controller
{
    /**
     * Bounded snapshot of indexed files for offline file-number lookup.
     *
     * Deliberately NOT the whole of file_indexings ⋈ fileNumber — that is far
     * too large to mirror onto a handset. The app seeds this from files the
     * surveyor actually opens, and may optionally pre-seed a working set by
     * LGA. Capped hard so a missing filter cannot turn into a multi-megabyte
     * download over 2G.
     */
    public function fileIndex(Request $request): JsonResponse
    {
        $request->validate([
            'lga'          => 'nullable|string|max:255',
            'district'     => 'nullable|string|max:255',
            'file_numbers' => 'nullable|array|max:500',
            'q'            => 'nullable|string|max:255',
            'limit'        => 'nullable|integer|min:1|max:1000',
        ]);

        $limit = (int) $request->input('limit', 500);

        $query = DB::connection('sqlsrv')
            ->table('file_indexings as fi')
            ->leftJoin('fileNumber as fn', 'fn.tracking_id', '=', 'fi.tracking_id')
            ->whereNotNull('fi.file_number')
            // DCIV files are excluded from SPAS everywhere else; keeping the
            // cache consistent with the desktop list avoids offline results
            // that vanish once the surveyor is back online.
            ->where('fi.file_number', 'not like', 'DCIV%')
            ->where('fi.file_number', 'not like', 'DC/%')
            ->select(
                'fi.id as file_indexing_id', 'fi.tracking_id', 'fi.file_number',
                'fi.land_use_type', 'fi.location', 'fi.district', 'fi.lga',
                'fi.phone', 'fi.current_holder', 'fn.FileName as file_title'
            );

        // Match the LGA's misspellings too, not just the canonical spelling.
        // `file_indexings.lga` is free text (196 distinct values against 45
        // canonical), so an exact filter on "Nasarawa" silently missed the
        // 3,388 files recorded as "NASSARAWA" — invisible offline, with no
        // error to explain the gap. See LgaNormalizer.
        if ($request->filled('lga')) {
            $query->whereIn('fi.lga', LgaNormalizer::variantsFor($request->input('lga')));
        }

        if ($request->filled('district')) {
            $query->where('fi.district', $request->district);
        }

        // Explicit list: the app asking to cache the files it has looked up.
        if ($request->filled('file_numbers')) {
            $query->whereIn('fi.file_number', $request->input('file_numbers'));
        }

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($sub) use ($q) {
                $sub->where('fi.file_number', 'like', '%'.$q.'%')
                    ->orWhere('fn.FileName', 'like', '%'.$q.'%')
                    ->orWhere('fi.location', 'like', '%'.$q.'%');
            });
        }

        $rows = $query->orderBy('fi.file_number')->limit($limit)->get();

        return response()->json([
            'success'     => true,
            'count'       => $rows->count(),
            'truncated'   => $rows->count() >= $limit,
            'server_time' => now()->toIso8601String(),
            'data'        => $rows->map(fn ($r) => [
                'file_number'      => $r->file_number,
                'file_title'       => $r->file_title ?? '',
                'owner_name'       => $this->ownerFromHolder($r->current_holder),
                'land_use_type'    => $r->land_use_type ?? '',
                'location'         => $r->location ?? '',
                'district'         => $r->district ?? '',
                'lga'              => $r->lga ?? '',
                'phone'            => $r->phone ?? '',
                'tracking_id'      => $r->tracking_id,
                'file_indexing_id' => $r->file_indexing_id,
            ]),
        ]);
    }

    /** Full pull — small table, no bounding needed. */
    public function landUses(): JsonResponse
    {
        $all = DB::connection('sqlsrv')->table('klas.dbo.land_uses')
            ->orderBy('landuse')->pluck('landuse');

        return response()->json([
            'success' => true,
            'data'    => $all,
            // Customary land is only ever held for these three uses, so the
            // customary General Landuse dropdown omits Industrial. Sent as a
            // separate list so the device does not re-derive (and re-diverge
            // from) that rule.
            'customary' => $all->filter(
                fn ($u) => in_array(strtoupper(trim($u)), ['RESIDENTIAL', 'COMMERCIAL', 'AGRICULTURAL'], true)
            )->values(),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /** 45 rows — trivial, mirror whole. */
    public function lgas(): JsonResponse
    {
        return response()->json([
            'success'     => true,
            'data'        => DB::connection('sqlsrv')->table('lgas')
                                ->where('is_active', 1)->orderBy('name')->pluck('name'),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * 1,818 rows, ~40KB — still trivial, mirror whole on login.
     *
     * Note `districts` has NO lga_id column, so districts cannot be filtered by
     * LGA. The two selects are independent by necessity, not by design; a
     * "districts within this LGA" feature needs a schema change first.
     */
    public function districts(): JsonResponse
    {
        return response()->json([
            'success'     => true,
            'data'        => DB::connection('sqlsrv')->table('districts')
                                ->where('is_active', 1)->orderBy('name')->pluck('name'),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Next customary file number.
     *
     * Online-only by nature: the sequence is server-authoritative. An offline
     * device creates the record with a local placeholder and adopts the real
     * number returned by the push response.
     */
    public function nextCustomaryFileNumber(): JsonResponse
    {
        return response()->json([
            'success'     => true,
            'file_number' => \App\Models\SpaApplication::generateCustomaryFileNumber(),
        ]);
    }

    private function ownerFromHolder($holder): string
    {
        $arr = $holder ? json_decode($holder, true) : null;

        if (! is_array($arr) || empty($arr)) {
            return '';
        }

        $first = $arr[0] ?? $arr;

        $name = trim(($first['title'] ?? '').' '.($first['first_name'] ?? '').' '.($first['surname'] ?? ''));

        return $name !== '' ? $name : (string) ($first['corporate_name'] ?? '');
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RelatedFileNumberController extends Controller
{
    private const PER_PAGE_DEFAULT = 50;
    private const PER_PAGE_MAX = 200;

    public function index(): View
    {
        $totals = DB::connection('sqlsrv')->selectOne(
            "SELECT COUNT(*) AS total_rows FROM related_file_number
             WHERE related_fileno NOT LIKE 'TEMP-%' AND related_fileno NOT LIKE 'TEMP%'"
        );

        return view('related_file_number.index', [
            'pageTitle' => 'Related File Numbers',
            'totals' => $totals,
        ]);
    }

    public function apiList(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->input('per_page', self::PER_PAGE_DEFAULT), self::PER_PAGE_MAX));
        $page = max(1, (int) $request->input('page', 1));

        $search = trim((string) $request->input('search', ''));
        $commentType = trim((string) $request->input('comment_type', '')); // kangis | mlpp | none | any

        $q = DB::connection('sqlsrv')->table('related_file_number')
            ->where('related_fileno', 'not like', 'TEMP-%')
            ->where('related_fileno', 'not like', 'TEMP%');

        if ($search !== '') {
            $q->where(function ($w) use ($search) {
                $w->where('related_fileno', 'like', '%' . $search . '%')
                  ->orWhere('file_number', 'like', '%' . $search . '%')
                  ->orWhere('file_title', 'like', '%' . $search . '%')
                  ->orWhere('party_2', 'like', '%' . $search . '%')
                  ->orWhere('comment', 'like', '%' . $search . '%');
            });
        }

        switch ($commentType) {
            case 'kangis':
                $q->where('comment', 'like', 'KANGIS%');
                break;
            case 'mlpp':
                $q->where('comment', 'like', 'MINISTRY%');
                break;
            case 'none':
                $q->whereNull('comment');
                break;
            // 'any' or '' = no filter
        }

        $total = (clone $q)->count();

        $rows = $q->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get([
                'id', 'related_fileno', 'prop_id', 'source_table', 'source_id',
                'file_number', 'file_title', 'party_2', 'location', 'comment', 'created_at',
            ]);

        return response()->json([
            'data' => $rows,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }
}

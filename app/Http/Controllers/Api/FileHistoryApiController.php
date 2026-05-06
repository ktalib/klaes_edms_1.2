<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FileHistoryApiController extends Controller
{
    private const FILE_HISTORY_TABLE = 'file_history_staging';

    private const TRANSACTION_PARTY_KEYWORDS = [
        'ASSIGN' => ['Assignor', 'Assignee'],
        'TRANSFER' => ['Assignor', 'Assignee'],
        'MORTGAGE' => ['Mortgagor', 'Mortgagee'],
        'SURRENDER' => ['Surrenderor', 'Surrenderee'],
        'LEASE' => ['Lessor', 'Lessee'],
        'GRANT' => ['Grantor', 'Grantee'],
        'COFO' => ['Grantor', 'Grantee'],
        'CERTIFICATE OF OCCUPANCY' => ['Grantor', 'Grantee'],
        'C OF O' => ['Grantor', 'Grantee'],
    ];

    private const FALLBACK_PARTY_PAIRS = [
        ['Mortgagor', 'Mortgagee'],
        ['MortgagorName', 'MortgageeName'],
        ['Mortgagor', 'MortgageeName'],
        ['MortgagorName', 'Mortgagee'],
        ['Assignor', 'Assignee'],
        ['Surrenderor', 'Surrenderee'],
        ['Lessor', 'Lessee'],
        ['Grantor', 'Grantee'],
    ];

    public function index(Request $request)
    {
        try {
            $perPage = (int) $request->input('per_page', 50);
            if ($perPage < 1) {
                $perPage = 50;
            }
            $perPage = min($perPage, 200);

            $page = (int) $request->input('page', 1);
            if ($page < 1) {
                $page = 1;
            }

            $query = DB::connection('sqlsrv')->table(self::FILE_HISTORY_TABLE);

            if ($request->filled('mlsFNo')) {
                $mlsFNo = trim((string) $request->input('mlsFNo'));
                $query->whereRaw('LOWER(mlsFNo) = ?', [strtolower($mlsFNo)]);
            }

            if ($request->filled('prop_id')) {
                $propId = trim((string) $request->input('prop_id'));
                $query->whereRaw('LOWER(prop_id) = ?', [strtolower($propId)]);
            }

            if ($request->filled('transaction_type')) {
                $transactionType = trim((string) $request->input('transaction_type'));
                $query->where('transaction_type', 'LIKE', '%' . $this->escapeLikeWildcards($transactionType) . '%');
            }

            if ($request->filled('location')) {
                $location = trim((string) $request->input('location'));
                $query->where('location', 'LIKE', '%' . $this->escapeLikeWildcards($location) . '%');
            }

            if ($request->filled('reg_date_from')) {
                $query->whereDate('reg_date', '>=', $request->input('reg_date_from'));
            }

            if ($request->filled('reg_date_to')) {
                $query->whereDate('reg_date', '<=', $request->input('reg_date_to'));
            }

            if ($request->filled('serialNo')) {
                $serialNo = trim((string) $request->input('serialNo'));
                $query->where('serialNo', $serialNo);
            }

            if ($request->filled('pageNo')) {
                $pageNo = trim((string) $request->input('pageNo'));
                $query->where('pageNo', $pageNo);
            }

            if ($request->filled('volumeNo')) {
                $volumeNo = trim((string) $request->input('volumeNo'));
                $query->where('volumeNo', $volumeNo);
            }

            static $cofoDateColumnExists = null;

            if ($cofoDateColumnExists === null) {
                $cofoDateColumnExists = Schema::connection('sqlsrv')->hasColumn(self::FILE_HISTORY_TABLE, 'cofo_date');
            }

            $selectColumns = [
                'prop_id',
                'mlsFNo',
                'transaction_type',
                'land_use',
                'location',
                'transaction_date',
                $cofoDateColumnExists
                    ? 'cofo_date'
                    : DB::raw('NULL AS cofo_date'),
                'serialNo',
                'pageNo',
                'volumeNo',
                'reg_date',
                'reg_time',
                'deeds_date',
                DB::raw('Assignor AS assignor'),
                DB::raw('Assignee AS assignee'),
                DB::raw('Mortgagor AS mortgagor'),
                DB::raw('Mortgagee AS mortgagee'),
                DB::raw('Surrenderor AS surrenderor'),
                DB::raw('Surrenderee AS surrenderee'),
                DB::raw('Lessor AS lessor'),
                DB::raw('Lessee AS lessee'),
                DB::raw('Grantor AS grantor'),
                DB::raw('Grantee AS grantee'),
            ];

            $filteredQuery = clone $query;
            $total = (clone $filteredQuery)->count();

            $records = $filteredQuery
                ->select($selectColumns)
                ->orderByDesc('reg_date')
                ->orderByDesc('reg_time')
                ->forPage($page, $perPage)
                ->get();

            $data = $records->map(function ($row) {
                $parties = $this->extractParties($row);

                return [
                    'prop_id' => $row->prop_id,
                    'mlsFNo' => $row->mlsFNo,
                    'transaction_type' => $row->transaction_type,
                    'land_use' => $row->land_use,
                    'location' => $row->location,
                    'transaction_date' => $row->transaction_date,
                    'deeds_date' => $row->deeds_date,
                    'display_date' => $this->getDisplayDate($row),
                    'serialNo' => $row->serialNo,
                    'pageNo' => $row->pageNo,
                    'volumeNo' => $row->volumeNo,
                    'reg_date' => $row->reg_date,
                    'reg_time' => $row->reg_time,
                    'parties' => $parties,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => $total > 0 ? (int) ceil($total / $perPage) : 1,
                ],
                'message' => 'File history retrieved successfully',
            ]);
        } catch (Exception $e) {
            Log::error('Error retrieving file history', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve file history',
            ], 500);
        }
    }

    private function extractParties($row): array
    {
        $fields = [
            'Mortgagor' => $row->mortgagor ?? $row->mortgagorname ?? null,
            'Mortgagee' => $row->mortgagee ?? $row->mortgageename ?? null,
            'Assignor' => $row->assignor ?? null,
            'Assignee' => $row->assignee ?? null,
            'Surrenderor' => $row->surrenderor ?? null,
            'Surrenderee' => $row->surrenderee ?? null,
            'Lessor' => $row->lessor ?? null,
            'Lessee' => $row->lessee ?? null,
            'Grantor' => $row->grantor ?? null,
            'Grantee' => $row->grantee ?? null,
        ];

        $normalizedType = strtoupper(trim((string) ($row->transaction_type ?? '')));

        if ($normalizedType !== '') {
            foreach (self::TRANSACTION_PARTY_KEYWORDS as $keyword => $pair) {
                if (Str::contains($normalizedType, $keyword)) {
                    $resolved = $this->collectParties($fields, $pair);
                    if (!empty($resolved)) {
                        return $resolved;
                    }
                }
            }
        }

        foreach (self::FALLBACK_PARTY_PAIRS as $pair) {
            $resolved = $this->collectParties($fields, $pair);
            if (!empty($resolved)) {
                return $resolved;
            }
        }

        foreach ($fields as $label => $value) {
            $cleaned = $this->normalizePartyValue($value);
            if ($cleaned !== null) {
                return [$label => $cleaned];
            }
        }

        return [];
    }

    private function collectParties(array $fields, array $pair): array
    {
        $collected = [];

        foreach ($pair as $label) {
            $value = $fields[$label] ?? null;
            $normalized = $this->normalizePartyValue($value);
            if ($normalized !== null) {
                $collected[$label] = $normalized;
            }
        }

        return $collected;
    }

    private function normalizePartyValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function escapeLikeWildcards(string $value): string
    {
        return str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $value);
    }

    /**
     * Determine the display date for a transaction
     * Uses cofo_date if transaction contains COFO and cofo_date exists, otherwise transaction_date
     */
    private function getDisplayDate($row): ?string
    {
        $transactionType = strtolower(trim((string) ($row->transaction_type ?? '')));
        
        // Check if transaction type contains COFO-related keywords
        $cofoKeywords = ['cofo', 'certificate of occupancy', 'c of o', 'cof o'];
        
        $isCofoTransaction = false;
        foreach ($cofoKeywords as $keyword) {
            if (strpos($transactionType, $keyword) !== false) {
                $isCofoTransaction = true;
                break;
            }
        }
        
        // If it's a COFO transaction and cofo_date exists, use cofo_date
        if ($isCofoTransaction && !empty($row->cofo_date)) {
            return $row->cofo_date;
        }
        
        // Otherwise use transaction_date
        return $row->transaction_date;
    }
}

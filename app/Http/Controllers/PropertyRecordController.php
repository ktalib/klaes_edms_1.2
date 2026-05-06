<?php

namespace App\Http\Controllers;

use App\Exceptions\PropIdAllocationException;
use App\Services\PropertyIdAllocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // Add Auth facade for user tracking
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema; // Schema facade to check columns safely
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log; // Add Log facade
use Illuminate\Validation\ValidationException;
use App\Models\InstrumentType;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PropertyRecordController extends Controller
{
    private const PROPERTY_TABLE = 'file_history_staging';
    private const COFO_TABLE = 'CofO_staging';
    private const COFO_TRANSACTION_TYPES = [
        'Certificate of Occupancy',
        'ST Certificate of Occupancy',
        'SLTR Certificate of Occupancy',
    ];
    private const PRA_TABLE = 'pra';
    private PropertyIdAllocationService $propertyIdAllocationService;

    /**
     * Check if a transaction type is an Occupancy Permit variant.
     */
    private static function isOccupancyPermit(string $transactionType): bool
    {
        return stripos($transactionType, 'Occupancy Permit (OP)') !== false;
    }

    public function __construct(PropertyIdAllocationService $propertyIdAllocationService)
    {
        $this->propertyIdAllocationService = $propertyIdAllocationService;
    }

    /**
     * Pre-check for duplicate property records before submission.
     *
     * For CofO variants, matches are read from CofO_staging and the form is locked only
     * when the existing row matches the file number plus enough identifying details.
     *
     * For non-CofO PRA records, the form is locked when the same transaction type already
     * exists on the same file number in the PRA table.
     */
    public function checkCofoDuplicate(Request $request)
    {
        $fileNumber = trim((string) ($request->input('file_number')
            ?? $request->input('mlsFNo')
            ?? $request->input('kangisFileNo')
            ?? $request->input('NewKANGISFileno')
            ?? $request->input('np_fileno')
            ?? $request->input('temp_fileno')
            ?? $request->input('fileno')));

        if ($fileNumber === '') {
            return response()->json([
                'success'   => true,
                'matches'   => [],
                'duplicate' => null,
                'lock_form' => false,
            ]);
        }

        $transactionType = trim((string) $request->input('transaction_type'));
        $isCofOType = in_array($transactionType, self::COFO_TRANSACTION_TYPES, true);

        $cofoTypeMap = [
            'Certificate of Occupancy'      => 'Legacy CofO',
            'ST Certificate of Occupancy'   => 'ST CofO',
            'SLTR Certificate of Occupancy' => 'SLTR CofO',
        ];
        $cofoType = $isCofOType ? ($cofoTypeMap[$transactionType] ?? null) : null;

        if ($isCofOType) {
            // Base query: any CofO row that shares this file number
            $baseQuery = DB::connection('sqlsrv')->table(self::COFO_TABLE)
                ->where(function ($q) {
                    $q->where('is_deleted', 0)->orWhereNull('is_deleted');
                })
                ->where(function ($q) use ($fileNumber) {
                    $q->where('mlsFNo', $fileNumber)
                      ->orWhere('kangisFileNo', $fileNumber)
                      ->orWhere('NewKANGISFileno', $fileNumber)
                      ->orWhere('np_fileno', $fileNumber)
                      ->orWhere('temp_fileno', $fileNumber)
                      ->orWhere('fileno', $fileNumber);
                });

            $matches = (clone $baseQuery)->orderByDesc('id')->limit(10)->get();

            // Decide whether to lock the form
            $lockForm = false;
            $lockingRecord = null;

            $party2 = trim((string) $request->input('party_2'));
            $transactionDate = $request->input('transaction_date');
            $regNo = trim((string) $request->input('reg_no'));
            $vol = trim((string) $request->input('vol'));
            $page = trim((string) $request->input('page'));
            $serial = trim((string) $request->input('serial'));
            $lga = trim((string) $request->input('lgsaOrCity'));
            $location = trim((string) $request->input('location'));

            foreach ($matches as $row) {
                if ($cofoType && ($row->cofo_type ?? null) !== $cofoType) {
                    continue;
                }

                $hits = 0;

                if ($party2 !== '') {
                    $candidates = array_filter([
                        $row->party_2 ?? null,
                        $row->Grantee ?? null,
                        $row->Assignee ?? null,
                        $row->Lessee ?? null,
                        $row->Mortgagee ?? null,
                    ]);
                    foreach ($candidates as $c) {
                        if (stripos((string) $c, $party2) !== false || stripos($party2, (string) $c) !== false) {
                            $hits++;
                            break;
                        }
                    }
                }

                if ($transactionDate && !empty($row->transaction_date)) {
                    if (substr((string) $row->transaction_date, 0, 10) === substr((string) $transactionDate, 0, 10)) {
                        $hits++;
                    }
                }

                if ($regNo !== '' && (string) ($row->regNo ?? '') === $regNo) {
                    $hits++;
                } elseif ($vol !== '' && $page !== '' && $serial !== ''
                    && (string) ($row->volumeNo ?? '') === $vol
                    && (string) ($row->pageNo ?? '') === $page
                    && (string) ($row->serialNo ?? '') === $serial) {
                    $hits++;
                }

                if ($lga !== '' && !empty($row->lgsaOrCity)
                    && strcasecmp((string) $row->lgsaOrCity, $lga) === 0) {
                    $hits++;
                }

                if ($location !== '' && !empty($row->location)
                    && stripos((string) $row->location, $location) !== false) {
                    $hits++;
                }

                if ($hits >= 2) {
                    $lockForm = true;
                    $lockingRecord = $row;
                    break;
                }
            }
            return response()->json([
                'success'        => true,
                'matches'        => $matches,
                'duplicate'      => $lockingRecord,
                'duplicate_type' => 'cofo_staging',
                'lock_form'      => $lockForm,
                'message'        => $lockForm
                    ? 'A Certificate of Occupancy with matching details already exists for file number ' . $fileNumber . '.'
                    : null,
            ]);
        }

        $praMatches = DB::connection('sqlsrv')->table(self::PRA_TABLE)
            ->where(function ($q) {
                $q->where('is_deleted', 0)->orWhereNull('is_deleted');
            })
            ->where(function ($q) use ($fileNumber) {
                // NOTE: the `pra` table does NOT carry an `np_fileno` column, so it
                // must be excluded from this OR-clause even though CofO_staging has it.
                $q->whereRaw('UPPER(LTRIM(RTRIM(mlsFNo))) = ?', [strtoupper($fileNumber)])
                  ->orWhereRaw('UPPER(LTRIM(RTRIM(kangisFileNo))) = ?', [strtoupper($fileNumber)])
                  ->orWhereRaw('UPPER(LTRIM(RTRIM(NewKANGISFileno))) = ?', [strtoupper($fileNumber)])
                  ->orWhereRaw('UPPER(LTRIM(RTRIM(temp_fileno))) = ?', [strtoupper($fileNumber)])
                  ->orWhereRaw('UPPER(LTRIM(RTRIM(fileno))) = ?', [strtoupper($fileNumber)]);
            })
            ->when($transactionType !== '', function ($query) use ($transactionType) {
                // Older rows stored the instrument in `instrument_type`; newer rows
                // in `transaction_type`. Match either so legacy data is caught too.
                $upTxn = strtoupper($transactionType);
                $query->where(function ($q) use ($upTxn) {
                    $q->whereRaw('UPPER(LTRIM(RTRIM(transaction_type))) = ?', [$upTxn])
                      ->orWhereRaw('UPPER(LTRIM(RTRIM(instrument_type))) = ?', [$upTxn]);
                });
            })
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $praDuplicate = $praMatches->first();

        // Only LOCK the form when the user has started entering details that
        // match an existing PRA row (hit-count rule, mirroring the CofO logic).
        // Otherwise we still surface the "Existing <instrument> for this File"
        // card so the user can see what already exists, but keep the form open.
        $lockForm = false;
        $lockingPraRecord = null;

        if ($praDuplicate !== null) {
            $party2 = trim((string) $request->input('party_2'));
            $transactionDate = $request->input('transaction_date');
            $regNo = trim((string) $request->input('reg_no'));
            $vol = trim((string) $request->input('vol'));
            $page = trim((string) $request->input('page'));
            $serial = trim((string) $request->input('serial'));
            $lga = trim((string) $request->input('lgsaOrCity'));
            $location = trim((string) $request->input('location'));

            foreach ($praMatches as $row) {
                $hits = 0;

                if ($party2 !== '') {
                    $candidates = array_filter([
                        $row->party_2 ?? null,
                        $row->Grantee ?? null,
                        $row->Assignee ?? null,
                        $row->Lessee ?? null,
                        $row->Mortgagee ?? null,
                        $row->Donee ?? null,
                        $row->Releasee ?? null,
                        $row->Surrenderee ?? null,
                    ]);
                    foreach ($candidates as $c) {
                        if (stripos((string) $c, $party2) !== false || stripos($party2, (string) $c) !== false) {
                            $hits++;
                            break;
                        }
                    }
                }

                if ($transactionDate && !empty($row->transaction_date)
                    && substr((string) $row->transaction_date, 0, 10) === substr((string) $transactionDate, 0, 10)) {
                    $hits++;
                }

                if ($regNo !== '' && (string) ($row->regNo ?? '') === $regNo) {
                    $hits++;
                } elseif ($vol !== '' && $page !== '' && $serial !== ''
                    && (string) ($row->volumeNo ?? '') === $vol
                    && (string) ($row->pageNo ?? '') === $page
                    && (string) ($row->serialNo ?? '') === $serial) {
                    $hits++;
                }

                if ($lga !== '' && !empty($row->lgsaOrCity)
                    && strcasecmp((string) $row->lgsaOrCity, $lga) === 0) {
                    $hits++;
                }

                if ($location !== '' && !empty($row->location)
                    && stripos((string) $row->location, $location) !== false) {
                    $hits++;
                }

                if ($hits >= 2) {
                    $lockForm = true;
                    $lockingPraRecord = $row;
                    break;
                }
            }
        }

        return response()->json([
            'success'        => true,
            'matches'        => $praMatches,
            'duplicate'      => $lockingPraRecord ?? $praDuplicate,
            'duplicate_type' => 'pra',
            'lock_form'      => $lockForm,
            'message'        => $lockForm
                ? 'A ' . $transactionType . ' with matching details already exists for file number ' . $fileNumber . '.'
                : null,
        ]);
    }

    /**
     * Store a newly created property record in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory
     */

    public function index()
    {
        $PageTitle = 'Property Record Assistant';
        $PageDescription = '';

        // Get only the most recent record for featured display (not entire table)
        $featuredRecord = DB::connection('sqlsrv')
            ->table('pra')
            ->whereRaw("(mlsFNo IS NOT NULL AND mlsFNo != '') 
                        OR (kangisFileNo IS NOT NULL AND kangisFileNo != '') 
                        OR (NewKANGISFileno IS NOT NULL AND NewKANGISFileno != '')")
            ->where(function ($query) {
                $query->where('title_type', '!=', 'Certificate of Occupancy')
                    ->orWhereNull('title_type');
            })
            ->where(function ($query) {
                $query->where('instrument_type', '!=', 'Certificate of Occupancy')
                    ->orWhereNull('instrument_type');
            })
            // created_at contains mixed legacy formats; id is the reliable chronological proxy
            ->orderByDesc('id')
            ->first();

        // Calculate Statistics
        $today = \Carbon\Carbon::today();
        $startOfMonth = \Carbon\Carbon::now()->startOfMonth();

        $dailyCount = DB::connection('sqlsrv')->table('pra')
            ->whereDate('created_at', $today)
            ->count();

        $monthlyCount = DB::connection('sqlsrv')->table('pra')
            ->where('created_at', '>=', $startOfMonth)
            ->count();

        $totalCount = DB::connection('sqlsrv')->table('pra')->count();

        $stats = [
            'daily' => $dailyCount,
            'monthly' => $monthlyCount,
            'total' => $totalCount
        ];

        // Fetch systemic active instrument types for filtering, matching the Add modal options
        $instrumentTypes = InstrumentType::active()
            ->orderBy('InstrumentName')
            ->pluck('InstrumentName');

        $pageLength = 20; // Pagination default matching DataTables config
        return view('propertycard.index', compact('pageLength', 'PageTitle', 'PageDescription', 'featuredRecord', 'stats', 'instrumentTypes'));
    }

    /**
     * Server-side DataTables endpoint for property records
     */
    public function getData(Request $request)
    {
        $draw = $request->get('draw', 1);
        $start = $request->get('start', 0);
        $length = $request->get('length', 20);
        $search = $request->get('search')['value'] ?? '';
        $orderColumn = $request->get('order')[0]['column'] ?? 0;
        $orderDir = $request->get('order')[0]['dir'] ?? 'desc';

        // Column mapping for ordering - updated to match frontend datatable
        $columns = [null, 'mlsFNo', 'regNo', 'transaction_type', 'party_1', 'party_2', 'party_3', 'property_description', 'created_at', 'id'];
        $orderByColumn = $columns[$orderColumn] ?? 'created_at';

        $recordMode = $request->input('record_mode', 'manual');
        $isIndexMode = $recordMode === 'index';
        $targetTable = $isIndexMode ? 'pic' : 'pra';

        $query = DB::connection('sqlsrv')
            ->table($targetTable)
            ->select([
                '*',
                'party_1',
                'party_2',
                'party_3',
                // Location/Property Description: always use property_description
                DB::raw("property_description as location_display"),
                // Registration particulars: fallback to serialNo/pageNo/volumeNo with 0 defaults
                DB::raw("ISNULL(NULLIF(LTRIM(RTRIM(regNo)),''), CASE WHEN COALESCE(serialNo, pageNo, volumeNo) IS NOT NULL THEN CONCAT(ISNULL(serialNo,'0'), '/', ISNULL(pageNo,'0'), '/', ISNULL(volumeNo,'0')) ELSE NULL END) as registration_particulars"),
            ]);

        // File number filter: must have at least one non-null/non-empty file number
        // Expanded to include legacy (fileno) and temporary (temp_fileno) numbers
        $query->where(function ($q) {
            $q->whereRaw("(mlsFNo IS NOT NULL AND mlsFNo != '') 
                          OR (kangisFileNo IS NOT NULL AND kangisFileNo != '') 
                          OR (NewKANGISFileno IS NOT NULL AND NewKANGISFileno != '')
                          OR (fileno IS NOT NULL AND fileno != '')
                          OR (temp_fileno IS NOT NULL AND temp_fileno != '')");
        });

        // Exclusion filters
        $query->where(function ($q) {
            $q->where('title_type', '!=', 'Certificate of Occupancy')
                ->orWhereNull('title_type');
        });

        $query->where(function ($q) {
            $q->where('instrument_type', '!=', 'Certificate of Occupancy')
                ->orWhereNull('instrument_type');
        });

        // Smart Filter: Instrument Type
        if ($request->filled('instrument_type')) {
            $query->where('instrument_type', $request->input('instrument_type'));
        }

        // Keep a copy before search for recordsTotal
        $queryBeforeSearch = clone $query;

        // Search filter - expanded to include registration numbers and more fields
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('mlsFNo', 'LIKE', "%{$search}%")
                    ->orWhere('kangisFileNo', 'LIKE', "%{$search}%")
                    ->orWhere('NewKANGISFileno', 'LIKE', "%{$search}%")
                    ->orWhere('fileno', 'LIKE', "%{$search}%")
                    ->orWhere('temp_fileno', 'LIKE', "%{$search}%")
                    ->orWhere('regNo', 'LIKE', "%{$search}%")
                    ->orWhere('prop_id', 'LIKE', "%{$search}%")
                    ->orWhere('serialNo', 'LIKE', "%{$search}%")
                    ->orWhere('pageNo', 'LIKE', "%{$search}%")
                    ->orWhere('volumeNo', 'LIKE', "%{$search}%")
                    ->orWhere('property_description', 'LIKE', "%{$search}%")
                    ->orWhere('plot_no', 'LIKE', "%{$search}%")
                    ->orWhere('location', 'LIKE', "%{$search}%")
                    ->orWhere('lgsaOrCity', 'LIKE', "%{$search}%")
                    ->orWhere('party_1', 'LIKE', "%{$search}%")
                    ->orWhere('party_2', 'LIKE', "%{$search}%")
                    ->orWhere('party_3', 'LIKE', "%{$search}%")
                    ->orWhere('party_4', 'LIKE', "%{$search}%")
                    ->orWhere('related_file_number', 'LIKE', "%{$search}%")
                    ->orWhere('Assignor', 'LIKE', "%{$search}%")
                    ->orWhere('Assignee', 'LIKE', "%{$search}%")
                    ->orWhere('Mortgagor', 'LIKE', "%{$search}%")
                    ->orWhere('Mortgagee', 'LIKE', "%{$search}%")
                    ->orWhere('Grantor', 'LIKE', "%{$search}%")
                    ->orWhere('Grantee', 'LIKE', "%{$search}%");
            });
        }

        // Collapse rows by prop_id while preserving rows without prop_id as unique rows.
        $groupKeyExpr = "CASE
            WHEN prop_id IS NULL OR LTRIM(RTRIM(CAST(prop_id AS NVARCHAR(100)))) = ''
                THEN CONCAT('__id_', CAST(id AS NVARCHAR(100)))
            ELSE CAST(prop_id AS NVARCHAR(100))
        END";

        $buildCollapsed = function ($base) use ($groupKeyExpr) {
            $ranked = (clone $base)->select([
                '*',
                DB::raw("property_description as location_display"),
                DB::raw("ISNULL(NULLIF(LTRIM(RTRIM(regNo)),''), CASE WHEN COALESCE(serialNo, pageNo, volumeNo) IS NOT NULL THEN CONCAT(ISNULL(serialNo,'0'), '/', ISNULL(pageNo,'0'), '/', ISNULL(volumeNo,'0')) ELSE NULL END) as registration_particulars"),
                DB::raw("CONVERT(VARCHAR(25), created_at, 126) as created_at_iso"),
                // Use id ordering to avoid mixed-format created_at issues
                DB::raw("ROW_NUMBER() OVER (PARTITION BY {$groupKeyExpr} ORDER BY id DESC) as rn"),
                DB::raw("COUNT(*) OVER (PARTITION BY {$groupKeyExpr}) as timeline_count"),
            ]);

            return DB::connection('sqlsrv')
                ->table(DB::raw("({$ranked->toSql()}) as ranked_rows"))
                ->mergeBindings($ranked)
                ->where('rn', 1);
        };

        $collapsedTotalQuery = $buildCollapsed($queryBeforeSearch);
        $recordsTotal = $collapsedTotalQuery->count();

        $collapsedFilteredQuery = $buildCollapsed($query);
        $recordsFiltered = $collapsedFilteredQuery->count();

        // Get paginated grouped data
        if ($orderByColumn === 'created_at') {
            $orderByColumn = 'id';
        }

        $data = $collapsedFilteredQuery
            ->orderBy($orderByColumn, $orderDir)
            ->skip($start)
            ->take($length)
            ->get();

        $timelineCounts = $this->resolveTimelineCountsForRecords($data);
        $data = $data->map(function ($row) use ($timelineCounts) {
            $propIdKey = trim((string) ($row->prop_id ?? ''));
            $fallbackKey = '__id_' . (string) ($row->id ?? '0');
            $resolvedCount = (int) ($timelineCounts[$propIdKey !== '' ? $propIdKey : $fallbackKey] ?? 1);
            $row->timeline_count = max(1, $resolvedCount);
            return $row;
        })->values();

        return response()->json([
            'draw' => intval($draw),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data
        ]);
    }

    /**
     * Resolve timeline counts for list rows using the same matching semantics as timeline modal:
     * prop_id OR file number across file_history_staging, CofO_staging, pra and deed_registrations.
     */
    protected function resolveTimelineCountsForRecords($records)
    {
        $counts = [];

        foreach ($records as $record) {
            $propId = trim((string) ($record->prop_id ?? ''));
            $key = $propId !== '' ? $propId : ('__id_' . (string) ($record->id ?? '0'));

            $fileNumbers = collect([
                $record->mlsFNo ?? null,
                $record->fileno ?? null,
                $record->kangisFileNo ?? null,
                $record->NewKANGISFileno ?? null,
            ])->filter(fn($v) => $v !== null && trim((string) $v) !== '')
                ->map(fn($v) => strtoupper(trim((string) $v)))
                ->unique()
                ->values()
                ->all();

            if ($propId === '' && empty($fileNumbers)) {
                $counts[$key] = 1;
                continue;
            }

            $total = 0;
            $total += $this->countTimelineMatches('file_history_staging', $propId, $fileNumbers, ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno']);
            $total += $this->countTimelineMatches('CofO_staging', $propId, $fileNumbers, ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno']);
            $total += $this->countTimelineMatches('pra', $propId, $fileNumbers, ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno'], true);
            $total += $this->countTimelineMatches('deed_registrations', $propId, $fileNumbers, ['fileno', 'parent_fileno']);

            $counts[$key] = max(1, $total);
        }

        return collect($counts);
    }

    /**
     * Count rows in a timeline table matching either prop_id or any of the provided file number columns.
     */
    protected function countTimelineMatches(string $table, string $propId, array $fileNumbers, array $fileColumns, bool $excludeDeleted = false): int
    {
        try {
            $query = DB::connection('sqlsrv')->table($table);

            $validFileColumns = array_values(array_filter($fileColumns, function ($column) use ($table) {
                return Schema::connection('sqlsrv')->hasColumn($table, $column);
            }));

            $query->where(function ($q) use ($propId, $fileNumbers, $validFileColumns) {
                if ($propId !== '') {
                    $q->orWhereRaw("CAST(prop_id AS NVARCHAR(50)) = ?", [$propId]);
                }

                foreach ($fileNumbers as $fileNo) {
                    foreach ($validFileColumns as $column) {
                        $q->orWhereRaw("UPPER({$column}) = ?", [$fileNo]);
                    }
                }
            });

            if ($excludeDeleted && Schema::connection('sqlsrv')->hasColumn($table, 'is_deleted')) {
                $query->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                });
            }

            return (int) $query->count();
        } catch (\Throwable $e) {
            Log::warning("countTimelineMatches failed for {$table}", ['error' => $e->getMessage()]);
            return 0;
        }
    }


    public function store(Request $request)
    {
        // Log the incoming request for debugging
        \Log::info('Property Record Store Request:', [
            'all_data' => $request->all(),
            'method' => $request->method(),
            'url' => $request->url(),
            'user_agent' => $request->userAgent()
        ]);

        $recordMode = $request->input('record_mode', 'property');
        $isIndexMode = $recordMode === 'index';
        $targetTable = $isIndexMode ? 'pic' : 'pra';
        $logChannel = $isIndexMode ? 'pic_submissions' : 'pra_submissions';

        // --- Robust Duplicate Check (5 Parameters) ---
        $incomingTransactionType = $request->input('transactionType') ?? $request->input('transaction_type');
        $isCofOSubmission = !$isIndexMode && in_array($incomingTransactionType, self::COFO_TRANSACTION_TYPES, true);

        $cofoTypeMap = [
            'Certificate of Occupancy'      => 'Legacy CofO',
            'ST Certificate of Occupancy'   => 'ST CofO',
            'SLTR Certificate of Occupancy' => 'SLTR CofO',
        ];

        $dupParams = [
            'fileno' => $request->input('mlsFNo') ?? $request->input('kangisFileNo') ?? $request->input('NewKANGISFileno') ?? $request->input('np_fileno') ?? $request->input('temp_fileno') ?? $request->input('fileno'),
            'prop_id' => $request->input('prop_id'),
            'reg_no' => $request->input('regNo'),
            'vol' => $request->input('volumeNo'),
            'page' => $request->input('pageNo'),
            'serial' => $request->input('serialNo'),
            'party_1' => $request->input('party_1'),
            'transaction_type' => $incomingTransactionType,
            'transaction_date' => $request->input('transactionDate'),
            'cofo_no' => $request->input('cofo_no'),
            'cofo_type' => $isCofOSubmission ? ($cofoTypeMap[$incomingTransactionType] ?? null) : null,
        ];

        $dupType = $isCofOSubmission ? 'cofo_staging' : 'pra';

        if ($duplicateFound = check_duplicate($dupType, $dupParams)) {
            $errorMsg = $isCofOSubmission
                ? 'A similar Certificate of Occupancy already exists. Please review the existing record before creating a new one.'
                : 'A similar Property Record already exists for this registration or file number.';

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success'        => false,
                    'message'        => $errorMsg,
                    'duplicate'      => $duplicateFound,
                    'duplicate_type' => $dupType,
                    'lock_form'      => $isCofOSubmission,
                ], 422);
            }
            return redirect()->back()->with('error', $errorMsg)->withInput();
        }
        // ----------------------------------------------

        // Updated validation rules for new field names
        $validator = Validator::make($request->all(), [
            'mlsFNo' => 'nullable|string|max:255',
            'kangisFileNo' => 'nullable|string|max:255',
            'NewKANGISFileno' => 'nullable|string|max:255',
            'fileno' => 'nullable|string|max:255',
            'title_type' => 'nullable|string|max:255', // Add title_type validation
            'transactionType' => 'nullable|string|max:255',
            'transactionDate' => 'nullable|date',
            'serialNo' => 'nullable|string|max:50',
            'pageNo' => 'nullable|string|max:50',
            'volumeNo' => 'nullable|string|max:50',
            'regDate' => 'nullable|date',
            'regTime' => 'nullable|string|max:10',
            'instrumentType' => 'nullable|string|max:255',
            'period' => 'nullable|numeric',
            'periodUnit' => 'nullable|string|max:50',
            // Party fields based on transaction type
            'Assignor' => 'nullable|string|max:500',
            'Assignee' => 'nullable|string|max:500',
            'Mortgagor' => 'nullable|string|max:500',
            'Mortgagee' => 'nullable|string|max:500',
            'Vendor' => 'nullable|string|max:500',
            'Purchaser' => 'nullable|string|max:500',
            'Surrenderor' => 'nullable|string|max:500',
            'Surrenderee' => 'nullable|string|max:500',
            'Lessor' => 'nullable|string|max:500',
            'Lessee' => 'nullable|string|max:500',
            'Grantor' => 'nullable|string|max:500',
            'Grantee' => 'nullable|string|max:500',
            // Property details
            'property_description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'plot_no' => 'nullable|string|max:100',
            'house_no' => 'nullable|string|max:100',
            // New fields
            'lgsaOrCity' => 'nullable|string|max:255',
            'layout' => 'nullable|string|max:255',
            'schedule' => 'nullable|string|max:255',
            // Additional fields added per request
            'tp_no' => 'nullable|string|max:255',
            'lpkn_no' => 'nullable|string|max:255',
            'approved_plan_no' => 'nullable|string|max:255',
            'plot_size' => 'nullable|string|max:255',
            'date_recommended' => 'nullable|date',
            'date_approved' => 'nullable|date',
            'lease_begins' => 'nullable|date',
            'lease_expires' => 'nullable|date',
            'metric_sheet' => 'nullable|string|max:255',
            'related_file_number' => 'nullable|string|max:255',
            'related_fileno' => 'nullable|string|max:255',
            'is_mapped' => 'nullable|boolean',
            'temp_fileno' => 'nullable|string|max:255',
            'op_type' => 'nullable|string|max:255',
            'op_serial_number' => 'nullable|string|max:255',
            'has_official_file_number' => 'nullable|in:0,1',
            'record_mode' => 'nullable|in:property,index',
            'instrument_entries' => 'nullable|array',
            'instrument_entries.*.type' => 'nullable|string|max:255',
            'instrument_entries.*.date' => 'nullable|date',
            'instrument_entries.*.party_from' => 'nullable|string|max:500',
            'instrument_entries.*.party_to' => 'nullable|string|max:500',
            'extra_regs' => 'nullable|array',
            'extra_regs.*.serial_no' => 'nullable|string|max:50',
            'extra_regs.*.page_no' => 'nullable|string|max:50',
            'extra_regs.*.volume_no' => 'nullable|string|max:50',
            'extra_regs.*.transaction_date' => 'nullable|date',
            'extra_regs.*.deeds_date' => 'nullable|date',
            'extra_regs.*.deeds_time' => 'nullable|string|max:10',
            'extra_regs.*.land_use' => 'nullable|string|max:255',
            'extra_regs.*.period' => 'nullable|numeric',
            'extra_regs.*.period_unit' => 'nullable|string|max:50',
            'party_4' => 'nullable|string|max:255',
            'Mortgagor_2' => 'nullable|string|max:255',
            'Mortgagor_3' => 'nullable|string|max:255',
            'Surrenderor_2' => 'nullable|string|max:255',
            // Generic party fields for fallback
            'firstParty' => 'nullable|string|max:500',
            'secondParty' => 'nullable|string|max:500',
            'thirdParty' => 'nullable|string|max:500',
            'fourthParty' => 'nullable|string|max:500',
        ]);

        // Add conditional validation for parties based on transaction type
        $validator->after(function ($validator) use ($request) {
            $tx = strtolower((string) $request->transactionType);
            if (!$tx) return;

            $hasFirst = $request->filled('firstParty') || $request->filled('Assignor') || $request->filled('Mortgagor') || 
                        $request->filled('Vendor') || $request->filled('Donor') || $request->filled('Surrenderor') || 
                        $request->filled('Lessor') || $request->filled('Grantor');
                        
            $hasSecond = $request->filled('secondParty') || $request->filled('Assignee') || $request->filled('Mortgagee') || 
                         $request->filled('Purchaser') || $request->filled('Donee') || $request->filled('Surrenderee') || 
                         $request->filled('Lessee') || $request->filled('Grantee');

            if (strpos($tx, 'assignment') !== false) {
                if (!$hasFirst) $validator->errors()->add('Assignor', 'The Assignor name is required for Assignment transactions.');
                if (!$hasSecond) $validator->errors()->add('Assignee', 'The Assignee name is required for Assignment transactions.');
            } elseif (strpos($tx, 'mortgage') !== false) {
                if (!$hasFirst) $validator->errors()->add('Mortgagor', 'The Mortgagor name is required for Mortgage transactions.');
                if (!$hasSecond) $validator->errors()->add('Mortgagee', 'The Mortgagee name is required for Mortgage transactions.');
                
                if (strpos($tx, 'tripartite') !== false) {
                    if (!$request->filled('thirdParty') && !$request->filled('Mortgagor_2')) {
                        $validator->errors()->add('thirdParty', 'A second Mortgagor/Co-mortgagor is required for Tripartite Mortgage.');
                    }
                }
            } elseif (strpos($tx, 'purchase') !== false) {
                if (!$hasFirst) $validator->errors()->add('Vendor', 'The Vendor name is required for Purchase transactions.');
                if (!$hasSecond) $validator->errors()->add('Purchaser', 'The Purchaser name is required for Purchase transactions.');
            } elseif (strpos($tx, 'gift') !== false) {
                if (!$hasFirst) $validator->errors()->add('Donor', 'The Donor name is required for Gift transactions.');
                if (!$hasSecond) $validator->errors()->add('Donee', 'The Donee name is required for Gift transactions.');
            } elseif (strpos($tx, 'surrender') !== false) {
                if (!$hasFirst) $validator->errors()->add('Surrenderor', 'The Surrenderor name is required for Surrender transactions.');
                if (!$hasSecond) $validator->errors()->add('Surrenderee', 'The Surrenderee name is required for Surrender transactions.');
            } elseif (strpos($tx, 'lease') !== false) {
                if (!$hasFirst) $validator->errors()->add('Lessor', 'The Lessor name is required for Lease transactions.');
                if (!$hasSecond) $validator->errors()->add('Lessee', 'The Lessee name is required for Lease transactions.');
            } elseif ($tx !== '') {
                // Default fallback
                if (!$hasFirst) $validator->errors()->add('firstParty', 'The first party name is required.');
                if (!$hasSecond) $validator->errors()->add('secondParty', 'The second party name is required.');
            }
        });

        if ($validator->fails()) {
            \Log::error('Property Record Validation Failed:', [
                'errors' => $validator->errors()->toArray(),
                'input' => $request->all()
            ]);

            $this->logSubmissionResult($logChannel, false, 'Submission validation failed', [
                'table' => $targetTable,
                'errors' => $validator->errors()->toArray(),
            ]);

            // Check if request expects JSON (AJAX) or normal redirect
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Start database transaction
        DB::connection('sqlsrv')->beginTransaction();

        try {
            // First, check if the destination table exists
            $tableExists = Schema::connection('sqlsrv')->hasTable($targetTable);
            if (!$tableExists) {
                throw new \Exception("Table {$targetTable} does not exist in the database");
            }

            // Log table columns for debugging
            $columns = Schema::connection('sqlsrv')->getColumnListing($targetTable);
            \Log::info('Target Property Table Columns:', [
                'table' => $targetTable,
                'columns' => $columns,
            ]);

            // Normalize file numbers coming from either manual fields or smart selector
            $mls = trim((string) $request->input('mlsFNo', ''));
            $kangis = trim((string) $request->input('kangisFileNo', ''));
            $newKangis = trim((string) $request->input('NewKANGISFileno', ''));
            $singleFileno = trim((string) $request->input('fileno', ''));

            // If only a single fileno is provided via smart selector, map it to MLS by default
            if ($mls === '' && $kangis === '' && $newKangis === '' && $singleFileno !== '') {
                $mls = $singleFileno;
            }

            $hasOfficialFileNumber = $request->input('has_official_file_number', '1') !== '0';
            $tempFileno = trim((string) $request->input('temp_fileno', ''));

            if (!$hasOfficialFileNumber) {
                if ($tempFileno === '') {
                    $tempFileno = $this->generateTempFileNumber();
                }
            } else {
                if ($mls === '' && $kangis === '' && $newKangis === '' && $singleFileno === '') {
                    throw new \Exception('Please provide at least one official file number or mark that no file number exists.');
                }
                $tempFileno = $tempFileno !== '' ? $tempFileno : null;
            }

            // If this record uses a temporary file number, also populate the MLS field
            // so downstream systems that expect `mlsFNo` are populated consistently.
            if (!$hasOfficialFileNumber && $tempFileno) {
                $mls = $tempFileno;
            }

            // Safety: if the file number follows the TEMP pattern, ensure temp_fileno is populated
            if (!$tempFileno && $mls && strpos($mls, 'TEMP-') === 0) {
                $tempFileno = $mls;
            }

            $relatedFileNumber = trim((string) ($request->input('related_file_number', $request->input('related_fileno', ''))));
            $relatedFileNumber = $relatedFileNumber !== '' ? $relatedFileNumber : null;

            $primaryFileIdentifier = $mls ?: $kangis ?: $newKangis ?: $singleFileno ?: $tempFileno;

            $landUse = $this->normalizeValue(
                $request->input(
                    'land_use',
                    $request->input('landUse', $request->input('land_use_type'))
                )
            );

            // Only allocate Property ID if we have a permanent (non-temporary) file number
            // Temporary file numbers are allowed in the database but should NOT be linked to Property ID master table
            $propId = null;

            if ($hasOfficialFileNumber) {
                try {
                    $propId = $this->propertyIdAllocationService->allocateOrRetrievePropId(
                        $primaryFileIdentifier,
                        $mls !== '' ? $mls : null,
                        $kangis !== '' ? $kangis : null,
                        $newKangis !== '' ? $newKangis : null,
                        [
                            'temp_fileno' => $tempFileno !== '' ? $tempFileno : null,
                        ]
                    );
                } catch (PropIdAllocationException $allocationException) {
                    throw ValidationException::withMessages([
                        'mlsFNo' => [$allocationException->getMessage()],
                        'fileno' => [$allocationException->getMessage()],
                    ]);
                } catch (\Throwable $allocationException) {
                    throw new \Exception(
                        'Unable to allocate property identifier: ' . $allocationException->getMessage(),
                        0,
                        $allocationException
                    );
                }

                \Log::info('Property ID allocated for permanent file number', [
                    'prop_id' => $propId,
                    'primary_file_number' => $primaryFileIdentifier,
                ]);
            } else {
                \Log::info('Skipping Property ID allocation for temporary file number', [
                    'temp_fileno' => $tempFileno,
                    'has_official_file_number' => $hasOfficialFileNumber,
                ]);
            }

            // Create registration number from components (use "0" for null values and strip leading zeros)
            $serialNo = $request->serialNo ? (string)intval(preg_replace('/\D/', '', $request->serialNo)) : '0';
            $pageNo = $request->pageNo ? (string)intval(preg_replace('/\D/', '', $request->pageNo)) : '0';
            $volumeNo = $request->volumeNo ? (string)intval(preg_replace('/\D/', '', $request->volumeNo)) : '0';
            $regNo = $serialNo . '/' . $pageNo . '/' . $volumeNo;

            // Get transaction-specific party information (robust to labels and field names)
            $partyData = [];
            $tx = $request->transactionType ? strtolower((string) $request->transactionType) : '';

            \Log::info('Processing transaction type:', ['type' => $tx]);

            if ($tx && strpos($tx, 'assignment') !== false) {
                $partyData['Assignor'] = $request->input('Assignor') ?? $request->input('trans-assignor-record');
                $partyData['Assignee'] = $request->input('Assignee') ?? $request->input('trans-assignee-record');
            } elseif ($tx && (strpos($tx, 'purchase') !== false || strpos($tx, 'certificate of purchase') !== false)) {
                // Certificate of Purchase -> Vendor / Purchaser
                $partyData['Vendor'] = $request->input('Vendor') ?? $request->input('vendor-record') ?? $request->input('firstParty');
                $partyData['Purchaser'] = $request->input('Purchaser') ?? $request->input('purchaser-record') ?? $request->input('secondParty');
            } elseif ($tx && strpos($tx, 'tripartite') !== false) {
                // Tripartite Mortgage: include Mortgagor, Mortgagee, Mortgagor_2, Mortgagor_3, fourth_Party
                $partyData['Mortgagor'] = $request->input('Mortgagor') ?? $request->input('mortgagor-record') ?? $request->input('firstParty');
                $partyData['Mortgagee'] = $request->input('Mortgagee') ?? $request->input('mortgagee-record') ?? $request->input('secondParty');
                $partyData['Mortgagor_2'] = $request->input('Mortgagor_2') ?? $request->input('thirdParty') ?? $request->input('party_3');
                $partyData['Mortgagor_3'] = $request->input('Mortgagor_3') ?? $request->input('fourthParty') ?? $request->input('party_4');
                $partyData['fourth_Party'] = $request->input('fourth_Party') ?? $request->input('fifthParty') ?? $request->input('party_5');
            } elseif ($tx && (strpos($tx, 'gift') !== false || strpos($tx, 'deed of gift') !== false)) {
                // Deed of Gift -> Donor / Donee
                $partyData['Donor'] = $request->input('Donor') ?? $request->input('donor-record') ?? $request->input('firstParty');
                $partyData['Donee'] = $request->input('Donee') ?? $request->input('donee-record') ?? $request->input('secondParty');
            } elseif ($tx && strpos($tx, 'mortgage') !== false) {
                $partyData['Mortgagor'] = $request->input('Mortgagor') ?? $request->input('mortgagor-record') ?? $request->input('firstParty');
                $partyData['Mortgagee'] = $request->input('Mortgagee') ?? $request->input('mortgagee-record') ?? $request->input('secondParty');
                $partyData['Mortgagor_2'] = $request->input('Mortgagor_2') ?? $request->input('thirdParty') ?? $request->input('party_3');
                $partyData['Mortgagor_3'] = $request->input('Mortgagor_3') ?? $request->input('fourthParty') ?? $request->input('party_4');
            } elseif ($tx && strpos($tx, 'surrender') !== false) {
                $partyData['Surrenderor'] = $request->input('Surrenderor') ?? $request->input('surrenderor-record') ?? $request->input('firstParty');
                $partyData['Surrenderee'] = $request->input('Surrenderee') ?? $request->input('surrenderee-record') ?? $request->input('secondParty');
                $partyData['Surrenderor_2'] = $request->input('Surrenderor_2') ?? $request->input('thirdParty');
            } elseif ($tx && strpos($tx, 'lease') !== false) {
                $partyData['Lessor'] = $request->input('Lessor') ?? $request->input('lessor-record') ?? $request->input('firstParty');
                $partyData['Lessee'] = $request->input('Lessee') ?? $request->input('lessee-record') ?? $request->input('secondParty');
            } elseif ($tx) {
                $partyData['Grantor'] = $request->input('Grantor') ?? $request->input('grantor-record') ?? $request->input('firstParty');
                $partyData['Grantee'] = $request->input('Grantee') ?? $request->input('grantee-record') ?? $request->input('secondParty');
            }

            \Log::info('Party data extracted:', $partyData);

            // Derive generic party_1 through party_5 values
            $derived_party_1 = $request->input('party_1') ?? $partyData['Mortgagor'] ?? $partyData['Assignor'] ?? $partyData['Vendor'] ?? $partyData['Donor'] ?? $partyData['Surrenderor'] ?? $partyData['Lessor'] ?? $partyData['Grantor'] ?? $request->input('firstParty');
            $derived_party_2 = $request->input('party_2') ?? $partyData['Mortgagee'] ?? $partyData['Assignee'] ?? $partyData['Purchaser'] ?? $partyData['Donee'] ?? $partyData['Surrenderee'] ?? $partyData['Lessee'] ?? $partyData['Grantee'] ?? $request->input('secondParty');
            $derived_party_3 = $partyData['Mortgagor_2'] ?? $partyData['Surrenderor_2'] ?? $request->input('Mortgagor_2') ?? $request->input('Surrenderor_2') ?? $request->input('thirdParty') ?? $request->input('party_3') ?? null;
            $derived_party_4 = $partyData['Mortgagor_3'] ?? $request->input('Mortgagor_3') ?? $request->input('party_4') ?? $request->input('fourthParty') ?? null;

            // Determine title_type based on transaction type or use provided value
            $titleType = $request->input('title_type');
            if (!$titleType) {
                // Auto-determine title_type based on transaction_type
                $titleType = $request->transactionType ? $this->determineTitleType($request->transactionType) : 'Other';
            }

            // NEW: Route CofO-related transactions to CofO table instead of property_records
            $cofoTransactionTypes = [
                'Certificate of Occupancy',
                'ST Certificate of Occupancy',
                'SLTR Certificate of Occupancy',
            ];
            $isCofO = $request->transactionType && in_array($request->transactionType, $cofoTransactionTypes, true);

            if (!$isIndexMode && $isCofO) {
                // Ensure CofO table exists
                if (!Schema::connection('sqlsrv')->hasTable('CofO_staging')) {
                    throw new \Exception('Table CofO does not exist in the database');
                }
                $cofoColumns = Schema::connection('sqlsrv')->getColumnListing('CofO_staging');

                // Map cofo_type
                $cofoTypeMap = [
                    'Certificate of Occupancy' => 'Legacy CofO',
                    'ST Certificate of Occupancy' => 'ST CofO',
                    'SLTR Certificate of Occupancy' => 'SLTR CofO',
                ];
                $cofoType = $cofoTypeMap[$request->transactionType] ?? null;

                // Build full data payload for CofO, then filter by available columns
                $allCofOData = [
                    'np_fileno' => $request->input('np_fileno') ?: null,
                    'mlsFNo' => $mls ?: null,
                    'kangisFileNo' => $kangis ?: null,
                    'NewKANGISFileno' => $newKangis ?: null,
                    'title_type' => $titleType,
                    'transaction_type' => $request->transactionType ?: null,
                    'transaction_date' => $request->transactionDate ?: null,
                    'transaction_time' => $request->regTime ?: '00:00:00',

                    'serialNo' => $serialNo,
                    'pageNo' => $pageNo,
                    'volumeNo' => $volumeNo,
                    'regNo' => $regNo,
                    'instrument_type' => $request->instrumentType ?: $request->transactionType,
                    'period' => $request->period ? (int) $request->period : null,
                    'period_unit' => $request->periodUnit,
                    'Assignor' => $partyData['Assignor'] ?? null,
                    'Assignee' => $partyData['Assignee'] ?? null,
                    'Mortgagor' => $partyData['Mortgagor'] ?? null,
                    'Mortgagee' => $partyData['Mortgagee'] ?? null,
                    'Surrenderor' => $partyData['Surrenderor'] ?? null,
                    'Surrenderee' => $partyData['Surrenderee'] ?? null,
                    'Lessor' => $partyData['Lessor'] ?? null,
                    'Lessee' => $partyData['Lessee'] ?? null,
                    'Grantor' => $partyData['Grantor'] ?? null,
                    'Grantee' => $partyData['Grantee'] ?? null,
                    'property_description' => $request->property_description,
                    'location' => $request->location,
                    'plot_no' => $request->plot_no,
                    'lgsaOrCity' => $request->lgsaOrCity,
                    'layout' => $request->layout,
                    'schedule' => $request->schedule,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                    'land_use' => $landUse,
                    'cofo_type' => $cofoType,
                    // New fields added per request
                    'tp_no' => $request->tp_no,
                    'lpkn_no' => $request->lpkn_no,
                    'approved_plan_no' => $request->approved_plan_no,
                    'plot_size' => $request->plot_size,
                    'date_recommended' => $request->date_recommended,
                    'date_approved' => $request->date_approved,
                    'lease_begins' => $request->lease_begins,
                    'lease_expires' => $request->lease_expires,
                    'metric_sheet' => $request->metric_sheet,
                    'temp_fileno' => $tempFileno,
                    'op_type' => $request->op_type,
                    'op_serial_number' => $request->op_serial_number,
                    'related_file_number' => $relatedFileNumber,
                    'related_fileno' => $relatedFileNumber,
                ];

                if (in_array('prop_id', $cofoColumns, true)) {
                    $allCofOData['prop_id'] = $propId;
                }

                // Keep only keys that exist as columns in CofO table
                $filteredCofOData = [];
                foreach ($allCofOData as $key => $value) {
                    if (in_array($key, $cofoColumns, true)) {
                        $filteredCofOData[$key] = $value;
                    }
                }

                \Log::info('CofO data for insertion:', $filteredCofOData);

                // Insert into CofO table
                $id = DB::connection('sqlsrv')->table('CofO_staging')->insertGetId($filteredCofOData);

                // Commit and respond
                DB::connection('sqlsrv')->commit();
                \Log::info('CofO record created successfully:', ['id' => $id]);

                $this->logSubmissionResult($logChannel, true, 'Submission stored successfully', [
                    'table' => 'CofO_staging',
                    'record_id' => $id,
                    'mls' => $mls ?: null,
                    'kangis' => $kangis ?: null,
                    'new_kangis' => $newKangis ?: null,
                    'temp_fileno' => $tempFileno ?: null,
                ]);

                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'status' => 'success',
                        'message' => 'CofO record created successfully',
                        'data' => ['id' => $id, 'table' => 'CofO']
                    ], 201);
                }

                return redirect()->route('propertycard.index')->with('success', 'CofO record created successfully');
            }

            $deedsDate = $this->normalizeValue($request->input('deeds_date', $request->input('deedsDate')));
            $deedsTime = $this->normalizeValue($request->input('deeds_time', $request->input('deedsTime')));

            // Prepare base data for database insertion
            $baseData = [
                'mlsFNo' => $mls ?: null,
                'kangisFileNo' => $kangis ?: null,
                'NewKANGISFileno' => $newKangis ?: null,
                'title_type' => $titleType, // Add required title_type field
                'transaction_type' => null,
                'transaction_date' => null,
                'serialNo' => $serialNo,
                'pageNo' => $pageNo,
                'volumeNo' => $volumeNo,
                'regNo' => $regNo,
                'instrument_type' => null,
                'period' => $request->period ? (int) $request->period : null,
                'period_unit' => $request->periodUnit,
                'property_description' => $request->property_description,
                'plot_no' => $request->plot_no,
                'house_no' => $request->input('house_no'),
            ];

            if (in_array('prop_id', $columns, true)) {
                $baseData['prop_id'] = $propId;
            }

            // Conditionally include optional columns if they exist in the table
            $optionalFields = [
                'location' => $request->location,
                'lgsaOrCity' => $request->lgsaOrCity,
                'layout' => $request->layout,
                'schedule' => $request->schedule,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
                // Registration date and time (use defaults if null)
                'regDate' => $request->regDate ?: now()->toDateString(),
                'regTime' => $request->regTime ?: '00:00:00',
                'land_use' => $landUse,
                // New fields added per request
                'tp_no' => $request->tp_no,
                'lpkn_no' => $request->lpkn_no,
                'approved_plan_no' => $request->approved_plan_no,
                'plot_size' => $request->plot_size,
                'date_recommended' => $request->date_recommended,
                'date_approved' => $request->date_approved,
                'lease_begins' => $request->lease_begins,
                'lease_expires' => $request->lease_expires,
                'metric_sheet' => $request->metric_sheet,
                'temp_fileno' => $tempFileno,
                'op_type' => $request->op_type,
                'op_serial_number' => $request->op_serial_number,
                'related_file_number' => $relatedFileNumber,
                'related_fileno' => $relatedFileNumber,
                'is_mapped' => $request->input('is_mapped') ? 1 : 0,
                'party_1' => $derived_party_1,
                'party_2' => $derived_party_2,
                'party_3' => $derived_party_3,
                'party_4' => $derived_party_4,
                'deeds_date' => $deedsDate,
                'deeds_time' => $deedsTime,
            ];

            foreach ($optionalFields as $field => $value) {
                if (in_array($field, $columns, true)) {
                    $baseData[$field] = $value;
                }
            }

            // Build the primary record payload (base instrument)
            $primaryRecordData = $baseData;
            $primaryRecordData['transaction_type'] = $request->transactionType ?: null;
            $primaryRecordData['transaction_date'] = $request->transactionDate ?: null;
            $primaryRecordData['instrument_type'] = $request->instrumentType ?: ($request->transactionType ?: null);

            // Add party data only for non-null values
            foreach ($partyData as $key => $value) {
                if ($value !== null && $value !== '' && in_array($key, $columns, true)) {
                    $primaryRecordData[$key] = $value;
                }
            }

            \Log::info('Final data for insertion:', $primaryRecordData);

            // Insert primary record into database
            $id = DB::connection('sqlsrv')->table($targetTable)->insertGetId($primaryRecordData);

            // Handle extra registration number rows (same record, different serial/page/volume)
            $extraRegs = $request->input('extra_regs', []);
            if (is_array($extraRegs) && count($extraRegs) > 0) {
                foreach ($extraRegs as $reg) {
                    $regSerial = isset($reg['serial_no']) ? trim((string) $reg['serial_no']) : null;
                    $regPage   = isset($reg['page_no'])   ? trim((string) $reg['page_no'])   : null;
                    $regVol    = isset($reg['volume_no']) ? trim((string) $reg['volume_no']) : null;
                    // Skip rows with no data at all
                    $hasAnyData = $regSerial || $regPage || $regVol
                        || !empty($reg['transaction_date']) || !empty($reg['deeds_date'])
                        || !empty($reg['land_use']) || !empty($reg['period']);
                    if (!$hasAnyData) continue;
                    $extraRegNo = ($regSerial || $regPage || $regVol)
                        ? implode('/', [$regSerial ?: '0', $regPage ?: '0', $regVol ?: '0'])
                        : null;
                    $extraData = $primaryRecordData;
                    $extraData['serialNo'] = $regSerial ?: null;
                    $extraData['pageNo']   = $regPage   ?: null;
                    $extraData['volumeNo'] = $regVol    ?: null;
                    $extraData['regNo']    = $extraRegNo;
                    // Override per-card fields if provided and column exists in the record
                    if (!empty($reg['transaction_date']) && array_key_exists('transaction_date', $extraData)) {
                        $extraData['transaction_date'] = $reg['transaction_date'];
                    }
                    if (!empty($reg['deeds_date']) && array_key_exists('deeds_date', $extraData)) {
                        $extraData['deeds_date'] = $reg['deeds_date'];
                    }
                    if (!empty($reg['deeds_time']) && array_key_exists('deeds_time', $extraData)) {
                        $extraData['deeds_time'] = $reg['deeds_time'];
                    }
                    if (!empty($reg['land_use']) && array_key_exists('land_use', $extraData)) {
                        $extraData['land_use'] = $reg['land_use'];
                    }
                    if (isset($reg['period']) && $reg['period'] !== '' && array_key_exists('period', $extraData)) {
                        $extraData['period'] = (int) $reg['period'];
                    }
                    if (!empty($reg['period_unit']) && array_key_exists('period_unit', $extraData)) {
                        $extraData['period_unit'] = $reg['period_unit'];
                    }
                    DB::connection('sqlsrv')->table($targetTable)->insert($extraData);
                }
            }

            // Handle any additional instrument entries (duplicates of base record with new instrument data)
            $additionalInstruments = $this->normalizeInstrumentEntries($request->input('instrument_entries', []));
            if ($additionalInstruments->isNotEmpty()) {
                foreach ($additionalInstruments as $entry) {
                    $entryData = $baseData;
                    $entryType = $entry['type'] ?: $request->transactionType;
                    $entryDate = $entry['date'] ?: $request->transactionDate;

                    $entryData['transaction_type'] = $entryType;
                    $entryData['instrument_type'] = $entryType;
                    $entryData['transaction_date'] = $entryDate;

                    $this->applyInstrumentPartyValues($entryData, $entryType, $entry['party_from'], $entry['party_to'], $columns);
                    $this->applyInstrumentDateColumns($entryData, $entryType, $entryDate, $columns);

                    DB::connection('sqlsrv')->table($targetTable)->insert($entryData);
                }
            }

            // Only sync prop_id to file history if we actually allocated a Property ID
            // Temporary file numbers won't have a prop_id, so skip the sync
            if ($primaryFileIdentifier && $propId !== null) {
                $this->propertyIdAllocationService->syncPropIdToFileHistory($primaryFileIdentifier, $propId);
            }

            // Commit the transaction
            DB::connection('sqlsrv')->commit();

            \Log::info('Property record created successfully:', ['id' => $id]);

            // Mark temp file number as used
            if ($tempFileno && strpos($tempFileno, 'TEMP-') === 0) {
                $sequenceId = (int) str_replace('TEMP-', '', $tempFileno);
                DB::connection('sqlsrv')->table('temp_fileno_sequence')
                    ->where('id', $sequenceId)
                    ->update(['is_used' => 1, 'updated_at' => now()]);
            }

            $this->logSubmissionResult($logChannel, true, 'Submission stored successfully', [
                'table' => $targetTable,
                'record_id' => $id,
                'mls' => $mls ?: null,
                'kangis' => $kangis ?: null,
                'new_kangis' => $newKangis ?: null,
                'temp_fileno' => $tempFileno ?: null,
            ]);

            // Check if request expects JSON (AJAX) or normal redirect
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Property record created successfully',
                    'data' => ['id' => $id, 'table' => $targetTable]
                ], 201);
            }

            // For normal form submissions, redirect with success message
            $redirectRoute = $isIndexMode ? 'property_index_card.index' : 'propertycard.index';
            return redirect()->route($redirectRoute)->with('success', 'Property record created successfully');

        } catch (ValidationException $e) {
            DB::connection('sqlsrv')->rollBack();

            $this->logSubmissionResult($logChannel, false, 'Submission aborted due to validation exception', [
                'table' => $targetTable,
                'errors' => $e->errors(),
            ]);

            throw $e;
        } catch (\Exception $e) {
            // Rollback the transaction
            DB::connection('sqlsrv')->rollback();

            \Log::error('Property Record Creation Failed:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $request->all()
            ]);

            $this->logSubmissionResult($logChannel, false, 'Submission failed due to unexpected exception', [
                'table' => $targetTable,
                'error' => $e->getMessage(),
            ]);

            // Check if request expects JSON (AJAX) or normal redirect
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to create property record',
                    'error' => $e->getMessage(),
                    'debug_info' => [
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]
                ], 500);
            }

            // For normal form submissions, redirect with error message
            return redirect()->back()->with('error', 'Failed to create property record: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Update the specified property record in storage.
     *
```
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        // **PIC/PRA ROUTING FIX**: Determine target table based on record_mode
        $recordMode = $request->input('record_mode', 'property');
        $isIndexMode = $recordMode === 'index';
        $targetTable = $isIndexMode ? 'pic' : 'pra';

        \Log::info("Property Record Update Request", [
            'id' => $id,
            'record_mode' => $recordMode,
            'target_table' => $targetTable
        ]);

        // Modified validation rules for update - make file numbers optional
        $validator = Validator::make($request->all(), [

            'transactionType' => 'nullable|string',
            'transactionDate' => 'nullable|date',
            'serialNo' => 'nullable|string',
            'pageNo' => 'nullable|string',
            'volumeNo' => 'nullable|string',
            'instrumentType' => 'nullable|string',
            'period' => 'nullable|numeric',
            'periodUnit' => 'nullable',
            // Party fields
            'Assignor' => 'nullable|string',
            'Assignee' => 'nullable|string',
            'Mortgagor' => 'nullable|string',
            'Mortgagee' => 'nullable|string',
            'Mortgagor_2' => 'nullable|string|max:500',
            'Mortgagor_3' => 'nullable|string|max:500',
            'fourth_Party' => 'nullable|string|max:500',
            'party_4' => 'nullable|string|max:500',
            'party_5' => 'nullable|string|max:500',
            'Surrenderor' => 'nullable|string|max:500',
            'Surrenderee' => 'nullable|string|max:500',
            'Lessor' => 'nullable|string|max:500',
            'Lessee' => 'nullable|string|max:500',
            'Grantor' => 'nullable|string|max:500',
            'Grantee' => 'nullable|string|max:500',
            'Donor' => 'nullable|string|max:500',
            'Donee' => 'nullable|string|max:500',
            'tripartite_has_third' => 'nullable|boolean',
            // Property details
            'property_description' => 'nullable|string',
            'location' => 'nullable|string',
            'plot_no' => 'nullable|string',
            'house_no' => 'nullable|string',
            // New fields
            'lgsaOrCity' => 'nullable|string',
            'layout' => 'nullable|string',
            'schedule' => 'nullable|string',
            'related_fileno' => 'nullable|string|max:255',
            'Surrenderor_2' => 'nullable|string|max:255',
            'temp_fileno' => 'nullable|string|max:255',
            'op_type' => 'nullable|string|max:255',
            'op_serial_number' => 'nullable|string|max:255',
            'party_3' => 'nullable|string|max:255',
            // Generic party fields for fallback
            'firstParty' => 'nullable|string|max:500',
            'secondParty' => 'nullable|string|max:500',
        ]);

        // Add conditional validation for parties based on transaction type
        $validator->after(function ($validator) use ($request) {
            $tx = strtolower((string) $request->transactionType);
            if (!$tx) return;

            $hasFirst = $request->filled('firstParty') || $request->filled('Assignor') || $request->filled('Mortgagor') || 
                        $request->filled('Vendor') || $request->filled('Donor') || $request->filled('Surrenderor') || 
                        $request->filled('Lessor') || $request->filled('Grantor');
                        
            $hasSecond = $request->filled('secondParty') || $request->filled('Assignee') || $request->filled('Mortgagee') || 
                         $request->filled('Purchaser') || $request->filled('Donee') || $request->filled('Surrenderee') || 
                         $request->filled('Lessee') || $request->filled('Grantee');

            if (strpos($tx, 'assignment') !== false) {
                if (!$hasFirst) $validator->errors()->add('Assignor', 'The Assignor name is required for Assignment transactions.');
                if (!$hasSecond) $validator->errors()->add('Assignee', 'The Assignee name is required for Assignment transactions.');
            } elseif (strpos($tx, 'mortgage') !== false) {
                if (!$hasFirst) $validator->errors()->add('Mortgagor', 'The Mortgagor name is required for Mortgage transactions.');
                if (!$hasSecond) $validator->errors()->add('Mortgagee', 'The Mortgagee name is required for Mortgage transactions.');
                
                if (strpos($tx, 'tripartite') !== false) {
                    if (!$request->filled('thirdParty') && !$request->filled('Mortgagor_2') && !$request->filled('party_3')) {
                        $validator->errors()->add('thirdParty', 'A second Mortgagor/Co-mortgagor is required for Tripartite Mortgage.');
                    }
                }
            } elseif (strpos($tx, 'purchase') !== false) {
                if (!$hasFirst) $validator->errors()->add('Vendor', 'The Vendor name is required for Purchase transactions.');
                if (!$hasSecond) $validator->errors()->add('Purchaser', 'The Purchaser name is required for Purchase transactions.');
            } elseif (strpos($tx, 'gift') !== false) {
                if (!$hasFirst) $validator->errors()->add('Donor', 'The Donor name is required for Gift transactions.');
                if (!$hasSecond) $validator->errors()->add('Donee', 'The Donee name is required for Gift transactions.');
            } elseif (strpos($tx, 'surrender') !== false) {
                if (!$hasFirst) $validator->errors()->add('Surrenderor', 'The Surrenderor name is required for Surrender transactions.');
                if (!$hasSecond) $validator->errors()->add('Surrenderee', 'The Surrenderee name is required for Surrender transactions.');
            } elseif (strpos($tx, 'lease') !== false) {
                if (!$hasFirst) $validator->errors()->add('Lessor', 'The Lessor name is required for Lease transactions.');
                if (!$hasSecond) $validator->errors()->add('Lessee', 'The Lessee name is required for Lease transactions.');
            } elseif ($tx !== '') {
                // Default fallback
                if (!$hasFirst) $validator->errors()->add('firstParty', 'The first party name is required.');
                if (!$hasSecond) $validator->errors()->add('secondParty', 'The second party name is required.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Create registration number from components (use "0" for null values and strip leading zeros)
            $serialNo = $request->serialNo ? (string)intval(preg_replace('/\D/', '', $request->serialNo)) : '0';
            $pageNo = $request->pageNo ? (string)intval(preg_replace('/\D/', '', $request->pageNo)) : '0';
            $volumeNo = $request->volumeNo ? (string)intval(preg_replace('/\D/', '', $request->volumeNo)) : '0';
            $regNo = $serialNo . '/' . $pageNo . '/' . $volumeNo;

            // Get existing property record to preserve file numbers
            $existingProperty = DB::connection('sqlsrv')->table($targetTable)
                ->where('id', $id)
                ->first();

            if (!$existingProperty) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Property record not found'
                ], 404);
            }

            // Get transaction-specific party information from edit form
            $partyData = [];

            // Add debug output to help diagnose party field issues
            \Log::info('Transaction Type: ' . $request->transactionType);
            \Log::info('Request data: ', $request->all());

            $normalizedType = strtolower($request->transactionType ?? '');

            switch ($normalizedType) {
                case 'assignment':
                case 'deed of assignment':
                case 'st assignment':
                    $partyData['Assignor'] = $request->input('Assignor') ?? $request->input('firstParty');
                    $partyData['Assignee'] = $request->input('Assignee') ?? $request->input('secondParty');
                    break;
                case 'mortgage':
                case 'deed of mortgage':
                    $partyData['Mortgagor'] = $request->input('Mortgagor') ?? $request->input('firstParty');
                    $partyData['Mortgagee'] = $request->input('Mortgagee') ?? $request->input('secondParty');
                    break;
                case 'tripartite mortgage':
                    $partyData['Mortgagor'] = $request->input('Mortgagor') ?? $request->input('firstParty');
                    $partyData['Mortgagee'] = $request->input('Mortgagee') ?? $request->input('secondParty');
                    $partyData['Mortgagor_2'] = $request->input('Mortgagor_2') ?? $request->input('thirdParty') ?? $request->input('party_3');
                    $partyData['Mortgagor_3'] = $request->input('Mortgagor_3') ?? $request->input('fourthParty') ?? $request->input('party_4');
                    $partyData['fourth_Party'] = $request->input('fourth_Party') ?? $request->input('fifthParty') ?? $request->input('party_5');
                    break;
                case 'gift':
                case 'deed of gift':
                    $partyData['Donor'] = $request->input('Donor') ?? $request->input('firstParty');
                    $partyData['Donee'] = $request->input('Donee') ?? $request->input('secondParty');
                    break;
                case 'surrender':
                case 'deed of surrender':
                case 'deed of surrender and release':
                    $partyData['Surrenderor'] = $request->input('Surrenderor') ?? $request->input('firstParty');
                    $partyData['Surrenderee'] = $request->input('Surrenderee') ?? $request->input('secondParty');
                    $partyData['Surrenderor_2'] = $request->input('Surrenderor_2') ?? $request->input('thirdParty');
                    break;
                case 'sub-lease':
                case 'sublease':
                case 'lease':
                case 'deed of lease':
                case 'deed of sub lease':
                case 'deed of sub under lease':
                case 'indenture of lease':
                case 'quarry lease':
                case 'private lease':
                case 'building lease':
                    $partyData['Lessor'] = $request->input('Lessor') ?? $request->input('firstParty');
                    $partyData['Lessee'] = $request->input('Lessee') ?? $request->input('secondParty');
                    break;
                default:
                    $partyData['Grantor'] = $request->input('Grantor') ?? $request->input('firstParty');
                    $partyData['Grantee'] = $request->input('Grantee') ?? $request->input('secondParty');
            }

            if ($normalizedType !== 'tripartite mortgage') {
                $partyData['Mortgagor_2'] = null;
            }
            if ($normalizedType !== 'surrender' && $normalizedType !== 'deed of surrender' && $normalizedType !== 'deed of surrender and release') {
                $partyData['Surrenderor_2'] = null;
            }

            // Log party data for debugging
            \Log::info('Party data to be updated: ', $partyData);

            // Derive generic party_1 through party_5 values for update
            $derived_party_1_upd = $request->input('party_1') ?? $partyData['Mortgagor'] ?? $partyData['Assignor'] ?? $partyData['Vendor'] ?? $partyData['Donor'] ?? $partyData['Surrenderor'] ?? $partyData['Lessor'] ?? $partyData['Grantor'] ?? $request->input('firstParty');
            $derived_party_2_upd = $request->input('party_2') ?? $partyData['Mortgagee'] ?? $partyData['Assignee'] ?? $partyData['Purchaser'] ?? $partyData['Donee'] ?? $partyData['Surrenderee'] ?? $partyData['Lessee'] ?? $partyData['Grantee'] ?? $request->input('secondParty');
            $derived_party_3_upd = $partyData['Mortgagor_2'] ?? $partyData['Surrenderor_2'] ?? $request->input('Mortgagor_2') ?? $request->input('Surrenderor_2') ?? $request->input('thirdParty') ?? $request->input('party_3') ?? null;
            $derived_party_4_upd = $partyData['Mortgagor_3'] ?? $request->input('Mortgagor_3') ?? $request->input('party_4') ?? $request->input('fourthParty') ?? null;
            $derived_party_5_upd = $partyData['fourth_Party'] ?? $request->input('fourth_Party') ?? $request->input('party_5') ?? $request->input('fifthParty') ?? null;

            $relatedFileNumber = trim((string) ($request->input('related_file_number', $request->input('related_fileno', ''))));
            $relatedFileNumber = $relatedFileNumber !== '' ? $relatedFileNumber : null;

            // Prepare data for database update (keep file numbers; include optional columns only if present)
            $data = [
                'mlsFNo' => $existingProperty->mlsFNo,
                'kangisFileNo' => $existingProperty->kangisFileNo,
                'NewKANGISFileno' => $existingProperty->NewKANGISFileno,
                'transaction_type' => $request->transactionType,
                'transaction_date' => $request->transactionDate,
                'serialNo' => $request->serialNo,
                'pageNo' => $request->pageNo,
                'volumeNo' => $request->volumeNo,
                'regNo' => $regNo,
                'instrument_type' => $request->instrumentType,
                'period' => $request->period,
                'period_unit' => $request->periodUnit,
                'property_description' => $request->property_description,
                'house_no' => $request->input('house_no'),
                'temp_fileno' => $request->input('temp_fileno') ?? $existingProperty->temp_fileno,
            ];

            // Safety check for update: if the transaction type or mlsFNo implies a temp record
            if (!$data['temp_fileno'] && $data['mlsFNo'] && strpos($data['mlsFNo'], 'TEMP-') === 0) {
                $data['temp_fileno'] = $data['mlsFNo'];
            }
            if (Schema::connection('sqlsrv')->hasColumn('pra', 'location')) {
                $data['location'] = $request->location;
            }
            if (Schema::connection('sqlsrv')->hasColumn('pra', 'lgsaOrCity')) {
                $data['lgsaOrCity'] = $request->lgsaOrCity;
            }
            if (Schema::connection('sqlsrv')->hasColumn('pra', 'layout')) {
                $data['layout'] = $request->layout;
            }
            if (Schema::connection('sqlsrv')->hasColumn('pra', 'schedule')) {
                $data['schedule'] = $request->schedule;
            }
            if (Schema::connection('sqlsrv')->hasColumn('pra', 'related_file_number')) {
                $data['related_file_number'] = $relatedFileNumber;
            }
            if (Schema::connection('sqlsrv')->hasColumn('pra', 'related_fileno')) {
                $data['related_fileno'] = $relatedFileNumber;
            }
            if (Schema::connection('sqlsrv')->hasColumn($targetTable, 'op_type')) {
                $data['op_type'] = $request->input('op_type');
            }
            if (Schema::connection('sqlsrv')->hasColumn($targetTable, 'op_serial_number')) {
                $data['op_serial_number'] = $request->input('op_serial_number');
            }
            if (Schema::connection('sqlsrv')->hasColumn('pra', 'land_use')) {
                $landUse = $this->normalizeValue(
                    $request->input(
                        'land_use',
                        $request->input('landUse', $request->input('land_use_type'))
                    )
                );
                $data['land_use'] = $landUse;
            }
            if (Schema::connection('sqlsrv')->hasColumn($targetTable, 'updated_by')) {
                $data['updated_by'] = Auth::id();
            }
            if (Schema::connection('sqlsrv')->hasColumn($targetTable, 'updated_at')) {
                $data['updated_at'] = now();
            }

            // Merge party data only if values are not null
            foreach ($partyData as $key => $value) {
                if ($value !== null && $value !== '' && Schema::connection('sqlsrv')->hasColumn($targetTable, $key)) {
                    $data[$key] = $value;
                }
            }

            // Persist generic party columns if present
            if (Schema::connection('sqlsrv')->hasColumn($targetTable, 'party_1')) {
                $data['party_1'] = $derived_party_1_upd;
            }
            if (Schema::connection('sqlsrv')->hasColumn($targetTable, 'party_2')) {
                $data['party_2'] = $derived_party_2_upd;
            }
            if (Schema::connection('sqlsrv')->hasColumn($targetTable, 'party_3')) {
                $data['party_3'] = $derived_party_3_upd;
            }
            if (Schema::connection('sqlsrv')->hasColumn($targetTable, 'party_4')) {
                $data['party_4'] = $derived_party_4_upd;
            }
            if (Schema::connection('sqlsrv')->hasColumn($targetTable, 'party_5')) {
                $data['party_5'] = $derived_party_5_upd;
            }

            // Debug: Log what data is being sent to the database
            \Log::info('Data to be sent to database: ', $data);
            \Log::info('Checking if Assignor column exists: ' . (Schema::connection('sqlsrv')->hasColumn($targetTable, 'Assignor') ? 'YES' : 'NO'));
            \Log::info('Checking if Assignee column exists: ' . (Schema::connection('sqlsrv')->hasColumn($targetTable, 'Assignee') ? 'YES' : 'NO'));
            \Log::info('Checking if party_1 column exists: ' . (Schema::connection('sqlsrv')->hasColumn($targetTable, 'party_1') ? 'YES' : 'NO'));
            \Log::info('Checking if party_2 column exists: ' . (Schema::connection('sqlsrv')->hasColumn($targetTable, 'party_2') ? 'YES' : 'NO'));

            // Update the database record in the correct table
            $affectedRows = DB::connection('sqlsrv')->table($targetTable)
                ->where('id', $id)
                ->update($data);

            \Log::info('Number of rows affected by update: ' . $affectedRows);

            if ($affectedRows > 0) {
                // Mark temp file number as used if it exists in the updated data or existing record
                $effectiveTempFileNo = $data['temp_fileno'] ?? ($existingProperty->temp_fileno ?? null);
                if ($effectiveTempFileNo && strpos($effectiveTempFileNo, 'TEMP-') === 0) {
                    $sequenceId = (int) str_replace('TEMP-', '', $effectiveTempFileNo);
                    DB::connection('sqlsrv')->table('temp_fileno_sequence')
                        ->where('id', $sequenceId)
                        ->update(['is_used' => 1, 'updated_at' => now()]);
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Property record updated successfully',
                'table' => $targetTable,  // Include table info for debugging
                'affected_rows' => $affectedRows
            ]);
        } catch (\Exception $e) {
            // Add more detailed error logging
            \Log::error('Error updating property record: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update property record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified property record from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            DB::connection('sqlsrv')->table('pra')
                ->where('id', $id)
                ->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Property record deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete property record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified property record.
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request  $request (optional)
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id, Request $request = null)
    {
        try {
            // **PIC/PRA ROUTING FIX**: Determine target table based on record_mode
            $recordMode = $request ? $request->input('record_mode', 'property') : 'property';
            $isIndexMode = $recordMode === 'index';
            $targetTable = $isIndexMode ? 'pic' : 'pra';

            \Log::info("Property Record Show Request", [
                'id' => $id,
                'record_mode' => $recordMode,
                'target_table' => $targetTable
            ]);

            // Try target table first
            $property = DB::connection('sqlsrv')->table($targetTable)
                ->where('id', $id)
                ->first();

            if (!$property) {
                // Fallback to the other table if not found
                $fallbackTable = $isIndexMode ? 'pra' : 'pic';
                $property = DB::connection('sqlsrv')->table($fallbackTable)
                    ->where('id', $id)
                    ->first();
            }

            if (!$property) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Property record not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $property
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve property record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search for file numbers for property records dropdown
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchFileNumbers(Request $request)
    {
        try {
            $search = $request->input('search', '');
            $page = $request->input('page', 1);
            $perPage = 10;
            $offset = ($page - 1) * $perPage;

            // Log the request for debugging
            \Log::info('File number search request:', [
                'search' => $search,
                'page' => $page,
                'method' => $request->method(),
                'all_input' => $request->all()
            ]);

            // Search across multiple sources for file numbers
            $fileNumbers = collect();

            // Search in existing property records
            if (!empty($search)) {
                // Fix: Use proper string comparison instead of complex where clauses
                $propertyRecords = DB::connection('sqlsrv')
                    ->table('pra')
                    ->select('id', 'mlsFNo as fileno', 'property_description as description', 'plot_no', 'lgsaOrCity as lga', 'location')
                    ->whereRaw("mlsFNo LIKE ?", ["%{$search}%"])
                    ->whereNotNull('mlsFNo')
                    ->whereRaw("mlsFNo != ''")
                    ->limit($perPage)
                    ->get();

                foreach ($propertyRecords as $record) {
                    $fileNumbers->push([
                        'id' => 'property_' . $record->id,
                        'fileno' => $record->fileno,
                        'description' => $record->description,
                        'plot_no' => $record->plot_no,
                        'lga' => $record->lga,
                        'location' => $record->location,
                        'source' => 'property_records'
                    ]);
                }

                // Search KANGIS file numbers
                $kangisRecords = DB::connection('sqlsrv')
                    ->table('pra')
                    ->select('id', 'kangisFileNo as fileno', 'property_description as description', 'plot_no', 'lgsaOrCity as lga', 'location')
                    ->whereRaw("kangisFileNo LIKE ?", ["%{$search}%"])
                    ->whereNotNull('kangisFileNo')
                    ->whereRaw("kangisFileNo != ''")
                    ->limit($perPage)
                    ->get();

                foreach ($kangisRecords as $record) {
                    $fileNumbers->push([
                        'id' => 'kangis_' . $record->id,
                        'fileno' => $record->fileno,
                        'description' => $record->description,
                        'plot_no' => $record->plot_no,
                        'lga' => $record->lga,
                        'location' => $record->location,
                        'source' => 'property_records'
                    ]);
                }

                // Search New KANGIS file numbers
                $newKangisRecords = DB::connection('sqlsrv')
                    ->table('pra')
                    ->select('id', 'NewKANGISFileno as fileno', 'property_description as description', 'plot_no', 'lgsaOrCity as lga', 'location')
                    ->whereRaw("NewKANGISFileno LIKE ?", ["%{$search}%"])
                    ->whereNotNull('NewKANGISFileno')
                    ->whereRaw("NewKANGISFileno != ''")
                    ->limit($perPage)
                    ->get();

                foreach ($newKangisRecords as $record) {
                    $fileNumbers->push([
                        'id' => 'newkangis_' . $record->id,
                        'fileno' => $record->fileno,
                        'description' => $record->description,
                        'plot_no' => $record->plot_no,
                        'lga' => $record->lga,
                        'location' => $record->location,
                        'source' => 'property_records'
                    ]);
                }

                // Search in applications table if it exists
                try {
                    $applications = DB::connection('sqlsrv')
                        ->table('dbo.mother_applications')
                        ->select('id', 'fileno', 'plot_no', 'lga_name as lga', 'layout_name as location')
                        ->whereRaw("fileno LIKE ?", ["%{$search}%"])
                        ->whereNotNull('fileno')
                        ->whereRaw("fileno != ''")
                        ->limit($perPage)
                        ->get();

                    foreach ($applications as $app) {
                        $fileNumbers->push([
                            'id' => 'app_' . $app->id,
                            'fileno' => $app->fileno,
                            'description' => 'Application Record',
                            'plot_no' => $app->plot_no,
                            'lga' => $app->lga,
                            'location' => $app->location,
                            'source' => 'applications'
                        ]);
                    }
                } catch (\Exception $e) {
                    // Applications table might not exist or be accessible
                }
            } else {
                // If no search term, return recent file numbers or sample data
                try {
                    $recentRecords = DB::connection('sqlsrv')
                        ->table('pra')
                        ->select('id', 'mlsFNo as fileno', 'property_description as description', 'plot_no', 'lgsaOrCity as lga', 'location')
                        ->whereNotNull('mlsFNo')
                        ->whereRaw("mlsFNo != ''")
                        ->orderBy('created_at', 'desc')
                        ->limit($perPage)
                        ->get();

                    foreach ($recentRecords as $record) {
                        $fileNumbers->push([
                            'id' => 'recent_' . $record->id,
                            'fileno' => $record->fileno,
                            'description' => $record->description,
                            'plot_no' => $record->plot_no,
                            'lga' => $record->lga,
                            'location' => $record->location,
                            'source' => 'property_records'
                        ]);
                    }
                } catch (\Exception $e) {
                    // If database query fails, provide sample data for testing
                    $sampleData = [
                        [
                            'id' => 'sample_1',
                            'fileno' => 'COM-2023-001',
                            'description' => 'Commercial Property',
                            'plot_no' => '123',
                            'lga' => 'Kano Municipal',
                            'location' => 'Sabon Gari',
                            'source' => 'sample'
                        ],
                        [
                            'id' => 'sample_2',
                            'fileno' => 'RES-2023-002',
                            'description' => 'Residential Property',
                            'plot_no' => '456',
                            'lga' => 'Fagge',
                            'location' => 'Fagge Layout',
                            'source' => 'sample'
                        ],
                        [
                            'id' => 'sample_3',
                            'fileno' => 'KNML 00001',
                            'description' => 'KANGIS Property',
                            'plot_no' => '789',
                            'lga' => 'Gwale',
                            'location' => 'Gwale District',
                            'source' => 'sample'
                        ]
                    ];

                    foreach ($sampleData as $sample) {
                        $fileNumbers->push($sample);
                    }
                }
            }

            // Remove duplicates based on fileno
            $uniqueFileNumbers = $fileNumbers->unique('fileno')->values();

            // Paginate results
            $total = $uniqueFileNumbers->count();
            $results = $uniqueFileNumbers->slice($offset, $perPage)->values();

            return response()->json([
                'success' => true,
                'file_numbers' => $results,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'more' => ($offset + $perPage) < $total
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error searching file numbers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate and return the next temporary file number.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetchTempFileNumber(Request $request)
    {
        try {
            // Wrap inside a transaction to reduce race conditions when multiple users request new numbers
            $tempFileno = DB::connection('sqlsrv')->transaction(function () {
                return $this->generateTempFileNumber();
            });

            return response()->json([
                'status' => 'success',
                'temp_fileno' => $tempFileno,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Failed to generate temporary file number', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Unable to generate a temporary file number',
            ], 500);
        }
    }

    /**
     * Import OP records into PRA from spreadsheet.
     *
     * Rules:
     * - mlsFNo must be NULL
     * - temp_fileno/fileno are generated uniquely
     * - prop_id must be allocated per imported row
     */
    /**
     * Download the CSV template for OP import.
     */
    public function downloadOpImportTemplate()
    {
        $headers = ['SerialNo', 'PageNo', 'VolNo', 'RegTime', 'RegDate', 'TPNo', 'OPSerialNo', 'PlotNo', 'District', 'Allottee'];

        $callback = function () use ($headers) {
            $out = fopen('php://output', 'w');
            // Write BOM for Excel compatibility
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            // Sample row
            fputcsv($out, ['282', '282', '238', '', '3/2/2026', 'TP/KW/UC/DTF/17', '2146', '1979', 'AIR FORCE KWA', 'Musa Yakubu']);
            fclose($out);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="OP_Import_Template.csv"',
        ]);
    }

    public function importOpToPra(Request $request)
    {
        ignore_user_abort(true);
        @set_time_limit(0);

        // Catch fatal errors that bypass try/catch
        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
                Log::error('OP import fatal shutdown', $error);
            }
        });

        $request->validate([
            'op_file' => 'required|file|mimes:csv,txt|max:20480',
            'op_type' => 'required|string|in:OP Direct Allocation,OP Resettlement',
        ]);

        $selectedOpType = trim((string) $request->input('op_type'));

        $summary = [
            'total_rows' => 0,
            'imported' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        try {
            $uploadedFile = $request->file('op_file');
            $realPath = $uploadedFile->getRealPath();

            Log::info('OP import to PRA started', [
                'user_id' => Auth::id(),
                'filename' => $uploadedFile->getClientOriginalName(),
                'size' => $uploadedFile->getSize(),
                'op_type' => $selectedOpType,
            ]);

            // === Step 1: Read CSV ===
            $rows = $this->readOpCsv($realPath);
            $summary['total_rows'] = count($rows);
            Log::info('OP import rows read', ['count' => count($rows)]);

            if (empty($rows)) {
                return redirect('/propertycard')
                    ->with('warning', 'No data rows found. Use the CSV template and ensure row 1 has the correct headers.');
            }

            // === Step 2: PRA columns ===
            $praColumns = Schema::connection('sqlsrv')->getColumnListing('pra');
            $praColumnLookup = array_flip($praColumns);

            // === Step 3: Existing duplicate check ===
            $serials = array_values(array_unique(array_filter(array_column($rows, 'op_serial_number'))));
            $existingKeys = [];
            if (!empty($serials)) {
                foreach (array_chunk($serials, 800) as $chunk) {
                    $existing = DB::connection('sqlsrv')->table('pra')
                        ->select(['op_serial_number', 'regNo', 'plot_no', 'location', 'party_1'])
                        ->whereIn('op_serial_number', $chunk)
                        ->get();
                    foreach ($existing as $r) {
                        $existingKeys[$this->buildOpImportDuplicateKey([
                            'reg_no' => $r->regNo ?? null,
                            'op_serial_number' => $r->op_serial_number ?? null,
                            'plot_no' => $r->plot_no ?? null,
                            'district' => $r->location ?? null,
                            'party_1' => $r->party_1 ?? null,
                        ])] = true;
                    }
                }
            }
            Log::info('OP import duplicates checked', ['existing' => count($existingKeys)]);

            // === Step 4: Process in chunks ===
            $seenKeys = [];
            $chunks = array_chunk($rows, 200);

            foreach ($chunks as $ci => $chunk) {
                $batch = [];
                Log::info('OP import chunk ' . ($ci + 1) . ' of ' . count($chunks));

                foreach ($chunk as $row) {
                    $rowNum = $row['__row'];

                    // Build reg_no and party_1 for duplicate key (matching PRA columns)
                    $row['reg_no'] = $this->buildImportedRegNo($row['serial_no'], $row['page_no'], $row['volume_no']);
                    $row['party_1'] = 'Kano State Government';

                    $dupKey = $this->buildOpImportDuplicateKey($row);

                    if (isset($existingKeys[$dupKey]) || isset($seenKeys[$dupKey])) {
                        $summary['skipped']++;
                        continue;
                    }

                    try {
                        $tempFileno = $this->allocateTempFileNumberForImport();
                        $propId = $this->propertyIdAllocationService->allocateOrRetrievePropId(
                            $tempFileno, null, null, null,
                            ['temp_fileno' => $tempFileno, 'allow_temp_only' => true, 'skip_lookup' => true]
                        );
                    } catch (\Throwable $e) {
                        $summary['skipped']++;
                        $summary['errors'][] = "Row {$rowNum}: {$e->getMessage()}";
                        Log::warning('OP import row error', ['row' => $rowNum, 'error' => $e->getMessage()]);
                        continue;
                    }

                    $district = $row['district'];
                    $plotNo = $row['plot_no'];
                    $propertyDescription = $this->buildImportedPropertyDescription($plotNo, $district);

                    $payload = [
                        'prop_id' => $propId,
                        'mlsFNo' => null,
                        'fileno' => $tempFileno,
                        'temp_fileno' => $tempFileno,
                        'transaction_type' => 'Occupancy Permit (OP)',
                        'instrument_type' => 'Occupancy Permit (OP)',
                        'title_type' => 'Other',
                        'serialNo' => $row['serial_no'],
                        'op_serial_number' => $row['op_serial_number'],
                        'op_type' => $selectedOpType,
                        'pageNo' => $row['page_no'],
                        'volumeNo' => $row['volume_no'],
                        'deeds_date' => $row['reg_date'],
                        'deeds_time' => $row['reg_time'],
                        'tp_no' => $row['tp_no'],
                        'plot_no' => $plotNo,
                        'location' => $district,
                        'property_description' => $propertyDescription,
                        'Grantor' => 'Kano State Government',
                        'Grantee' => $row['allottee'],
                        'party_1' => 'Kano State Government',
                        'party_2' => $row['allottee'],
                        'created_by' => Auth::id(),
                        'updated_by' => Auth::id(),
                        'created_at' => now(),
                        'updated_at' => now(),
                        'source' => 'OP Import',
                    ];

                    if (isset($praColumnLookup['regNo'])) {
                        $payload['regNo'] = $this->buildImportedRegNo($row['serial_no'], $row['page_no'], $row['volume_no']);
                    }

                    if (isset($praColumnLookup['lgsaOrCity'])) {
                        $payload['lgsaOrCity'] = null;
                    }

                    $batch[] = array_intersect_key($payload, $praColumnLookup);
                    $seenKeys[$dupKey] = true;
                    $existingKeys[$dupKey] = true;
                }

                // Insert batch - try bulk first, fallback to row-by-row
                if (!empty($batch)) {
                    try {
                        DB::connection('sqlsrv')->table('pra')->insert($batch);
                        $summary['imported'] += count($batch);
                    } catch (\Throwable $bulkErr) {
                        Log::error('OP import bulk insert failed, trying row-by-row', ['error' => $bulkErr->getMessage()]);
                        foreach ($batch as $single) {
                            try {
                                DB::connection('sqlsrv')->table('pra')->insert($single);
                                $summary['imported']++;
                            } catch (\Throwable $rowErr) {
                                $summary['skipped']++;
                                Log::warning('OP import row insert failed', ['error' => $rowErr->getMessage()]);
                            }
                        }
                    }
                }

                Log::info('OP import chunk done', ['chunk' => $ci + 1, 'imported_so_far' => $summary['imported']]);
            }

            $summary['skipped'] = $summary['total_rows'] - $summary['imported'];

            Log::info('OP import to PRA completed', $summary);

            return redirect('/propertycard')
                ->with('op_import_summary', $summary)
                ->with('success', "OP import complete. Imported {$summary['imported']} row(s), skipped {$summary['skipped']}.");
        } catch (\Throwable $e) {
            Log::error('OP import to PRA failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return redirect('/propertycard')
                ->with('error', 'OP import failed: ' . $e->getMessage());
        }
    }

    /**
     * Read OP rows from CSV. Fixed column order matching template:
     * SerialNo, PageNo, VolNo, RegTime, RegDate, TPNo, OPSerialNo, PlotNo, District
     */
    private function readOpCsv(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if ($content === false || strlen($content) < 5) {
            Log::warning('OP import: file unreadable or empty');
            return [];
        }

        // Strip BOM
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
            file_put_contents($filePath, $content);
        }

        // Detect delimiter from first line
        $firstLine = strtok($content, "\r\n");
        $delimiter = ',';
        if (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
            $delimiter = "\t";
        } elseif (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
            $delimiter = ';';
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \RuntimeException('Cannot open CSV file.');
        }

        // Read and map headers
        $headerRaw = fgetcsv($handle, 0, $delimiter);
        if (!$headerRaw || count($headerRaw) < 3) {
            Log::warning('OP import: invalid header row', ['raw' => $headerRaw]);
            fclose($handle);
            return [];
        }

        // Build normalized header lookup:  normalized_name => col_index
        $headerLookup = [];
        foreach ($headerRaw as $i => $val) {
            $headerLookup[preg_replace('/[^a-z0-9]/', '', strtolower((string) $val))] = $i;
        }

        Log::info('OP import CSV headers found', ['lookup' => $headerLookup, 'delimiter' => $delimiter === "\t" ? 'TAB' : $delimiter]);

        // Map to our field names - try multiple possible header spellings
        $find = function (array $candidates) use ($headerLookup) {
            foreach ($candidates as $c) {
                if (isset($headerLookup[$c])) return $headerLookup[$c];
            }
            return null;
        };

        $col = [
            'serial_no'  => $find(['serialno', 'serial']),
            'page_no'    => $find(['pageno', 'page']),
            'volume_no'  => $find(['volno', 'volume', 'volumeno']),
            'reg_time'   => $find(['regtime']),
            'reg_date'   => $find(['regdate']),
            'tp_no'      => $find(['tpno', 'tpnumber']),
            'op_serial_number' => $find(['opserialno', 'opserialnumber', 'opserno']),
            'plot_no'    => $find(['plotno', 'plotnumber', 'plot']),
            'district'   => $find(['district']),
            'allottee'   => $find(['allottee', 'name', 'newparty']),
        ];

        Log::info('OP import column mapping', ['col' => $col]);

        $rows = [];
        $lineNum = 1;

        while (($csvRow = fgetcsv($handle, 0, $delimiter)) !== false) {
            $lineNum++;

            $get = function ($field) use ($csvRow, $col) {
                $idx = $col[$field];
                if ($idx === null) return null;
                $val = trim((string) ($csvRow[$idx] ?? ''));
                return $val !== '' ? $val : null;
            };

            $opSerial   = $get('op_serial_number');
            $plotNo     = $get('plot_no');
            $district   = $get('district');

            // Skip empty rows
            if ($opSerial === null && $plotNo === null && $district === null) {
                continue;
            }

            $rows[] = [
                '__row'            => $lineNum,
                'serial_no'        => $get('serial_no'),
                'page_no'          => $get('page_no'),
                'volume_no'        => $get('volume_no'),
                'reg_time'         => $get('reg_time'),
                'reg_date'         => $get('reg_date'),
                'tp_no'            => $get('tp_no'),
                'op_serial_number' => $opSerial,
                'plot_no'          => $plotNo,
                'district'         => $district,
                'allottee'         => $get('allottee'),
            ];
        }

        fclose($handle);

        Log::info('OP import CSV parsed', ['rows' => count($rows), 'total_lines' => $lineNum]);

        return $rows;
    }

    private function allocateTempFileNumberForImport(): string
    {
        // Always create a fresh sequence entry for import (avoids reusing same unused number)
        $sequenceId = DB::connection('sqlsrv')->table('temp_fileno_sequence')->insertGetId([
            'created_by' => Auth::id(),
            'is_used' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'TEMP-' . str_pad((string) $sequenceId, 5, '0', STR_PAD_LEFT);
    }

    private function buildImportedPropertyDescription(?string $plotNo, ?string $district): ?string
    {
        $plot = $this->normalizeValue($plotNo);
        $dist = $this->normalizeValue($district);

        if ($plot === null && $dist === null) {
            return null;
        }

        if ($plot !== null && $dist !== null) {
            return "Plot {$plot} - {$dist}";
        }

        return $plot !== null ? "Plot {$plot}" : $dist;
    }

    private function buildImportedRegNo(?string $serialNo, ?string $pageNo, ?string $volumeNo): ?string
    {
        $parts = array_values(array_filter([
            $this->normalizeValue($serialNo),
            $this->normalizeValue($pageNo),
            $this->normalizeValue($volumeNo),
        ]));

        if (empty($parts)) {
            return null;
        }

        return implode('/', $parts);
    }

    private function buildOpImportDuplicateKey(array $row): string
    {
        $regNo = strtoupper((string) ($this->normalizeValue($row['reg_no'] ?? null) ?? ''));
        $serial = strtoupper((string) ($this->normalizeValue($row['op_serial_number'] ?? null) ?? ''));
        $plot = strtoupper((string) ($this->normalizeValue($row['plot_no'] ?? null) ?? ''));
        $district = strtoupper((string) ($this->normalizeValue($row['district'] ?? null) ?? ''));
        $party1 = strtoupper((string) ($this->normalizeValue($row['party_1'] ?? null) ?? ''));

        return implode('|', [$regNo, $serial, $plot, $district, $party1]);
    }

    /**
     * Determine title type based on transaction type
     *
     * @param string $transactionType
     * @return string
     */
    private function determineTitleType($transactionType)
    {
        $titleTypeMap = [
            'Certificate of Occupancy' => 'C of O',
            'ST Certificate of Occupancy' => 'ST C of O',
            'SLTR Certificate of Occupancy' => 'SLTR C of O',
            'Customary Right of Occupancy' => 'Customary R of O',
            'Deed of Assignment' => 'Assignment',
            'ST Assignment' => 'ST Assignment',
            'Deed of Mortgage' => 'Mortgage',
            'Tripartite Mortgage' => 'Tripartite Mortgage',
            'Deed of Lease' => 'Lease',
            'Deed of Sub Lease' => 'Sub Lease',
            'Deed of Sub Under Lease' => 'Sub Under Lease',
            'Indenture of Lease' => 'Indenture Lease',
            'Quarry Lease' => 'Quarry Lease',
            'Private Lease' => 'Private Lease',
            'Building Lease' => 'Building Lease',
            'Tenancy Agreement' => 'Tenancy',
            'Deed of Surrender' => 'Surrender',
            'Deed of Transfer' => 'Transfer',
            'Deed of Gift' => 'Gift',
            'Power of Attorney' => 'Power of Attorney',
            'Irrevocable Power of Attorney' => 'Irrevocable POA',
            'Deed of Release' => 'Release',
            'Letter of Administration' => 'Letter of Admin',
            'Certificate of Purchase' => 'Certificate of Purchase',
            'Deed of Variation' => 'Variation',
            'Vesting Assent' => 'Vesting Assent',
            'Court Judgement' => 'Court Judgement',
            'Exchange of Letters' => 'Exchange of Letters',
            'Revocation of Power of Attorney' => 'Revocation POA',
            'Deed of Convenyence' => 'Convenyence',
            'Memorandom of Agreement' => 'MOU',
            'Deed of Partition' => 'Partition',
            'Non-European Occupational Lease' => 'Non-European Lease',
            'Deed of Revocation' => 'Revocation',
            'Deed of Reconveyance' => 'Reconveyance',
            'Customary Inhertitance' => 'Customary Inheritance',
            'Deed of Rectification' => 'Rectification',
            'Memorandum of Loss' => 'Memorandum of Loss',
            'Vesting Deed' => 'Vesting Deed',
            'ST Fragmentation' => 'ST Fragmentation',
        ];

        return $titleTypeMap[$transactionType] ?? 'Other';
    }

    /**
     * Determine the next sequential temporary file number shared between PRA and PIC tables.
     *
     * @return string
     */
    /**
     * Determine the next sequential temporary file number shared between PRA and PIC tables.
     * Uses a dedicated sequence table to ensure concurrency safety.
     *
     * @return string
     */
    private function generateTempFileNumber(): string
    {
        $prefix = 'TEMP-';

        // Atomically allocate a NEW sequence id for every call.
        //
        // BUG FIX: the previous implementation looked up an existing
        // `is_used = 0` row for the current user and reused its id, but the
        // row was only marked as used much later (or, in some flows, never).
        // Concurrent / repeated captures therefore kept seeing the same
        // "unused" row and produced duplicate TEMP-XXXXX values across
        // instrument_capture / pra / pic. We now always INSERT a fresh row
        // with `is_used = 1` so each call is guaranteed to get a unique id.
        try {
            $sequenceId = DB::connection('sqlsrv')->table('temp_fileno_sequence')->insertGetId([
                'created_by' => Auth::id(),
                'is_used'    => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $prefix . str_pad((string) $sequenceId, 5, '0', STR_PAD_LEFT);
        } catch (\Exception $e) {
            \Log::error('Failed to generate temp file number from sequence table: ' . $e->getMessage());

            // Fallback to old method if sequence table fails (e.g. missing table)
            // This ensures backward compatibility if migration didn't run
            return $this->generateTempFileNumberLegacy();
        }
    }

    /**
     * Fallback method for generating temp file numbers (Legacy Logic)
     * Used only if sequence table insertion fails
     */
    private function generateTempFileNumberLegacy(): string
    {
        $prefix = 'TEMP-';
        $maxNumber = 0;
        $tablesToCheck = ['pra', 'pic', 'instrument_capture'];

        foreach ($tablesToCheck as $table) {
            if (!Schema::connection('sqlsrv')->hasTable($table)) {
                continue;
            }

            // Check both temp_fileno AND mlsFNo columns since some records miss temp_fileno
            $colsToCheck = ['temp_fileno', 'mlsFNo'];
            foreach ($colsToCheck as $col) {
                if (!Schema::connection('sqlsrv')->hasColumn($table, $col))
                    continue;

                $latestValue = DB::connection('sqlsrv')
                    ->table($table)
                    ->whereNotNull($col)
                    ->where($col, 'like', $prefix . '%')
                    ->orderByDesc(DB::raw("TRY_CONVERT(INT, REPLACE(CAST(" . $col . " AS VARCHAR), '{$prefix}', ''))"))
                    ->orderByDesc($col)
                    ->value($col);

                if ($latestValue && preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/i', $latestValue, $matches)) {
                    $maxNumber = max($maxNumber, (int) $matches[1]);
                }
            }
        }

        $nextNumber = $maxNumber + 1;
        return $prefix . str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Check for existing property records for a file number
     * Used by Edit File Index page to determine Add vs Update button
     *
     * @param  string  $fileNumber
     * @return \Illuminate\Http\JsonResponse
     */
    public function buildFileNumberVariants(string $fileNumber): array
    {
        $normalized = strtoupper(trim($fileNumber));
        $variants = [$normalized];

        // Remove spaces
        $variants[] = str_replace(' ', '', $normalized);

        // Handle structured formats like RES-YYYY-#### or CON-RES-YYYY-####
        if (preg_match('/^(?:(CO?N?|CN|C)-?)?([A-Z]{3})-(\d{4})-(\d+)$/', $normalized, $matches)) {
            $prefix = strtoupper((string) ($matches[1] ?? ''));
            $landUse = $matches[2];
            $year = $matches[3];
            $serialRaw = $matches[4];

            $serialTrimmed = ltrim($serialRaw, '0');
            if ($serialTrimmed === '') {
                $serialTrimmed = '0';
            }

            $serialInt = (int) $serialRaw;
            $serialVariants = array_values(array_unique(array_filter([
                $serialRaw,
                $serialTrimmed,
                (string) $serialInt,
                str_pad((string) $serialInt, max(strlen($serialRaw), 4), '0', STR_PAD_LEFT),
            ], static function ($value) {
                return $value !== null && $value !== '';
            })));

            $normalizedPrefix = rtrim($prefix, '-');
            $baseWithDash = $normalizedPrefix !== ''
                ? sprintf('%s-%s-%s', $normalizedPrefix, $landUse, $year)
                : sprintf('%s-%s', $landUse, $year);
            $baseTight = $normalizedPrefix !== ''
                ? sprintf('%s%s-%s', $normalizedPrefix, $landUse, $year)
                : null;

            foreach ($serialVariants as $serialVariant) {
                $variants[] = sprintf('%s-%s', $baseWithDash, $serialVariant);

                if ($baseTight) {
                    $variants[] = sprintf('%s-%s', $baseTight, $serialVariant);
                }
            }
        }

        // Remove all separators variant
        $variants[] = str_replace('-', '', $normalized);

        // Add separator variants
        if (strpos($normalized, '-') !== false) {
            $variants[] = str_replace('-', '_', $normalized);
            $variants[] = str_replace('-', '/', $normalized);
            $variants[] = str_replace('-', ' ', $normalized);
        }

        return array_values(array_unique(array_filter($variants)));
    }

    /**
     * Check for existing property records for a file number
     * Used by Edit File Index page to determine Add vs Update button
     *
     * @param  string  $fileNumber
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkExistingRecords($fileNumber)
    {
        try {
            \Log::info('Checking existing property records for file number: ' . $fileNumber);

            $variants = $this->buildFileNumberVariants($fileNumber);
            
            \Log::info('Built variants for file number search:', $variants);

            // Search for existing property records in file_history_staging
            $existingRecords = DB::connection('sqlsrv')->table(self::PROPERTY_TABLE)
                ->where(function ($query) use ($variants) {
                    $query->whereIn('mlsFNo', $variants)
                          ->orWhereIn('kangisFileNo', $variants)
                          ->orWhereIn('NewKANGISFileno', $variants)
                          ->orWhereIn('fileno', $variants);
                })
                ->orderByRaw("COALESCE(TRY_CONVERT(DATETIME, NULLIF(transaction_date, '')), TRY_CONVERT(DATETIME, NULLIF(reg_date, '')), created_at) ASC")
                ->orderBy('id')
                ->get()
                ->toArray();

            // Search for CofO records in CofO_staging table
            $cofoRecords = DB::connection('sqlsrv')->table(self::COFO_TABLE)
                ->where(function ($query) use ($variants) {
                    $query->whereIn('mlsFNo', $variants)
                          ->orWhereIn('fileno', $variants)
                          ->orWhereIn('kangisFileNo', $variants)
                          ->orWhereIn('NewKANGISFileno', $variants);
                })
                ->orderByRaw("COALESCE(TRY_CONVERT(DATETIME, NULLIF(transaction_date, '')), created_at) ASC")
                ->orderBy('id')
                ->get()
                ->map(function ($record) {
                    $arr = (array) $record;
                    if (!isset($arr['first_party'])) {
                        $arr['first_party'] = $arr['Grantor'] ?? $arr['party_1'] ?? '';
                    }
                    if (!isset($arr['second_party'])) {
                        $arr['second_party'] = $arr['Grantee'] ?? $arr['party_2'] ?? '';
                    }
                    $arr['_source'] = 'cofo';
                    return $arr;
                })
                ->toArray();

            // Also search for OP records in pra table
            $praRecords = DB::connection('sqlsrv')->table(self::PRA_TABLE)
                ->where(function ($query) use ($variants) {
                    $query->whereIn('mlsFNo', $variants)
                          ->orWhereIn('fileno', $variants);
                })
                ->where(function ($query) {
                    $query->where('transaction_type', 'like', '%Occupancy Permit%')
                          ->orWhere('instrument_type', 'like', '%Occupancy Permit%')
                          ->orWhere('transaction_type', 'like', '%OP%');
                })
                ->orderByRaw("COALESCE(TRY_CONVERT(DATETIME, NULLIF(transaction_date, '')), created_at) ASC")
                ->orderBy('id')
                ->get()
                ->map(function ($record) {
                    // Normalize pra record to match file_history_staging field naming
                    $arr = (array) $record;
                    // Map Grantor/Grantee to first_party/second_party if needed
                    if (!isset($arr['first_party'])) {
                        $arr['first_party'] = $arr['Grantor'] ?? $arr['party_1'] ?? '';
                    }
                    if (!isset($arr['second_party'])) {
                        $arr['second_party'] = $arr['Grantee'] ?? $arr['party_2'] ?? '';
                    }
                    $arr['_source'] = 'pra';
                    return $arr;
                })
                ->toArray();

            // Merge all records; History first, then CofO, then PRA
            $allRecords = array_merge($existingRecords, $cofoRecords, $praRecords);

            \Log::info('Found property records: ' . count($existingRecords) . ' (file_history), ' . count($cofoRecords) . ' (cofo), ' . count($praRecords) . ' (pra)');

            return response()->json([
                'success' => true,
                'records' => $allRecords,
                'count' => count($allRecords),
                'variants_used' => $variants
            ]);

        } catch (\Exception $e) {
            \Log::error('Error checking existing property records: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error checking existing records',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store property record transactions from file indexing
     * Also creates/updates fileNumber table entry
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeFromIndexing(Request $request)
    {
        try {
            \Log::info('=== Property Record from File Indexing START ===');
            \Log::info('Request Data:', $request->all());

            // Validate the request - file_indexing_id is now optional
            $validator = Validator::make($request->all(), [
                'file_number' => 'required|string|max:255',
                'transactions' => 'required|array|min:1',
                'transactions.*.transaction_type' => 'required|string',
                'transactions.*.transaction_date' => 'required|date',
                'transactions.*.op_serial_number' => 'nullable|string|max:255',
                'transactions.*.record_id' => 'nullable|integer',
            ]);

            $validator->after(function ($validator) use ($request) {
                $transactions = (array) $request->input('transactions', []);

                foreach ($transactions as $index => $transaction) {
                    $transactionType = trim((string) ($transaction['transaction_type'] ?? ''));
                    $opSerialNumber = trim((string) ($transaction['op_serial_number'] ?? ''));

                    if (self::isOccupancyPermit($transactionType) && $opSerialNumber === '') {
                        $validator->errors()->add(
                            "transactions.{$index}.op_serial_number",
                            'OP serial number is required for Occupancy Permit (OP) transactions.'
                        );
                    }
                }
            });

            if ($validator->fails()) {
                \Log::error('Validation Failed:', [
                    'errors' => $validator->errors()->toArray(),
                    'input' => $request->all()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $fileNumber = $request->file_number;
            $transactions = $request->transactions;

            \Log::info('Validation passed. Processing file number: ' . $fileNumber);

            $testControl = $request->input('test_control');
            if ($testControl === null) {
                $testControl = $this->resolveTestControlFlag($fileNumber);
            }

            // Parse file number to determine which format it is
            $mlsFNo = '';
            $kangisFileNo = '';
            $newKangisFileNo = '';

            // Clean up file number (remove extra spaces)
            $fileNumber = trim($fileNumber);

            \Log::info('Parsing file number format', ['file_number' => $fileNumber]);

            // Check for different file number formats based on ACTUAL database patterns
            if (preg_match('/^(MLKN|KNML)\s*\d+/i', $fileNumber)) {
                // Old KANGIS format: MLKN 01084, MLKN 01640, KNML 08791
                $kangisFileNo = $fileNumber;
                \Log::info('Identified as old KANGIS format (MLKN/KNML)', ['kangisFileNo' => $kangisFileNo]);
            } elseif (preg_match('/^KN\s*\d+$/i', $fileNumber)) {
                // Simple KN format: KN 12, KN122 (goes to NewKANGISFileNo)
                $newKangisFileNo = $fileNumber;
                \Log::info('Identified as simple KN format', ['newKangisFileNo' => $newKangisFileNo]);
            } elseif (preg_match('/^(RES|COM|IND|AGR|CON)-/i', $fileNumber)) {
                // Modern land use formats: RES-2025-9, COM-2016-318, CON-AGR-2025-10, IND-2025-6
                // These go to mlsfNo column (based on actual database)
                $mlsFNo = $fileNumber;
                \Log::info('Identified as modern land use format (goes to mlsfNo)', ['mlsFNo' => $mlsFNo]);
            } elseif (preg_match('/^(MLS|MLSF)[\s-]/i', $fileNumber)) {
                // Actual MLS/MLSF format with space or hyphen: MLS 123, MLSF-456
                $mlsFNo = $fileNumber;
                \Log::info('Identified as traditional MLS/MLSF format', ['mlsFNo' => $mlsFNo]);
            } elseif (preg_match('/^ST-/i', $fileNumber)) {
                // New ST format: ST-{LAND_USE}-{YEAR}-{SERIAL}
                $mlsFNo = $fileNumber;
                \Log::info('Identified as ST format (goes to mlsfNo)', ['mlsFNo' => $mlsFNo]);
            } elseif (preg_match('/^KANGIS[\/-]\d{4}/i', $fileNumber)) {
                // NewKANGIS format with year: KANGIS/2025/123, KANGIS-2025-456
                $newKangisFileNo = $fileNumber;
                \Log::info('Identified as NewKANGIS format with year', ['newKangisFileNo' => $newKangisFileNo]);
            } elseif (preg_match('/^[A-Z]{2,}-[A-Z]{2,}-\d+/i', $fileNumber)) {
                // Legacy hyphenated formats: AG-RC-81-30 (goes to kangisFileNo)
                $kangisFileNo = $fileNumber;
                \Log::info('Identified as legacy hyphenated format', ['kangisFileNo' => $kangisFileNo]);
            } else {
                // Default to mlsfNo for any unrecognized format (modern default)
                $mlsFNo = $fileNumber;
                \Log::info('Using mlsfNo (modern fallback)', ['mlsFNo' => $mlsFNo]);
            }

            $response = DB::connection('sqlsrv')->transaction(function () use ($request, $fileNumber, $transactions, $mlsFNo, $kangisFileNo, $newKangisFileNo, $testControl) {
                $propertyTable = self::PROPERTY_TABLE;

                // Check if file number already exists in fileNumber table
                $fileNumberExists = null;
                if ($mlsFNo || $kangisFileNo || $newKangisFileNo) {
                    $fileNumberExists = DB::connection('sqlsrv')->table('fileNumber')
                        ->where(function ($query) use ($mlsFNo, $kangisFileNo, $newKangisFileNo) {
                            if ($mlsFNo)
                                $query->orWhere('mlsfNo', $mlsFNo);
                            if ($kangisFileNo)
                                $query->orWhere('kangisFileNo', $kangisFileNo);
                            if ($newKangisFileNo)
                                $query->orWhere('NewKANGISFileNo', $newKangisFileNo);
                        })
                        ->lockForUpdate()
                        ->first();
                }

                if (!$fileNumberExists) {
                    // Helper to safely extract a scalar string from request values that may be arrays
                    // e.g. lpkn_no arrives as [null] instead of null
                    $safeString = function ($value) {
                        if (is_array($value)) {
                            $filtered = array_filter($value, function ($v) {
                                return $v !== null && $v !== '';
                            });
                            return count($filtered) > 0 ? implode(', ', $filtered) : null;
                        }
                        return $value;
                    };

                    $fileNumberInserted = DB::connection('sqlsrv')->table('fileNumber')->insert([
                        'mlsfNo' => $mlsFNo ?: null,
                        'kangisFileNo' => $kangisFileNo ?: null,
                        'NewKANGISFileNo' => $newKangisFileNo ?: null,
                        'FileName' => $safeString($request->file_title) ?? null,
                        'location' => $safeString($request->property_description) ?? null,
                        'plot_no' => $safeString($request->plot_no) ?? null,
                        'tp_no' => $safeString($request->tp_no) ?? null,
                        'type' => 'indexing',
                        'SOURCE' => 'indexing',
                        'created_by' => Auth::id(),
                        'updated_by' => Auth::id(),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    \Log::info('fileNumber insert result: ' . ($fileNumberInserted ? 'SUCCESS' : 'FAILED'), [
                        'mlsfNo' => $mlsFNo,
                        'kangisFileNo' => $kangisFileNo,
                        'newKangisFileNo' => $newKangisFileNo,
                        'file_title' => $request->file_title
                    ]);
                } else {
                    \Log::info('Skipping fileNumber insert - record already exists', [
                        'file_number' => $fileNumber,
                        'existing_id' => $fileNumberExists->id ?? 'unknown'
                    ]);
                }

                // Only allocate Property ID if we have a permanent (non-temporary) file number
                // Check if the file number is temporary (starts with TEMP-)
                $isTempFileNumber = !empty($request->input('temp_fileno'))
                    || (stripos($fileNumber, 'TEMP-') === 0)
                    || (empty($mlsFNo) && empty($kangisFileNo) && empty($newKangisFileNo));

                $propId = null;

                if (!$isTempFileNumber) {
                    try {
                        $propId = $this->propertyIdAllocationService->allocateOrRetrievePropId(
                            $fileNumber,
                            $mlsFNo ?: null,
                            $kangisFileNo ?: null,
                            $newKangisFileNo ?: null,
                            [
                                'temp_fileno' => $request->input('temp_fileno'),
                            ]
                        );

                        \Log::info('Property ID allocated for permanent file number (indexing)', [
                            'prop_id' => $propId,
                            'file_number' => $fileNumber,
                        ]);
                    } catch (PropIdAllocationException $allocationException) {
                        return response()->json([
                            'success' => false,
                            'message' => $allocationException->getMessage(),
                        ], 422);
                    }
                } else {
                    \Log::info('Skipping Property ID allocation for temporary file number (indexing)', [
                        'file_number' => $fileNumber,
                        'temp_fileno' => $request->input('temp_fileno'),
                    ]);
                }

                $persistedRecords = [
                    'created' => [],
                    'updated' => [],
                    'cofo_created' => [],
                    'pra_created' => [],
                    'pra_updated' => [],
                ];

                // Pre-fetch CofO table columns once (used only if needed)
                $cofoTableColumns = null;
                $cofoTypeMap = [
                    'Certificate of Occupancy' => 'Legacy CofO',
                    'ST Certificate of Occupancy' => 'ST CofO',
                    'SLTR Certificate of Occupancy' => 'SLTR CofO',
                ];

                foreach ($transactions as $transaction) {
                    $registrationNumber = trim(implode('/', array_filter([
                        $transaction['serial_no'] ?? '',
                        $transaction['page_no'] ?? '',
                        $transaction['volume_no'] ?? ''
                    ]))) ?: null;

                    $recordId = isset($transaction['record_id']) && $transaction['record_id'] !== ''
                        ? (int) $transaction['record_id']
                        : null;

                    $propertyData = [
                        'prop_id' => $propId,
                        'mlsFNo' => $mlsFNo ?: $fileNumber,
                        'kangisFileNo' => $kangisFileNo ?: null,
                        'NewKANGISFileno' => $newKangisFileNo ?: null,
                        'fileno' => $fileNumber,
                        'transaction_type' => $transaction['transaction_type'] ?? null,
                        'instrument_type' => $transaction['instrument_type'] ?? ($transaction['transaction_type'] ?? null),
                        'title_type' => $this->determineTitleType($transaction['transaction_type'] ?? ''),
                        'transaction_date' => $transaction['transaction_date'] ?? null,
                        'op_serial_number' => $transaction['op_serial_number'] ?? null,
                        'regNo' => $registrationNumber,
                        'serialNo' => $transaction['serial_no'] ?? null,
                        'pageNo' => $transaction['page_no'] ?? null,
                        'volumeNo' => $transaction['volume_no'] ?? null,
                        'reg_date' => $transaction['reg_date'] ?? ($transaction['transaction_date'] ?? null),
                        'reg_time' => $transaction['reg_time'] ?? null,
                        'period' => $transaction['period'] ?? null,
                        'period_unit' => $transaction['period_unit'] ?? null,
                        'property_description' => $request->property_description ?? null,
                        'location' => $request->property_description ?? null,
                        'districtName' => $request->district ?? null,
                        'plot_no' => $request->plot_no ?? null,
                        'lgsaOrCity' => $request->lga ?? null,
                        'land_use' => $transaction['land_use'] ?? $request->input('land_use_type') ?? null,
                        'tp_no' => $request->tp_no ?? null,
                        'lpkn_no' => $request->lpkn_no ?? null,
                        'source' => 'indexing',
                        'test_control' => $testControl,
                        'updated_by' => Auth::id(),
                        'updated_at' => now(),
                    ];

                    $partyFields = $this->getPartyFieldsFromTransaction($transaction);
                    $propertyData = array_merge($propertyData, $partyFields);

                    // Filter propertyData against actual table columns to prevent SQL errors
                    $tableColumns = Schema::connection('sqlsrv')->getColumnListing($propertyTable);
                    $propertyData = array_filter($propertyData, function ($key) use ($tableColumns) {
                        return in_array($key, $tableColumns, true);
                    }, ARRAY_FILTER_USE_KEY);

                    // Flatten any array values to prevent "Array to string conversion" errors
                    // This can happen when jQuery serializes nested objects or form inputs use array names
                    // e.g. lpkn_no arrives as [null] instead of null
                    $propertyData = array_map(function ($value) {
                        if (is_array($value)) {
                            // Filter out nulls and empty strings, then join remaining values
                            $filtered = array_filter($value, function ($v) {
                                return $v !== null && $v !== '';
                            });
                            // If nothing meaningful remains, return null (not empty string)
                            return count($filtered) > 0 ? implode(', ', $filtered) : null;
                        }
                        return $value;
                    }, $propertyData);

                    // Route CofO transactions to CofO_staging table
                    $transactionType = $transaction['transaction_type'] ?? '';
                    $isCofO = in_array($transactionType, self::COFO_TRANSACTION_TYPES, true);

                    $isOP = self::isOccupancyPermit($transactionType);

                    \Log::info('Transaction routing decision', [
                        'transaction_type' => $transactionType,
                        'is_cofo' => $isCofO,
                        'is_op' => $isOP,
                        'record_id' => $recordId,
                        'target_table' => $isCofO ? self::COFO_TABLE : ($isOP ? self::PRA_TABLE : self::PROPERTY_TABLE),
                    ]);

                    if ($isCofO && !$recordId) {
                        // Lazy-load CofO columns once
                        if ($cofoTableColumns === null) {
                            $cofoTableColumns = Schema::connection('sqlsrv')->getColumnListing(self::COFO_TABLE);
                        }

                        $cofoData = $propertyData;
                        $cofoData['cofo_type'] = $cofoTypeMap[$transactionType] ?? null;
                        $cofoData['source'] = 'indexing';

                        // Remap fields that differ between tables
                        if (isset($cofoData['reg_date'])) {
                            $cofoData['transaction_date'] = $cofoData['transaction_date'] ?? $cofoData['reg_date'];
                        }
                        if (isset($cofoData['reg_time'])) {
                            $cofoData['transaction_time'] = $cofoData['reg_time'];
                        }

                        $cofoData['created_by'] = Auth::id();
                        $cofoData['created_at'] = now();

                        // Filter to only columns that exist in CofO_staging
                        $cofoData = array_filter($cofoData, function ($key) use ($cofoTableColumns) {
                            return in_array($key, $cofoTableColumns, true);
                        }, ARRAY_FILTER_USE_KEY);

                        $newCofoId = DB::connection('sqlsrv')->table(self::COFO_TABLE)
                            ->insertGetId($cofoData);

                        $persistedRecords['cofo_created'][] = $newCofoId;

                        \Log::info('Created CofO record from indexing', [
                            'id' => $newCofoId,
                            'transaction_type' => $transactionType,
                            'cofo_type' => $cofoTypeMap[$transactionType] ?? null,
                            'table' => self::COFO_TABLE,
                            'prop_id' => $propId,
                        ]);

                        continue;
                    }

                    // Update existing CofO record in CofO_staging table
                    if ($isCofO && $recordId) {
                        // Lazy-load CofO columns once
                        if ($cofoTableColumns === null) {
                            $cofoTableColumns = Schema::connection('sqlsrv')->getColumnListing(self::COFO_TABLE);
                        }

                        $cofoData = $propertyData;
                        $cofoData['cofo_type'] = $cofoTypeMap[$transactionType] ?? null;

                        // Remap fields that differ between tables
                        if (isset($cofoData['reg_date'])) {
                            $cofoData['transaction_date'] = $cofoData['transaction_date'] ?? $cofoData['reg_date'];
                        }
                        if (isset($cofoData['reg_time'])) {
                            $cofoData['transaction_time'] = $cofoData['reg_time'];
                        }

                        $cofoData['updated_by'] = Auth::id();
                        $cofoData['updated_at'] = now();

                        // Filter to only columns that exist in CofO_staging
                        $cofoData = array_filter($cofoData, function ($key) use ($cofoTableColumns) {
                            return in_array($key, $cofoTableColumns, true);
                        }, ARRAY_FILTER_USE_KEY);

                        DB::connection('sqlsrv')->table(self::COFO_TABLE)
                            ->where('id', $recordId)
                            ->update($cofoData);

                        $persistedRecords['cofo_updated'][] = $recordId;

                        \Log::info('Updated CofO record in staging from indexing', [
                            'id' => $recordId,
                            'transaction_type' => $transactionType,
                            'table' => self::COFO_TABLE,
                        ]);

                        continue;
                    }

                    // Route Occupancy Permit transactions to pra table
                    if ($isOP && !$recordId) {
                        // Lazy-load PRA columns once
                        static $praTableColumns = null;
                        if ($praTableColumns === null) {
                            $praTableColumns = Schema::connection('sqlsrv')->getColumnListing(self::PRA_TABLE);
                        }

                        $praData = $propertyData;
                        $praData['source'] = 'indexing';

                        // Map Grantor/Grantee from party fields
                        if (!empty($praData['party_1'])) {
                            $praData['Grantor'] = $praData['party_1'];
                        }
                        if (!empty($praData['party_2'])) {
                            $praData['Grantee'] = $praData['party_2'];
                        }

                        if (empty($praData['op_serial_number'])) {
                            $praData['op_serial_number'] = $transaction['op_serial_number'] ?? null;
                        }

                        $praData['created_by'] = Auth::id();
                        $praData['created_at'] = now();

                        // Filter to only columns that exist in pra
                        $praData = array_filter($praData, function ($key) use ($praTableColumns) {
                            return in_array($key, $praTableColumns, true);
                        }, ARRAY_FILTER_USE_KEY);

                        // Duplicate check
                        $opDuplicateCheck = DB::connection('sqlsrv')->table(self::PRA_TABLE)
                            ->where('created_at', '>=', now()->subMinutes(2))
                            ->where(function ($q) {
                                $q->where('transaction_type', 'like', '%Occupancy Permit%')
                                  ->orWhere('instrument_type', 'like', '%Occupancy Permit%');
                            })
                            ->where(function ($query) use ($mlsFNo, $fileNumber) {
                                $query->where('fileno', $fileNumber);
                                if ($mlsFNo) $query->orWhere('mlsFNo', $mlsFNo);
                            })
                            ->when(!empty($praData['serialNo']), function ($q) use ($praData) {
                                return $q->where('serialNo', $praData['serialNo']);
                            })
                            ->when(!empty($praData['op_serial_number']), function ($q) use ($praData) {
                                return $q->where('op_serial_number', $praData['op_serial_number']);
                            })
                            ->first();

                        if ($opDuplicateCheck) {
                            \Log::info('Duplicate OP submission detected - skipping PRA insertion', [
                                'existing_id' => $opDuplicateCheck->id,
                                'fileno' => $fileNumber,
                            ]);
                            $persistedRecords['created'][] = $opDuplicateCheck->id;
                            continue;
                        }

                        $newPraId = DB::connection('sqlsrv')->table(self::PRA_TABLE)
                            ->insertGetId($praData);

                        $persistedRecords['pra_created'][] = $newPraId;

                        \Log::info('Created OP record in PRA from indexing', [
                            'id' => $newPraId,
                            'transaction_type' => $transactionType,
                            'table' => self::PRA_TABLE,
                            'prop_id' => $propId,
                        ]);

                        continue;
                    }

                    // Update existing OP record in pra table
                    if ($isOP && $recordId) {
                        static $praTableColumnsUpdate = null;
                        if ($praTableColumnsUpdate === null) {
                            $praTableColumnsUpdate = Schema::connection('sqlsrv')->getColumnListing(self::PRA_TABLE);
                        }

                        $praData = $propertyData;

                        // Map Grantor/Grantee from party fields
                        if (!empty($praData['party_1'])) {
                            $praData['Grantor'] = $praData['party_1'];
                        }
                        if (!empty($praData['party_2'])) {
                            $praData['Grantee'] = $praData['party_2'];
                        }

                        if (empty($praData['op_serial_number'])) {
                            $praData['op_serial_number'] = $transaction['op_serial_number'] ?? null;
                        }

                        $praData['updated_by'] = Auth::id();
                        $praData['updated_at'] = now();

                        // Filter to only columns that exist in pra
                        $praData = array_filter($praData, function ($key) use ($praTableColumnsUpdate) {
                            return in_array($key, $praTableColumnsUpdate, true);
                        }, ARRAY_FILTER_USE_KEY);

                        DB::connection('sqlsrv')->table(self::PRA_TABLE)
                            ->where('id', $recordId)
                            ->update($praData);

                        $persistedRecords['pra_updated'][] = $recordId;

                        \Log::info('Updated OP record in PRA from indexing', [
                            'id' => $recordId,
                            'transaction_type' => $transactionType,
                            'table' => self::PRA_TABLE,
                        ]);

                        continue;
                    }

                    if ($recordId) {
                        // Final check: if this record came from PRA or CofO but wasn't caught by specific blocks above,
                        // we should try to update it in the correct table if possible, or default to propertyTable.
                        $source = $transaction['_source'] ?? 'file_history';
                        $targetTable = $propertyTable;
                        
                        if ($source === 'pra' || $isOP) {
                            $targetTable = self::PRA_TABLE;
                        } elseif ($source === 'cofo' || $isCofO) {
                            $targetTable = self::COFO_TABLE;
                        }

                        DB::connection('sqlsrv')->table($targetTable)
                            ->where('id', $recordId)
                            ->update($propertyData);

                        $persistedRecords['updated'][] = $recordId;

                        \Log::info('Updated property record', [
                            'id' => $recordId,
                            'table' => $targetTable,
                            'transaction_type' => $transaction['transaction_type'],
                            'prop_id' => $propId,
                        ]);
                    } else {
                        // Check for duplicate submissions (within last 2 minutes) to prevent network lag issues
                        $duplicateCheck = DB::connection('sqlsrv')->table($propertyTable)
                            ->where('created_at', '>=', now()->subMinutes(2))
                            ->where('transaction_type', $propertyData['transaction_type'])
                            ->where(function ($query) use ($mlsFNo, $kangisFileNo, $newKangisFileNo, $fileNumber) {
                                $query->where('fileno', $fileNumber);
                                if ($mlsFNo)
                                    $query->orWhere('mlsFNo', $mlsFNo);
                                if ($kangisFileNo)
                                    $query->orWhere('kangisFileNo', $kangisFileNo);
                                if ($newKangisFileNo)
                                    $query->orWhere('NewKANGISFileno', $newKangisFileNo);
                            })
                            ->when(!empty($propertyData['transaction_date']), function ($q) use ($propertyData) {
                                return $q->where('transaction_date', $propertyData['transaction_date']);
                            })
                            ->when(!empty($propertyData['serialNo']), function ($q) use ($propertyData) {
                                return $q->where('serialNo', $propertyData['serialNo']);
                            })
                            ->when(!empty($propertyData['pageNo']), function ($q) use ($propertyData) {
                                return $q->where('pageNo', $propertyData['pageNo']);
                            })
                            ->when(!empty($propertyData['volumeNo']), function ($q) use ($propertyData) {
                                return $q->where('volumeNo', $propertyData['volumeNo']);
                            })
                            ->first();

                        if ($duplicateCheck) {
                            \Log::info('Duplicate transaction submission detected - skipping insertion', [
                                'existing_id' => $duplicateCheck->id,
                                'fileno' => $fileNumber,
                                'transaction_type' => $propertyData['transaction_type']
                            ]);

                            $persistedRecords['created'][] = $duplicateCheck->id;
                            continue;
                        }

                        $propertyData['created_by'] = Auth::id();
                        $propertyData['created_at'] = now();

                        $newRecordId = DB::connection('sqlsrv')->table($propertyTable)
                            ->insertGetId($propertyData);

                        $persistedRecords['created'][] = $newRecordId;

                        \Log::info('Created property record', [
                            'id' => $newRecordId,
                            'transaction_type' => $transaction['transaction_type'],
                            'prop_id' => $propId,
                        ]);
                    }
                }

                $this->purgePraIndexingRecords(
                    [
                        'mlsFNo' => $mlsFNo ?: null,
                        'kangisFileNo' => $kangisFileNo ?: null,
                        'NewKANGISFileno' => $newKangisFileNo ?: null,
                        'fileno' => $fileNumber,
                    ],
                    $transactions,
                    array_merge($persistedRecords['pra_created'] ?? [], $persistedRecords['pra_updated'] ?? [])
                );

                $allIds = array_merge($persistedRecords['created'], $persistedRecords['updated']);
                $cofoIds = $persistedRecords['cofo_created'];
                $praIds = array_merge($persistedRecords['pra_created'] ?? [], $persistedRecords['pra_updated'] ?? []);
                $fileHistoryCount = count($allIds);
                $cofoCount = count($cofoIds);
                $praCount = count($praIds);

                // Build descriptive message
                $messageParts = [];
                if ($fileHistoryCount > 0) {
                    $messageParts[] = $fileHistoryCount . ' transaction' . ($fileHistoryCount > 1 ? 's' : '') . ' saved to File History';
                }
                if ($cofoCount > 0) {
                    $messageParts[] = $cofoCount . ' CofO transaction' . ($cofoCount > 1 ? 's' : '') . ' saved to CofO';
                }
                if ($praCount > 0) {
                    $messageParts[] = $praCount . ' OP transaction' . ($praCount > 1 ? 's' : '') . ' saved to PRA';
                }
                $message = implode(', ', $messageParts) ?: 'Property transaction details saved successfully!';

                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data' => [
                        'property_record_ids' => $allIds,
                        'created_ids' => $persistedRecords['created'],
                        'updated_ids' => $persistedRecords['updated'],
                        'cofo_ids' => $cofoIds,
                        'pra_ids' => $praIds,
                        'transaction_count' => count($transactions),
                        'file_history_count' => $fileHistoryCount,
                        'cofo_count' => $cofoCount,
                        'pra_count' => $praCount,
                        'prop_id' => $propId,
                    ]
                ]);
            });

            return $response;

        } catch (\Exception $e) {
            \Log::error('Error storing property record from indexing:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save transaction details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extract party fields from transaction data
     */
    private function getPartyFieldsFromTransaction($transaction)
    {
        // Helper to ensure party values are always strings, not arrays
        $safeStr = function ($value) {
            if (is_array($value)) {
                $filtered = array_filter($value, function ($v) {
                    return $v !== null && $v !== '';
                });
                return count($filtered) > 0 ? implode(', ', $filtered) : null;
            }
            return $value;
        };

        $partyFields = [];
        $transactionType = $safeStr($transaction['transaction_type'] ?? '');
        $firstParty = $safeStr($transaction['first_party'] ?? '');
        $secondParty = $safeStr($transaction['second_party'] ?? '');
        $coFirstParty = $safeStr($transaction['co_first_party'] ?? null);
        $thirdParty = $safeStr($transaction['third_party'] ?? $transaction['Mortgagor_2'] ?? $transaction['Surrenderor_2'] ?? $transaction['party_3'] ?? null);
        $fourthParty = $safeStr($transaction['fourth_party'] ?? $transaction['Mortgagor_3'] ?? $transaction['party_4'] ?? null);
        $fifthParty = $safeStr($transaction['fifth_party'] ?? $transaction['fourth_Party'] ?? $transaction['party_5'] ?? null);

        // Map transaction types to party field names
        $partyMapping = [
            'Deed of Assignment' => ['Assignor', 'Assignee'],
            'ST Assignment' => ['Assignor', 'Assignee'],
            'Deed of Mortgage' => ['Mortgagor', 'Mortgagee'],
            'Tripartite Mortgage' => ['Mortgagor', 'Mortgagee'],
            'Deed of Surrender' => ['Surrenderor', 'Surrenderee'],
            'Deed of Surrender and Release' => ['Surrenderor', 'Surrenderee'],
            'Deed of Sub Lease' => ['Lessor', 'Lessee'],
            'Deed of Sub Under Lease' => ['Lessor', 'Lessee'],
            'Indenture of Lease' => ['Lessor', 'Lessee'],
            'Quarry Lease' => ['Lessor', 'Lessee'],
            'Private Lease' => ['Lessor', 'Lessee'],
            'Building Lease' => ['Lessor', 'Lessee'],
            'Deed of Lease' => ['Lessor', 'Lessee'],
            'Tenancy Agreement' => ['Landlord', 'Tenant'],
            'Deed of Release' => ['Releasor', 'Releasee'],
            'Deed of Transfer' => ['Transferor', 'Transferee'],
            'Deed of Gift' => ['Donor', 'Donee'],
            'Letter of Administration' => ['Administrator', 'Beneficiary'],
            'Certificate of Purchase' => ['Vendor', 'Purchaser']
        ];

        // Always populate generic party columns
        // Rule: party_1 is first, party_2 is second, party_3 is third, party_4 is fourth, party_5 is fifth
        $partyFields['party_1'] = $firstParty;
        $partyFields['party_2'] = $secondParty;
        $partyFields['party_3'] = $thirdParty ?: $coFirstParty;
        $partyFields['party_4'] = $fourthParty;
        $partyFields['party_5'] = $fifthParty;

        if (isset($partyMapping[$transactionType])) {
            $partyFields[$partyMapping[$transactionType][0]] = $firstParty;
            $partyFields[$partyMapping[$transactionType][1]] = $secondParty;

            // Handle extended parties
            if ($transactionType === 'Deed of Surrender' || $transactionType === 'Deed of Surrender and Release') {
                if ($coFirstParty || $thirdParty) {
                    $partyFields['Surrenderor_2'] = $thirdParty ?: $coFirstParty;
                }
            } elseif ($transactionType === 'Tripartite Mortgage') {
                if ($coFirstParty || $thirdParty) {
                    $partyFields['Mortgagor_2'] = $thirdParty ?: $coFirstParty;
                }
                if ($fourthParty) {
                    $partyFields['Mortgagor_3'] = $fourthParty;
                }
                if ($fifthParty) {
                    $partyFields['fourth_Party'] = $fifthParty;
                }
            }
        } else {
            // Default to Grantor/Grantee
            $partyFields['Grantor'] = $firstParty;
            $partyFields['Grantee'] = $secondParty;
        }

        return $partyFields;
    }

    /**
     * Remove any legacy PRA rows that may have been created for indexing submissions.
     */
    private function purgePraIndexingRecords(array $fileIdentifiers, array $transactions, array $excludeIds = []): void
    {
        try {
            if (
                !Schema::connection('sqlsrv')->hasTable('pra') ||
                !Schema::connection('sqlsrv')->hasColumn('pra', 'source')
            ) {
                return;
            }

            $normalizedIdentifiers = [];
            foreach ($fileIdentifiers as $column => $value) {
                $normalized = $this->normalizeValue($value ?? null);
                if ($normalized !== null) {
                    $normalizedIdentifiers[$column] = $normalized;
                }
            }

            if (empty($normalizedIdentifiers)) {
                return;
            }

            $hasRegDateColumn = Schema::connection('sqlsrv')->hasColumn('pra', 'regDate');
            $hasVolumeColumn = Schema::connection('sqlsrv')->hasColumn('pra', 'volumeNo');

            foreach ($transactions as $transaction) {
                $query = DB::connection('sqlsrv')
                    ->table('pra')
                    ->where('source', 'indexing')
                    ->where(function ($builder) use ($normalizedIdentifiers) {
                        foreach ($normalizedIdentifiers as $column => $value) {
                            $builder->orWhere($column, $value);
                        }
                    });

                if (!empty($excludeIds)) {
                    $query->whereNotIn('id', $excludeIds);
                }

                $transactionType = $this->normalizeValue($transaction['transaction_type'] ?? null);
                if ($transactionType !== null) {
                    $query->where('transaction_type', $transactionType);
                }

                $serial = $this->normalizeValue($transaction['serial_no'] ?? null);
                if ($serial !== null) {
                    $query->where('serialNo', $serial);
                }

                if ($hasVolumeColumn) {
                    $volume = $this->normalizeValue($transaction['volume_no'] ?? null);
                    if ($volume !== null) {
                        $query->where(function ($builder) use ($volume) {
                            $builder->whereNull('volumeNo')->orWhere('volumeNo', $volume);
                        });
                    }
                }

                if ($hasRegDateColumn) {
                    $regDate = $this->normalizeValue($transaction['reg_date'] ?? null);
                    if ($regDate !== null) {
                        $query->whereDate('regDate', $regDate);
                    }
                }

                $deleted = $query->delete();

                if ($deleted > 0) {
                    Log::info('Purged PRA rows created through indexing payload', [
                        'file_identifiers' => $normalizedIdentifiers,
                        'transaction_type' => $transactionType,
                        'serial' => $serial,
                        'deleted_rows' => $deleted,
                    ]);
                }
            }
        } catch (\Throwable $exception) {
            Log::warning('Failed to purge PRA indexing rows', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function resolveTestControlFlag(string $fileNumber): ?string
    {
        $normalized = $this->normalizeValue($fileNumber);
        if ($normalized === null) {
            return null;
        }

        return DB::connection('sqlsrv')
            ->table('file_indexings')
            ->where('file_number', $normalized)
            ->orderByDesc('id')
            ->value('test_control');
    }

    protected function syncFileHistoryRecord(string $fileNumber, array $transaction, array $context): void
    {
        $transactionType = $this->normalizeValue($transaction['transaction_type'] ?? null);
        if ($transactionType === null) {
            return;
        }

        // Route OP records to pra table instead of file_history_staging
        if (self::isOccupancyPermit($transactionType)) {
            $this->syncPraRecord($fileNumber, $transaction, $context);
            return;
        }

        $serial = $this->normalizeValue($transaction['serial_no'] ?? null);
        $page = $this->normalizeValue($transaction['page_no'] ?? null) ?? $serial;
        $volume = $this->normalizeValue($transaction['volume_no'] ?? null);
        $transactionDate = $this->normalizeValue($transaction['transaction_date'] ?? null);
        $regDate = $this->normalizeValue($transaction['reg_date'] ?? null) ?? $transactionDate;
        $regTime = $this->normalizeValue($transaction['reg_time'] ?? null);

        $duplicateQuery = DB::connection('sqlsrv')->table('file_history_staging')
            ->where('mlsFNo', $fileNumber)
            ->where('transaction_type', $transactionType);

        if ($serial !== null) {
            $duplicateQuery->where('serialNo', $serial);
        }

        if ($page !== null) {
            $duplicateQuery->where('pageNo', $page);
        }

        if ($volume !== null) {
            $duplicateQuery->where('volumeNo', $volume);
        }

        if ($serial === null && $page === null && $volume === null && $transactionDate) {
            $duplicateQuery->whereDate('transaction_date', $transactionDate);
        }

        $existing = $duplicateQuery->first();

        $recordPayload = array_merge([
            'mlsFNo' => $fileNumber,
            'fileno' => $fileNumber,
            'transaction_type' => $transactionType,
            'transaction_date' => $transactionDate,
            'serialNo' => $serial,
            'pageNo' => $page,
            'volumeNo' => $volume,
            'regNo' => $this->formatRegistrationComponents($serial, $page, $volume),
            'reg_date' => $regDate,
            'reg_time' => $regTime,
            'land_use' => $context['land_use'] ?? null,
            'location' => $context['location'] ?? null,
            'plot_no' => $context['plot_no'] ?? null,
            'tp_no' => $context['tp_no'] ?? null,
            'lpkn_no' => $context['lpkn_no'] ?? null,
            'lgsaOrCity' => $context['lga'] ?? null,
            'districtName' => $context['district'] ?? null,
            'source' => 'indexing',
            'prop_id' => $context['prop_id'] ?? null,
            'test_control' => $context['test_control'] ?? null,
            'updated_at' => now(),
            'updated_by' => Auth::id(),
        ], $this->getPartyFieldsFromTransaction($transaction));

        if ($existing) {
            DB::connection('sqlsrv')
                ->table('file_history_staging')
                ->where('id', $existing->id)
                ->update($recordPayload);

            return;
        }

        $recordPayload['created_at'] = now();
        $recordPayload['created_by'] = Auth::id();

        DB::connection('sqlsrv')
            ->table('file_history_staging')
            ->insert($recordPayload);
    }

    /**
     * Sync an Occupancy Permit transaction to the pra table (instead of file_history_staging).
     */
    protected function syncPraRecord(string $fileNumber, array $transaction, array $context): void
    {
        $transactionType = $this->normalizeValue($transaction['transaction_type'] ?? null);

        $serial = $this->normalizeValue($transaction['serial_no'] ?? null);
        $page = $this->normalizeValue($transaction['page_no'] ?? null) ?? $serial;
        $volume = $this->normalizeValue($transaction['volume_no'] ?? null);
        $transactionDate = $this->normalizeValue($transaction['transaction_date'] ?? null);
        $regDate = $this->normalizeValue($transaction['reg_date'] ?? null) ?? $transactionDate;
        $regTime = $this->normalizeValue($transaction['reg_time'] ?? null);

        $duplicateQuery = DB::connection('sqlsrv')->table(self::PRA_TABLE)
            ->where('mlsFNo', $fileNumber)
            ->where(function ($q) {
                $q->where('transaction_type', 'like', '%Occupancy Permit%')
                  ->orWhere('instrument_type', 'like', '%Occupancy Permit%');
            });

        if ($serial !== null) {
            $duplicateQuery->where('serialNo', $serial);
        }

        if ($page !== null) {
            $duplicateQuery->where('pageNo', $page);
        }

        if ($volume !== null) {
            $duplicateQuery->where('volumeNo', $volume);
        }

        if ($serial === null && $page === null && $volume === null && $transactionDate) {
            $duplicateQuery->whereDate('transaction_date', $transactionDate);
        }

        $existing = $duplicateQuery->first();

        $partyFields = $this->getPartyFieldsFromTransaction($transaction);

        $recordPayload = array_merge([
            'mlsFNo' => $fileNumber,
            'fileno' => $fileNumber,
            'transaction_type' => $transactionType,
            'instrument_type' => $transactionType,
            'transaction_date' => $transactionDate,
            'serialNo' => $serial,
            'pageNo' => $page,
            'volumeNo' => $volume,
            'regNo' => $this->formatRegistrationComponents($serial, $page, $volume),
            'reg_date' => $regDate,
            'reg_time' => $regTime,
            'land_use' => $context['land_use'] ?? null,
            'location' => $context['location'] ?? null,
            'property_description' => $context['location'] ?? null,
            'plot_no' => $context['plot_no'] ?? null,
            'tp_no' => $context['tp_no'] ?? null,
            'lgsaOrCity' => $context['lga'] ?? null,
            'districtName' => $context['district'] ?? null,
            'source' => 'indexing',
            'prop_id' => $context['prop_id'] ?? null,
            'test_control' => $context['test_control'] ?? null,
            'updated_at' => now(),
            'updated_by' => Auth::id(),
        ], $partyFields);

        // Map party fields to Grantor/Grantee
        if (!empty($partyFields['party_1'])) {
            $recordPayload['Grantor'] = $partyFields['party_1'];
        }
        if (!empty($partyFields['party_2'])) {
            $recordPayload['Grantee'] = $partyFields['party_2'];
        }

        // Filter to only columns that exist in pra
        $praColumns = Schema::connection('sqlsrv')->getColumnListing(self::PRA_TABLE);
        $recordPayload = array_filter($recordPayload, function ($key) use ($praColumns) {
            return in_array($key, $praColumns, true);
        }, ARRAY_FILTER_USE_KEY);

        if ($existing) {
            DB::connection('sqlsrv')
                ->table(self::PRA_TABLE)
                ->where('id', $existing->id)
                ->update($recordPayload);

            return;
        }

        $recordPayload['created_at'] = now();
        $recordPayload['created_by'] = Auth::id();

        DB::connection('sqlsrv')
            ->table(self::PRA_TABLE)
            ->insert($recordPayload);
    }

    protected function formatRegistrationComponents(?string ...$parts): ?string
    {
        $filtered = array_values(array_filter(array_map(function ($value) {
            return $this->normalizeValue($value);
        }, $parts)));

        if (empty($filtered)) {
            return null;
        }

        return implode('/', $filtered);
    }

    private function logSubmissionResult(string $channel, bool $success, string $message, array $context = []): void
    {
        $payload = array_merge([
            'date' => now()->toDateTimeString(),
            'user_id' => Auth::id(),
        ], $context);

        $level = $success ? 'info' : 'error';

        \Log::channel($channel)->log($level, $message, $payload);
    }

    protected function normalizeValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = is_string($value) ? trim($value) : trim((string) $value);

        return $stringValue === '' ? null : $stringValue;
    }

    /**
     * Normalize instrument entries coming from the form.
     */
    private function normalizeInstrumentEntries($entries)
    {
        if (empty($entries) || !is_array($entries)) {
            return collect();
        }

        return collect($entries)->map(function ($entry) {
            return [
                'type' => isset($entry['type']) ? trim((string) $entry['type']) : '',
                'date' => $entry['date'] ?? null,
                'party_from' => isset($entry['party_from']) ? trim((string) $entry['party_from']) : '',
                'party_to' => isset($entry['party_to']) ? trim((string) $entry['party_to']) : '',
            ];
        })->filter(function ($entry) {
            return $entry['type'] !== '' || $entry['date'] || $entry['party_from'] !== '' || $entry['party_to'] !== '';
        })->values();
    }

    /**
     * Apply party columns for a specific instrument type.
     */
    private function applyInstrumentPartyValues(array &$entryData, ?string $instrumentType, ?string $from, ?string $to, array $columns): void
    {
        $map = [
            'assignment' => ['Assignor', 'Assignee'],
            'surrender' => ['Surrenderor', 'Surrenderee'],
            'subdivision' => ['Grantor', 'Grantee'],
            'merger' => ['Grantor', 'Grantee'],
            'withdrawn' => ['Grantor', 'Grantee'],
            'revoke' => ['Grantor', 'Grantee'],
        ];

        $key = strtolower((string) $instrumentType);
        $pair = $map[$key] ?? ['Grantor', 'Grantee'];

        if ($from !== null && $from !== '' && in_array($pair[0], $columns, true)) {
            $entryData[$pair[0]] = $from;
        }

        if ($to !== null && $to !== '' && in_array($pair[1], $columns, true)) {
            $entryData[$pair[1]] = $to;
        }
    }

    /**
     * Persist date columns specific to certain instrument types.
     */
    private function applyInstrumentDateColumns(array &$entryData, ?string $instrumentType, ?string $date, array $columns): void
    {
        if (!$date) {
            return;
        }

        $map = [
            'assignment' => 'assignment_date',
            'surrender' => 'surrender_date',
            'withdrawn' => 'surrender_date',
            'revoke' => 'revoked_date',
        ];

        $key = strtolower((string) $instrumentType);
        if (isset($map[$key]) && in_array($map[$key], $columns, true)) {
            $entryData[$map[$key]] = $date;
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\FileIndexing;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection; 
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class IndexedFileTableController extends Controller
{
    public function index()
    {
        $PageTitle = 'Indexed Files';
        $PageDescription = 'Browse indexed files with fast server-side pagination.';

        return view('indexed_files.index', compact('PageTitle', 'PageDescription'));
    }

    public function stats(Request $request): JsonResponse
    {
        $registry = trim((string) $request->input('registry', ''));
        $isCorrespondingFile = filter_var($request->input('is_corresponding_file', false), FILTER_VALIDATE_BOOLEAN);
        $cacheRegistry = $registry !== '' ? strtoupper($registry) : 'ALL';
        $cacheKey = $isCorrespondingFile ? "indexed_files_stats_{$cacheRegistry}_corresponding" : "indexed_files_stats_{$cacheRegistry}";

        $stats = Cache::remember($cacheKey, 60, function () use ($registry, $isCorrespondingFile) {
            $baseQuery = FileIndexing::on('sqlsrv');

            if ($registry !== '') {
                if (strtoupper($registry) === 'KANGIS') {
                    $baseQuery->whereIn('registry', ['KANGIS', 'KANGIS Registry']);
                } else {
                    $baseQuery->where('registry', $registry);
                }
            }

            if ($isCorrespondingFile) {
                $baseQuery->where('is_corresponding_file', 1);
            }

            // Apply user-level restriction for non-admins, except for KANGIS
            $currentUser = Auth::user();
            if ($currentUser) {
                $assignRole = strtolower((string) ($currentUser->assign_role ?? ''));
                $isSuperAdmin = in_array($assignRole, ['super admin', 'supper admin', 'administrator', 'admin']);

                if (!$isSuperAdmin) {
                    $registryUpper = strtoupper(trim((string) $registry));
                    if ($registryUpper !== 'KANGIS') {
                        $userName = trim(sprintf('%s %s', $currentUser->first_name ?? '', $currentUser->last_name ?? ''));
                        if ($userName === '') {
                            $userName = $currentUser->name ?? $currentUser->email ?? null;
                        }

                        if ($userName) {
                            $baseQuery->where('file_indexings.created_by', $userName);
                        }
                    }
                }
            }

            $totalIndexed = (clone $baseQuery)->count();

            $today = Carbon::today('Africa/Lagos');
            $indexedToday = (clone $baseQuery)
                ->where('created_at', '>=', $today)
                ->count();

            $uniqueRegistries = (clone $baseQuery)
                ->whereNotNull('registry')
                ->where('registry', '<>', '')
                ->distinct('registry')
                ->count('registry');

            $topLandUses = (clone $baseQuery)
                ->select('land_use_type', DB::raw('COUNT(*) as total'))
                ->groupBy('land_use_type')
                ->orderByDesc(DB::raw('COUNT(*)'))
                ->limit(4)
                ->get()
                ->map(function ($row) {
                    return [
                        'land_use_type' => $row->land_use_type ?? 'Unknown',
                        'total' => (int) $row->total,
                    ];
                });

            return [
                'total_indexed' => $totalIndexed,
                'indexed_today' => $indexedToday,
                'unique_registries' => $uniqueRegistries,
                'top_land_uses' => $topLandUses,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min($perPage, 100));
        $page = max(1, (int) $request->input('page', 1));
        $search = $this->normalizeSearch($request->input('search'));
        $direction = strtolower($request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $sortMap = [
            'tracking_id' => 'file_indexings.tracking_id',
            'shelf_location' => 'file_indexings.shelf_location',
            'registry' => 'file_indexings.registry',
            'registry_batch_no' => 'file_indexings.registry_batch_no',
            'sys_batch_no' => 'file_indexings.sys_batch_no',
            'batch_no' => 'file_indexings.batch_no',
            'group_no' => 'file_indexings.group',
            'file_number' => 'file_indexings.file_number',
            'file_title' => 'file_indexings.file_title',
            'plot_number' => 'file_indexings.plot_number',
            'created_at' => 'file_indexings.created_at',
            'indexed_at' => 'file_indexings.created_at',
            'tp_no' => 'file_indexings.tp_no',
            'lpkn_no' => 'file_indexings.lpkn_no',
            'land_use_type' => 'file_indexings.land_use_type',
            'district' => 'file_indexings.district',
            'lga' => 'file_indexings.lga',
            'general_registry' => 'file_indexings.general_registry',
            'physical_registry' => 'file_indexings.physical_registry',
            'id' => 'file_indexings.id',
        ];

        $sortInput = (string) $request->input('sort', 'id');
        $sortColumn = $sortMap[$sortInput] ?? 'file_indexings.created_at';

        $query = FileIndexing::on('sqlsrv')
            ->select([
                'file_indexings.id',
                'file_indexings.tracking_id',
                'file_indexings.shelf_location',
                'file_indexings.registry',
                'file_indexings.registry_batch_no',
                'file_indexings.sys_batch_no',
                'file_indexings.batch_no',
                DB::raw('[file_indexings].[group] as group_no'),
                'file_indexings.file_number',
                'file_indexings.file_title',
                'file_indexings.plot_number',
                'file_indexings.created_at',
                'file_indexings.tp_no',
                'file_indexings.lpkn_no',
                'file_indexings.land_use_type',
                'file_indexings.district',
                'file_indexings.lga',
                'file_indexings.general_registry',
                'file_indexings.physical_registry',
                'file_indexings.created_by',
                'file_indexings.batch_generated',
                'file_indexings.last_batch_id',
                'file_indexings.location',
                'file_indexings.temp_file_no',
                'file_indexings.kangis_fileno_placeholder',
                'file_indexings.corresponding_fileno',
            ]);

        $isCorrespondingFile = filter_var($request->input('is_corresponding_file', false), FILTER_VALIDATE_BOOLEAN);
        if ($isCorrespondingFile) {
            $query->where('file_indexings.is_corresponding_file', 1);
        }

        $registry = $request->input('registry');
        if ($registry !== null && $registry !== '') {
            $registryUpper = strtoupper(trim((string) $registry));

            // Treat 'KANGIS' as covering both 'KANGIS' and 'KANGIS Registry'
            if ($registryUpper === 'KANGIS') {
                $query->whereIn('file_indexings.registry', ['KANGIS', 'KANGIS Registry']);
                $query->addSelect(DB::raw("(SELECT TOP 1 LTRIM(RTRIM(file_number)) FROM file_indexing_links WHERE file_indexing_id = file_indexings.id AND file_number IS NOT NULL AND LTRIM(RTRIM(file_number)) <> '' ORDER BY id ASC) as related_file_no"));
            } else {
                $query->where('file_indexings.registry', $registry);
            }
        }

        if ($search !== null) {
            $like = '%' . $this->escapeLikePattern($search) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('file_indexings.file_number', 'like', $like)
                    ->orWhere('file_indexings.file_title', 'like', $like)
                    ->orWhere('file_indexings.tracking_id', 'like', $like)
                    ->orWhere('file_indexings.registry', 'like', $like)
                    ->orWhere('file_indexings.sys_batch_no', 'like', $like)
                    ->orWhere('file_indexings.batch_no', 'like', $like)
                    ->orWhere(DB::raw('[file_indexings].[group]'), 'like', $like)
                    ->orWhere('file_indexings.plot_number', 'like', $like)
                    ->orWhere('file_indexings.district', 'like', $like)
                    ->orWhere('file_indexings.lga', 'like', $like);
            });
        }

        $currentUser = Auth::user();
        if ($currentUser) {
            $assignRole = strtolower((string) ($currentUser->assign_role ?? ''));
            $isSuperAdmin = in_array($assignRole, ['super admin', 'supper admin', 'administrator', 'admin']);

            if (!$isSuperAdmin && strtoupper(trim((string) ($request->input('registry', '')))) !== 'KANGIS') { 
                $userName = trim(sprintf('%s %s', $currentUser->first_name ?? '', $currentUser->last_name ?? ''));
                if ($userName === '') {
                    $userName = $currentUser->name ?? $currentUser->email ?? null;
                }

                if ($userName) {
                    $query->where('file_indexings.created_by', $userName);
                }
            }
        }

        if ($sortColumn === 'file_indexings.created_at') {
            $query->orderBy('file_indexings.created_at', $direction)
                ->orderBy('file_indexings.updated_at', $direction)
                ->orderBy('file_indexings.id', $direction === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy($sortColumn, $direction);
            if ($sortColumn !== 'file_indexings.id') {
                $query->orderBy('file_indexings.id', 'desc');
            }
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $items = Collection::make($paginator->items());

        $indexedIds = $items->pluck('id')->map(function ($value) {
            return is_numeric($value) ? (int) $value : null;
        })->filter()->values();

        $scanningCounts = $indexedIds->isEmpty()
            ? collect()
            : DB::connection('sqlsrv')
                ->table('scannings')
                ->select('file_indexing_id', DB::raw('COUNT(*) as total'))
                ->whereIn('file_indexing_id', $indexedIds)
                ->groupBy('file_indexing_id')
                ->pluck('total', 'file_indexing_id');

        $pageTypingCounts = $indexedIds->isEmpty()
            ? collect()
            : DB::connection('sqlsrv')
                ->table('pagetypings')
                ->select('file_indexing_id', DB::raw('COUNT(*) as total'))
                ->whereIn('file_indexing_id', $indexedIds)
                ->groupBy('file_indexing_id')
                ->pluck('total', 'file_indexing_id');

        $relatedFileCounts = $indexedIds->isEmpty()
            ? collect()
            : DB::connection('sqlsrv')
                ->table('file_indexing_links')
                ->select('file_indexing_id', DB::raw('COUNT(*) as total'))
                ->whereIn('file_indexing_id', $indexedIds)
                ->groupBy('file_indexing_id')
                ->pluck('total', 'file_indexing_id');

        // Pre-fetch grouping fallbacks to avoid N+1 queries
        $fileNumbers = $items->pluck('file_number')->filter()->unique()->values();
        $groupingFallbacks = $fileNumbers->isEmpty()
            ? collect()
            : DB::connection('sqlsrv')
                ->table('grouping')
                ->whereIn('awaiting_fileno', $fileNumbers)
                ->select('awaiting_fileno', 'registry', 'registry_batch_no', 'sys_batch_no', 'mdc_batch_no')
                ->get()
                ->groupBy('awaiting_fileno')
                ->map(function ($rows) {
                    return $rows->first(); // Take first if multiple
                });

        // Pre-compute which file_numbers actually have a folder under any of the
        // EDMS registry roots, so the UI can hide the "View Files" button when empty.
        $edmsFolderMap = $this->buildEdmsFolderMap($fileNumbers);

        // Since created_by now contains names directly, no need for user lookup
        $creators = collect();

        $data = $items->map(function ($item) use ($scanningCounts, $pageTypingCounts, $creators, $groupingFallbacks, $relatedFileCounts, $edmsFolderMap) {
            $scanned = (int) ($scanningCounts->get($item->id) ?? 0);
            $typed = (int) ($pageTypingCounts->get($item->id) ?? 0);
            $hasRelatedFiles = (int) ($relatedFileCounts->get($item->id) ?? 0) > 0;

            $status = 'Indexed';
            if ($typed > 0) {
                $status = 'Typed';
            } elseif ($scanned > 0) {
                $status = 'Scanned';
            }

            $indexedAt = $item->created_at instanceof Carbon
                ? $item->created_at
                : Carbon::parse($item->created_at);

            // Use pre-fetched fallback
            $fallback = $groupingFallbacks->get($item->file_number);

            $rowData = [
                'id' => (int) $item->id,
                'tracking_id' => $item->tracking_id ?? '-',
                'shelf_location' => $item->shelf_location ?? '-',
                'registry' => $item->registry ?: 1,
                'registry_batch_no' => $item->registry_batch_no ?: '-',
                'sys_batch_no' => $item->sys_batch_no ?: '-',
                'batch_no' => $item->batch_no ?: '-',
                'group_no' => $item->group_no ?? '-',
                'file_number' => $item->file_number ?? '-',
                'file_title' => $item->file_title ?? '-',
                'plot_number' => $item->plot_number ?? '-',
                'indexed_at' => $indexedAt ? $indexedAt->format('Y-m-d H:i') : null,
                'indexed_date' => $indexedAt ? $indexedAt->toDateString() : null,
                'indexed_by' => $this->resolveCreatorName($item->created_by, $creators),
                'tp_no' => $item->tp_no ?? '-',
                'lpkn_no' => $item->lpkn_no ?? '-',
                'land_use_type' => $item->land_use_type ?? '-',
                'district' => $item->district ?? '-',
                'lga' => $item->lga ?? '-',
                'location' => $item->location ?? '-',
                'general_registry' => $item->general_registry
                    ?? \App\Models\FileIndexing::detectRegistryFromFileNumber($item->file_number)
                    ?? '-',
                'physical_registry' => $item->physical_registry ?? '-',
                'status' => $status,
                'batch_generated' => $this->normalizeBoolean($item->batch_generated),
                'last_batch_id' => $item->last_batch_id ?? null,
                'has_related_files' => $hasRelatedFiles,
                'view_url' => route('fileindex.show', ['fileindex' => $item->id]),
            ];

            // Eloquent attributes are dynamic; avoid property_exists() here.
            $rowData['related_file_no'] = $item->related_file_no ?? '-';
            $rowData['temp_file_no'] = $item->temp_file_no ?? null;
            $rowData['kangis_fileno_placeholder'] = $item->kangis_fileno_placeholder ?? null;
            $rowData['corresponding_fileno'] = $item->corresponding_fileno ?? null;

            // Flags whether scanned files exist in any EDMS registry folder
            $rowData['has_edms_files'] = (bool) ($edmsFolderMap[$item->file_number] ?? false);

            return $rowData;
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function viewList(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min($perPage, 100));
        $page = max(1, (int) $request->input('page', 1));
        $search = $this->normalizeSearch($request->input('search'));
        $direction = strtolower($request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        // Sort mapping for the new view
        $sortMap = [
            'registry' => 'file_indexings.registry',
            'file_number' => 'file_indexings.file_number',
            'file_title' => 'file_indexings.file_title',
            'current_holder' => 'file_indexings.current_holder',
            'original_holder' => 'file_indexings.original_holder',
            'plot_number' => 'file_indexings.plot_number',
            'district' => 'file_indexings.district',
            'lga' => 'file_indexings.lga',
            'general_registry' => 'file_indexings.general_registry',
            'physical_registry' => 'file_indexings.physical_registry',
            'state' => 'state', // Calculated column
            'id' => 'file_indexings.id',
        ];

        $sortInput = (string) $request->input('sort', 'id');
        $sortColumn = $sortMap[$sortInput] ?? 'file_indexings.id';

        $query = FileIndexing::on('sqlsrv')
            ->select([
                'file_indexings.id',
                'file_indexings.registry',
                'file_indexings.file_number',
                'file_indexings.file_title',
                'file_indexings.current_holder',
                'file_indexings.original_holder',
                'file_indexings.plot_number',
                'file_indexings.district',
                'file_indexings.lga',
                'file_indexings.general_registry',
                'file_indexings.physical_registry',
                'file_indexings.land_use_type',
                DB::raw("'Kano' as state"),
            ]);

        if ($search !== null) {
            $like = '%' . $this->escapeLikePattern($search) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('file_indexings.file_number', 'like', $like)
                    ->orWhere('file_indexings.file_title', 'like', $like)
                    ->orWhere('file_indexings.registry', 'like', $like)
                    ->orWhere('file_indexings.current_holder', 'like', $like)
                    ->orWhere('file_indexings.original_holder', 'like', $like)
                    ->orWhere('file_indexings.plot_number', 'like', $like)
                    ->orWhere('file_indexings.district', 'like', $like)
                    ->orWhere('file_indexings.lga', 'like', $like);
            });
        }

        $currentUser = Auth::user();
        if ($currentUser) {
            $assignRole = strtolower((string) ($currentUser->assign_role ?? ''));
            $isSuperAdmin = in_array($assignRole, ['super admin', 'supper admin', 'administrator', 'admin']);

            if (!$isSuperAdmin && strtoupper(trim((string) ($request->input('registry', '')))) !== 'KANGIS') { 
                $userName = trim(sprintf('%s %s', $currentUser->first_name ?? '', $currentUser->last_name ?? ''));
                if ($userName === '') {
                    $userName = $currentUser->name ?? $currentUser->email ?? null;
                }

                if ($userName) {
                    $query->where('file_indexings.created_by', $userName);
                }
            }
        }

        // Apply sorting
        if ($sortColumn === 'state') {
            // Constant column, no real sort impact unless mixed
            $query->orderBy('file_indexings.id', $direction === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy($sortColumn, $direction);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $items = Collection::make($paginator->items());

        $indexedIds = $items->pluck('id')->map(function ($value) {
            return is_numeric($value) ? (int) $value : null;
        })->filter()->values();

        $relatedFileCounts = $indexedIds->isEmpty()
            ? collect()
            : DB::connection('sqlsrv')
                ->table('file_indexing_links')
                ->select('file_indexing_id', DB::raw('COUNT(*) as total'))
                ->whereIn('file_indexing_id', $indexedIds)
                ->groupBy('file_indexing_id')
                ->pluck('total', 'file_indexing_id');

        $data = $items->map(function ($item) use ($relatedFileCounts) {
            $hasRelatedFiles = (int) ($relatedFileCounts->get($item->id) ?? 0) > 0;
            return [
                'id' => (int) $item->id,
                'registry' => $item->registry ?? 1,
                'file_number' => $item->file_number ?? '-',
                'file_title' => $item->file_title ?? '-',
                'current_holder' => $item->current_holder ?? '-',
                'original_holder' => $item->original_holder ?? '-',
                'plot_number' => $item->plot_number ?? '-',
                'district' => $item->district ?? '-',
                'lga' => $item->lga ?? '-',
                'general_registry' => $item->general_registry
                    ?? \App\Models\FileIndexing::detectRegistryFromFileNumber($item->file_number)
                    ?? '-',
                'physical_registry' => $item->physical_registry ?? '-',
                'land_use_type' => $item->land_use_type ?? '-',
                'state' => $item->state ?? 'Kano',
                'has_related_files' => $hasRelatedFiles,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    private function normalizeBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes'], true);
        }

        return false;
    }

    private function normalizeSearch($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function escapeLikePattern(string $value): string
    {
        return addcslashes($value, '%_[]');
    }

    private function resolveCreatorName($rawCreatedBy, Collection $creators): string
    {
        if ($rawCreatedBy === null) {
            return 'Unknown';
        }

        // Since created_by now contains names directly, just return the trimmed name
        $trimmed = trim((string) $rawCreatedBy);
        return $trimmed === '' ? 'Unknown' : $trimmed;
    }

    /**
     * Get fallback batch/registry data from grouping table
     */
    private function getGroupingFallback(?string $fileNumber): array
    {
        if (!$fileNumber) {
            return [];
        }

        $grouping = DB::connection('sqlsrv')
            ->table('grouping')
            ->where('awaiting_fileno', $fileNumber)
            ->select('registry', 'registry_batch_no', 'sys_batch_no', 'mdc_batch_no')
            ->orderByDesc('date_index')
            ->orderByDesc('id')
            ->first();

        if (!$grouping) {
            return [];
        }

        return [
            'registry' => $grouping->registry,
            'registry_batch_no' => $grouping->registry_batch_no,
            'sys_batch_no' => $grouping->sys_batch_no,
            'mdc_batch_no' => $grouping->mdc_batch_no,
        ];
    }

    public function getRelatedFiles($id): JsonResponse
    {
        try {
            $relatedFiles = DB::connection('sqlsrv')
                ->table('file_indexing_links as fil')
                ->join('file_indexings as fi', 'fil.file_indexing_id', '=', 'fi.id')
                ->where('fil.file_indexing_id', $id)
                ->select([
                    'fil.id',
                    'fil.file_indexing_id',
                    'fil.file_number',
                    'fil.file_title',
                    'fil.plot_number',
                    'fil.tp_no',
                    'fil.lpkn_no',
                    'fil.location',
                    'fil.created_by',
                    'fil.created_at',
                    'fi.file_number as main_file_number'
                ])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $relatedFiles,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load related files.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateRelatedFile(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'file_number' => 'required|string',
                'file_title' => 'required|string',
                'location' => 'nullable|string',
                'plot_number' => 'nullable|string',
                'tp_no' => 'nullable|string',
                'lpkn_no' => 'nullable|string',
            ]);

            DB::connection('sqlsrv')
                ->table('file_indexing_links')
                ->where('id', $id)
                ->update([
                    'file_number' => $validated['file_number'],
                    'file_title' => $validated['file_title'],
                    'location' => $validated['location'],
                    'plot_number' => $validated['plot_number'],
                    'tp_no' => $validated['tp_no'],
                    'lpkn_no' => $validated['lpkn_no'],
                    'updated_at' => now(),
                    'updated_by' => Auth::user()->name ?? 'System',
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Related file updated successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update related file.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function markAsDuplicate(Request $request, $id): JsonResponse
    {
        try {
            $fileId = (int) $id;
            if ($fileId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file ID.',
                ], 422);
            }

            if (!Schema::connection('sqlsrv')->hasColumn('file_indexings', 'is_duplicate')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Column is_duplicate not found on file_indexings table.',
                ], 500);
            }

            $affected = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $fileId)
                ->update([
                    'is_duplicate' => 1,
                    'updated_at' => now(),
                ]);

            if ($affected === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Indexed file not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'File marked as duplicate successfully.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark file as duplicate.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function setTempFile(Request $request, $id): JsonResponse
    {
        try {
            $fileId = (int) $id;
            if ($fileId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file ID.',
                ], 422);
            }

            $tempFileNo = trim((string) $request->input('temp_file_no', ''));
            if ($tempFileNo === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Temporary File Number is required.',
                ], 422);
            }

            // Find the file indexing record
            $fileIndexing = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $fileId)
                ->first();

            if (!$fileIndexing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Indexed file not found.',
                ], 404);
            }

            // Update file_indexings table
            DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $fileId)
                ->update([
                    'has_temp_file' => 1,
                    'temp_file_no' => $tempFileNo,
                    'updated_at' => now(),
                ]);

            // Also update fileNumber table if a matching record exists
            $fileNumber = $fileIndexing->file_number;
            if ($fileNumber) {
                $fileNumberRecord = DB::connection('sqlsrv')
                    ->table('fileNumber')
                    ->where('kangisFileNo', $fileNumber)
                    ->orWhere('mlsfNo', $fileNumber)
                    ->orWhere('NewKANGISFileNo', $fileNumber)
                    ->first();

                if ($fileNumberRecord) {
                    DB::connection('sqlsrv')
                        ->table('fileNumber')
                        ->where('id', $fileNumberRecord->id)
                        ->update([
                            'has_temp_file' => 1,
                            'temp_file_no' => $tempFileNo,
                        ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Temporary file number saved successfully.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to set temporary file number.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check whether a selected KANGIS file number (e.g. "MLKN 1") already has
     * existing KANGIS-variant siblings in file_indexings.
     * Used for the live pre-submit check in the create form.
     *
     * GET /api/kangis-placeholder/check?file_number=MLKN+1&exclude_id=123
     */
    public function checkKangisPlaceholder(Request $request): JsonResponse
    {
        $raw       = trim((string) $request->query('file_number', $request->query('value', '')));
        $excludeId = (int) $request->query('exclude_id', 0);

        if ($raw === '') {
            return response()->json(['bare_exists' => false, 'has_siblings' => false, 'count' => 0, 'siblings' => []]);
        }

        // Check whether the bare file number already exists (any record, not just
        // placeholder ones — the first indexed record won't have a placeholder set).
        $bareQuery = DB::connection('sqlsrv')
            ->table('file_indexings')
            ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = UPPER(?)', [$raw]);

        if ($excludeId > 0) {
            $bareQuery->where('id', '!=', $excludeId);
        }

        $bareExists = $bareQuery->exists();

        // Count existing _N suffixed variants (file_number = raw + '_' + digits).
        $suffixedQuery = DB::connection('sqlsrv')
            ->table('file_indexings')
            ->where('file_number', 'like', $raw . '_%');

        if ($excludeId > 0) {
            $suffixedQuery->where('id', '!=', $excludeId);
        }

        $suffixedVariants = $suffixedQuery
            ->get(['id', 'file_number', 'kangis_fileno_placeholder', 'kangis_fileno_resolved'])
            ->filter(function ($row) use ($raw) {
                $suffix = substr(trim((string) $row->file_number), strlen($raw) + 1);
                return ctype_digit($suffix) && $suffix !== '';
            })->values();

        $suffixedCount = $suffixedVariants->count();

        // Determine which collision case this is so the frontend shows the right message.
        // Case A: no bare, no suffixed  → first record, will be saved bare (no suffix)
        // Case B: bare exists, count=0  → first collision: bare→_1, new→_2
        // Case C: bare gone, count>0   → subsequent: new→_(count+1)
        // Case D: bare+count>0          → unusual: new→_(count+1)
        $firstCollision = $bareExists && $suffixedCount === 0;

        return response()->json([
            'bare_exists'     => $bareExists,
            'has_siblings'    => $bareExists || $suffixedCount > 0,
            'count'           => $suffixedCount,
            'first_collision' => $firstCollision,
            'siblings'        => $suffixedVariants->map(fn($r) => [
                'id'          => $r->id,
                'file_number' => $r->file_number,
                'placeholder' => $r->kangis_fileno_placeholder,
                'resolved'    => $r->kangis_fileno_resolved,
            ])->values(),
        ]);
    }

    /**
     * Build a map of file_number => true for any file_number that has a
     * matching folder under one of the EDMS registry roots. Cheap because
     * we only scandir() each registry root once per request and reuse a
     * static cache for the lifetime of the process where possible.
     *
     * @param  \Illuminate\Support\Collection<int,string>  $fileNumbers
     * @return array<string,bool>
     */
    private function buildEdmsFolderMap($fileNumbers): array
    {
        if (!$fileNumbers || $fileNumbers->isEmpty()) {
            return [];
        }

        $resolvePath = function (string $path): string {
            return function_exists('file_storage_path') ? file_storage_path($path) : storage_path($path);
        };

        // Standard EDMS registry roots
        $registries = ['Cadastral_Registry', 'SLTR_Registry', 'DCIV_Registry', 'KANGIS_Registry', 'Lands_Registry'];
        $registryRoots = [];
        foreach ($registries as $registry) {
            $root = realpath($resolvePath('app/public/EDMS/UPLOAD/' . $registry));
            if ($root && is_dir($root)) {
                $registryRoots[] = $root;
            }
        }

        $map = [];
        foreach ($fileNumbers as $fn) {
            $key = trim((string) $fn);
            if ($key === '') {
                continue;
            }
            
            // Instead of scanning the entire root once, check if the specific 
            // file number folder exists in any of the registry roots.
            // Much faster for paginated results (e.g. 20-50 checks vs 50,000+).
            $found = false;
            foreach ($registryRoots as $root) {
                if (is_dir($root . DIRECTORY_SEPARATOR . $key)) {
                    $found = true;
                    break;
                }
            }
            $map[$key] = $found;
        }

        return $map;
    }

    /**
     * List files inside the EDMS Cadastral_Registry folder for a given indexed file.
     * Path: storage/app/public/EDMS/UPLOAD/Cadastral_Registry/{fileNumber}/**
     */
    public function getEdmsFiles($id): JsonResponse
    {
        try {
            $fileId = (int) $id;
            if ($fileId <= 0) {
                return response()->json(['success' => false, 'message' => 'Invalid ID.'], 422);
            }

            $row = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $fileId)
                ->select(['id', 'file_number'])
                ->first();

            if (!$row) {
                return response()->json(['success' => false, 'message' => 'Record not found.'], 404);
            }

            $fileNumber = trim((string) ($row->file_number ?? ''));
            if ($fileNumber === '') {
                return response()->json(['success' => true, 'has_files' => false, 'files' => [], 'file_number' => null]);
            }

            // Allow the caller to specify which registry folder to look in
            $allowedFolders = ['Cadastral_Registry', 'SLTR_Registry', 'DCIV_Registry', 'KANGIS_Registry', 'Lands_Registry'];
            $requestedFolder = request()->query('folder', 'Cadastral_Registry');
            $registryFolder = in_array($requestedFolder, $allowedFolders, true) ? $requestedFolder : 'Cadastral_Registry';

            // Use file_storage_path() to honour the dedicated STORAGE_PATH (.env)
            // because the storage_path() override in app/Helper/helper.php is
            // shadowed by Laravel's own helpers.php at autoload time.
            $resolveStoragePath = function (string $path): string {
                return function_exists('file_storage_path')
                    ? file_storage_path($path)
                    : storage_path($path);
            };

            $baseRoot  = realpath($resolveStoragePath('app/public/EDMS/UPLOAD/' . $registryFolder));
            if (!$baseRoot) {
                return response()->json(['success' => true, 'has_files' => false, 'files' => [], 'file_number' => $fileNumber]);
            }

            $folderPath  = $baseRoot . DIRECTORY_SEPARATOR . $fileNumber;
            $realFolder  = realpath($folderPath);

            // Security: prevent path traversal
            if (!$realFolder || strncmp($realFolder, $baseRoot, strlen($baseRoot)) !== 0) {
                return response()->json(['success' => true, 'has_files' => false, 'files' => [], 'file_number' => $fileNumber]);
            }

            $storagePublicRoot = realpath($resolveStoragePath('app/public'));
            $files = [];
            $this->scanEdmsFolder($realFolder, $baseRoot, $storagePublicRoot, $files);

            return response()->json([
                'success'     => true,
                'has_files'   => !empty($files),
                'file_number' => $fileNumber,
                'files'       => $files,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to load files.'], 500);
        }
    }

    /**
     * Recursively scan a folder for viewable files (images + PDFs).
     */
    private function scanEdmsFolder(string $dir, string $baseRoot, string $storagePublicRoot, array &$files): void
    {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'tif', 'tiff', 'bmp', 'webp', 'pdf'];
        $entries  = @scandir($dir) ?: [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fullPath = $dir . DIRECTORY_SEPARATOR . $entry;
            $realPath = realpath($fullPath);

            // Security: stay inside base root
            if (!$realPath || strncmp($realPath, $baseRoot, strlen($baseRoot)) !== 0) {
                continue;
            }

            if (is_dir($realPath)) {
                $this->scanEdmsFolder($realPath, $baseRoot, $storagePublicRoot, $files);
            } else {
                $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed, true)) {
                    continue;
                }

                $relative = ltrim(str_replace('\\', '/', substr($realPath, strlen($storagePublicRoot))), '/');
                $files[] = [
                    'name' => $entry,
                    'url'  => asset('storage/' . $relative),
                    'type' => in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'tif', 'tiff', 'bmp', 'webp'], true) ? 'image' : 'pdf',
                    'ext'  => $ext,
                ];
            }
        }
    }
}

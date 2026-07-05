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
use Illuminate\Support\Facades\Log;

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
                $isSuperAdmin = in_array($assignRole, ['super admin', 'supper admin', 'administrator', 'admin', 'editor']);

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
                'file_indexings.pp_lands_fileno',
                'file_indexings.pp_lands_matching',
                'file_indexings.pp_lands_date_matched',
                'file_indexings.pp_lands_time_matched',
                'file_indexings.mls_file_no',
                'file_indexings.kangis_file_no',
                'file_indexings.new_kangis_file_no',
                'file_indexings.related_fileno',
                'file_indexings.dciv_status',
                'file_indexings.dciv_fileno',
                'file_indexings.dciv_reason',
                'file_indexings.latitude',
                'file_indexings.longitude',
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
                    ->orWhere('file_indexings.temp_file_no', 'like', $like)
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
            $isSuperAdmin = in_array($assignRole, ['super admin', 'supper admin', 'administrator', 'admin', 'editor']);

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

        // Pre-fetch grouping fallbacks to avoid N+1 queries using display or temporary file number
        $fileNumbers = $items->map(function ($item) {
            $displayFileNo = $item->file_number;
            if (empty($displayFileNo) || trim($displayFileNo) === '' || trim($displayFileNo) === '-') {
                if (!empty($item->temp_file_no) && trim($item->temp_file_no) !== '' && trim($item->temp_file_no) !== '-' && strtoupper(trim($item->temp_file_no)) !== 'NONE') {
                    $displayFileNo = $item->temp_file_no;
                }
            }
            return $displayFileNo;
        })->filter()->unique()->values();

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

        // Pre-compute which file_numbers are flagged as duplicates (present in
        // duplicate_fileno), so the UI can enable the "Duplicate Call-up" action.
        $duplicateSet = $fileNumbers->isEmpty()
            ? collect()
            : DB::connection('sqlsrv')
                ->table('duplicate_fileno')
                ->whereIn('file_number', $fileNumbers)
                ->distinct()
                ->pluck('file_number')
                ->map(fn ($v) => trim((string) $v))
                ->flip();

        // Since created_by now contains names directly, no need for user lookup
        $creators = collect();

        $data = $items->map(function ($item) use ($scanningCounts, $pageTypingCounts, $creators, $groupingFallbacks, $relatedFileCounts, $edmsFolderMap, $duplicateSet) {
            $scanned = (int) ($scanningCounts->get($item->id) ?? 0);
            $typed = (int) ($pageTypingCounts->get($item->id) ?? 0);
            $hasRelatedFilesFromLinks = (int) ($relatedFileCounts->get($item->id) ?? 0) > 0;

            // Check if there are related files in the JSON column
            $jsonRelated = null;
            if (!empty($item->related_fileno)) {
                $jsonRelated = json_decode($item->related_fileno, true);
            }
            $hasRelatedFilesFromJson = !empty($jsonRelated) && is_array($jsonRelated);

            $hasRelatedFiles = $hasRelatedFilesFromLinks || $hasRelatedFilesFromJson;

            $relatedFileDisplay = '-';
            if ($hasRelatedFilesFromJson && count($jsonRelated) > 0) {
                $relatedFileDisplay = $jsonRelated[0];
                if (count($jsonRelated) > 1) {
                    $relatedFileDisplay .= ' (+' . (count($jsonRelated) - 1) . ')';
                }
            } elseif ($hasRelatedFilesFromLinks) {
                // We don't have the first link number easily here without another query or mapping, 
                // so we'll let the JS handle the 'View' button or show a generic count.
                $relatedFileDisplay = 'Linked Records';
            }

            $status = 'Indexed';
            if ($typed > 0) {
                $status = 'Typed';
            } elseif ($scanned > 0) {
                $status = 'Scanned';
            }

            $indexedAt = $item->created_at instanceof Carbon
                ? $item->created_at
                : Carbon::parse($item->created_at);

            // Use display file number with fallback to temp file number
            $displayFileNo = $item->file_number;
            $isTempFallback = false;
            if (empty($displayFileNo) || trim($displayFileNo) === '' || trim($displayFileNo) === '-') {
                if (!empty($item->temp_file_no) && trim($item->temp_file_no) !== '' && trim($item->temp_file_no) !== '-' && strtoupper(trim($item->temp_file_no)) !== 'NONE') {
                    $displayFileNo = $item->temp_file_no;
                    $isTempFallback = true;
                }
            }

            // Use pre-fetched fallback
            $fallback = $groupingFallbacks->get($displayFileNo);

            $rowData = [
                'id' => (int) $item->id,
                'tracking_id' => $item->tracking_id ?? '-',
                'shelf_location' => $item->shelf_location ?? '-',
                'registry' => $item->registry ?: 1,
                'registry_batch_no' => $item->registry_batch_no ?: '-',
                'sys_batch_no' => $item->sys_batch_no ?: '-',
                'batch_no' => $item->batch_no ?: '-',
                'group_no' => $item->group_no ?? '-',
                'file_number' => !empty($displayFileNo) ? $displayFileNo : '-',
                'is_temp_fallback' => $isTempFallback,
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
                    ?? \App\Models\FileIndexing::detectRegistryFromFileNumber($displayFileNo)
                    ?? '-',
                'physical_registry' => $item->physical_registry ?? '-',
                'latitude' => $item->latitude !== null ? (float) $item->latitude : null,
                'longitude' => $item->longitude !== null ? (float) $item->longitude : null,
                'status' => $status,
                'batch_generated' => $this->normalizeBoolean($item->batch_generated),
                'last_batch_id' => $item->last_batch_id ?? null,
                'has_related_files' => $hasRelatedFiles,
                'view_url' => route('fileindex.show', ['fileindex' => $item->id]),
                'related_file_display' => $relatedFileDisplay,
            ];

            // Eloquent attributes are dynamic; avoid property_exists() here.
            $rowData['related_file_no_json'] = $item->related_fileno ?? null;
            $rowData['related_file_no'] = $item->related_file_no ?? '-';
            $rowData['temp_file_no'] = $item->temp_file_no ?? null;
            $rowData['kangis_fileno_placeholder'] = $item->kangis_fileno_placeholder ?? null;
            $rowData['corresponding_fileno'] = $item->corresponding_fileno ?? null;
            $rowData['pp_lands_fileno'] = $item->pp_lands_fileno ?? null;
            $rowData['pp_lands_matching'] = (int) ($item->pp_lands_matching ?? 0);
            $rowData['pp_lands_date_matched'] = $item->pp_lands_date_matched ?? null;
            $rowData['pp_lands_time_matched'] = $item->pp_lands_time_matched ?? null;
            $rowData['mls_file_no'] = $item->mls_file_no ?? null;
            $rowData['kangis_file_no'] = $item->kangis_file_no ?? null;
            $rowData['new_kangis_file_no'] = $item->new_kangis_file_no ?? null;

            // Flags whether scanned files exist in any EDMS registry folder
            $rowData['has_edms_files'] = (bool) ($edmsFolderMap[$displayFileNo] ?? false);

            // Flags whether this file number is in duplicate_fileno (enables Call-up)
            $rowData['has_duplicate'] = $duplicateSet->has(trim((string) $displayFileNo));

            // DCIV investigation flag: 1 when this file is referenced as a related
            // file by a DCIV record. Reason + DCIV file number are shown when set.
            $rowData['dciv_status'] = (int) ($item->dciv_status ?? 0);
            $rowData['dciv_fileno'] = $item->dciv_fileno ?? null;
            $rowData['dciv_reason'] = $item->dciv_reason ?? null;

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
                'file_indexings.related_fileno',
                'file_indexings.temp_file_no',
                DB::raw("'Kano' as state"),
            ]);

        if ($search !== null) {
            $like = '%' . $this->escapeLikePattern($search) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('file_indexings.file_number', 'like', $like)
                    ->orWhere('file_indexings.temp_file_no', 'like', $like)
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
            $isSuperAdmin = in_array($assignRole, ['super admin', 'supper admin', 'administrator', 'admin', 'editor']);

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
            $hasRelatedFilesFromLinks = (int) ($relatedFileCounts->get($item->id) ?? 0) > 0;

            $jsonRelated = null;
            if (!empty($item->related_fileno)) {
                $jsonRelated = json_decode($item->related_fileno, true);
            }
            $hasRelatedFilesFromJson = !empty($jsonRelated) && is_array($jsonRelated);
            $hasRelatedFiles = $hasRelatedFilesFromLinks || $hasRelatedFilesFromJson;

            $relatedFileDisplay = '-';
            if ($hasRelatedFilesFromJson && count($jsonRelated) > 0) {
                $relatedFileDisplay = $jsonRelated[0];
                if (count($jsonRelated) > 1) {
                    $relatedFileDisplay .= ' (+' . (count($jsonRelated) - 1) . ')';
                }
            } elseif ($hasRelatedFilesFromLinks) {
                $relatedFileDisplay = 'Linked Records';
            }

            $displayFileNo = $item->file_number;
            $isTempFallback = false;
            if (empty($displayFileNo) || trim($displayFileNo) === '' || trim($displayFileNo) === '-') {
                if (!empty($item->temp_file_no) && trim($item->temp_file_no) !== '' && trim($item->temp_file_no) !== '-' && strtoupper(trim($item->temp_file_no)) !== 'NONE') {
                    $displayFileNo = $item->temp_file_no;
                    $isTempFallback = true;
                }
            }

            return [
                'id' => (int) $item->id,
                'registry' => $item->registry ?? 1,
                'file_number' => !empty($displayFileNo) ? $displayFileNo : '-',
                'is_temp_fallback' => $isTempFallback,
                'file_title' => $item->file_title ?? '-',
                'current_holder' => $item->current_holder ?? '-',
                'original_holder' => $item->original_holder ?? '-',
                'plot_number' => $item->plot_number ?? '-',
                'district' => $item->district ?? '-',
                'lga' => $item->lga ?? '-',
                'general_registry' => $item->general_registry
                    ?? \App\Models\FileIndexing::detectRegistryFromFileNumber($displayFileNo)
                    ?? '-',
                'physical_registry' => $item->physical_registry ?? '-',
                'land_use_type' => $item->land_use_type ?? '-',
                'state' => $item->state ?? 'Kano',
                'has_related_files' => $hasRelatedFiles,
                'related_file_display' => $relatedFileDisplay,
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
            // 1. Fetch links from the file_indexing_links table (usually children/subdivisions)
            $relatedLinks = DB::connection('sqlsrv')
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
                    'fi.file_number as main_file_number',
                    'fi.temp_file_no as main_temp_file_no'
                ])
                ->get()
                ->toArray();

            $relatedLinks = array_map(function ($link) {
                $linkArr = (array) $link;
                $mainFn = $linkArr['main_file_number'] ?? '';
                $mainIsTempFallback = false;
                if (empty($mainFn) || trim($mainFn) === '' || trim($mainFn) === '-') {
                    $mainTemp = $linkArr['main_temp_file_no'] ?? '';
                    if (!empty($mainTemp) && trim($mainTemp) !== '' && trim($mainTemp) !== '-' && strtoupper(trim($mainTemp)) !== 'NONE') {
                        $linkArr['main_file_number'] = $mainTemp;
                        $mainIsTempFallback = true;
                    }
                }
                $linkArr['main_is_temp_fallback'] = $mainIsTempFallback;
                return $linkArr;
            }, $relatedLinks);

            // 2. Fetch the main record to check for parent links in the JSON column
            $mainRecord = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $id)
                ->select(['file_number', 'related_fileno', 'file_title', 'temp_file_no'])
                ->first();

            $finalResults = $relatedLinks;

            if ($mainRecord && !empty($mainRecord->related_fileno)) {
                $rawRelated = trim($mainRecord->related_fileno);
                $parents = json_decode($rawRelated, true);

                // If it's not valid JSON array, treat it as a plain string (legacy format)
                if (!is_array($parents)) {
                    // Check if it's a simple string like "FILE/NO" or "FILE/NO, FILE/NO2"
                    if (str_contains($rawRelated, ',')) {
                        $parents = array_map('trim', explode(',', $rawRelated));
                    } else {
                        $parents = [$rawRelated];
                    }
                }

                if (is_array($parents)) {
                    // Fetch all titles for these parent numbers in one query
                    $parentTitles = DB::connection('sqlsrv')
                        ->table('file_indexings')
                        ->whereIn('file_number', $parents)
                        ->pluck('file_title', 'file_number')
                        ->toArray();

                    $mainFileNumber = $mainRecord->file_number;
                    $mainIsTempFallback = false;
                    if (empty($mainFileNumber) || trim($mainFileNumber) === '' || trim($mainFileNumber) === '-') {
                        if (!empty($mainRecord->temp_file_no) && trim($mainRecord->temp_file_no) !== '' && trim($mainRecord->temp_file_no) !== '-' && strtoupper(trim($mainRecord->temp_file_no)) !== 'NONE') {
                            $mainFileNumber = $mainRecord->temp_file_no;
                            $mainIsTempFallback = true;
                        }
                    }

                    foreach ($parents as $parentNo) {
                        if (empty($parentNo) || $parentNo === '[]' || $parentNo === '-')
                            continue;

                        // Avoid duplicates if already in links
                        $exists = false;
                        foreach ($relatedLinks as $link) {
                            $linkFn = is_object($link) ? ($link->file_number ?? '') : ($link['file_number'] ?? '');
                            if (strtoupper(trim($linkFn)) === strtoupper(trim($parentNo))) {
                                $exists = true;
                                break;
                            }
                        }

                        if (!$exists) {
                            $finalResults[] = [
                                'id' => 'json_' . md5($parentNo),
                                'file_indexing_id' => $id,
                                'file_number' => $parentNo,
                                'file_title' => $parentTitles[$parentNo] ?? 'Related File',
                                'plot_number' => '-',
                                'tp_no' => '-',
                                'lpkn_no' => '-',
                                'location' => '-',
                                'created_by' => 'System',
                                'created_at' => null,
                                'main_file_number' => !empty($mainFileNumber) ? $mainFileNumber : '-',
                                'main_is_temp_fallback' => $mainIsTempFallback,
                                'is_json_parent' => true
                            ];
                        }
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => $finalResults,
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

    public function updateCoordinates(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
            ]);

            $fileIndexing = FileIndexing::on('sqlsrv')->findOrFail($id);
            $fileIndexing->latitude = $validated['latitude'] ?? null;
            $fileIndexing->longitude = $validated['longitude'] ?? null;
            $fileIndexing->save();

            return response()->json([
                'success' => true,
                'message' => 'Coordinates updated successfully.',
                'data' => [
                    'latitude' => $fileIndexing->latitude,
                    'longitude' => $fileIndexing->longitude,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update coordinates.',
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

            $hasTransaction = $request->has('has_transaction') ? (int) filter_var($request->input('has_transaction'), FILTER_VALIDATE_BOOLEAN) : null;

            // Update file_indexings table
            $updateData = [
                'has_temp_file' => 1,
                'temp_file_no' => $tempFileNo,
                'updated_at' => now(),
            ];

            if ($hasTransaction !== null) {
                $updateData['has_transaction'] = $hasTransaction;
            }

            DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $fileId)
                ->update($updateData);

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
                    $fnUpdateData = [
                        'has_temp_file' => 1,
                        'temp_file_no' => $tempFileNo,
                    ];
                    if ($hasTransaction !== null) {
                        $fnUpdateData['has_transaction'] = $hasTransaction;
                    }

                    DB::connection('sqlsrv')
                        ->table('fileNumber')
                        ->where('id', $fileNumberRecord->id)
                        ->update($fnUpdateData);
                }
            }

            $updatedFile = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $fileId)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Temporary file number and transaction status saved successfully.',
                'data' => $updatedFile,
            ]);
        } catch (\Throwable $e) {
            //Log the error
            Log::error('Error setting temporary file number: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to set temporary file number.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Match an indexed file to a correspondence file number.
     * Sets is_corresponding_file = 1 and stores the chosen corresponding_fileno.
     *
     * POST /api/indexed-files/{id}/match-correspondence
     */
    public function matchCorrespondence(Request $request, $id): JsonResponse
    {
        try {
            $fileId = (int) $id;
            if ($fileId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file ID.',
                ], 422);
            }

            $correspondingFileNo = trim((string) $request->input('corresponding_fileno', ''));
            if ($correspondingFileNo === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Correspondence File Number is required.',
                ], 422);
            }

            $exists = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $fileId)
                ->exists();

            if (!$exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Indexed file not found.',
                ], 404);
            }

            DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $fileId)
                ->update([
                    'is_corresponding_file' => 1,
                    'corresponding_fileno'  => $correspondingFileNo,
                    'updated_at'            => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => "Correspondence file {$correspondingFileNo} matched successfully.",
            ]);
        } catch (\Throwable $e) {
            Log::error('Error matching correspondence file: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to match correspondence file.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the correspondence match from an indexed file.
     * Clears is_corresponding_file and corresponding_fileno (e.g. mistaken match).
     *
     * POST /api/indexed-files/{id}/unmatch-correspondence
     */
    public function unmatchCorrespondence(Request $request, $id): JsonResponse
    {
        try {
            $fileId = (int) $id;
            if ($fileId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file ID.',
                ], 422);
            }

            $affected = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $fileId)
                ->update([
                    'is_corresponding_file' => 0,
                    'corresponding_fileno'  => null,
                    'updated_at'            => now(),
                ]);

            if ($affected === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Indexed file not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Correspondence match removed successfully.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Error removing correspondence match: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove correspondence match.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Match an indexed (Land) file to a Physical Planning shadow file number.
     * Stores pp_lands_fileno + sets pp_lands_matching = 1 with the match date/time
     * on file_indexings, and mirrors the match flag/timestamps onto the fileNumber
     * record so the Lands matching module reflects the match.
     *
     * POST /api/indexed-files/{id}/match-physical-planning
     */
    public function matchPhysicalPlanning(Request $request, $id): JsonResponse
    {
        try {
            $fileId = (int) $id;
            if ($fileId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file ID.',
                ], 422);
            }

            $ppLandsFileNo = trim((string) $request->input('pp_lands_fileno', ''));
            if ($ppLandsFileNo === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Physical Planning File Number is required.',
                ], 422);
            }

            $file = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $fileId)
                ->first(['id', 'file_number']);

            if (!$file) {
                return response()->json([
                    'success' => false,
                    'message' => 'Indexed file not found.',
                ], 404);
            }

            $now = now();

            DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $fileId)
                ->update([
                    'pp_lands_fileno'        => $ppLandsFileNo,
                    'pp_lands_matching'      => 1,
                    'pp_lands_date_matched'  => $now->toDateString(),
                    'pp_lands_time_matched'  => $now->toTimeString(),
                    'updated_at'             => $now,
                ]);

            // Mirror the match onto the fileNumber record (matching-only architecture)
            $landFileNo = trim((string) ($file->file_number ?? ''));
            if ($landFileNo !== '') {
                DB::connection('sqlsrv')
                    ->table('fileNumber')
                    ->where('mlsfNo', $landFileNo)
                    ->update([
                        'pp_lands_matching'     => 1,
                        'pp_lands_date_matched' => $now->toDateString(),
                        'pp_lands_time_matched' => $now->toTimeString(),
                    ]);
            }

            return response()->json([
                'success' => true,
                'message' => "Physical Planning file {$ppLandsFileNo} matched successfully.",
            ]);
        } catch (\Throwable $e) {
            Log::error('Error matching Physical Planning file: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to match Physical Planning file.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the Physical Planning match from an indexed file.
     * Clears pp_lands_matching / pp_lands_fileno and the match timestamps.
     *
     * POST /api/indexed-files/{id}/unmatch-physical-planning
     */
    public function unmatchPhysicalPlanning(Request $request, $id): JsonResponse
    {
        try {
            $fileId = (int) $id;
            if ($fileId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file ID.',
                ], 422);
            }

            $file = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $fileId)
                ->first(['id', 'file_number']);

            if (!$file) {
                return response()->json([
                    'success' => false,
                    'message' => 'Indexed file not found.',
                ], 404);
            }

            $now = now();

            DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $fileId)
                ->update([
                    'pp_lands_fileno'        => null,
                    'pp_lands_matching'      => 0,
                    'pp_lands_date_matched'  => null,
                    'pp_lands_time_matched'  => null,
                    'updated_at'             => $now,
                ]);

            $landFileNo = trim((string) ($file->file_number ?? ''));
            if ($landFileNo !== '') {
                DB::connection('sqlsrv')
                    ->table('fileNumber')
                    ->where('mlsfNo', $landFileNo)
                    ->update([
                        'pp_lands_matching'     => 0,
                        'pp_lands_date_matched' => null,
                        'pp_lands_time_matched' => null,
                    ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Physical Planning match removed successfully.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Error removing Physical Planning match: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove Physical Planning match.',
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
        $raw = trim((string) $request->query('file_number', $request->query('value', '')));
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
            ->where('file_number', 'like', str_replace('_', '[_]', $raw) . '[_]%');

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
            'bare_exists' => $bareExists,
            'has_siblings' => $bareExists || $suffixedCount > 0,
            'count' => $suffixedCount,
            'first_collision' => $firstCollision,
            'siblings' => $suffixedVariants->map(fn($r) => [
                'id' => $r->id,
                'file_number' => $r->file_number,
                'placeholder' => $r->kangis_fileno_placeholder,
                'resolved' => $r->kangis_fileno_resolved,
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

            $baseRoot = realpath($resolveStoragePath('app/public/EDMS/UPLOAD/' . $registryFolder));
            if (!$baseRoot) {
                return response()->json(['success' => true, 'has_files' => false, 'files' => [], 'file_number' => $fileNumber]);
            }

            $folderPath = $baseRoot . DIRECTORY_SEPARATOR . $fileNumber;
            $realFolder = realpath($folderPath);

            // Security: prevent path traversal
            if (!$realFolder || strncmp($realFolder, $baseRoot, strlen($baseRoot)) !== 0) {
                return response()->json(['success' => true, 'has_files' => false, 'files' => [], 'file_number' => $fileNumber]);
            }

            $storagePublicRoot = realpath($resolveStoragePath('app/public'));
            $files = [];
            $this->scanEdmsFolder($realFolder, $baseRoot, $storagePublicRoot, $files);

            return response()->json([
                'success' => true,
                'has_files' => !empty($files),
                'file_number' => $fileNumber,
                'files' => $files,
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
        $entries = @scandir($dir) ?: [];

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
                    'url' => asset('storage/' . $relative),
                    'type' => in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'tif', 'tiff', 'bmp', 'webp'], true) ? 'image' : 'pdf',
                    'ext' => $ext,
                ];
            }
        }
    }

    public function updateKangisPlaceholder(Request $request, $id): JsonResponse
    {
        try {
            $fileId = (int) $id;
            if ($fileId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file ID.',
                ], 422);
            }

            $placeholder = trim((string) $request->input('placeholder', ''));
            if ($placeholder === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Placeholder is required.',
                ], 422);
            }

            $fileIndexing = \Illuminate\Support\Facades\DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $fileId)
                ->first();

            if (!$fileIndexing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Indexed file not found.',
                ], 404);
            }

            $affected = \Illuminate\Support\Facades\DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('id', $fileId)
                ->update([
                    'kangis_fileno_placeholder' => $placeholder,
                    'updated_at' => now(),
                ]);

            // Also update kangis_grouping table if matching record exists
            $fileNumber = $fileIndexing->file_number;
            if ($fileNumber) {
                // Strip suffix if any (e.g. KNML 1_2 -> KNML 1)
                $baseFileNumber = preg_replace('/_\d+$/', '', $fileNumber);

                \Illuminate\Support\Facades\DB::connection('sqlsrv')
                    ->table('kangis_grouping')
                    ->where('kangis_awaiting_fileno', $baseFileNumber)
                    ->update([
                        'kangis_fileno_placeholder' => $placeholder,
                        'updated_at' => now(),
                    ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'KANGIS placeholder updated successfully.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update placeholder.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function findFile(Request $request): JsonResponse
    {
        try {
            $fileNumber = trim((string) $request->query('file_number', ''));
            if ($fileNumber === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'File number is required.',
                ], 422);
            }

            $file = \Illuminate\Support\Facades\DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('file_number', $fileNumber)
                ->orWhere('kangis_fileno_placeholder', $fileNumber)
                ->first();

            if (!$file) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $file,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Search failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

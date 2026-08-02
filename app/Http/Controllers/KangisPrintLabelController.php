<?php

namespace App\Http\Controllers;

use App\Models\FileIndexing;
use App\Models\KangisRackShelfLabel;
use App\Models\KangisPrintLabelBatch;
use App\Models\KangisPrintLabelBatchItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class KangisPrintLabelController extends Controller
{
    const RACK_SHELF_CAPACITY = 50;
    const MAX_BATCH_SELECTION = 500;

    /** Batch Index "all statuses except pending" sentinel. */
    const STATUS_ANY = 'any';

    const PREFIXES = ['KN', 'KNML', 'MNKL', 'MLKN', 'KNGP'];

    // -------------------------------------------------------------------------
    // Public endpoints
    // -------------------------------------------------------------------------

    public function index(Request $request)
    {
        $recentBatches = KangisPrintLabelBatch::with(['creator'])
            ->withCount('batchItems')
            ->where('status', '!=', KangisPrintLabelBatch::STATUS_PENDING)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

            
        return view('kangis_printlabel.index', compact('recentBatches'));
    }

    /**
     * Return the list of allowed KANGIS prefixes with a file count each.
     */
    public function getPrefixes()
    {
        try {
            $result = [];
            foreach (self::PREFIXES as $prefix) {
                $cfg = $this->groupingConfig($prefix);
                // Count ready-to-label files from the prefix's grouping table.
                $count = DB::connection('sqlsrv')
                    ->table($cfg['table'])
                    ->where($cfg['awaiting'], 'like', $prefix . '%')
                    ->when($cfg['has_is_indexed'], function ($q) {
                        $q->where('is_indexed', 1);
                    })
                    ->count();

                $result[] = [
                    'prefix' => $prefix,
                    'label'  => $prefix,
                    'count'  => $count,
                ];
            }

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            Log::error('kangis-printlabel.getPrefixes', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Suggest the next unprocessed sys_batch_no for the given prefix.
     */
    public function getNextRangeForPrefix(Request $request)
    {
        $prefix = $this->validatePrefix($request->input('prefix', ''));
        if (!$prefix) {
            return response()->json(['success' => false, 'message' => 'Invalid prefix.'], 422);
        }

        try {
            $cfg = $this->groupingConfig($prefix);

            // Find the highest registry_batch_no whose files already appear in our batch items
            $maxBatched = DB::connection('sqlsrv')
                ->table($cfg['table'] . ' as g')
                ->join('kangis_print_label_batch_items as i', 'i.file_number', '=', 'g.' . $cfg['awaiting'])
                ->where('g.' . $cfg['awaiting'], 'like', $prefix . '%')
                ->max('g.registry_batch_no');

            $nextBatchNo = $maxBatched ? (int)$maxBatched + 1 : 1;

            // Verify the suggested batch actually exists in the grouping table
            $exists = DB::connection('sqlsrv')
                ->table($cfg['table'])
                ->where($cfg['awaiting'], 'like', $prefix . '%')
                ->where('registry_batch_no', $nextBatchNo)
                ->exists();

            return response()->json([
                'success' => true,
                'data' => [
                    'prefix'        => $prefix,
                    'next_batch_no' => $nextBatchNo,
                    'exists'        => $exists,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('kangis-printlabel.getNextRangeForPrefix', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Fetch file_indexings records for a given prefix (matched on kangis_fileno_placeholder).
     */
    public function getAvailableFiles(Request $request)
    {
        try {
            $prefix = $this->validatePrefix($request->input('prefix', ''));
            if (!$prefix) {
                return response()->json(['success' => false, 'message' => 'Invalid prefix.'], 422);
            }

            $registryBatchNo = $request->input('registry_batch_no'); // Can be a string like "1,2,3" or single integer
            $search          = trim((string) $request->input('search', ''));
            $excludeAssigned = filter_var($request->input('exclude_assigned', false), FILTER_VALIDATE_BOOLEAN);

            $cfg = $this->groupingConfig($prefix);

            // Normalise column names via aliases so the downstream mapping is table-agnostic.
            $query = DB::connection('sqlsrv')
                ->table($cfg['table'])
                ->where($cfg['awaiting'], 'like', $prefix . '%')
                ->when($cfg['has_is_indexed'], function ($q) {
                    // Only load files that are ready (indexed) but not yet batched for labels.
                    $q->where('is_indexed', 1);
                })
                ->select([
                    'id',
                    'tracking_id',
                    $cfg['awaiting'] . ' as awaiting_fileno',
                    'registry_batch_no',
                    $cfg['secondary'] . ' as secondary_fileno',
                ]);

            if ($registryBatchNo) {
                if (str_contains($registryBatchNo, ',')) {
                    $batches = array_filter(array_map('trim', explode(',', $registryBatchNo)));
                    $query->whereIn('registry_batch_no', $batches);
                } else {
                    $query->where('registry_batch_no', trim($registryBatchNo));
                }
            }

            if ($search !== '') {
                $query->where(function ($q) use ($search, $cfg) {
                    $q->where($cfg['awaiting'], 'like', '%' . $search . '%')
                      ->orWhere('tracking_id', 'like', '%' . $search . '%');
                });
            }

            $rows = $query->orderBy($cfg['awaiting'])->get();

            // For kangis_grouping, prefer the KANGIS file-no placeholder from file_indexings
            // as the secondary number when available. kn_grouping has no placeholder concept,
            // so its kn_fileno (aliased to secondary_fileno) stands as the secondary number.
            if ($rows->isNotEmpty() && $cfg['has_placeholder']) {
                $fileNumbers = $rows->pluck('awaiting_fileno')->filter()->unique()->values();
                if ($fileNumbers->isNotEmpty()) {
                    $indexings = DB::connection('sqlsrv')
                        ->table('file_indexings')
                        ->whereIn('file_number', $fileNumbers)
                        ->where(function ($q) {
                            $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                        })
                        ->whereNotNull('kangis_fileno_placeholder')
                        ->where('kangis_fileno_placeholder', '!=', '')
                        ->pluck('kangis_fileno_placeholder', 'file_number')
                        ->all();

                    foreach ($rows as $r) {
                        $fn = $r->awaiting_fileno;
                        if (isset($indexings[$fn])) {
                            $r->secondary_fileno = $indexings[$fn];
                        }
                    }
                }
            }

            if ($rows->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'files'   => [],
                        'missing' => [],
                        'total'   => 0,
                        'prefix'  => $prefix,
                        'message' => 'No records found in ' . $cfg['table'] . ' for this prefix/batch.',
                    ],
                ]);
            }

            // Mark which records are already in an existing label batch (safety check)
            $alreadyBatched = DB::connection('sqlsrv')
                ->table('kangis_print_label_batch_items')
                ->whereIn('kangis_grouping_id', $rows->pluck('id')->all())
                ->pluck('kangis_grouping_id')
                ->flip()
                ->all();

            $mapped = $rows->map(function ($r) use ($alreadyBatched) {
                return [
                    'id'                        => $r->id,
                    'file_number'               => $r->awaiting_fileno,
                    'secondary_file_number'     => $r->secondary_fileno,
                    'kangis_fileno_placeholder' => $r->secondary_fileno,
                    'tracking_id'               => $r->tracking_id,
                    'registry_batch_no'         => $r->registry_batch_no,
                    'already_batched'           => isset($alreadyBatched[$r->id]),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'files'   => $mapped->values(),
                    'missing' => [],
                    'total'   => $mapped->count(),
                    'prefix'  => $prefix,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('kangis-printlabel.getAvailableFiles', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Fetch kangis_grouping rows by manually entered file numbers (comma/space/newline separated).
     * Returns found records and a list of missing file numbers.
     */
    public function fetchManualFiles(Request $request)
    {
        try {
            $raw = (string) $request->input('file_numbers', '');
            if (empty(trim($raw))) {
                return response()->json(['success' => true, 'data' => ['files' => [], 'missing' => []]]);
            }

            // Split on commas / newlines / semicolons ONLY — never on internal spaces:
            // KANGIS awaiting file numbers contain a space (e.g. "KNML 1"), so splitting
            // on whitespace would shatter each number into non-matching fragments.
            $parts = preg_split('/[,;\r\n]+/', $raw);
            $parts = array_filter(array_map('trim', $parts), fn($p) => $p !== '');
            $parts = array_values(array_unique($parts));
            $parts = array_slice($parts, 0, 100); // limit to 100

            if (empty($parts)) {
                return response()->json(['success' => true, 'data' => ['files' => [], 'missing' => []]]);
            }

            // Manual Registry Override matches the entered values against any of the
            // file's identifiers (awaiting file no, tracking id, or the KANGIS file no
            // placeholder) and ignores the is_indexed gate, so a file can be resolved
            // and reprinted regardless of its current batch state.
            $rows = DB::connection('sqlsrv')
                ->table('kangis_grouping')
                ->where(function ($q) use ($parts) {
                    $q->whereIn('kangis_awaiting_fileno', $parts)
                      ->orWhereIn('tracking_id', $parts)
                      ->orWhereIn('kangis_fileno_placeholder', $parts);
                })
                ->select(['id', 'tracking_id', 'kangis_awaiting_fileno', 'registry_batch_no', 'kangis_fileno_placeholder'])
                ->get();

            // An entered value counts as "found" when it matches any identifier on a row.
            $matchedValues = [];
            foreach ($rows as $r) {
                foreach ([$r->kangis_awaiting_fileno, $r->tracking_id, $r->kangis_fileno_placeholder] as $v) {
                    if ($v !== null && $v !== '') {
                        $matchedValues[] = (string) $v;
                    }
                }
            }
            $missing = array_values(array_filter($parts, function ($p) use ($matchedValues) {
                return !in_array($p, $matchedValues, true);
            }));

            // Fill placeholders from file_indexings where available
            $fileNumbers = $rows->pluck('kangis_awaiting_fileno')->filter()->unique()->values();
            if ($fileNumbers->isNotEmpty()) {
                $indexings = DB::connection('sqlsrv')
                    ->table('file_indexings')
                    ->whereIn('file_number', $fileNumbers)
                    ->where(function ($q) {
                        $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                    })
                    ->whereNotNull('kangis_fileno_placeholder')
                    ->where('kangis_fileno_placeholder', '!=', '')
                    ->pluck('kangis_fileno_placeholder', 'file_number')
                    ->all();

                foreach ($rows as $r) {
                    $fn = $r->kangis_awaiting_fileno;
                    if (isset($indexings[$fn])) {
                        $r->kangis_fileno_placeholder = $indexings[$fn];
                    }
                }
            }

            $mapped = $rows->map(function ($r) {
                return [
                    'id' => $r->id,
                    'file_number' => $r->kangis_awaiting_fileno,
                    'secondary_file_number' => $r->kangis_fileno_placeholder,
                    'tracking_id' => $r->tracking_id,
                    'registry_batch_no' => $r->registry_batch_no,
                ];
            })->values();

            return response()->json(['success' => true, 'data' => ['files' => $mapped, 'missing' => $missing]]);
        } catch (\Throwable $e) {
            Log::error('kangis-printlabel.fetchManualFiles', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get rack/shelf label capacity status.
     */
    public function getRackLabelStatus(Request $request)
    {
        try {
            $fullLabel = strtoupper(trim((string) $request->input('full_label', '')));
            if ($fullLabel === '') {
                return response()->json(['success' => false, 'message' => 'full_label is required.'], 422);
            }

            $label = $this->resolveRackLabelRecord($fullLabel);

            if (!$label) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'full_label' => $fullLabel,
                        'counter'    => 0,
                        'capacity'   => self::RACK_SHELF_CAPACITY,
                        'remaining'  => self::RACK_SHELF_CAPACITY,
                        'is_full'    => false,
                        'exists'     => false,
                    ],
                ]);
            }

            $counter   = max(0, (int) ($label->counter ?? 0));
            $remaining = max(0, self::RACK_SHELF_CAPACITY - $counter);

            return response()->json([
                'success' => true,
                'data' => [
                    'label_id'   => $label->id,
                    'full_label' => $label->full_label ?? $fullLabel,
                    'rack'       => $label->rack,
                    'shelf'      => $label->shelf,
                    'counter'    => $counter,
                    'capacity'   => self::RACK_SHELF_CAPACITY,
                    'remaining'  => $remaining,
                    'is_full'    => $counter >= self::RACK_SHELF_CAPACITY,
                    'assigned'   => $label->assigned,
                    'exists'     => true,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('kangis-printlabel.getRackLabelStatus', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Create a label batch for the selected KANGIS files on a given shelf.
     */
    public function createBatch(Request $request)
    {
        try {
            // Manual Registry Override prints labels for typed-in file numbers that may
            // span any prefix, so the prefix selection is optional in that mode.
            $manualOverride = filter_var($request->input('manual_override'), FILTER_VALIDATE_BOOLEAN);

            $validated = $request->validate([
                'prefix'            => ($manualOverride ? 'nullable' : 'required') . '|string' . ($manualOverride ? '' : '|in:' . implode(',', self::PREFIXES)),
                'manual_override'   => 'nullable|boolean',
                'registry_batch_no' => 'nullable|string',
                'file_ids'          => 'required|array|min:1|max:' . self::MAX_BATCH_SELECTION,
                'file_ids.*'        => 'integer|min:1',
                'full_label'        => 'required|string|max:20',
                'rack_primary'      => 'required|string|max:5',
                'shelf_number'      => 'required|integer|min:1|max:9999',
                'rack_secondary'    => 'nullable|string|max:5',
            ]);

            // Label prefix used on batch numbers / QR payloads. In manual override with
            // no prefix chosen, fall back to a generic marker.
            $prefix        = trim((string) ($validated['prefix'] ?? '')) ?: ($manualOverride ? 'OVERRIDE' : '');
            $fileIds       = array_unique(array_map('intval', $validated['file_ids']));
            $fullLabel     = strtoupper(trim($validated['full_label']));
            $rackPrimary   = strtoupper(trim($validated['rack_primary']));
            $rackSecondary = isset($validated['rack_secondary']) ? strtoupper(trim($validated['rack_secondary'])) : null;
            $shelfNumber   = (int) $validated['shelf_number'];

            // Manual override always sources kangis_grouping (its file ids come from there);
            // otherwise the prefix decides the grouping table (KN -> kn_grouping).
            $cfg = $manualOverride ? $this->groupingConfig('KANGIS') : $this->groupingConfig($prefix);

            $result = DB::connection('sqlsrv')->transaction(function () use (
                $prefix, $fileIds, $fullLabel, $rackPrimary, $rackSecondary, $shelfNumber, $manualOverride, $cfg
            ) {
                $now = Carbon::now();

                // Fetch files from the grouping table to group them by their registry_batch_no.
                // In manual override mode the selected files may span multiple prefixes,
                // so match strictly by id and skip the prefix filter.
                $filesFromDb = DB::connection('sqlsrv')
                    ->table($cfg['table'])
                    ->whereIn('id', $fileIds)
                    ->when(!$manualOverride, function ($q) use ($prefix, $cfg) {
                        $q->where($cfg['awaiting'], 'like', $prefix . '%');
                    })
                    ->get();

                if ($filesFromDb->isEmpty()) {
                    throw ValidationException::withMessages(['file_ids' => 'No valid matching records found in ' . $cfg['table'] . '.']);
                }

                // Group by registry_batch_no and order ascending so the lowest batch
                // maps to the starting shelf the user selected, then increments (B1, B2, ...).
                $groups = $filesFromDb->groupBy('registry_batch_no')
                    ->sortBy(function ($groupFiles, $key) {
                        return is_numeric($key) ? (int) $key : PHP_INT_MAX;
                    }, SORT_REGULAR);
                
                $createdBatchesData = [];
                $allLabelItems = [];
                $currentFullLabel = $fullLabel;
                $currentRackPrimary = $rackPrimary;
                $currentRackSecondary = $rackSecondary;
                $currentShelfNumber = $shelfNumber;

                // Anchor = lowest loaded registry batch. It maps to the shelf the user selected;
                // every other batch is offset from it so gaps in the loaded batches are preserved
                // (e.g. start B1 at batch 25 -> batch 32 lands on B8, not B2).
                $anchorBatch = $groups->keys()
                    ->filter(function ($k) { return is_numeric($k); })
                    ->map(function ($k) { return (int) $k; })
                    ->min();

                foreach ($groups as $regBatchNo => $groupFiles) {
                    $regBatchNoStr = (string)$regBatchNo;

                    // Derive the shelf from the file's registry batch range, preserving gaps:
                    //   shelf = selected start shelf + (this batch - lowest loaded batch)
                    // so batch 25 -> B1, 32 -> B8, regardless of which batches were skipped.
                    if ($anchorBatch !== null && is_numeric($regBatchNoStr)) {
                        $currentShelfNumber = max(1, $shelfNumber + ((int) $regBatchNoStr - $anchorBatch));
                        $currentFullLabel   = $currentRackPrimary . $currentShelfNumber;
                    }

                    // 2. Generate Batch Number
                    $batchNumber = 'KANGIS-' . $prefix . '-' . $now->format('YmdHis') . ($groups->count() > 1 ? '-' . $regBatchNoStr : '');

                    // 3. Create the Batch Record
                    $batch = KangisPrintLabelBatch::create([
                        'batch_number'   => $batchNumber,
                        'prefix'         => $prefix,
                        'sys_batch_no'   => $regBatchNoStr,
                        'status'         => KangisPrintLabelBatch::STATUS_PENDING,
                        'full_label'     => $currentFullLabel,
                        'rack_primary'   => $currentRackPrimary,
                        'rack_secondary' => $currentRackSecondary,
                        'shelf_number'   => $currentShelfNumber,
                        'created_by'     => auth()->id(),
                        'updated_by'     => auth()->id(),
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ]);

                    // 3. Resolve/Update Shelf Label Counter
                    $shelfLabelRecord = $this->resolveRackLabelRecord($currentFullLabel, $currentRackPrimary, $currentRackSecondary, $currentShelfNumber, false, true);
                    if ($shelfLabelRecord) {
                        $count = $groupFiles->count();
                        $shelfLabelRecord->update([
                            'assigned'   => 'KANGIS-' . $prefix,
                            'counter'    => DB::raw("CAST(CAST(ISNULL(NULLIF(counter, ''), '0') AS INT) + {$count} AS NVARCHAR(MAX))"),
                            'status'     => 'Occupied',
                            'updated_at' => $now,
                        ]);
                    }

                    $items = $groupFiles->values()->map(function ($file, $index) use ($batch, $currentFullLabel, $prefix, $now, $cfg) {
                        $fileNumber = $file->{$cfg['awaiting']};
                        $placeholder = $cfg['has_placeholder']
                            ? ($file->kangis_fileno_placeholder ?? null)
                            : ($file->{$cfg['fileno']} ?? null);
                        $qrPayload  = [
                            'file_number'  => $fileNumber,
                            'tracking_id'  => $file->tracking_id ?? $fileNumber,
                            'prefix'       => $prefix,
                            'shelf_label'  => $currentFullLabel,
                            'generated_at' => $now->toIso8601String(),
                        ];

                        return [
                            'batch_id'           => $batch->id,
                            'kangis_grouping_id' => $file->id,
                            'file_number'        => $fileNumber,
                            'prefix'             => $prefix,
                            'file_title'         => $placeholder,
                            'plot_number'        => null,
                            'district'           => null,
                            'lga'                => null,
                            'land_use_type'      => null,
                            'shelf_location'     => $currentFullLabel,
                            'label_position'     => $index + 1,
                            'qr_code_data'       => json_encode($qrPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'barcode_data'       => $fileNumber,
                            'is_printed'         => false,
                            'created_at'         => $now,
                            'updated_at'         => $now,
                        ];
                    })->all();

                    $this->insertBatchItemsInChunks($items);

                    $batch->update([
                        'status'          => KangisPrintLabelBatch::STATUS_GENERATED,
                        'generated_count' => $groupFiles->count(),
                        'updated_by'      => auth()->id(),
                    ]);

                    // Mark the consumed rows. kn_grouping has no is_indexed column, so only
                    // touch it where the table supports it.
                    $consumeUpdate = ['updated_at' => $now, 'updated_by' => auth()->id()];
                    if ($cfg['has_is_indexed']) {
                        $consumeUpdate['is_indexed'] = 0;
                    }
                    DB::connection('sqlsrv')
                        ->table($cfg['table'])
                        ->whereIn('id', $groupFiles->pluck('id')->all())
                        ->update($consumeUpdate);

                    // Gather label items formatted for frontend print preview
                    $groupFiles->values()->each(function ($file, $index) use (&$allLabelItems, $currentFullLabel, $prefix, $cfg) {
                        $fileNumber = $file->{$cfg['awaiting']};
                        $placeholder = $cfg['has_placeholder']
                            ? ($file->kangis_fileno_placeholder ?? null)
                            : ($file->{$cfg['fileno']} ?? null);
                        $allLabelItems[] = [
                            'id'                    => $file->id,
                            'file_number'           => $fileNumber,
                            'secondary_file_number' => $placeholder,
                            'file_title'            => $placeholder,
                            'plot_number'           => null,
                            'district'              => null,
                            'lga'                   => null,
                            'land_use_type'         => null,
                            'shelf_location'        => $currentFullLabel,
                            'shelf_value'           => $currentFullLabel,
                            'shelf_label'           => $currentFullLabel,
                            'tracking_id'           => $file->tracking_id ?? $fileNumber,
                            'qr_value'              => $file->tracking_id ?? $fileNumber,
                            'label_position'        => $index + 1,
                            'prefix'                => $prefix,
                        ];
                    });

                    $createdBatchesData[] = [
                        'batch' => $batch->fresh(),
                        'file_count' => $groupFiles->count()
                    ];
                }

                return [
                    'batches_data' => $createdBatchesData,
                    'label_items'  => $allLabelItems,
                ];
            });

            $batchesData = $result['batches_data'];
            $first = $batchesData[0];

            return response()->json([
                'success' => true,
                'message' => count($batchesData) > 1 ? count($batchesData) . ' separate batches created successfully.' : 'Label batch created successfully.',
                'data' => [
                    'batch_id'     => $first['batch']->id,
                    'batch_number' => $first['batch']->batch_number,
                    'file_count'   => count($fileIds),
                    'label_items'  => $result['label_items'],
                ],
            ]);

        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('kangis-printlabel.createBatch', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Error creating batch: ' . $e->getMessage()], 500);
        }
    }

    /**
     * List generated batches (kangis only).
     */
    public function getBatches(Request $request)
    {
        try {
$query = KangisPrintLabelBatch::with(['creator'])
            ->withCount('batchItems')
            ->where('status', '!=', KangisPrintLabelBatch::STATUS_PENDING);

            if ($request->filled('search')) {
                $query->where('batch_number', 'like', '%' . $request->search . '%');
            }

            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $perPage = min(100, max(1, (int) $request->get('per_page', 20)));
            $batches = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data'    => $batches->items(),
                'pagination' => [
                    'current_page' => $batches->currentPage(),
                    'last_page'    => $batches->lastPage(),
                    'per_page'     => $batches->perPage(),
                    'total'        => $batches->total(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('kangis-printlabel.getBatches', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Return a batch with all label items ready for printing.
     */
    public function getBatchForPrinting($batchId)
    {
        try {
            $batch = KangisPrintLabelBatch::with(['batchItems'])->findOrFail($batchId);

            // The grouping table follows the batch's prefix (KN -> kn_grouping).
            $cfg = $this->groupingConfig($batch->prefix);

            $kangisGroupingIds = $batch->batchItems->pluck('kangis_grouping_id')->filter()->unique()->values();

            $groupingDetails = collect();
            if ($kangisGroupingIds->isNotEmpty()) {
                $groupingDetails = DB::connection('sqlsrv')
                    ->table($cfg['table'])
                    ->whereIn('id', $kangisGroupingIds)
                    ->get()
                    ->keyBy('id');

                // Normalise the awaiting-file column so downstream lookups are table-agnostic.
                foreach ($groupingDetails as $r) {
                    $r->awaiting_fileno = $r->{$cfg['awaiting']} ?? null;
                    if (!$cfg['has_placeholder']) {
                        $r->kangis_fileno_placeholder = $r->{$cfg['fileno']} ?? null;
                    }
                }

                if ($cfg['has_placeholder']) {
                    $fileNumbers = $groupingDetails->pluck('awaiting_fileno')->filter()->unique()->values();
                    if ($fileNumbers->isNotEmpty()) {
                        $indexings = DB::connection('sqlsrv')
                            ->table('file_indexings')
                            ->whereIn('file_number', $fileNumbers)
                            ->where(function ($q) {
                                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                            })
                            ->whereNotNull('kangis_fileno_placeholder')
                            ->where('kangis_fileno_placeholder', '!=', '')
                            ->pluck('kangis_fileno_placeholder', 'file_number')
                            ->all();

                        foreach ($groupingDetails as $r) {
                            $fn = $r->awaiting_fileno;
                            if (isset($indexings[$fn])) {
                                $r->kangis_fileno_placeholder = $indexings[$fn];
                            }
                        }
                    }
                }
            }

            $files = $batch->batchItems->map(function ($item) use ($groupingDetails) {
                $details = $groupingDetails->get($item->kangis_grouping_id);

                $qrData = [];
                if (!empty($item->qr_code_data)) {
                    $decoded = json_decode($item->qr_code_data, true);
                    if (is_array($decoded)) {
                        $qrData = $decoded;
                    }
                }

                $trackingId = $qrData['tracking_id'] ?? optional($details)->tracking_id ?? $item->file_number;
                $shelfValue = $item->shelf_location ?? optional($details)->shelf_location ?? null;

                return [
                    'id'                    => $item->kangis_grouping_id,
                    'batch_item_id'         => $item->id,
                    'file_number'           => $item->file_number,
                    'secondary_file_number' => $item->file_title, // Placeholder is stored here
                    'file_title'            => $item->file_title,
                    'plot_number'           => $item->plot_number,
                    'district'              => $item->district,
                    'lga'                   => $item->lga,
                    'land_use_type'         => $item->land_use_type,
                    'shelf_location'        => $shelfValue,
                    'shelf_value'           => $shelfValue,
                    'shelf_label'           => $shelfValue,
                    'tracking_id'           => $trackingId,
                    'qr_code_data'          => $qrData,
                    'qr_value'              => $trackingId,
                    'label_position'        => $item->label_position,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'batch' => $batch,
                    'files' => $files->values(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('kangis-printlabel.getBatchForPrinting', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Batch Index for QR-coded files, grouped/ordered by Shelf/Rack.
     *
     * One row per generated label batch: registry batch no, prefix, the serial
     * range of the file numbers it contains, and the rack/shelf it sits on.
     */
    public function getBatchIndex(Request $request)
    {
        try {
            $rows = $this->buildBatchIndexRows($request);

            return response()->json([
                'success' => true,
                'data' => [
                    'rows'    => $rows,
                    'total'   => count($rows),
                    'filters' => $this->batchIndexFilters($request),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('kangis-printlabel.getBatchIndex', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Printable Batch Index sheet (A4 portrait, two signature blocks).
     */
    public function printBatchIndex(Request $request)
    {
        $rows    = $this->buildBatchIndexRows($request);
        $filters = $this->batchIndexFilters($request);

        return view('kangis_printlabel.batch-index-print', [
            'rows'      => $rows,
            'filters'   => $filters,
            'autoPrint' => filter_var($request->input('auto_print', false), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    /**
     * Normalised Batch Index filter set (shared by the JSON and print endpoints).
     */
    private function batchIndexFilters(Request $request): array
    {
        $status = strtolower(trim((string) $request->input('status', '')));
        if (!in_array($status, [self::STATUS_ANY, KangisPrintLabelBatch::STATUS_GENERATED, KangisPrintLabelBatch::STATUS_PRINTED, KangisPrintLabelBatch::STATUS_COMPLETED], true)) {
            $status = self::STATUS_ANY;
        }

        // Registry Batch No mirrors the Select Files panel: a single number or a
        // comma-separated list ("1" or "1,4,5"). Never split on whitespace.
        $batchNos = array_values(array_unique(array_filter(
            array_map('trim', preg_split('/[,;\r\n]+/', (string) $request->input('registry_batch_no', ''))),
            fn($v) => $v !== '' && is_numeric($v)
        )));

        $rack          = strtoupper(trim((string) $request->input('rack', '')));
        $rackSecondary = strtoupper(trim((string) $request->input('rack_secondary', '')));
        $shelf         = is_numeric($request->input('shelf')) ? (int) $request->input('shelf') : null;

        return [
            'prefix'            => $this->validatePrefix((string) $request->input('prefix', '')),
            'registry_batch_no' => array_map('intval', $batchNos),
            'rack'              => $rack !== '' ? $rack : null,
            'rack_secondary'    => $rackSecondary !== '' ? $rackSecondary : null,
            'shelf'             => $shelf,
            // Full Label is display-only (auto-derived from rack + shelf, like the
            // Select Files panel); the rack/shelf values above do the filtering.
            'full_label'        => $rack !== '' ? $rack . ($shelf !== null ? $shelf : '') : null,
            'status'            => $status,
            // Detailed = one row per print batch. Default consolidates the print
            // batches of a registry batch on the same shelf into a single index row,
            // which is how the registry's index sheet is laid out.
            'detailed'          => filter_var($request->input('detailed', false), FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * Build the Batch Index rows, ordered by rack then shelf.
     */
    private function buildBatchIndexRows(Request $request): array
    {
        $f = $this->batchIndexFilters($request);

        // SQL Server has no regex, so derive the numeric serial two ways and take
        // whichever casts cleanly: the last space-delimited token ("MLKN 1050" -> 1050)
        // or everything from the first digit onwards ("MLKN1050" -> 1050).
        $trimmed   = "LTRIM(RTRIM(i.file_number))";
        $lastToken = "REVERSE(LEFT(REVERSE({$trimmed}), CHARINDEX(' ', REVERSE({$trimmed}) + ' ') - 1))";
        $fromDigit = "CASE WHEN PATINDEX('%[0-9]%', {$trimmed}) > 0
                           THEN SUBSTRING({$trimmed}, PATINDEX('%[0-9]%', {$trimmed}), LEN({$trimmed}))
                           ELSE NULL END";
        $serial    = "COALESCE(TRY_CAST({$lastToken} AS INT), TRY_CAST({$fromDigit} AS INT))";

        $query = DB::connection('sqlsrv')
            ->table('kangis_print_label_batches as b')
            ->join('kangis_print_label_batch_items as i', 'i.batch_id', '=', 'b.id')
            ->where('b.status', '!=', KangisPrintLabelBatch::STATUS_PENDING)
            ->groupBy(
                'b.id',
                'b.batch_number',
                'b.prefix',
                'b.sys_batch_no',
                'b.rack_primary',
                'b.rack_secondary',
                'b.shelf_number',
                'b.full_label',
                'b.status',
                'b.created_at'
            )
            ->select([
                'b.id',
                'b.batch_number',
                'b.prefix',
                'b.sys_batch_no',
                'b.rack_primary',
                'b.rack_secondary',
                'b.shelf_number',
                'b.full_label',
                'b.status',
                'b.created_at',
                DB::raw('COUNT(i.id) as file_count'),
                DB::raw("MIN({$serial}) as serial_from"),
                DB::raw("MAX({$serial}) as serial_to"),
            ]);

        if ($f['status'] !== self::STATUS_ANY) {
            $query->where('b.status', $f['status']);
        }
        if ($f['prefix']) {
            $query->where('b.prefix', $f['prefix']);
        }
        if ($f['rack']) {
            $query->whereRaw('UPPER(LTRIM(RTRIM(b.rack_primary))) = ?', [$f['rack']]);
        }
        if ($f['rack_secondary']) {
            $query->whereRaw('UPPER(LTRIM(RTRIM(b.rack_secondary))) = ?', [$f['rack_secondary']]);
        }
        if ($f['shelf'] !== null) {
            $query->where('b.shelf_number', $f['shelf']);
        }
        if (!empty($f['registry_batch_no'])) {
            $query->whereIn(DB::raw('TRY_CAST(b.sys_batch_no AS INT)'), $f['registry_batch_no']);
        }

        $rows = $query
            ->orderBy('b.rack_primary')
            ->orderBy('b.shelf_number')
            ->orderByRaw('TRY_CAST(b.sys_batch_no AS INT)')
            ->get();

        $this->attachBatchIndexFileNumbers($rows);

        if (!$f['detailed']) {
            $rows = $this->consolidateBatchIndexRows($rows);
        }

        $sn = 0;

        return $rows->map(function ($r) use (&$sn) {
            $rack  = strtoupper(trim((string) ($r->rack_primary ?? '')));
            $shelf = $r->shelf_number !== null ? (string) $r->shelf_number : '';
            $full  = trim((string) ($r->full_label ?? '')) ?: trim($rack . $shelf);

            $from = $r->serial_from !== null ? (int) $r->serial_from : null;
            $to   = $r->serial_to !== null ? (int) $r->serial_to : null;
            if ($from !== null && $to !== null) {
                $range = $from === $to ? (string) $from : $from . ' - ' . $to;
            } else {
                $range = '—';
            }

            return [
                'sn'                => ++$sn,
                'batch_id'          => $r->id,
                'batch_number'      => $r->batch_number,
                'registry_batch_no' => $r->sys_batch_no,
                'file_prefix'       => $r->prefix,
                'serial_from'       => $from,
                'serial_to'         => $to,
                'serial_range'      => $range,
                'rack'              => $rack,
                'rack_secondary'    => $r->rack_secondary,
                'shelf'             => $shelf,
                'shelf_rack'        => $full,
                'file_count'        => (int) $r->file_count,
                'batch_count'       => (int) ($r->batch_count ?? 1),
                'files'             => $r->files ?? [],
                'status'            => $r->status,
                'created_at'        => $r->created_at,
            ];
        })->all();
    }

    /**
     * Attach the member file numbers to each index row, sorted by their serial.
     */
    private function attachBatchIndexFileNumbers($rows): void
    {
        $batchIds = $rows->pluck('id')->filter()->unique()->values();
        if ($batchIds->isEmpty()) {
            return;
        }

        $byBatch = [];
        foreach ($batchIds->chunk(1000) as $chunk) {
            DB::connection('sqlsrv')
                ->table('kangis_print_label_batch_items')
                ->whereIn('batch_id', $chunk->all())
                ->select(['batch_id', 'file_number', 'file_title', 'shelf_location', 'label_position'])
                ->orderBy('batch_id')
                ->orderBy('label_position')
                ->get()
                ->each(function ($item) use (&$byBatch) {
                    $number = trim((string) $item->file_number);
                    if ($number === '') {
                        return;
                    }

                    // The batch item's own file_title column holds the KANGIS
                    // file-no placeholder captured at batch time (see createBatch),
                    // not a title — it is only the fallback below.
                    $byBatch[$item->batch_id][] = [
                        'file_number'    => $number,
                        'file_title'     => '',
                        'placeholder'    => trim((string) ($item->file_title ?? '')),
                        'shelf_location' => trim((string) ($item->shelf_location ?? '')),
                    ];
                });
        }

        // The real file title (owner / holder) lives on the indexing record.
        $titles = $this->resolveFileTitles(
            collect($byBatch)->flatten(1)->pluck('file_number')->unique()->values()
        );

        foreach ($rows as $r) {
            $files = $byBatch[$r->id] ?? [];
            foreach ($files as &$file) {
                $file['file_title'] = $titles[strtoupper($file['file_number'])] ?? $file['placeholder'];
            }
            unset($file);

            $r->files = $this->sortFilesBySerial($files);
        }
    }

    /**
     * Look up file titles from file_indexings, keyed by upper-cased file number.
     */
    private function resolveFileTitles($fileNumbers): array
    {
        $titles = [];

        foreach ($fileNumbers->chunk(1000) as $chunk) {
            DB::connection('sqlsrv')
                ->table('file_indexings')
                ->whereIn('file_number', $chunk->all())
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->whereNotNull('file_title')
                ->where('file_title', '!=', '')
                ->select(['file_number', 'file_title'])
                ->get()
                ->each(function ($r) use (&$titles) {
                    $key = strtoupper(trim((string) $r->file_number));
                    if ($key !== '' && !isset($titles[$key])) {
                        $titles[$key] = trim((string) $r->file_title);
                    }
                });
        }

        return $titles;
    }

    /**
     * Order member files by their trailing serial ("MLKN 9" before "MLKN 10"),
     * falling back to a plain string sort when no serial can be read.
     */
    private function sortFilesBySerial(array $files): array
    {
        // De-duplicate on the file number; a file listed twice in a batch is noise
        // on an index sheet.
        $unique = [];
        foreach ($files as $file) {
            $key = strtoupper($file['file_number']);
            if (!isset($unique[$key])) {
                $unique[$key] = $file;
            }
        }
        $files = array_values($unique);

        usort($files, function ($a, $b) {
            $sa = preg_match('/(\d+)\s*$/', $a['file_number'], $ma) ? (int) $ma[1] : null;
            $sb = preg_match('/(\d+)\s*$/', $b['file_number'], $mb) ? (int) $mb[1] : null;

            if ($sa !== null && $sb !== null && $sa !== $sb) {
                return $sa <=> $sb;
            }

            return strcasecmp($a['file_number'], $b['file_number']);
        });

        return $files;
    }

    /**
     * Collapse the print batches of one registry batch sitting on the same
     * shelf into a single index row (min/max serial, summed file count).
     */
    private function consolidateBatchIndexRows($rows)
    {
        return $rows
            ->groupBy(function ($r) {
                return implode('|', [
                    strtoupper(trim((string) $r->prefix)),
                    (string) $r->sys_batch_no,
                    strtoupper(trim((string) $r->rack_primary)),
                    (string) $r->shelf_number,
                ]);
            })
            ->map(function ($group) {
                $first    = $group->first();
                $statuses = $group->pluck('status')->unique()->values();

                $merged = clone $first;
                $merged->serial_from = $group->pluck('serial_from')->filter(fn($v) => $v !== null)->min();
                $merged->serial_to   = $group->pluck('serial_to')->filter(fn($v) => $v !== null)->max();
                $merged->file_count  = $group->sum('file_count');
                $merged->batch_count = $group->count();
                $merged->status      = $statuses->count() === 1 ? $statuses->first() : 'mixed';
                $merged->created_at  = $group->pluck('created_at')->filter()->min();
                $merged->files = $this->sortFilesBySerial(
                    $group->flatMap(fn($r) => $r->files ?? [])->all()
                );

                return $merged;
            })
            ->values();
    }

    /**
     * Backfill sys_batch_no for batches where it is null/empty,
     * by joining through batch items → kangis_grouping.registry_batch_no.
     */
    public function backfillSysBatchNo()
    {
        try {
            $updated = DB::connection('sqlsrv')->statement("
                UPDATE b
                SET b.sys_batch_no = CAST(g.registry_batch_no AS NVARCHAR(100)),
                    b.updated_at   = GETDATE()
                FROM kangis_print_label_batches b
                INNER JOIN kangis_print_label_batch_items bi ON bi.batch_id = b.id
                INNER JOIN kangis_grouping g ON g.id = bi.kangis_grouping_id
                WHERE (b.sys_batch_no IS NULL OR LTRIM(RTRIM(b.sys_batch_no)) = '')
                  AND g.registry_batch_no IS NOT NULL
            ");

            $remaining = KangisPrintLabelBatch::whereNull('sys_batch_no')
                ->orWhere('sys_batch_no', '')->count();

            return response()->json([
                'success'   => true,
                'message'   => 'Backfill complete.',
                'remaining' => $remaining,
            ]);
        } catch (\Throwable $e) {
            Log::error('kangis-printlabel.backfillSysBatchNo', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Mark a batch as printed.
     */
    public function markBatchAsPrinted($batchId)
    {
        try {
            $batch = KangisPrintLabelBatch::findOrFail($batchId);
            $batch->markAsPrinted();
            $batch->batchItems()->update(['is_printed' => true, 'printed_at' => now()]);

            Log::info('kangis-printlabel.markBatchAsPrinted', ['batch_id' => $batchId, 'user_id' => auth()->id()]);

            return response()->json(['success' => true, 'message' => 'Batch marked as printed.']);
        } catch (\Throwable $e) {
            Log::error('kangis-printlabel.markBatchAsPrinted', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a (non-printed) batch.
     */
    public function deleteBatch($batchId)
    {
        try {
            $batch = KangisPrintLabelBatch::findOrFail($batchId);

            if (in_array($batch->status, [KangisPrintLabelBatch::STATUS_PRINTED, KangisPrintLabelBatch::STATUS_COMPLETED])) {
                return response()->json(['success' => false, 'message' => 'Cannot delete printed or completed batches.'], 400);
            }

            // Return the rows to the "ready" pool. kn_grouping has no is_indexed column,
            // so only restore that flag on the tables that support it.
            $cfg = $this->groupingConfig($batch->prefix);
            if ($cfg['has_is_indexed']) {
                $ids = $batch->batchItems->pluck('kangis_grouping_id')->filter()->all();
                if (!empty($ids)) {
                    DB::connection('sqlsrv')
                        ->table($cfg['table'])
                        ->whereIn('id', $ids)
                        ->update(['is_indexed' => 1, 'updated_at' => now()]);
                }
            }

            $batch->delete();

            Log::info('kangis-printlabel.deleteBatch', ['batch_id' => $batchId, 'user_id' => auth()->id()]);

            return response()->json(['success' => true, 'message' => 'Batch deleted.']);
        } catch (\Throwable $e) {
            Log::error('kangis-printlabel.deleteBatch', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function validatePrefix(string $raw): ?string
    {
        $value = strtoupper(trim($raw));
        return in_array($value, self::PREFIXES, true) ? $value : null;
    }

    /**
     * Resolve the grouping table and its column shape for a given prefix.
     *
     * The "KN" prefix is sourced from `kn_grouping`, which uses kn_* column names
     * and lacks the is_indexed / placeholder columns; every other prefix comes from
     * `kangis_grouping`. Callers use this so the same workflow serves both tables.
     */
    private function groupingConfig(?string $prefix): array
    {
        if (strtoupper(trim((string) $prefix)) === 'KN') {
            return [
                'table'           => 'kn_grouping',
                'awaiting'        => 'kn_awaiting_fileno',
                'fileno'          => 'kn_fileno',
                'secondary'       => 'kn_fileno',
                'has_is_indexed'  => false,
                'has_placeholder' => false,
            ];
        }

        return [
            'table'           => 'kangis_grouping',
            'awaiting'        => 'kangis_awaiting_fileno',
            'fileno'          => 'kangis_fileno',
            'secondary'       => 'kangis_fileno_placeholder',
            'has_is_indexed'  => true,
            'has_placeholder' => true,
        ];
    }

    private function resolveRackLabelRecord(
        string $fullLabel,
        ?string $rackPrimary = null,
        ?string $rackSecondary = null,
        ?int $shelfNumber = null,
        bool $lockForUpdate = false,
        bool $createIfMissing = false
    ): ?KangisRackShelfLabel {
        $normalized = strtoupper(trim($fullLabel));

        $query = KangisRackShelfLabel::query();
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $label = $query->whereRaw('UPPER(LTRIM(RTRIM(full_label))) = ?', [$normalized])->first();
        if ($label) {
            return $label;
        }

        // Try to parse the label if parts not provided
        if (($rackPrimary === null || $shelfNumber === null) && preg_match('/^([A-Z]{1,2})(\d{1,4})$/', $normalized, $matches)) {
            $rackGroup   = $matches[1];
            $rackPrimary = substr($rackGroup, 0, 1);
            $rackSecondary = strlen($rackGroup) > 1 ? substr($rackGroup, 1) : null;
            $shelfNumber = (int) $matches[2];
        }

        if ($createIfMissing && $rackPrimary !== null && $shelfNumber !== null) {
            return KangisRackShelfLabel::create([
                'rack'       => strtoupper(trim($rackPrimary . ($rackSecondary ?? ''))),
                'shelf'      => (string) $shelfNumber,
                'full_label' => $normalized,
                'is_used'    => false,
                'counter'    => '0',
                'status'     => 'Available',
                'created_at' => now(),
            ]);
        }

        return null;
    }

    private function insertBatchItemsInChunks(array $items): void
    {
        if (empty($items)) {
            return;
        }

        $columnCount  = count($items[0]);
        $maxPerInsert = max(1, (int) floor(2000 / $columnCount));

        foreach (array_chunk($items, $maxPerInsert) as $chunk) {
            KangisPrintLabelBatchItem::insert($chunk);
        }
    }

    /**
     * Increment shelf label (e.g. A1 -> A2).
     */
    private function getNextShelfLabel(string $fullLabel): string
    {
        if (preg_match('/^([A-Z]{1,2})(\d+)$/i', $fullLabel, $matches)) {
            $prefix = $matches[1];
            $num = (int)$matches[2];
            return strtoupper($prefix) . ($num + 1);
        }
        return $fullLabel . '_NEXT';
    }
}

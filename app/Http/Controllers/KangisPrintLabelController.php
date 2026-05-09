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

    const PREFIXES = ['KNML', 'MNKL', 'MLKN', 'KNGP'];

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
                // Count from kangis_grouping where is_indexed = 1
                $count = DB::connection('sqlsrv')
                    ->table('kangis_grouping')
                    ->where('kangis_awaiting_fileno', 'like', $prefix . '%')
                    ->where('is_indexed', 1)
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
            // Find the highest registry_batch_no whose files already appear in our batch items
            $maxBatched = DB::connection('sqlsrv')
                ->table('kangis_grouping as g')
                ->join('kangis_print_label_batch_items as i', 'i.file_number', '=', 'g.kangis_awaiting_fileno')
                ->where('g.kangis_awaiting_fileno', 'like', $prefix . '%')
                ->max('g.registry_batch_no');

            $nextBatchNo = $maxBatched ? (int)$maxBatched + 1 : 1;

            // Verify the suggested batch actually exists in kangis_grouping
            $exists = DB::connection('sqlsrv')
                ->table('kangis_grouping')
                ->where('kangis_awaiting_fileno', 'like', $prefix . '%')
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

            $query = DB::connection('sqlsrv')
                ->table('kangis_grouping')
                ->where('kangis_awaiting_fileno', 'like', $prefix . '%')
                ->where('is_indexed', 1) // Only load files that are ready (indexed) but not yet batched for labels
                ->select([
                    'id',
                    'tracking_id',
                    'kangis_awaiting_fileno',
                    'registry_batch_no',
                    'kangis_fileno_placeholder'
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
                $query->where(function ($q) use ($search) {
                    $q->where('kangis_awaiting_fileno', 'like', '%' . $search . '%')
                      ->orWhere('tracking_id', 'like', '%' . $search . '%');
                });
            }

            $rows = $query->orderBy('kangis_awaiting_fileno')->get();

            if ($rows->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'files'   => [],
                        'missing' => [],
                        'total'   => 0,
                        'prefix'  => $prefix,
                        'message' => 'No records found in kangis_grouping for this prefix/batch.',
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
                    'file_number'               => $r->kangis_awaiting_fileno,
                    'secondary_file_number'     => $r->kangis_fileno_placeholder,
                    'kangis_fileno_placeholder' => $r->kangis_fileno_placeholder,
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
            $validated = $request->validate([
                'prefix'            => 'required|string|in:' . implode(',', self::PREFIXES),
                'registry_batch_no' => 'nullable|string',
                'file_ids'          => 'required|array|min:1|max:' . self::MAX_BATCH_SELECTION,
                'file_ids.*'        => 'integer|min:1',
                'full_label'        => 'required|string|max:20',
                'rack_primary'      => 'required|string|max:5',
                'shelf_number'      => 'required|integer|min:1|max:9999',
                'rack_secondary'    => 'nullable|string|max:5',
            ]);

            $prefix        = $validated['prefix'];
            $fileIds       = array_unique(array_map('intval', $validated['file_ids']));
            $fullLabel     = strtoupper(trim($validated['full_label']));
            $rackPrimary   = strtoupper(trim($validated['rack_primary']));
            $rackSecondary = isset($validated['rack_secondary']) ? strtoupper(trim($validated['rack_secondary'])) : null;
            $shelfNumber   = (int) $validated['shelf_number'];

            $result = DB::connection('sqlsrv')->transaction(function () use (
                $prefix, $fileIds, $fullLabel, $rackPrimary, $rackSecondary, $shelfNumber
            ) {
                $now = Carbon::now();
                
                // Fetch files from kangis_grouping to group them by their registry_batch_no
                $filesFromDb = DB::connection('sqlsrv')
                    ->table('kangis_grouping')
                    ->whereIn('id', $fileIds)
                    ->where('kangis_awaiting_fileno', 'like', $prefix . '%')
                    ->get();

                if ($filesFromDb->isEmpty()) {
                    throw ValidationException::withMessages(['file_ids' => 'No valid matching records found in kangis_grouping.']);
                }

                // Group by registry_batch_no (this allows "1 will A1 and 5 will have A2" logic)
                $groups = $filesFromDb->groupBy('registry_batch_no');
                
                $createdBatchesData = [];
                $currentFullLabel = $fullLabel;
                $currentRackPrimary = $rackPrimary;
                $currentRackSecondary = $rackSecondary;
                $currentShelfNumber = $shelfNumber;

                foreach ($groups as $regBatchNo => $groupFiles) {
                    $regBatchNoStr = (string)$regBatchNo;
                    
                    // 1. Generate Batch Number
                    $batchNumber = 'KANGIS-' . $prefix . '-' . $now->format('YmdHis') . ($groups->count() > 1 ? '-' . $regBatchNoStr : '');

                    // 2. Create the Batch Record
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

                    $items = $groupFiles->values()->map(function ($file, $index) use ($batch, $currentFullLabel, $prefix, $now) {
                        $fileNumber = $file->kangis_awaiting_fileno;
                        $placeholder = $file->kangis_fileno_placeholder;
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

                    DB::connection('sqlsrv')
                        ->table('kangis_grouping')
                        ->whereIn('id', $groupFiles->pluck('id')->all())
                        ->update([
                            'is_indexed' => 0,
                            'updated_at' => $now,
                            'updated_by' => auth()->id(),
                        ]);

                    $createdBatchesData[] = [
                        'batch' => $batch->fresh(),
                        'file_count' => $groupFiles->count()
                    ];

                    if ($groups->count() > 1) {
                        $currentFullLabel = $this->getNextShelfLabel($currentFullLabel);
                        if (preg_match('/^([A-Z]{1,2})(\d+)$/i', $currentFullLabel, $m)) {
                            $currentShelfNumber = (int)$m[2];
                        }
                    }
                }

                return $createdBatchesData;
            });

            $first = $result[0];

            return response()->json([
                'success' => true,
                'message' => count($result) > 1 ? count($result) . ' separate batches created successfully.' : 'Label batch created successfully.',
                'data' => [
                    'batch_id'     => $first['batch']->id,
                    'batch_number' => $first['batch']->batch_number,
                    'file_count'   => $first['file_count'],
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

            $kangisGroupingIds = $batch->batchItems->pluck('kangis_grouping_id')->filter()->unique()->values();

            $groupingDetails = collect();
            if ($kangisGroupingIds->isNotEmpty()) {
                $groupingDetails = DB::connection('sqlsrv')
                    ->table('kangis_grouping')
                    ->whereIn('id', $kangisGroupingIds)
                    ->get()
                    ->keyBy('id');
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

            $ids = $batch->batchItems->pluck('kangis_grouping_id')->filter()->all();
            if (!empty($ids)) {
                DB::connection('sqlsrv')
                    ->table('kangis_grouping')
                    ->whereIn('id', $ids)
                    ->update(['is_indexed' => 1, 'updated_at' => now()]);
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

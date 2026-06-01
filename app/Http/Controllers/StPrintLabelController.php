<?php

namespace App\Http\Controllers;

use App\Models\FileIndexing;
use App\Models\StRackShelfLabel;
use App\Models\StPrintLabelBatch;
use App\Models\StPrintLabelBatchItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class StPrintLabelController extends Controller
{
    const RACK_SHELF_CAPACITY = 999999;
    const MAX_BATCH_SELECTION = 500;

    const PREFIX = 'ST';

    // Allowed ST application types stored in file_indexings.st_application_type
    const APPLICATION_TYPES = ['primary', 'pua', 'sua'];

    private const APPLICATION_TYPE_LABELS = [
        'primary' => 'Primary',
        'pua'     => 'PUA (Parented Units Application)',
        'sua'     => 'SUA (Standalone Unit Application)',
    ];

    // -------------------------------------------------------------------------
    // Public endpoints
    // -------------------------------------------------------------------------

    public function index(Request $request)
    {
        $recentBatches = StPrintLabelBatch::with(['creator'])
            ->withCount('batchItems')
            ->where('status', '!=', StPrintLabelBatch::STATUS_PENDING)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('st_printlabel.index', compact('recentBatches'));
    }

    /**
     * Return the single ST prefix with a file count.
     */
    public function getPrefixes()
    {
        try {
            $count = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->whereIn('st_application_type', self::APPLICATION_TYPES)
                ->count();

            $result = [
                [
                    'prefix' => self::PREFIX,
                    'label'  => self::PREFIX,
                    'count'  => $count,
                ],
            ];

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            Log::error('st-printlabel.getPrefixes', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Return the ST application types (primary, pua, sua) with a live count
     * of indexed files available for label generation.
     */
    public function getApplicationTypes()
    {
        try {
            $counts = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->whereIn('st_application_type', self::APPLICATION_TYPES)
                ->selectRaw('st_application_type, COUNT(*) as total')
                ->groupBy('st_application_type')
                ->pluck('total', 'st_application_type');

            $data = [];
            foreach (self::APPLICATION_TYPES as $type) {
                $data[] = [
                    'type'  => $type,
                    'label' => self::APPLICATION_TYPE_LABELS[$type] ?? strtoupper($type),
                    'count' => (int) ($counts[$type] ?? 0),
                ];
            }

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            Log::error('st-printlabel.getApplicationTypes', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Suggest the next unprocessed batch number/index.
     */
    public function getNextRangeForPrefix(Request $request)
    {
        try {
            $maxBatched = DB::connection('sqlsrv')
                ->table('st_print_label_batches')
                ->max('sys_batch_no');

            $nextBatchNo = $maxBatched ? (int)$maxBatched + 1 : 1;

            $exists = DB::connection('sqlsrv')
                ->table('st_print_label_batches')
                ->where('sys_batch_no', $nextBatchNo)
                ->exists();

            return response()->json([
                'success' => true,
                'data' => [
                    'prefix'        => self::PREFIX,
                    'next_batch_no' => $nextBatchNo,
                    'exists'        => $exists,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('st-printlabel.getNextRangeForPrefix', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Fetch available file_indexings records for a given ST application type.
     */
    public function getAvailableFiles(Request $request)
    {
        try {
            $applicationType = strtolower(trim((string) $request->input('application_type', '')));
            $search          = trim((string) $request->input('search', ''));

            if ($applicationType === '' || !in_array($applicationType, self::APPLICATION_TYPES, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select a valid ST application type (primary, pua, sua).',
                ], 422);
            }

            $query = DB::connection('sqlsrv')
                ->table('file_indexings as fi')
                ->leftJoin('mother_applications as ma', 'ma.id', '=', 'fi.main_application_id')
                ->leftJoin('subapplications as sa', 'sa.id', '=', 'fi.subapplication_id')
                ->where('fi.st_application_type', $applicationType)
                ->select([
                    'fi.id',
                    'fi.file_number',
                    'fi.tracking_id',
                    'fi.land_use_type',
                    'fi.shelf_location',
                    'fi.st_application_type',
                    DB::raw('COALESCE(sa.np_fileno, ma.np_fileno) AS np_file_number'),
                    DB::raw('COALESCE(sa.fileno, ma.fileno) AS application_file_number'),
                ]);

            if ($search !== '') {
                $like = '%' . $search . '%';
                $query->where(function ($q) use ($like) {
                    $q->where('fi.file_number', 'like', $like)
                      ->orWhere('fi.tracking_id', 'like', $like)
                      ->orWhere('ma.np_fileno', 'like', $like)
                      ->orWhere('sa.np_fileno', 'like', $like);
                });
            }

            // Exclude files that are already in a batch (already generated/printed)
            $query->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('st_print_label_batch_items')
                    ->whereColumn('st_print_label_batch_items.file_number', 'fi.file_number');
            });

            // Handle exclude_assigned parameter (Skip Assigned Shelves)
            if ($request->boolean('exclude_assigned')) {
                $query->where(function ($q) {
                    $q->whereNull('fi.shelf_location')
                      ->orWhere('fi.shelf_location', '')
                      ->orWhere('fi.shelf_location', 'like', '%N/A%');
                });
            }

            $rows = $query->orderBy('fi.file_number')->get();

            if ($rows->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'files'            => [],
                        'missing'          => [],
                        'total'            => 0,
                        'prefix'           => self::PREFIX,
                        'application_type' => $applicationType,
                        'message'          => 'No indexed ST files found for application type "' . strtoupper($applicationType) . '".',
                    ],
                ]);
            }

            $mapped = $rows->map(function ($r) {
                $np    = isset($r->np_file_number) ? trim((string) $r->np_file_number) : '';
                $appNo = isset($r->application_file_number) ? trim((string) $r->application_file_number) : '';
                return [
                    'id'                      => $r->id,
                    'file_number'             => $r->file_number,
                    'np_file_number'          => $np !== '' ? $np : null,
                    'application_file_number' => $appNo !== '' ? $appNo : null,
                    'file_title'              => null,
                    'plot_number'             => null,
                    'district'                => null,
                    'lga'                     => null,
                    'land_use_type'           => $r->land_use_type,
                    'shelf_location'          => $r->shelf_location,
                    'tracking_id'             => $r->tracking_id,
                    'st_application_type'     => $r->st_application_type,
                    'already_batched'         => false,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'files'              => $mapped->values(),
                    'missing'            => [],
                    'total'              => $mapped->count(),
                    'prefix'             => self::PREFIX,
                    'application_type'   => $applicationType,
                    'indexed_count'      => $rows->count(),
                    'batch_already_used' => false,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('st-printlabel.getAvailableFiles', ['error' => $e->getMessage()]);
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
            Log::error('st-printlabel.getRackLabelStatus', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Create a label batch for the selected ST files on a given shelf.
     */
    public function createBatch(Request $request)
    {
        try {
            $validated = $request->validate([
                'prefix'           => 'required|string|in:' . self::PREFIX,
                'application_type' => 'nullable|string|in:' . implode(',', self::APPLICATION_TYPES),
                'file_ids'         => 'required|array|min:1|max:' . self::MAX_BATCH_SELECTION,
                'file_ids.*'       => 'integer|min:1',
                'full_label'       => 'required|string|max:20',
                'rack_primary'     => 'required|string|max:5',
                'shelf_number'     => 'required|integer|min:1|max:9999',
                'rack_secondary'   => 'nullable|string|max:5',
            ]);

            $prefix          = self::PREFIX;
            $applicationType = isset($validated['application_type']) ? strtolower($validated['application_type']) : null;
            $fileIds         = array_unique(array_map('intval', $validated['file_ids']));
            $fullLabel    = strtoupper(trim($validated['full_label']));
            $rackPrimary  = strtoupper(trim($validated['rack_primary']));
            $rackSecondary = isset($validated['rack_secondary']) ? strtoupper(trim($validated['rack_secondary'])) : null;
            $shelfNumber  = (int) $validated['shelf_number'];

            $result = DB::connection('sqlsrv')->transaction(function () use (
                $prefix, $applicationType, $fileIds, $fullLabel, $rackPrimary, $rackSecondary, $shelfNumber
            ) {
                $now = Carbon::now();

                // Reserve the next (prefix, sys_batch_no) pair — there's a unique constraint on that pair.
                $sysBatchNo = (int) DB::connection('sqlsrv')
                    ->table('st_print_label_batches')
                    ->where('prefix', $prefix)
                    ->max('sys_batch_no');
                $sysBatchNo = $sysBatchNo > 0 ? $sysBatchNo + 1 : 1;

                $batchNumber = 'ST-' . $now->format('YmdHis') . '-' . $sysBatchNo;

                $batch = StPrintLabelBatch::create([
                    'batch_number'    => $batchNumber,
                    'prefix'          => $prefix,
                    'application_type'=> $applicationType,
                    'sys_batch_no'    => $sysBatchNo,
                    'status'          => StPrintLabelBatch::STATUS_PENDING,
                    'full_label'      => $fullLabel,
                    'rack_primary'    => $rackPrimary,
                    'rack_secondary'  => $rackSecondary,
                    'shelf_number'    => $shelfNumber,
                    'created_by'      => auth()->id(),
                    'updated_by'      => auth()->id(),
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);

                // Fetch files from file_indexings, joined to mother/sub applications for np_fileno
                $files = DB::connection('sqlsrv')
                    ->table('file_indexings as fi')
                    ->leftJoin('mother_applications as ma', 'ma.id', '=', 'fi.main_application_id')
                    ->leftJoin('subapplications as sa', 'sa.id', '=', 'fi.subapplication_id')
                    ->whereIn('fi.id', $fileIds)
                    ->select([
                        'fi.id',
                        'fi.file_number',
                        'fi.tracking_id',
                        'fi.land_use_type',
                        'fi.shelf_location',
                        'fi.location',
                        'fi.file_title',
                        'fi.plot_number',
                        DB::raw('COALESCE(sa.np_fileno, ma.np_fileno) AS np_file_number'),
                        DB::raw('COALESCE(sa.fileno, ma.fileno) AS application_file_number'),
                    ])
                    ->get();

                if ($files->isEmpty()) {
                    throw ValidationException::withMessages(['file_ids' => 'No matching files found in indexed files.']);
                }

                // Resolve / create rack label record
                $shelfLabelRecord = $this->resolveRackLabelRecord($fullLabel, $rackPrimary, $rackSecondary, $shelfNumber, false, true);

                $items = $files->values()->map(function ($file, $index) use ($batch, $fullLabel, $prefix, $now) {
                    $fileNumber   = $file->file_number;
                    $npFileNumber = isset($file->np_file_number) ? trim((string) $file->np_file_number) : '';
                    $npFileNumber = $npFileNumber !== '' ? $npFileNumber : null;
                    $appFileNumber = isset($file->application_file_number) ? trim((string) $file->application_file_number) : '';
                    $appFileNumber = $appFileNumber !== '' ? $appFileNumber : null;
                    $qrPayload = [
                        'file_number'             => $fileNumber,
                        'np_file_number'          => $npFileNumber,
                        'application_file_number' => $appFileNumber,
                        'tracking_id'             => $file->tracking_id ?? $fileNumber,
                        'prefix'                  => $prefix,
                        'shelf_label'             => $fullLabel,
                        'generated_at'            => $now->toIso8601String(),
                    ];

                    return [
                        'batch_id'        => $batch->id,
                        'file_indexing_id'=> $file->id,
                        'file_number'     => $fileNumber,
                        'prefix'          => $prefix,
                        'file_title'      => null,
                        'plot_number'     => null,
                        'district'        => null,
                        'lga'             => null,
                        'land_use_type'   => $file->land_use_type,
                        'shelf_location'  => $fullLabel,
                        'label_position'  => $index + 1,
                        'qr_code_data'    => json_encode($qrPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'barcode_data'    => $npFileNumber ?: $fileNumber,
                        'is_printed'      => false,
                        'created_at'      => $now,
                        'updated_at'      => $now,
                    ];
                })->all();

                // Insert batch items in chunks
                $this->insertBatchItemsInChunks($items);

                // Update file_indexings.shelf_location with the assigned label
                $ids = $files->pluck('id')->all();
                foreach (array_chunk($ids, 100) as $chunk) {
                    FileIndexing::on('sqlsrv')
                        ->whereIn('id', $chunk)
                        ->update([
                            'shelf_location' => $fullLabel,
                            'updated_at' => $now,
                        ]);
                }

                // Bump the rack label counter
                if ($shelfLabelRecord) {
                    $count = count($ids);
                    $shelfLabelRecord->update([
                        'assigned'  => 'ST',
                        'counter'   => DB::raw("CAST(CAST(ISNULL(NULLIF(counter, ''), '0') AS INT) + {$count} AS NVARCHAR(MAX))"),
                        'status'    => 'Occupied',
                        'updated_at'=> $now,
                    ]);
                }

                // Finalize batch
                $batch->update([
                    'status'          => StPrintLabelBatch::STATUS_GENERATED,
                    'generated_count' => count($ids),
                    'updated_by'      => auth()->id(),
                ]);

                // Return label items for immediate print preview
                $labelItems = $files->values()->map(function ($file, $index) use ($fullLabel, $prefix) {
                    $fileNumber   = $file->file_number;
                    $npFileNumber = isset($file->np_file_number) ? trim((string) $file->np_file_number) : '';
                    $npFileNumber = $npFileNumber !== '' ? $npFileNumber : null;
                    $appFileNumber = isset($file->application_file_number) ? trim((string) $file->application_file_number) : '';
                    $appFileNumber = $appFileNumber !== '' ? $appFileNumber : null;
                    return [
                        'file_indexing_id'        => $file->id,
                        'file_number'             => $fileNumber,
                        'np_file_number'          => $npFileNumber,
                        'application_file_number' => $appFileNumber,
                        'file_title'              => $file->file_title ?? null,
                        'plot_number'             => $file->plot_number ?? null,
                        'district'                => $file->location ?? null,
                        'lga'                     => null,
                        'land_use_type'           => $file->land_use_type,
                        'shelf_location'          => $fullLabel,
                        'shelf_value'             => $fullLabel,
                        'shelf_label'             => $fullLabel,
                        'tracking_id'             => $file->tracking_id ?? $fileNumber,
                        'qr_value'                => $file->tracking_id ?? $fileNumber,
                        'label_position'          => $index + 1,
                        'prefix'                  => $prefix,
                    ];
                })->values()->toArray();

                return [
                    'batch'       => $batch->fresh(),
                    'label_items' => $labelItems,
                    'file_count'  => count($ids),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Label batch created successfully.',
                'data' => [
                    'batch_id'     => $result['batch']->id,
                    'batch_number' => $result['batch']->batch_number,
                    'file_count'   => $result['file_count'],
                    'label_items'  => $result['label_items'],
                ],
            ]);

        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('st-printlabel.createBatch', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Error creating batch: ' . $e->getMessage()], 500);
        }
    }

    /**
     * List generated batches (ST only).
     */
    public function getBatches(Request $request)
    {
        try {
            $query = StPrintLabelBatch::with(['creator'])
                ->withCount('batchItems')
                ->where('status', '!=', StPrintLabelBatch::STATUS_PENDING);

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
            Log::error('st-printlabel.getBatches', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Return a batch with all label items ready for printing.
     */
    public function getBatchForPrinting($batchId)
    {
        try {
            $batch = StPrintLabelBatch::with(['batchItems'])->findOrFail($batchId);

            $fileIndexingIds = $batch->batchItems->pluck('file_indexing_id')->filter()->unique()->values();

            $indexingDetails = collect();
            if ($fileIndexingIds->isNotEmpty()) {
                $indexingDetails = DB::connection('sqlsrv')
                    ->table('file_indexings as fi')
                    ->leftJoin('mother_applications as ma', 'ma.id', '=', 'fi.main_application_id')
                    ->leftJoin('subapplications as sa', 'sa.id', '=', 'fi.subapplication_id')
                    ->whereIn('fi.id', $fileIndexingIds)
                    ->select([
                        'fi.id',
                        'fi.tracking_id',
                        'fi.shelf_location',
                        DB::raw('COALESCE(sa.np_fileno, ma.np_fileno) AS np_file_number'),
                        DB::raw('COALESCE(sa.fileno, ma.fileno) AS application_file_number'),
                    ])
                    ->get()
                    ->keyBy('id');
            }

            $files = $batch->batchItems->map(function ($item) use ($indexingDetails) {
                $details = $indexingDetails->get($item->file_indexing_id);

                $qrData = [];
                if (!empty($item->qr_code_data)) {
                    $decoded = json_decode($item->qr_code_data, true);
                    if (is_array($decoded)) {
                        $qrData = $decoded;
                    }
                }

                $trackingId    = $qrData['tracking_id'] ?? optional($details)->tracking_id ?? $item->file_number;
                $shelfValue    = $item->shelf_location ?? optional($details)->shelf_location ?? null;
                $npFileNumber  = $qrData['np_file_number'] ?? optional($details)->np_file_number ?? null;
                $appFileNumber = $qrData['application_file_number'] ?? optional($details)->application_file_number ?? null;
                $npFileNumber  = is_string($npFileNumber) && trim($npFileNumber) !== '' ? trim($npFileNumber) : null;
                $appFileNumber = is_string($appFileNumber) && trim($appFileNumber) !== '' ? trim($appFileNumber) : null;

                return [
                    'id'                      => $item->file_indexing_id,
                    'batch_item_id'           => $item->id,
                    'file_number'             => $item->file_number,
                    'np_file_number'          => $npFileNumber,
                    'application_file_number' => $appFileNumber,
                    'file_title'              => $item->file_title,
                    'plot_number'             => $item->plot_number,
                    'district'                => $item->district,
                    'lga'                     => $item->lga,
                    'land_use_type'           => $item->land_use_type,
                    'shelf_location'          => $shelfValue,
                    'shelf_value'             => $shelfValue,
                    'shelf_label'             => $shelfValue,
                    'tracking_id'             => $trackingId,
                    'qr_code_data'            => $qrData,
                    'qr_value'                => $trackingId,
                    'label_position'          => $item->label_position,
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
            Log::error('st-printlabel.getBatchForPrinting', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Mark a batch as printed.
     */
    public function markBatchAsPrinted($batchId)
    {
        try {
            $batch = StPrintLabelBatch::findOrFail($batchId);
            $batch->markAsPrinted();
            $batch->batchItems()->update(['is_printed' => true, 'printed_at' => now()]);

            Log::info('st-printlabel.markBatchAsPrinted', ['batch_id' => $batchId, 'user_id' => auth()->id()]);

            return response()->json(['success' => true, 'message' => 'Batch marked as printed.']);
        } catch (\Throwable $e) {
            Log::error('st-printlabel.markBatchAsPrinted', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a (non-printed) batch.
     */
    public function deleteBatch($batchId)
    {
        try {
            $batch = StPrintLabelBatch::findOrFail($batchId);

            if (in_array($batch->status, [StPrintLabelBatch::STATUS_PRINTED, StPrintLabelBatch::STATUS_COMPLETED])) {
                return response()->json(['success' => false, 'message' => 'Cannot delete printed or completed batches.'], 400);
            }

            $batch->delete();

            Log::info('st-printlabel.deleteBatch', ['batch_id' => $batchId, 'user_id' => auth()->id()]);

            return response()->json(['success' => true, 'message' => 'Batch deleted.']);
        } catch (\Throwable $e) {
            Log::error('st-printlabel.deleteBatch', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function resolveRackLabelRecord(
        string $fullLabel,
        ?string $rackPrimary = null,
        ?string $rackSecondary = null,
        ?int $shelfNumber = null,
        bool $lockForUpdate = false,
        bool $createIfMissing = false
    ): ?StRackShelfLabel {
        $normalized = strtoupper(trim($fullLabel));

        $query = StRackShelfLabel::query();
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
            return StRackShelfLabel::create([
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
            StPrintLabelBatchItem::insert($chunk);
        }
    }
}

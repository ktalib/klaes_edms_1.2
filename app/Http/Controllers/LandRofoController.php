<?php

namespace App\Http\Controllers;

use App\Models\LandRecommendation;
use App\Services\Pra\RofoPraSyncer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\PrintLog;

class LandRofoController extends Controller
{
    public function index(Request $request)
    {
        $ossViewOnly = $request->query('view') === 'only';

        // Show approved recommendations AND OSS-type records (CoN applications ready to print)
        // Select only the columns the view needs — avoids loading large text fields (recommendation, survey_report, etc.)
        $query = LandRecommendation::with('creator')
            ->select([
                'id', 'file_number', 'applicant_name', 'purpose_of_clause', 'location',
                'plot_number', 'layout_plan_no', 'term', 'ground_rent', 'development_period',
                'survey_fees', 'development_value', 'development_charge', 'type',
                'rofo_status', 'status', 'approved_at', 'land_rofo_serial_no',
                'created_at', 'created_by', 'land_use', 'land_use_id', 'purpose_id',
            ]);

        if ($ossViewOnly) {
            $query->whereRaw("UPPER(ISNULL(type, '')) = 'OSS'");
        } else {
            $query->where(function ($q) {
                $q->where('status', LandRecommendation::STATUS_APPROVED)
                  ->orWhereRaw("UPPER(ISNULL(type, '')) = 'OSS'");
            });
        }

        // Tab filter: "printed" (RofO already printed at least once) vs
        // "not_printed" (still awaiting print). rofo_print_count is incremented by
        // both batch and individual prints, so it is the authoritative flag.
        // The OSS-only view is a separate print flow, so tabs apply to the land view only.
        $tab = $request->query('tab', 'not_printed');
        if (!in_array($tab, ['printed', 'not_printed'], true)) {
            $tab = 'not_printed';
        }
        if (!$ossViewOnly) {
            if ($tab === 'printed') {
                $query->whereRaw("ISNULL(rofo_print_count, 0) > 0");
            } else { // not_printed
                $query->whereRaw("ISNULL(rofo_print_count, 0) = 0");
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('file_number', 'LIKE', "%{$search}%")
                  ->orWhere('applicant_name', 'LIKE', "%{$search}%")
                  ->orWhere('location', 'LIKE', "%{$search}%");
            });
        }

        $recommendations = $query->latest()->paginate(20)->withQueryString();

        $PageTitle = $ossViewOnly ? 'OSS RofO' : 'Land RofO';
        $landUses = \App\Models\LandUse::orderBy('landuse')->get();

        // Single aggregated query for all stats to avoid multiple full-table scans
        $statsRow = DB::connection('sqlsrv')->table('land_recommendations')->selectRaw("
            COUNT(CASE WHEN UPPER(ISNULL(type,'')) <> 'OSS' AND status = 'approved'                                          THEN 1 END)   AS total_eligible,
            COUNT(CASE WHEN UPPER(ISNULL(type,'')) <> 'OSS' AND status = 'approved' AND ISNULL(rofo_status,'') = 'pending'   THEN 1 END)   AS pending_generation,
            COUNT(CASE WHEN UPPER(ISNULL(type,'')) <> 'OSS' AND ISNULL(rofo_status,'') = 'generated'                        THEN 1 END)   AS generated,
            COUNT(CASE WHEN UPPER(ISNULL(type,'')) <> 'OSS'                                                                THEN 1 END)   AS total_land,
            COUNT(CASE WHEN ((status = 'approved' AND UPPER(ISNULL(type,'')) <> 'OSS') OR UPPER(ISNULL(type,'')) = 'OSS') AND ISNULL(rofo_print_count,0) > 0 THEN 1 END) AS printed,
            COUNT(CASE WHEN ((status = 'approved' AND UPPER(ISNULL(type,'')) <> 'OSS') OR UPPER(ISNULL(type,'')) = 'OSS') AND ISNULL(rofo_print_count,0) = 0 THEN 1 END) AS not_printed,
            ISNULL(SUM(CASE WHEN UPPER(ISNULL(type,'')) <> 'OSS' AND ISNULL(rofo_status,'') = 'generated' THEN ISNULL(rofo_dev_charge,0) ELSE 0 END), 0) AS total_dev_charge
        ")->first();

        // Count OSS Applications from the authoritative source (oss_applications) so
        // the stat matches the Change of Name page instead of counting type='OSS' rows
        // in land_recommendations which may have duplicates or test records.
        $ossColumns = DB::connection('sqlsrv')->getSchemaBuilder()->getColumnListing('oss_applications');
        $ossHasIsDeleted = in_array('is_deleted', array_map('strtolower', $ossColumns));
        $ossBaseQuery = DB::connection('sqlsrv')->table('oss_applications')
            ->where('system_source', 'OSSOPCHANGEOFNAME')
            ->where(function ($q) use ($ossHasIsDeleted) {
                if ($ossHasIsDeleted) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                }
            });
        $ossTotal = (clone $ossBaseQuery)->count();
        $ossDailyTotal = (clone $ossBaseQuery)->whereDate('created_at', now()->toDateString())->count();

        $stats = [
            'total_eligible'    => (int) ($statsRow->total_eligible    ?? 0),
            'pending_generation'=> (int) ($statsRow->pending_generation ?? 0),
            'generated'         => (int) ($statsRow->generated          ?? 0),
            'printed'           => (int) ($statsRow->printed            ?? 0),
            'not_printed'       => (int) ($statsRow->not_printed        ?? 0),
            'total_land'        => (int) ($statsRow->total_land         ?? 0),
            'total_dev_charge'  => (float) ($statsRow->total_dev_charge ?? 0),
            'oss_total'         => $ossTotal,
            'oss_daily'         => $ossDailyTotal,
        ];

        // Only fetch the paper_code column — the view only ever reads s.paper_code
        $availableSerials = DB::connection('sqlsrv')->table('global_security_paper_codes')
            ->select('paper_code')
            ->where('is_used', false)
            ->orderBy('paper_code', 'asc')
            ->get();

        // Batch-load the auto-generated security codes for the rows on this page so
        // the table can show the same serial-no fraction as the printed certificate,
        // without generating a new code for every listed record (side-effect free).
        $rofoSerials = [];
        $recIds = $recommendations->getCollection()->pluck('id')->all();
        if (!empty($recIds)) {
            $codeService = app(\App\Services\SecurityCodeService::class);
            $codes = DB::connection('sqlsrv')->table('security_codes')
                ->whereIn('document_id', $recIds)
                ->where('document_type', 'Land ROFO')
                ->where('is_used', 0)
                ->orderBy('id')
                ->get(['document_id', 'code']);
            foreach ($codes as $c) {
                // getOrGenerateForDocument returns the first unused code; mirror that
                // by keeping the earliest per document.
                if (!isset($rofoSerials[$c->document_id])) {
                    $rofoSerials[$c->document_id] = $codeService->formatForDisplay($c->code);
                }
            }
        }

        return view('land_rofos.index', compact('recommendations', 'PageTitle', 'landUses', 'stats', 'availableSerials', 'ossViewOnly', 'rofoSerials', 'tab'));
    }

    /**
     * Column metadata shared by the JSON preview, the client-side CSV/PDF and the
     * streamed server CSV, so all three stay in step.
     * `pdfWidth` is in mm — A4 landscape gives ~277mm of usable width.
     */
    private function exportColumns(): array
    {
        return [
            ['key' => 'sn',             'label' => 'S/N',               'pdfWidth' => 9,  'wrap' => false],
            ['key' => 'file_number',    'label' => 'File Number',       'pdfWidth' => 26, 'wrap' => false],
            ['key' => 'source',         'label' => 'Source',            'pdfWidth' => 11],
            ['key' => 'applicant_name', 'label' => 'Applicant Name',    'pdfWidth' => 34],
            ['key' => 'purpose',        'label' => 'Land Use / Purpose','pdfWidth' => 22],
            // No pdfWidth: Location is the flexible column and absorbs the
            // remaining page width (see buildColumnStyles in records_export.js).
            ['key' => 'location',       'label' => 'Location'],
            ['key' => 'plot_number',    'label' => 'Plot No',           'pdfWidth' => 12],
            ['key' => 'layout_plan_no', 'label' => 'Layout Plan',       'pdfWidth' => 14],
            ['key' => 'term',           'label' => 'Term',              'pdfWidth' => 10],
            ['key' => 'ground_rent',    'label' => 'Ground Rent',       'pdfWidth' => 18, 'align' => 'right'],
            ['key' => 'dev_period',     'label' => 'Dev. Period',       'pdfWidth' => 12],
            ['key' => 'survey_fees',    'label' => 'Survey Fees',       'pdfWidth' => 18, 'align' => 'right'],
            ['key' => 'dev_value',      'label' => 'Dev. Value',        'pdfWidth' => 18, 'align' => 'right'],
            ['key' => 'dev_charge',     'label' => 'Dev. Charge',       'pdfWidth' => 18, 'align' => 'right'],
            ['key' => 'status',         'label' => 'Status',            'pdfWidth' => 14],
            ['key' => 'approved_on',    'label' => 'Approved On',       'pdfWidth' => 18],
            ['key' => 'created_by',     'label' => 'Created By',        'pdfWidth' => 18],
            ['key' => 'paper_code',     'label' => 'Security Paper Code','pdfWidth' => 18],
            ['key' => 'date_generated', 'label' => 'Date Generated',    'pdfWidth' => 18],
        ];
    }

    /**
     * Build the filtered export query. Mirrors index() plus the export-only
     * status and created_at date-range filters.
     */
    private function buildExportQuery(Request $request, bool $ossViewOnly)
    {
        $query = LandRecommendation::with('creator')
            ->select([
                'id', 'file_number', 'applicant_name', 'purpose_of_clause', 'location',
                'plot_number', 'house_no', 'layout_plan_no', 'term', 'ground_rent', 'development_period',
                'survey_fees', 'development_value', 'development_charge', 'type',
                'rofo_status', 'status', 'approved_at', 'land_rofo_serial_no',
                'created_at', 'created_by', 'land_use', 'land_use_id', 'purpose_id',
            ]);

        if ($ossViewOnly) {
            $query->whereRaw("UPPER(ISNULL(type, '')) = 'OSS'");
        } else {
            $query->where(function ($q) {
                $q->where('status', LandRecommendation::STATUS_APPROVED)
                  ->orWhereRaw("UPPER(ISNULL(type, '')) = 'OSS'");
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('file_number', 'LIKE', "%{$search}%")
                  ->orWhere('applicant_name', 'LIKE', "%{$search}%")
                  ->orWhere('location', 'LIKE', "%{$search}%");
            });
        }

        // Status here means RofO generation state, matching the on-screen badge.
        $status = strtolower(trim((string) $request->query('status', '')));
        if ($status === 'generated') {
            $query->whereRaw("ISNULL(rofo_status, '') = ?", [LandRecommendation::ROFO_GENERATED])
                  ->whereRaw("UPPER(ISNULL(type, '')) <> 'OSS'");
        } elseif ($status === 'pending') {
            $query->whereRaw("ISNULL(rofo_status, '') <> ?", [LandRecommendation::ROFO_GENERATED])
                  ->whereRaw("UPPER(ISNULL(type, '')) <> 'OSS'");
        } elseif ($status === 'oss') {
            $query->whereRaw("UPPER(ISNULL(type, '')) = 'OSS'");
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->query('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->query('end_date'));
        }

        return $query->orderByDesc('created_at');
    }

    /**
     * Flatten one record into the export column keys.
     */
    private function exportRow(LandRecommendation $rec, int $sn): array
    {
        $isOss = strtoupper($rec->type ?? '') === 'OSS';

        return [
            'sn'             => $sn,
            'file_number'    => $rec->file_number,
            'source'         => $isOss ? 'OSS' : 'Land',
            'applicant_name' => $rec->applicant_name,
            'purpose'        => $rec->purpose_of_clause,
            'location'       => $rec->display_location,
            'plot_number'    => $rec->plot_number,
            'layout_plan_no' => $rec->layout_plan_no,
            'term'           => $rec->term,
            'ground_rent'    => number_format((float) $rec->ground_rent, 2),
            'dev_period'     => $rec->development_period,
            'survey_fees'    => number_format((float) $rec->survey_fees, 2),
            'dev_value'      => number_format((float) $rec->development_value, 2),
            'dev_charge'     => number_format((float) $rec->development_charge, 2),
            'status'         => $isOss
                ? 'PRINT READY'
                : ($rec->rofo_status === LandRecommendation::ROFO_GENERATED ? 'APPROVED' : 'PENDING'),
            'approved_on'    => $isOss ? '' : ($rec->approved_at ? $rec->approved_at->format('Y-m-d h:i A') : 'N/A'),
            'created_by'     => $rec->creator->name ?? 'System',
            'paper_code'     => $isOss ? 'N/A' : ($rec->land_rofo_serial_no ?: 'Unassigned'),
            'date_generated' => $rec->created_at ? $rec->created_at->format('Y-m-d h:i A') : 'N/A',
        ];
    }

    public function export(Request $request)
    {
        $ossViewOnly = $request->query('view') === 'only';
        $columns = $this->exportColumns();
        $query = $this->buildExportQuery($request, $ossViewOnly);

        // JSON feed for the export preview modal (client-side CSV + PDF).
        if ($request->query('format') === 'json') {
            $rows = [];
            $sn = 0;
            $query->chunk(500, function ($chunk) use (&$rows, &$sn) {
                foreach ($chunk as $rec) {
                    $rows[] = $this->exportRow($rec, ++$sn);
                }
            });

            return response()->json([
                'success' => true,
                'columns' => $columns,
                'data'    => $rows,
                'count'   => count($rows),
            ]);
        }

        $filename = ($ossViewOnly ? 'oss-rofo' : 'land-rofo') . '-export-' . now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
        ];

        $callback = function () use ($query, $columns) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel renders the ₦ sign and other characters correctly
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_column($columns, 'label'));

            $sn = 0;
            $query->chunk(500, function ($rows) use ($out, $columns, &$sn) {
                foreach ($rows as $rec) {
                    $row = $this->exportRow($rec, ++$sn);
                    fputcsv($out, array_map(fn ($column) => $row[$column['key']] ?? '', $columns));
                }
            });

            fclose($out);
        };

        return response()->streamDownload($callback, $filename, $headers);
    }

    public function assignSecurityPaperCode(Request $request, $id)
    {
        $request->validate([
            'paper_code' => 'required|string|exists:sqlsrv.global_security_paper_codes,paper_code',
        ]);

        $recommendation = LandRecommendation::findOrFail($id);

        DB::connection('sqlsrv')->beginTransaction();
        try {
            // Check if paper code is already used
            $serial = DB::connection('sqlsrv')->table('global_security_paper_codes')
                ->where('paper_code', $request->paper_code)
                ->first();

            if ($serial->is_used) {
                return response()->json(['success' => false, 'message' => 'Security paper code already in use.'], 422);
            }

            // If recommendation already has a paper code, mark the old one as unused
            if ($recommendation->land_rofo_serial_no) {
                DB::connection('sqlsrv')->table('global_security_paper_codes')
                    ->where('paper_code', $recommendation->land_rofo_serial_no)
                    ->update([
                        'is_used' => false,
                        'assigned_to_type' => null,
                        'assigned_to_id' => null,
                        'assigned_by' => null,
                        'assigned_at' => null,
                    ]);
            }

            // Assign new paper code
            $recommendation->update(['land_rofo_serial_no' => $request->paper_code]);

            DB::connection('sqlsrv')->table('global_security_paper_codes')
                ->where('paper_code', $request->paper_code)
                ->update([
                    'is_used' => true,
                    'assigned_to_type' => 'LandRecommendation',
                    'assigned_to_id' => $recommendation->id,
                    'assigned_by' => Auth::id(),
                    'assigned_at' => now(),
                ]);

            // Also update security_codes table for tracking/linking
            DB::connection('sqlsrv')->table('security_codes')->insert([
                'code' => 'L-' . $request->paper_code, // Use L- prefix for Land
                'security_paper_code' => $request->paper_code,
                'used_security_paper_code' => $request->paper_code,
                'is_used' => true,
                'assigned_to' => 'Land ROFO',
                'assigned_by' => Auth::id(),
                'assigned_at' => now(),
                'file_number' => $recommendation->file_number,
                'document_id' => $recommendation->id,
                'document_type' => 'Land ROFO',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::connection('sqlsrv')->commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to assign security paper code. ' . $e->getMessage()], 500);
        }
    }

    public function resetSecurityPaperCode($id)
    {
        $recommendation = LandRecommendation::findOrFail($id);

        if (!$recommendation->land_rofo_serial_no) {
            return response()->json(['success' => false, 'message' => 'No security paper code assigned to reset.'], 422);
        }

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $oldCode = $recommendation->land_rofo_serial_no;

            // Mark the old code as unused in global_security_paper_codes
            DB::connection('sqlsrv')->table('global_security_paper_codes')
                ->where('paper_code', $oldCode)
                ->update([
                    'is_used' => false,
                    'assigned_to_type' => null,
                    'assigned_to_id' => null,
                    'assigned_by' => null,
                    'assigned_at' => null,
                ]);

            // Remove from security_codes table
            DB::connection('sqlsrv')->table('security_codes')
                ->where('security_paper_code', $oldCode)
                ->where('assigned_to', 'Land ROFO')
                ->delete();

            // Clear the paper code on the recommendation
            $recommendation->update(['land_rofo_serial_no' => null]);

            DB::connection('sqlsrv')->commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to reset security paper code. ' . $e->getMessage()], 500);
        }
    }

    public function generate(Request $request, $id)
    {
        $recommendation = LandRecommendation::findOrFail($id);
        
        if ($recommendation->status !== LandRecommendation::STATUS_APPROVED) {
            return response()->json([
                'success' => false,
                'message' => 'Recommendation must be approved before generating ROFO.'
            ], 403);
        }

        $validated = $request->validate([
            'rofo_survey_fees' => 'nullable|numeric',
            'rofo_dev_charge' => 'nullable|numeric',
            'rofo_director_survey' => 'nullable|string|in:YES,NO',
            'rofo_licensed_surveyor' => 'nullable|string|in:YES,NO',
            'rofo_land_use_category' => 'nullable|string',
            'rofo_date_generated' => 'nullable|date',
            'rofo_time_generated' => 'nullable|string',
            'land_use_id' => 'nullable|exists:sqlsrv.land_uses,id',
            'purpose_id' => 'nullable|exists:sqlsrv.purposes,id',
        ]);

        if ($request->filled('land_use_id')) {
            $lu = \App\Models\LandUse::find($request->land_use_id);
            if ($lu) $validated['land_use'] = $lu->landuse;
        }

        if ($request->filled('purpose_id')) {
            $p = \App\Models\Purpose::find($request->purpose_id);
            if ($p) $validated['purpose_of_clause'] = $p->name;
        }

        // Use stored values if the request is empty (quick-generate from index)
        $mergedDate = $request->rofo_date_generated ?: $recommendation->rofo_date_generated;
        $mergedTime = $request->rofo_time_generated ?: $recommendation->rofo_time_generated;

        $generatedAt = now();
        if ($mergedDate && $mergedTime) {
            $generatedAt = \Carbon\Carbon::parse($mergedDate . ' ' . $mergedTime);
        } elseif ($mergedDate) {
            $generatedAt = \Carbon\Carbon::parse($mergedDate);
        }

        // Fill missing validated fields from stored record when quick-generating
        if (empty($validated['rofo_director_survey']))  $validated['rofo_director_survey']  = $recommendation->rofo_director_survey;
        if (empty($validated['rofo_licensed_surveyor'])) $validated['rofo_licensed_surveyor'] = $recommendation->rofo_licensed_surveyor;
        if (empty($validated['rofo_survey_fees']))       $validated['rofo_survey_fees']       = $recommendation->survey_fees ?? $recommendation->preparation_fees;
        if (empty($validated['rofo_dev_charge']))        $validated['rofo_dev_charge']        = $recommendation->development_charge;
        if (empty($validated['rofo_land_use_category'])) $validated['rofo_land_use_category'] = $recommendation->land_use;

        $recommendation->update(array_merge($validated, [
            'rofo_status'        => LandRecommendation::ROFO_GENERATED,
            'rofo_generated_at'  => $generatedAt,
            'rofo_date_generated'=> $mergedDate,
            'rofo_time_generated'=> $mergedTime,
        ]));

        app(RofoPraSyncer::class)->syncLand($recommendation->fresh());

        return response()->json(['success' => true]);
    }

    public function print(Request $request, $id)
    {
        $recommendation = LandRecommendation::findOrFail($id);

        // Resolve land_use text from land_use_id if the text column is empty
        if (empty($recommendation->land_use) && $recommendation->land_use_id) {
            $lu = \App\Models\LandUse::find($recommendation->land_use_id);
            if ($lu) $recommendation->land_use = $lu->landuse;
        }

        // Resolve purpose_of_clause text from purpose_id if the text column is empty
        if (empty($recommendation->purpose_of_clause) && $recommendation->purpose_id) {
            $p = \App\Models\Purpose::find($recommendation->purpose_id);
            if ($p) $recommendation->purpose_of_clause = $p->name;
        }

        // Bypass limit check for Certified True Copy
        $isCTC = $request->query('status') === 'CTC' || $request->query('isCTC') == 1;
        // Generate security code for this print
        $securityCodeService = app(\App\Services\SecurityCodeService::class);
        $securityCode = $securityCodeService->getOrGenerateForDocument(
            $recommendation->file_number,
            $recommendation->id,
            'Land ROFO'
        );

        if (!$isCTC && $recommendation->rofo_print_count >= 2) {
            abort(403, 'Maximum ROFO print limit reached.');
        }

        return view('land_rofos.templates.rofo_print', compact('recommendation', 'securityCode'));
    }

    public function unprintedJson()
    {
        // File numbers already batch-printed
        $printed = DB::connection('sqlsrv')->table('print_logs')
            ->where('document_type', 'Land ROFO')
            ->where('print_type', 'LandRofoBatch')
            ->pluck('reference_number')
            ->map(fn($r) => strtoupper(trim((string) $r)))
            ->unique()
            ->all();

        $records = LandRecommendation::select([
                'id', 'file_number', 'applicant_name', 'location', 'plot_number',
                'land_rofo_serial_no', 'rofo_status',
            ])
            ->where('rofo_status', LandRecommendation::ROFO_GENERATED)
            ->get()
            ->filter(fn($r) => !in_array(strtoupper(trim((string) $r->file_number)), $printed))
            ->values();

        return response()->json(['success' => true, 'data' => $records, 'count' => $records->count()]);
    }

    public function batchPrint(Request $request)
    {
        $ids = array_filter((array) $request->input('ids', []), 'is_numeric');

        $records = LandRecommendation::whereIn('id', $ids)
            ->where('rofo_status', LandRecommendation::ROFO_GENERATED)
            ->get();

        // Use the same service the individual print uses so codes are consistent
        $securityCodeService = app(\App\Services\SecurityCodeService::class);
        $securityCodes = [];
        foreach ($records as $rec) {
            $sc = $securityCodeService->getOrGenerateForDocument(
                $rec->file_number,
                $rec->id,
                'Land ROFO'
            );
            if ($sc) {
                $securityCodes[$rec->id] = $sc;
            }
        }

        return view('land_rofos.templates.batch_rofo_print', compact('records', 'securityCodes'));
    }

    public function batchPrintLog(Request $request)
    {
        $ids = array_filter((array) $request->input('ids', []), 'is_numeric');

        if (empty($ids)) {
            return response()->json(['success' => false, 'message' => 'No records specified.'], 422);
        }

        $records = LandRecommendation::whereIn('id', $ids)
            ->where('rofo_status', LandRecommendation::ROFO_GENERATED)
            ->get();

        DB::connection('sqlsrv')->beginTransaction();
        try {
            foreach ($records as $rec) {
                foreach (['Original', 'Duplicate', 'Triplicate'] as $copy) {
                    PrintLog::create([
                        'reference_number' => $rec->file_number,
                        'document_type'    => 'Land ROFO',
                        'print_type'       => 'LandRofoBatch',
                        'status'           => $copy,
                        'user_id'          => Auth::id(),
                    ]);
                }
                $rec->increment('rofo_print_count');
            }
            DB::connection('sqlsrv')->commit();
            return response()->json(['success' => true, 'count' => $records->count()]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function logPrint(Request $request, $id)
    {
        $recommendation = LandRecommendation::findOrFail($id);

        $status = $request->query('status', 'Original');
        $isCTC = $status === 'CTC' || $request->query('isCTC') == 1;

        // Only enforce limits for non-CTC prints
        if (!$isCTC && $recommendation->rofo_print_count >= 2) {
            return response()->json([
                'success' => false,
                'message' => 'Maximum ROFO print limit reached.'
            ], 403);
        }

        DB::beginTransaction();
        try {
            PrintLog::create([
                'reference_number' => $recommendation->file_number,
                'document_type' => 'Land ROFO',
                'print_type' => 'Individual',
                'status' => $status,
                'user_id' => Auth::id()
            ]);

            // Only increment count for non-CTC prints
            if ($status !== 'CTC') {
                $recommendation->increment('rofo_print_count');
            }

            DB::commit();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error logging ROFO print: ' . $e->getMessage()
            ], 500);
        }
    }
}

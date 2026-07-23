<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\LandRecommendation;
use App\Models\LandUse;
use App\Models\Purpose;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\PrintLog;

class LandRecommendationController extends Controller
{
    public function index(Request $request)
    {
        $viewType = strtoupper(trim((string) $request->query('type', '')));
        if (!in_array($viewType, ['OSS', 'ROFO'], true)) {
            return redirect()->route('home');
        }

        $ossHasIsDeleted = false;
        try {
            $ossHasIsDeleted = Schema::connection('sqlsrv')->hasColumn('oss_applications', 'is_deleted');
        } catch (\Throwable $e) {
            $ossHasIsDeleted = false;
        }

        $applyOssChangeOfNameOriginFilter = function ($builder) use ($ossHasIsDeleted) {
            $builder->whereExists(function ($subQuery) use ($ossHasIsDeleted) {
                $subQuery->select(DB::raw('1'))
                    ->from('oss_applications as oa')
                    ->whereRaw("UPPER(LTRIM(RTRIM(ISNULL(oa.file_no, '')))) = UPPER(LTRIM(RTRIM(ISNULL(land_recommendations.file_number, ''))))")
                    ->where('oa.system_source', 'OSSOPCHANGEOFNAME');

                if ($ossHasIsDeleted) {
                    $subQuery->where(function ($q) {
                        $q->whereNull('oa.is_deleted')
                          ->orWhere('oa.is_deleted', 0);
                    });
                }
            });
        };

        $query = LandRecommendation::with('creator');
        $isOssView = $viewType === 'OSS';

        if ($isOssView) {
            $ossAddressSubSql = "(
                SELECT
                    file_no,
                    address,
                    residential_address,
                    correspondence_address,
                    ROW_NUMBER() OVER (
                        PARTITION BY UPPER(LTRIM(RTRIM(ISNULL(file_no, ''))))
                        ORDER BY id DESC
                    ) as rn
                FROM oss_applications
                WHERE system_source = 'OSSOPCHANGEOFNAME'";

            if ($ossHasIsDeleted) {
                $ossAddressSubSql .= " AND (is_deleted IS NULL OR is_deleted = 0)";
            }

            $ossAddressSubSql .= "
            ) as oa_addr";

            $query->leftJoin(DB::raw($ossAddressSubSql), function ($join) {
                $join->whereRaw("oa_addr.rn = 1")
                    ->whereRaw("UPPER(LTRIM(RTRIM(ISNULL(oa_addr.file_no, '')))) = UPPER(LTRIM(RTRIM(ISNULL(land_recommendations.file_number, ''))))");
            });

            $query->select('land_recommendations.*')
                ->selectRaw("COALESCE(
                    NULLIF(LTRIM(RTRIM(ISNULL(land_recommendations.applicant_address, ''))), ''),
                    NULLIF(LTRIM(RTRIM(ISNULL(oa_addr.address, ''))), ''),
                    NULLIF(LTRIM(RTRIM(ISNULL(oa_addr.residential_address, ''))), ''),
                    NULLIF(LTRIM(RTRIM(ISNULL(oa_addr.correspondence_address, ''))), '')
                ) as resolved_applicant_address");

            $query->whereRaw("UPPER(ISNULL(type, '')) = ?", ['OSS']);
            $applyOssChangeOfNameOriginFilter($query);
        } else {
            $query->where(function ($q) {
                $q->whereNull('type')
                  ->orWhereRaw("UPPER(ISNULL(type, '')) <> ?", ['OSS']);
            });
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
        $PageTitle ='Recommendation For Grant Of Statutory Right Of Occupancy';

        $statsQuery = LandRecommendation::query();
        if ($isOssView) {
            $statsQuery->whereRaw("UPPER(ISNULL(type, '')) = ?", ['OSS']);
            $applyOssChangeOfNameOriginFilter($statsQuery);
        } else {
            $statsQuery->where(function ($q) {
                $q->whereNull('type')
                  ->orWhereRaw("UPPER(ISNULL(type, '')) <> ?", ['OSS']);
            });
        }

        $stats = [
            'total' => (clone $statsQuery)->count(),
            'pending' => (clone $statsQuery)->where('status', LandRecommendation::STATUS_PENDING)->count(),
            'approved' => (clone $statsQuery)->where('status', LandRecommendation::STATUS_APPROVED)->count(),
            'total_ground_rent' => (clone $statsQuery)->sum('ground_rent')
        ];

        return view('land_recommendations.index', compact('recommendations', 'PageTitle', 'stats', 'isOssView'));
    }

    /**
     * Column metadata shared by the JSON preview, the client-side CSV/PDF and the
     * streamed server CSV, so all three stay in step.
     * `pdfWidth` is in mm — A4 landscape gives ~277mm of usable width.
     */
    private function exportColumns(): array
    {
        return [
            ['key' => 'sn',             'label' => 'S/N',              'pdfWidth' => 9,  'wrap' => false],
            ['key' => 'file_number',    'label' => 'File Number',      'pdfWidth' => 26, 'wrap' => false],
            ['key' => 'applicant_name', 'label' => 'Applicant Name',   'pdfWidth' => 34],
            ['key' => 'purpose',        'label' => 'Purpose Clause',   'pdfWidth' => 22],
            // No pdfWidth: Location is the flexible column and absorbs the
            // remaining page width (see buildColumnStyles in records_export.js).
            ['key' => 'location',       'label' => 'Location'],
            ['key' => 'address',        'label' => 'Applicant Address','pdfWidth' => 32],
            ['key' => 'plot_number',    'label' => 'Plot No',          'pdfWidth' => 12],
            ['key' => 'layout_plan_no', 'label' => 'Layout Plan',      'pdfWidth' => 14],
            ['key' => 'term',           'label' => 'Term',             'pdfWidth' => 10],
            ['key' => 'ground_rent',    'label' => 'Ground Rent',      'pdfWidth' => 18, 'align' => 'right'],
            ['key' => 'dev_period',     'label' => 'Dev. Period',      'pdfWidth' => 12],
            ['key' => 'prep_fees',      'label' => 'Prep. Fees',       'pdfWidth' => 18, 'align' => 'right'],
            ['key' => 'dev_value',      'label' => 'Dev. Value',       'pdfWidth' => 18, 'align' => 'right'],
            ['key' => 'status',         'label' => 'Status',           'pdfWidth' => 14],
            ['key' => 'created_by',     'label' => 'Created By',       'pdfWidth' => 18],
            ['key' => 'date_generated', 'label' => 'Date Generated',   'pdfWidth' => 18],
            ['key' => 'application_date','label' => 'Application Date','pdfWidth' => 18],
        ];
    }

    /**
     * Flatten one record into the export column keys.
     */
    private function exportRow(LandRecommendation $rec, int $sn, bool $isOssView): array
    {
        return [
            'sn'               => $sn,
            'file_number'      => $rec->file_number,
            'applicant_name'   => $rec->applicant_name,
            'purpose'          => $rec->purpose_of_clause,
            'location'         => $rec->display_location,
            'address'          => $rec->resolved_applicant_address ?? $rec->applicant_address ?? 'N/A',
            'plot_number'      => $rec->plot_number,
            'layout_plan_no'   => $rec->layout_plan_no,
            'term'             => $rec->term,
            'ground_rent'      => number_format((float) $rec->ground_rent, 2),
            'dev_period'       => $rec->development_period,
            'prep_fees'        => number_format((float) $rec->preparation_fees, 2),
            'dev_value'        => number_format((float) $rec->development_value, 2),
            'status'           => $isOssView
                ? 'GENERATED'
                : ($rec->status === LandRecommendation::STATUS_APPROVED ? 'APPROVED' : 'PENDING'),
            'created_by'       => $rec->creator->name ?? 'System',
            'date_generated'   => $rec->created_at ? $rec->created_at->format('Y-m-d h:i A') : 'N/A',
            'application_date' => $rec->application_date
                ? $rec->application_date->format('Y-m-d')
                : ($rec->created_at ? $rec->created_at->format('Y-m-d') : 'N/A'),
        ];
    }

    /**
     * Export the Land / OSS recommendation register.
     * `format=json` feeds the preview modal (client-side CSV + PDF);
     * otherwise a UTF-8 CSV is streamed straight to the browser.
     */
    public function export(Request $request)
    {
        $viewType = strtoupper(trim((string) $request->query('type', '')));
        if (!in_array($viewType, ['OSS', 'ROFO'], true)) {
            return redirect()->route('home');
        }

        $isOssView = $viewType === 'OSS';

        $ossHasIsDeleted = false;
        try {
            $ossHasIsDeleted = Schema::connection('sqlsrv')->hasColumn('oss_applications', 'is_deleted');
        } catch (\Throwable $e) {
            $ossHasIsDeleted = false;
        }

        $query = LandRecommendation::with('creator');

        if ($isOssView) {
            // Same resolved-address join the index page uses, so the exported
            // address column matches what is on screen.
            $ossAddressSubSql = "(
                SELECT
                    file_no,
                    address,
                    residential_address,
                    correspondence_address,
                    ROW_NUMBER() OVER (
                        PARTITION BY UPPER(LTRIM(RTRIM(ISNULL(file_no, ''))))
                        ORDER BY id DESC
                    ) as rn
                FROM oss_applications
                WHERE system_source = 'OSSOPCHANGEOFNAME'";

            if ($ossHasIsDeleted) {
                $ossAddressSubSql .= " AND (is_deleted IS NULL OR is_deleted = 0)";
            }

            $ossAddressSubSql .= "
            ) as oa_addr";

            $query->leftJoin(DB::raw($ossAddressSubSql), function ($join) {
                $join->whereRaw("oa_addr.rn = 1")
                    ->whereRaw("UPPER(LTRIM(RTRIM(ISNULL(oa_addr.file_no, '')))) = UPPER(LTRIM(RTRIM(ISNULL(land_recommendations.file_number, ''))))");
            });

            $query->select('land_recommendations.*')
                ->selectRaw("COALESCE(
                    NULLIF(LTRIM(RTRIM(ISNULL(land_recommendations.applicant_address, ''))), ''),
                    NULLIF(LTRIM(RTRIM(ISNULL(oa_addr.address, ''))), ''),
                    NULLIF(LTRIM(RTRIM(ISNULL(oa_addr.residential_address, ''))), ''),
                    NULLIF(LTRIM(RTRIM(ISNULL(oa_addr.correspondence_address, ''))), '')
                ) as resolved_applicant_address");

            $query->whereRaw("UPPER(ISNULL(land_recommendations.type, '')) = ?", ['OSS']);
            $query->whereExists(function ($subQuery) use ($ossHasIsDeleted) {
                $subQuery->select(DB::raw('1'))
                    ->from('oss_applications as oa')
                    ->whereRaw("UPPER(LTRIM(RTRIM(ISNULL(oa.file_no, '')))) = UPPER(LTRIM(RTRIM(ISNULL(land_recommendations.file_number, ''))))")
                    ->where('oa.system_source', 'OSSOPCHANGEOFNAME');

                if ($ossHasIsDeleted) {
                    $subQuery->where(function ($q) {
                        $q->whereNull('oa.is_deleted')
                          ->orWhere('oa.is_deleted', 0);
                    });
                }
            });
        } else {
            $query->where(function ($q) {
                $q->whereNull('type')
                  ->orWhereRaw("UPPER(ISNULL(type, '')) <> ?", ['OSS']);
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

        $status = strtolower(trim((string) $request->query('status', '')));
        if (in_array($status, [LandRecommendation::STATUS_PENDING, LandRecommendation::STATUS_APPROVED], true)) {
            $query->where('land_recommendations.status', $status);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('land_recommendations.created_at', '>=', $request->query('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->whereDate('land_recommendations.created_at', '<=', $request->query('end_date'));
        }

        $query->orderByDesc('land_recommendations.created_at');

        $columns = $this->exportColumns();

        if ($request->query('format') === 'json') {
            $rows = [];
            $sn = 0;
            $query->chunk(500, function ($chunk) use (&$rows, &$sn, $isOssView) {
                foreach ($chunk as $rec) {
                    $rows[] = $this->exportRow($rec, ++$sn, $isOssView);
                }
            });

            return response()->json([
                'success' => true,
                'columns' => $columns,
                'data'    => $rows,
                'count'   => count($rows),
            ]);
        }

        $filename = ($isOssView ? 'oss-recommendation' : 'land-recommendation')
            . '-export-' . now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
        ];

        $callback = function () use ($query, $columns, $isOssView) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel renders the ₦ sign and other characters correctly
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_column($columns, 'label'));

            $sn = 0;
            $query->chunk(500, function ($chunk) use ($out, $columns, $isOssView, &$sn) {
                foreach ($chunk as $rec) {
                    $row = $this->exportRow($rec, ++$sn, $isOssView);
                    fputcsv($out, array_map(fn ($column) => $row[$column['key']] ?? '', $columns));
                }
            });

            fclose($out);
        };

        return response()->streamDownload($callback, $filename, $headers);
    }

    public function create()
    {
        $PageTitle ='Recommendation For Grant Of Statutory Right Of Occupancy';
        $landUses = LandUse::orderBy('landuse')->get();
        return view('land_recommendations.form', compact('PageTitle', 'landUses'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'file_number' => 'required|string',
            'applicant_name' => 'required|string',
            'purpose_of_clause' => 'nullable|string',
            'purpose_id' => 'required|exists:sqlsrv.purposes,id',
            'location' => 'nullable|string',
            'term' => 'nullable|string',
            'cofo_year' => 'nullable|integer|min:1900|max:' . date('Y'),
            'ground_rent' => 'nullable|numeric',
            'effective_date' => 'nullable|date',
            'premium' => 'nullable|numeric',
            'development_period' => 'nullable|string',
            'survey_fees' => 'nullable|numeric',
            'preparation_fees' => 'nullable|numeric',
            'land_use' => 'nullable|string',
            'land_use_id' => 'required|exists:sqlsrv.land_uses,id',
            'meeting_date' => 'nullable|date',
            'recommendation' => 'nullable|string',
            'plot_number' => 'nullable|string',
            'house_no' => 'nullable|string',
            'street_name' => 'nullable|string',
            'district' => 'nullable|string',
            'state' => 'nullable|string',
            'layout_plan_no' => 'nullable|string',
            'development_value' => 'nullable|numeric',
            'development_charge' => 'nullable|numeric',
            'tracking_id' => 'nullable|string',
            'application_date' => 'required|date',
            'applicant_address' => 'required|string',
            'type' => 'nullable|string',
            'application_type' => 'nullable|string',
            'page' => 'nullable|string',
            'page_survey_report' => 'nullable|string',
            'survey_report' => 'nullable|string',
            'improvement' => 'nullable|string',
            'revision_period' => 'nullable|string',
            'time_of_erection' => 'nullable|string',
            'rofo_survey_method' => 'nullable|string|in:DIRECTOR,LICENSED',
            'rofo_date_generated' => 'nullable|date',
            'rofo_time_generated' => 'nullable|string',
            'premium' => 'nullable|numeric',
            'num_plots' => 'nullable|string',
            'file_title' => 'nullable|string',
            'premium_words' => 'nullable|string',
            'preparation_fees_words' => 'nullable|string',
            'plot_sizes' => 'nullable|string',
            'page_2' => 'nullable|string',
            'page_3' => 'nullable|string',
            'page_4' => 'nullable|string',
            'page_5' => 'nullable|string',
            'purpose_description' => 'nullable|string',
            'dimensions_text' => 'nullable|string',
        ]);

        // Map survey method radio to YES/NO flags
        $validated['rofo_director_survey']  = ($request->rofo_survey_method === 'DIRECTOR') ? 'YES' : 'NO';
        $validated['rofo_licensed_surveyor'] = ($request->rofo_survey_method === 'LICENSED') ? 'YES' : 'NO';
        unset($validated['rofo_survey_method']);

        if ($request->filled('land_use_id')) {
            $lu = LandUse::find($request->land_use_id);
            if ($lu) $validated['land_use'] = $lu->landuse;
        }

        if ($request->filled('purpose_id')) {
            $p = Purpose::find($request->purpose_id);
            if ($p) $validated['purpose_of_clause'] = $p->name;
        }

        $validated['created_by'] = Auth::id();
        $validated['updated_by'] = Auth::id();

        $recommendation = LandRecommendation::create($validated);

        return redirect()->route('land-recommendations.index', ['type' => 'ROFO'])
            ->with('success', 'Recommendation created successfully.');
    }

    public function show($id)
    {
        $recommendation = LandRecommendation::findOrFail($id);
        return view('land_recommendations.show', compact('recommendation'));
    }

    public function edit($id)
    {
        $recommendation = LandRecommendation::findOrFail($id);

        // Legacy records stored the district name inside the freeform `location`
        // field (e.g. "PLOT 47, FARI, DAWAKIN KUDU") before the structured
        // District/LGA builder existed, so `location` is almost never equal to
        // just the district name — it needs a substring match, not equality.
        // District catalog names are also often suffixed with "District" (e.g.
        // "Dawakin Kudu District") which never appears verbatim in the legacy
        // text, so that suffix is stripped before comparing.
        if (empty($recommendation->district) && !empty($recommendation->location)) {
            $locationUpper = strtoupper(trim($recommendation->location));

            $matchedDistrict = District::where('is_active', true)
                ->get()
                ->map(function ($district) {
                    $district->display_name = trim(preg_replace('/\s+District$/i', '', $district->name));
                    return $district;
                })
                ->filter(fn ($district) => $district->display_name !== '' && str_contains($locationUpper, strtoupper($district->display_name)))
                ->sortByDesc(fn ($district) => strlen($district->display_name))
                ->first();

            if ($matchedDistrict) {
                $recommendation->district = $matchedDistrict->display_name;
            }
        }

        $PageTitle ='Recommendation For Grant Of Statutory Right Of Occupancy';
        $landUses = LandUse::orderBy('landuse')->get();
        $purposes = [];
        if ($recommendation->land_use_id) {
            $purposes = Purpose::where('landuseid', $recommendation->land_use_id)->orderBy('name')->get();
        }
        return view('land_recommendations.form', compact('recommendation', 'PageTitle', 'landUses', 'purposes'));
    }

    public function update(Request $request, $id)
    {
        $recommendation = LandRecommendation::findOrFail($id);

        $validated = $request->validate([
            'file_number' => 'required|string',
            'applicant_name' => 'required|string',
            'purpose_of_clause' => 'nullable|string',
            'purpose_id' => 'required|exists:sqlsrv.purposes,id',
            'location' => 'nullable|string',
            'term' => 'nullable|string',
            'cofo_year' => 'nullable|integer|min:1900|max:' . date('Y'),
            'ground_rent' => 'nullable|numeric',
            'effective_date' => 'nullable|date',
            'premium' => 'nullable|numeric',
            'development_period' => 'nullable|string',
            'survey_fees' => 'nullable|numeric',
            'preparation_fees' => 'nullable|numeric',
            'land_use' => 'nullable|string',
            'land_use_id' => 'required|exists:sqlsrv.land_uses,id',
            'meeting_date' => 'nullable|date',
            'recommendation' => 'nullable|string',
            'plot_number' => 'nullable|string',
            'house_no' => 'nullable|string',
            'street_name' => 'nullable|string',
            'district' => 'nullable|string',
            'state' => 'nullable|string',
            'layout_plan_no' => 'nullable|string',
            'development_value' => 'nullable|numeric',
            'development_charge' => 'nullable|numeric',
            'tracking_id' => 'nullable|string',
            'application_date' => 'required|date',
            'applicant_address' => 'required|string',
            'edit_reason' => 'nullable|string',
            'type' => 'nullable|string',
            'page' => 'nullable|string',
            'page_survey_report' => 'nullable|string',
            'survey_report' => 'nullable|string',
            'improvement' => 'nullable|string',
            'revision_period' => 'nullable|string',
            'time_of_erection' => 'nullable|string',
            'rofo_survey_method' => 'nullable|string|in:DIRECTOR,LICENSED',
            'rofo_date_generated' => 'nullable|date',
            'rofo_time_generated' => 'nullable|string',
            'type' => 'nullable|string',
            'application_type' => 'nullable|string',
            'premium' => 'nullable|numeric',
            'num_plots' => 'nullable|string',
            'file_title' => 'nullable|string',
            'premium_words' => 'nullable|string',
            'preparation_fees_words' => 'nullable|string',
            'plot_sizes' => 'nullable|string',
            'page_2' => 'nullable|string',
            'page_3' => 'nullable|string',
            'page_4' => 'nullable|string',
            'page_5' => 'nullable|string',
            'purpose_description' => 'nullable|string',
            'dimensions_text' => 'nullable|string',
        ]);

        $validated['rofo_director_survey']  = ($request->rofo_survey_method === 'DIRECTOR') ? 'YES' : 'NO';
        $validated['rofo_licensed_surveyor'] = ($request->rofo_survey_method === 'LICENSED') ? 'YES' : 'NO';
        unset($validated['rofo_survey_method']);

        if ($request->filled('land_use_id')) {
            $lu = LandUse::find($request->land_use_id);
            if ($lu) $validated['land_use'] = $lu->landuse;
        }

        if ($request->filled('purpose_id')) {
            $p = Purpose::find($request->purpose_id);
            if ($p) $validated['purpose_of_clause'] = $p->name;
        }

        $validated['updated_by'] = Auth::id();

        $recommendation->update($validated);

        return redirect()->route('land-recommendations.index', ['type' => 'ROFO'])
            ->with('success', 'Recommendation updated successfully.');
    }

    public function approve($id)
    {
        $recommendation = LandRecommendation::findOrFail($id);
        $recommendation->update([
            'status' => LandRecommendation::STATUS_APPROVED,
            'approved_at' => now()
        ]);

        return response()->json(['success' => true]);
    }

    public function batchApprove(Request $request)
    {
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];

        $count = LandRecommendation::whereIn('id', $ids)
            ->where('status', LandRecommendation::STATUS_PENDING)
            ->update(['status' => LandRecommendation::STATUS_APPROVED, 'approved_at' => now()]);

        return response()->json(['success' => true, 'approved' => $count]);
    }

    public function print(Request $request, $id)
    {
        $recommendation = LandRecommendation::findOrFail($id);
        $isOssRecommendation = strtoupper((string) ($recommendation->type ?? '')) === 'OSS';

        if ($isOssRecommendation) {
            $formatNaira = static function ($value): string {
                if ($value === null || $value === '') {
                    return '';
                }

                $number = is_numeric($value)
                    ? (float) $value
                    : (float) preg_replace('/[^0-9.\-]/', '', (string) $value);

                return 'N' . number_format($number, 2);
            };

            $record = (object) [
                'applicant_name'    => strtoupper((string) ($recommendation->applicant_name ?? '')),
                'file_ref'          => (string) ($recommendation->file_number ?? ''),
                'purpose'           => strtoupper((string) ($recommendation->purpose_of_clause ?? $recommendation->land_use ?? '')),
                'location'          => (string) ($recommendation->location ?? ''),
                'plot_no'           => strtoupper((string) ($recommendation->plot_number ?? '')),
                'plan_no'           => strtoupper((string) ($recommendation->layout_plan_no ?? '')),
                'term'              => (string) ($recommendation->term ?? ''),
                'dev_value'         => $formatNaira($recommendation->development_value ?? null),
                'completion_time'   => (string) ($recommendation->development_period ?? ''),
                'ground_rent'       => $formatNaira($recommendation->ground_rent ?? null),
                'dev_charge'        => $formatNaira($recommendation->development_charge ?? null),
                'survey_charges'    => $formatNaira($recommendation->survey_fees ?? null),
                'director_reasons'  => (string) ($recommendation->recommendation ?? ''),
                'director_sign'     => '',
                'director_date'     => '',
                'ps_plot'           => strtoupper((string) ($recommendation->plot_number ?? '')),
                'ps_location'       => strtoupper((string) ($recommendation->location ?? '')),
                'ps_sign'           => '',
                'ps_date'           => '',
                'commissioner_name' => '',
                'commissioner_date' => '',
                'approval_status'   => '',
                'tracking_id'       => (string) ($recommendation->tracking_id ?? ''),
                'rofo_serial_no'    => (string) ($recommendation->land_rofo_serial_no ?? ''),
            ];

            return view('lands_one_stop_shop.partials.print_recommendation', compact('record'));
        }

        if (!$isOssRecommendation && $recommendation->status !== LandRecommendation::STATUS_APPROVED) {
            abort(403, 'Document must be approved before printing.');
        }

        // Print limit enforcement disabled for now.

        // Route by Application Type first; fall back to Recommendation Type
        $primaryAppType = $recommendation->application_type ?? null;

        $appTypeTemplates = [
            'Private Layout'                                     => 'land_recommendations.templates.application_for_plot_merger',
            'Plot Merger'                                        => 'land_recommendations.templates.application_for_plot_merger',
            'Plot Subdivision'                                   => 'land_recommendations.templates.application_for_plot_subdivision',
            'Plot Extension'                                     => 'land_recommendations.templates.application_for_plot_extension',
            'Temporary File No'             => 'land_recommendations.templates.application_for_temporary_file_no',
            'Ministry of Works'             => 'land_recommendations.templates.application_for_ministry_of_works',
            'Change of Purpose'             => 'land_recommendations.templates.application_for_change_of_purpose',
            // Legacy keys kept for old records
            'Statutory Right of Occupancy'                => 'land_recommendations.templates.application_for_statutory_right_of_occupancy',
            'Statutory Right of Occupancy (Residential)'  => 'land_recommendations.templates.application_for_statutory_right_of_occupancy',
            'Statutory Right of Occupancy (Commercial)'   => 'land_recommendations.templates.application_for_statutory_right_of_occupancy',
        ];

        if ($primaryAppType && isset($appTypeTemplates[$primaryAppType])) {
            $record = $recommendation;
            // Attach plotSizes as a collection for templates that use dimension tables
            $plotSizesRaw = $recommendation->plot_sizes ?? null;
            $record->plotSizes = $plotSizesRaw ? collect(json_decode($plotSizesRaw)) : collect([]);
            // Detect residential vs commercial from file number prefix
            $filePrefix = strtoupper(substr($recommendation->file_number ?? '', 0, 3));
            $isResidential = $filePrefix === 'RES';
            $isCommercial  = in_array($filePrefix, ['COM', 'IND', 'CON']);
            return view($appTypeTemplates[$primaryAppType], compact('recommendation', 'record', 'isResidential', 'isCommercial'));
        }

        if ($recommendation->type === 'Conversion') {
            return view('land_recommendations.templates.conversion_print', compact('recommendation'));
        }

        return view('land_recommendations.templates.standalone_print', compact('recommendation'));
    }

    public function logPrint(Request $request, $id)
    {
        $recommendation = LandRecommendation::findOrFail($id);

        $status = $request->query('status', 'Original');

        // Print limit enforcement disabled for now.

        DB::beginTransaction();
        try {
            PrintLog::create([
                'reference_number' => $recommendation->file_number,
                'document_type' => 'Land Recommendation',
                'print_type' => 'Individual',
                'status' => $status,
                'user_id' => Auth::id()
            ]);

            // Only increment count for non-CTC prints
            if ($status !== 'CTC') {
                $recommendation->increment('print_count');
            }

            DB::commit();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error logging print: ' . $e->getMessage()
            ], 500);
        }
    }
}

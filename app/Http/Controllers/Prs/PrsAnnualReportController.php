<?php

namespace App\Http\Controllers\Prs;

use App\Http\Controllers\Controller;
use App\Services\Prs\PrsSampleData;
use Illuminate\Http\Request;

/**
 * PRS Annual Progress Report — UI PROTOTYPE.
 *
 * Serves the report shell from PrsSampleData (a static fixture transcribed from
 * the 2025 PRS reports). No aggregation, no database access — the data layer is
 * deliberately deferred until the source-data questions in
 * docs/prs-2025/11-implementation-plan.md are resolved.
 *
 * When the aggregators land, swap PrsSampleData for PrsReportAggregator and the
 * views should not need to change.
 */
class PrsAnnualReportController extends Controller
{
    public function __construct(private PrsSampleData $data)
    {
    }

    public function index(Request $request)
    {
        $department = $request->query('dept', 'all');

        $sections = $this->data->sections();

        if ($department !== 'all') {
            $sections = array_values(array_filter(
                $sections,
                fn ($s) => $s['department'] === $department
            ));
        }

        return view('prs.annual_report.index', [
            'year'        => $this->data->year(),
            'sections'    => $sections,
            'highlights'  => $this->data->highlights(),
            'departments' => PrsSampleData::DEPARTMENTS,
            'department'  => $department,
        ]);
    }
}

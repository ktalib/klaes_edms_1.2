<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ToTStagingController extends Controller
{
    private function isGovernmentEntity($name)
    {
        if (!$name) return false;
        $name = strtoupper(trim($name));
        $patterns = [
            'GOVERNMENT',
            'JUDICIARY',
            'STATE',
            'FEDERAL',
            'MINISTRY',
            'DEPARTMENT',
            'AGENCY',
            'COMMISSION',
            'AUTHORITY'
        ];
        foreach ($patterns as $pattern) {
            if (strpos($name, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }

    public function index(Request $request)
    {
        $records = DB::connection('sqlsrv')->table('pra_tot_staging')
            ->where('status', 'pending')
            ->whereNotNull('op_name')
            ->where('op_name', '<>', '')
            ->whereNotNull('ro_name')
            ->where('ro_name', '<>', '')
            ->whereNotIn('ro_name', function ($query) {
                $query->select(DB::raw("DISTINCT ro_name"))
                    ->from('pra_tot_staging')
                    ->where(function ($q) {
                        $q->whereRaw("ro_name LIKE '%GOVERNMENT%'")
                          ->orWhereRaw("ro_name LIKE '%JUDICIARY%'")
                          ->orWhereRaw("ro_name LIKE '%FEDERAL%'")
                          ->orWhereRaw("ro_name LIKE '%MINISTRY%'")
                          ->orWhereRaw("ro_name LIKE '%DEPARTMENT%'")
                          ->orWhereRaw("ro_name LIKE '%AGENCY%'")
                          ->orWhereRaw("ro_name LIKE '%COMMISSION%'")
                          ->orWhereRaw("ro_name LIKE '%AUTHORITY%'");
                    });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        return view('maintenance.tot_staging', compact('records'));
    }

    public function generate(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['success' => false, 'message' => 'No records selected.']);
        }

        $results = [];
        $processedCount = 0;
        $skippedCount = 0;

        foreach ($ids as $id) {
            $staging = DB::connection('sqlsrv')->table('pra_tot_staging')->where('id', $id)->first();
            if (!$staging || $staging->is_processed)
                continue;

            if ($this->isGovernmentEntity($staging->ro_name)) {
                $skippedCount++;
                continue;
            }

            // Fetch OP record for full data
            $opRecord = DB::connection('sqlsrv')->table('pra')->where('id', $staging->op_id)->first();
            if (!$opRecord)
                continue;

            // Prepare ToT record
            $totData = (array) $opRecord;
            // Drop PK and SQL Server computed/managed columns that can't be inserted
            unset(
                $totData['id'],
                $totData['resolved_fileno'],
                $totData['updated_at'],
                $totData['deleted_at']
            );

            $totData['transaction_type'] = 'Transfer Of Title (OP)';
            $totData['Grantor'] = $staging->op_name;
            $totData['Grantee'] = $staging->ro_name;
            $totData['party_1'] = $staging->op_name;
            $totData['party_2'] = $staging->ro_name;
            $totData['created_at'] = now();
            $totData['remarks'] = (isset($totData['remarks']) ? $totData['remarks'] : '') . " | Auto-generated ToT from OP/RO mismatch (Staging ID: {$id})";

            // Insert into pra
            DB::connection('sqlsrv')->table('pra')->insert($totData);

            // Update staging
            DB::connection('sqlsrv')->table('pra_tot_staging')->where('id', $id)->update([
                'status' => 'processed',
                'is_processed' => 1,
                'processed_at' => now(),
                'processed_by' => auth()->id() ?? 1,
                'remarks' => 'Successfully generated ToT record.'
            ]);

            $processedCount++;
        }

        $msg = "Successfully generated {$processedCount} ToT records.";
        if ($skippedCount > 0) {
            $msg .= " ({$skippedCount} government/judiciary records skipped)";
        }

        return response()->json([
            'success' => true,
            'message' => $msg
        ]);
    }

    public function ignore(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['success' => false, 'message' => 'No records selected.']);
        }

        DB::connection('sqlsrv')->table('pra_tot_staging')->whereIn('id', $ids)->update([
            'status' => 'ignored',
            'is_processed' => 1,
            'processed_at' => now(),
            'processed_by' => auth()->id() ?? 1
        ]);

        return response()->json([
            'success' => true,
            'message' => "Ignored " . count($ids) . " records."
        ]);
    }
}



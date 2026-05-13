<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ToTStagingController extends Controller
{
    public function index(Request $request)
    {
        $records = DB::connection('sqlsrv')->table('pra_tot_staging')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

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

        foreach ($ids as $id) {
            $staging = DB::connection('sqlsrv')->table('pra_tot_staging')->where('id', $id)->first();
            if (!$staging || $staging->is_processed)
                continue;

            // Fetch OP record for full data
            $opRecord = DB::connection('sqlsrv')->table('pra')->where('id', $staging->op_id)->first();
            if (!$opRecord)
                continue;

            // Prepare ToT record
            $totData = (array) $opRecord;
            unset($totData['id']);

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

        return response()->json([
            'success' => true,
            'message' => "Successfully generated {$processedCount} ToT records."
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



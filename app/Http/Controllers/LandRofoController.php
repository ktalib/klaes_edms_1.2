<?php

namespace App\Http\Controllers;

use App\Models\LandRecommendation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\PrintLog;

class LandRofoController extends Controller
{
    public function index(Request $request)
    {
        // Only show approved recommendations
        $query = LandRecommendation::with('creator')
            ->where('status', LandRecommendation::STATUS_APPROVED);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('file_number', 'LIKE', "%{$search}%")
                  ->orWhere('applicant_name', 'LIKE', "%{$search}%")
                  ->orWhere('location', 'LIKE', "%{$search}%");
            });
        }

        $recommendations = $query->latest()->paginate(20);
        
        $PageTitle='Lands RofO';
        $landUses = \App\Models\LandUse::orderBy('landuse')->get();

        $stats = [
            'total_eligible' => LandRecommendation::where('status', LandRecommendation::STATUS_APPROVED)->count(),
            'pending_generation' => LandRecommendation::where('status', LandRecommendation::STATUS_APPROVED)
                ->where('rofo_status', LandRecommendation::ROFO_PENDING)->count(),
            'generated' => LandRecommendation::where('rofo_status', LandRecommendation::ROFO_GENERATED)->count(),
            'total_dev_charge' => LandRecommendation::where('rofo_status', LandRecommendation::ROFO_GENERATED)->sum('rofo_dev_charge')
        ];

        // Fetch available (unused) security paper codes sorted ascending
        $availableSerials = DB::connection('sqlsrv')->table('land_rofo_security_paper_codes')
            ->where('is_used', false)
            ->orderBy('paper_code', 'asc')
            ->get();

        return view('land_rofos.index', compact('recommendations', 'PageTitle', 'landUses', 'stats', 'availableSerials'));
    }

    public function assignSecurityPaperCode(Request $request, $id)
    {
        $request->validate([
            'paper_code' => 'required|string|exists:sqlsrv.land_rofo_security_paper_codes,paper_code',
        ]);

        $recommendation = LandRecommendation::findOrFail($id);

        DB::connection('sqlsrv')->beginTransaction();
        try {
            // Check if paper code is already used
            $serial = DB::connection('sqlsrv')->table('land_rofo_security_paper_codes')
                ->where('paper_code', $request->paper_code)
                ->first();

            if ($serial->is_used) {
                return response()->json(['success' => false, 'message' => 'Security paper code already in use.'], 422);
            }

            // If recommendation already has a paper code, mark the old one as unused
            if ($recommendation->land_rofo_serial_no) {
                DB::connection('sqlsrv')->table('land_rofo_security_paper_codes')
                    ->where('paper_code', $recommendation->land_rofo_serial_no)
                    ->update([
                        'is_used' => false,
                        'assigned_to' => null,
                        'assigned_by' => null,
                        'assigned_at' => null,
                    ]);
            }

            // Assign new paper code
            $recommendation->update(['land_rofo_serial_no' => $request->paper_code]);

            DB::connection('sqlsrv')->table('land_rofo_security_paper_codes')
                ->where('paper_code', $request->paper_code)
                ->update([
                    'is_used' => true,
                    'assigned_to' => $recommendation->id,
                    'assigned_by' => Auth::id(),
                    'assigned_at' => now(),
                ]);

            // Also update security_codes table for tracking/linking
            DB::connection('sqlsrv')->table('security_codes')->insert([
                'code' => 'L-' . $request->paper_code, // Use L- prefix for Lands
                'security_paper_code' => $request->paper_code,
                'used_security_paper_code' => $request->paper_code,
                'is_used' => true,
                'assigned_to' => 'Lands ROFO',
                'assigned_by' => Auth::id(),
                'assigned_at' => now(),
                'file_number' => $recommendation->file_number,
                'document_id' => $recommendation->id,
                'document_type' => 'Lands ROFO',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::connection('sqlsrv')->commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
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

        $generatedAt = now();
        if ($request->rofo_date_generated && $request->rofo_time_generated) {
            $generatedAt = \Carbon\Carbon::parse($request->rofo_date_generated . ' ' . $request->rofo_time_generated);
        }

        $recommendation->update(array_merge($validated, [
            'rofo_status' => LandRecommendation::ROFO_GENERATED,
            'rofo_generated_at' => $generatedAt
        ]));

        return response()->json(['success' => true]);
    }

    public function print(Request $request, $id)
    {
        $recommendation = LandRecommendation::findOrFail($id);
        
        if ($recommendation->rofo_status !== LandRecommendation::ROFO_GENERATED) {
            abort(403, 'ROFO must be generated before printing.');
        }

        // Bypass limit check for Certified True Copy
        $isCTC = $request->query('status') === 'CTC' || $request->query('isCTC') == 1;
        // Generate security code for this print
        $securityCodeService = app(\App\Services\SecurityCodeService::class);
        $securityCode = $securityCodeService->getOrGenerateForDocument(
            $recommendation->file_number,
            $recommendation->id,
            'Lands ROFO'
        );

        if (!$isCTC && $recommendation->rofo_print_count >= 2) {
            abort(403, 'Maximum ROFO print limit reached.');
        }

        return view('land_rofos.templates.rofo_print', compact('recommendation', 'securityCode'));
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

<?php

namespace App\Http\Controllers;

use App\Models\SltrRecommendation;
use App\Services\Pra\RofoPraSyncer;
use App\Services\SecurityPaperCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SltrRofoController extends Controller
{
    public function index(Request $request)
    {
        $query = SltrRecommendation::with('creator')
            ->where('status', SltrRecommendation::STATUS_APPROVED);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('sltr_number', 'LIKE', "%{$s}%")
                  ->orWhere('applicant_name', 'LIKE', "%{$s}%")
                  ->orWhere('location', 'LIKE', "%{$s}%");
            });
        }

        $recommendations = $query->latest()->paginate(20);

        $stats = [
            'total_eligible'      => SltrRecommendation::where('status', SltrRecommendation::STATUS_APPROVED)->count(),
            'pending_generation'  => SltrRecommendation::where('status', SltrRecommendation::STATUS_APPROVED)
                                        ->where('rofo_status', SltrRecommendation::ROFO_PENDING)->count(),
            'generated'           => SltrRecommendation::where('rofo_status', SltrRecommendation::ROFO_GENERATED)->count(),
            'total_ground_rent'   => SltrRecommendation::where('rofo_status', SltrRecommendation::ROFO_GENERATED)->sum('ground_rent'),
        ];

        $PageTitle = 'SLTR RofO Management';

        $canApprove = $this->userCanApprove();

        return view('sltr_rofos.index', compact('recommendations', 'stats', 'PageTitle', 'canApprove'));
    }

    /**
     * Only a Supper Admin (assign_role) or a user whose rank is "Director SLTR"
     * may reprint SLTR records.
     */
    private function userCanApprove(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        $roleNames = method_exists($user, 'assignedRoleNames') ? $user->assignedRoleNames() : [];
        if (\in_array('supper admin', $roleNames, true)) {
            return true;
        }

        return strcasecmp(trim((string) ($user->rank ?? '')), 'Director SLTR') === 0;
    }

    public function generate(Request $request, $id)
    {
        $rec = SltrRecommendation::findOrFail($id);

        if ($rec->status !== SltrRecommendation::STATUS_APPROVED) {
            return response()->json(['success' => false, 'message' => 'Recommendation must be approved before generating ROFO.'], 403);
        }

        $validated = $request->validate([
            'rofo_director_survey'  => 'nullable|string|in:YES,NO',
            'rofo_licensed_surveyor'=> 'nullable|string|in:YES,NO',
            'rofo_date_generated'   => 'nullable|date',
            'rofo_time_generated'   => 'nullable|string',
        ]);

        $generatedAt = now();
        if ($request->filled('rofo_date_generated')) {
            $time = $request->filled('rofo_time_generated') ? $request->rofo_time_generated : '00:00';
            $generatedAt = \Carbon\Carbon::parse($request->rofo_date_generated . ' ' . $time);
        }

        $rec->update(array_merge($validated, [
            'rofo_status'       => SltrRecommendation::ROFO_GENERATED,
            'rofo_generated_at' => $generatedAt,
            'rofo_date_generated'=> $request->rofo_date_generated ?? now()->toDateString(),
            'updated_by'        => Auth::id(),
        ]));

        app(RofoPraSyncer::class)->syncSltr($rec->fresh());

        return response()->json(['success' => true, 'message' => 'SLTR RofO generated successfully.']);
    }

    public function print(Request $request, $id)
    {
        $recommendation = SltrRecommendation::findOrFail($id);

        if ($recommendation->rofo_status !== SltrRecommendation::ROFO_GENERATED) {
            abort(403, 'ROFO must be generated before printing.');
        }

        return view('sltr_rofos.templates.rofo_print', compact('recommendation'));
    }

    public function assignSecurityPaperCode(Request $request, $id)
    {
        $request->validate([
            'paper_code' => 'required|string|exists:sqlsrv.global_security_paper_codes,paper_code',
        ]);

        $rec = SltrRecommendation::findOrFail($id);

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $serial = DB::connection('sqlsrv')->table('global_security_paper_codes')
                ->where('paper_code', $request->paper_code)->first();

            if (($serial->status ?? null) === 'voided') {
                DB::connection('sqlsrv')->rollBack();
                return response()->json(['success' => false, 'message' => 'That security paper was voided (' . SecurityPaperCodeService::label($serial->void_reason ?? null) . ') and cannot be reissued.'], 422);
            }

            if ($serial->is_used) {
                DB::connection('sqlsrv')->rollBack();
                return response()->json(['success' => false, 'message' => 'Security paper code already in use.'], 422);
            }

            if ($rec->sltr_rofo_serial_no) {
                DB::connection('sqlsrv')->table('global_security_paper_codes')
                    ->where('paper_code', $rec->sltr_rofo_serial_no)
                    ->update(['is_used' => false, 'assigned_to_type' => null, 'assigned_to_id' => null, 'assigned_by' => null, 'assigned_at' => null]);
            }

            $rec->update(['sltr_rofo_serial_no' => $request->paper_code]);

            DB::connection('sqlsrv')->table('global_security_paper_codes')
                ->where('paper_code', $request->paper_code)
                ->update(['is_used' => true, 'assigned_to_type' => 'SltrRecommendation', 'assigned_to_id' => $rec->id, 'assigned_by' => Auth::id(), 'assigned_at' => now()]);

            DB::connection('sqlsrv')->commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function resetSecurityPaperCode(Request $request, $id)
    {
        $request->validate([
            'reason' => ['required', 'string', Rule::in(array_keys(SecurityPaperCodeService::REASONS))],
            'note'   => ['nullable', 'string', 'max:500'],
        ]);

        $rec = SltrRecommendation::findOrFail($id);

        if (!$rec->sltr_rofo_serial_no) {
            return response()->json(['success' => false, 'message' => 'No security paper code assigned to reset.'], 422);
        }

        DB::connection('sqlsrv')->beginTransaction();
        try {
            $oldCode = $rec->sltr_rofo_serial_no;

            SecurityPaperCodeService::release($oldCode, $request->reason, 'SLTR ROFO', $request->note);

            $rec->update(['sltr_rofo_serial_no' => null]);

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success'          => true,
                'returned_to_pool' => SecurityPaperCodeService::returnsToPool($request->reason),
                'paper_code'       => $oldCode,
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to reset security paper code. ' . $e->getMessage()], 500);
        }
    }

    public function logPrint(Request $request, $id)
    {
        $recommendation = SltrRecommendation::findOrFail($id);

        $recommendation->increment('rofo_print_count');

        return response()->json(['success' => true]);
    }
}

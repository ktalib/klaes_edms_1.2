<?php

namespace App\Http\Controllers;

use App\Models\SltrRecommendation;
use App\Services\Pra\RofoPraSyncer;
use App\Services\SecurityPaperCodeService;
use App\Models\PrintLog;
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

        // Which rows have had their proof run off: the Print Manager opens on the
        // strength of it, and the White Copy closes with it. Keyed by sltr_number,
        // which is what the proof is logged against.
        $whiteCopyDone = array_flip(PrintLog::whiteCopyPrinted(
            'SLTR RofO',
            $recommendations->getCollection()->pluck('sltr_number')->filter()->all()
        ));

        return view('sltr_rofos.index', compact('recommendations', 'stats', 'PageTitle', 'canApprove', 'whiteCopyDone'));
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

    /**
     * The White Copy: a black & white proof of the SLTR letter, for vetting before
     * anything is put on security paper.
     *
     * The same record through the same template, with every mark of an issued
     * document taken off it — arms, QR, serial, copy designation, signature blocks —
     * and marked WHITE COPY instead. Nothing about official print state is touched:
     * the template omits the afterprint call to log-print, so no print_logs row is
     * written and the RofO does not move onto the Printed side.
     *
     * Recorded under its own document type so the proofing stage can be seen to be
     * done without any "is this printed?" query mistaking it for a real run.
     */
    /**
     * Store the date of issue on its own, for the White Copy card and the Print
     * Manager's Edit.
     *
     * Mirrors LandRofoController::saveIssueDate, including the apply rule: a date
     * already on a record is what an issued letter out in the world carries, so
     * 'missing' (the default) fills only the blanks and 'all' is sent only when an
     * operator has unlocked the field and confirmed the change.
     */
    public function saveIssueDate(Request $request)
    {
        $validated = $request->validate([
            'ids'        => 'required|array|min:1',
            'ids.*'      => 'integer',
            'issue_date' => 'required|date',
        ]);

        $date      = \Carbon\Carbon::parse($validated['issue_date'])->startOfDay();
        $overwrite = $request->input('issue_date_apply') === 'all';

        $records = SltrRecommendation::whereIn('id', $validated['ids'])->get();

        foreach ($records as $rec) {
            if (!$overwrite && filled($rec->date_issued)) {
                continue;
            }

            $rec->date_issued = $date;
            $rec->updated_by  = Auth::id();
            $rec->save();
        }

        return response()->json(['success' => true, 'count' => $records->count()]);
    }

    public function printWhiteCopy(Request $request, $id)
    {
        $view = $this->print($request, $id, true);

        PrintLog::logWhiteCopy(
            'SLTR RofO',
            SltrRecommendation::find($id)?->sltr_number,
            Auth::id()
        );

        return $view;
    }

    public function print(Request $request, $id, bool $whiteCopy = false)
    {
        $recommendation = SltrRecommendation::findOrFail($id);

        if ($recommendation->rofo_status !== SltrRecommendation::ROFO_GENERATED) {
            abort(403, 'ROFO must be generated before printing.');
        }

        $isWhiteCopy = $whiteCopy;

        return view('sltr_rofos.templates.rofo_print', compact('recommendation', 'isWhiteCopy'));
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

<?php

namespace App\Http\Controllers;

use App\Models\TitleStatusApplication;
use App\Services\RegrantTermService;
use App\Services\TitleStatusService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Re-grant management.
 *
 * Two views over the same subject:
 *   - "register"  — every Re-grant on record. These live in `title_status_applications`
 *                   with title_type = 'Re-grant'; there is no separate re-grant table.
 *   - "due"       — files whose statutory term has run out and which therefore ought to
 *                   have been re-granted, computed by {@see RegrantTermService}.
 *
 * Raising a Re-grant from the "due" list writes through {@see TitleStatusService} so the
 * record and its file flags are created exactly as the Title Status module would.
 */
class RegrantController extends Controller
{
    public function __construct(
        protected RegrantTermService $termService,
        protected TitleStatusService $titleStatusService
    ) {}

    public function index(Request $request): View
    {
        $tab    = $request->input('tab') === 'due' ? 'due' : 'register';
        $limit  = max(10, min((int) $request->input('limit', 25), 200));
        $search = trim((string) $request->input('search'));

        $filters = [
            'source' => trim((string) $request->input('source')),
            'term'   => trim((string) $request->input('term')),
            'search' => $search,
        ];

        if ($tab === 'due') {
            $records = $this->termService->due($filters, $limit)->appends($request->query());
        } else {
            $records = $this->registerQuery($search)
                ->paginate($limit)
                ->appends($request->query());
        }

        return view('regrant.index', [
            'tab'           => $tab,
            'records'       => $records,
            'limit'         => $limit,
            'search'        => $search,
            'filters'       => $filters,
            'stats'         => $this->stats(),
            'unassessable'  => $this->termService->unassessableCounts(),
            'currentYear'   => (int) now()->format('Y'),
        ]);
    }

    /** Every Re-grant application on record, newest first. */
    private function registerQuery(string $search)
    {
        return TitleStatusApplication::query()
            ->where('title_type', TitleStatusApplication::TYPE_REGRANT)
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('file_no', 'LIKE', "%{$search}%")
                        ->orWhere('see_fileno', 'LIKE', "%{$search}%")
                        ->orWhere('file_title', 'LIKE', "%{$search}%")
                        ->orWhere('applicant_name', 'LIKE', "%{$search}%")
                        ->orWhere('plot_no', 'LIKE', "%{$search}%")
                        ->orWhere('location', 'LIKE', "%{$search}%");
                });
            })
            ->orderByDesc('created_at');
    }

    /** Counters for the page header. */
    private function stats(): array
    {
        $register = TitleStatusApplication::query()
            ->where('title_type', TitleStatusApplication::TYPE_REGRANT)
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            });

        $due = $this->termService->dueCounts();

        return [
            'register_total' => (clone $register)->count(),
            'register_pending' => (clone $register)->where('status', TitleStatusApplication::STATUS_PENDING)->count(),
            'register_approved' => (clone $register)->where('status', TitleStatusApplication::STATUS_APPROVED)->count(),
            'due_total' => $due['total'],
            'due_cofo'  => $due['cofo'],
            'due_rofo'  => $due['rofo'],
        ];
    }

    /**
     * Raise a Re-grant against a file from the "due" list. Delegates to TitleStatusService
     * so the application row, the file flags and the linkage are written by the same code
     * path the Title Status module uses — this controller does not duplicate that logic.
     */
    public function raise(Request $request): JsonResponse
    {
        $fileNo = trim((string) $request->input('file_no', ''));
        $reason = trim((string) $request->input('reason', ''));

        if ($fileNo === '') {
            return response()->json(['success' => false, 'message' => 'File number is required.'], 422);
        }

        $existing = TitleStatusApplication::where('file_no', $fileNo)
            ->where('title_type', TitleStatusApplication::TYPE_REGRANT)
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->exists();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => "A Re-grant is already on record for {$fileNo}.",
            ], 409);
        }

        $details = DB::connection('sqlsrv')->table('file_indexings')
            ->where('file_number', $fileNo)
            ->first();

        $this->titleStatusService->recordRegrant($fileNo, '', [
            'url'              => 'land',
            'file_indexing_id' => $details->id ?? null,
            'prop_id'          => $details->prop_id ?? null,
            'file_title'       => $details->file_title ?? null,
            'applicant_name'   => $details->current_holder ?? ($details->original_holder ?? null),
            'plot_no'          => $details->plot_number ?? null,
            'district'         => $details->district ?? null,
            'lga'              => $details->lga ?? null,
            'location'         => $details->location ?? null,
            'land_use'         => $details->land_use_type ?? null,
        ]);

        if ($reason !== '') {
            TitleStatusApplication::where('file_no', $fileNo)
                ->where('title_type', TitleStatusApplication::TYPE_REGRANT)
                ->latest('id')
                ->limit(1)
                ->update(['reason' => $reason, 'updated_by' => Auth::id()]);
        }

        // The file now has a Re-grant on record, so it must drop out of the due list —
        // which is cached, and would otherwise keep offering it for up to 15 minutes.
        $this->termService->flushCache();

        return response()->json([
            'success' => true,
            'message' => "Re-grant raised for {$fileNo}.",
        ]);
    }
}

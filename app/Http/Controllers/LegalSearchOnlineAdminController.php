<?php

namespace App\Http\Controllers;
 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\LegalSearchOnlinePayment;
use App\Models\LegalSearchOnlineFeedback;
use App\Models\LegalSearchOnlineRequest;
use App\Services\LegalSearchApprovalService;

class LegalSearchOnlineAdminController extends Controller
{
    // ── Admin Dashboard ─────────────────────────────────────────────

    public function admin()
    {
        $PageTitle = 'Online Legal Search — Admin';

        // KPI stats
        $totalRevenue      = LegalSearchOnlinePayment::where('status', 'paid')->sum('amount'); // kobo
        $totalPaid         = LegalSearchOnlinePayment::where('status', 'paid')->count();
        $todayRevenue      = LegalSearchOnlinePayment::where('status', 'paid')
                                ->whereDate('paid_at', today())->sum('amount');
        $monthRevenue      = LegalSearchOnlinePayment::where('status', 'paid')
                                ->whereMonth('paid_at', now()->month)
                                ->whereYear('paid_at', now()->year)
                                ->sum('amount');
        $openFeedback      = LegalSearchOnlineFeedback::where('status', 'open')->count();

        $stats = [
            'total_revenue'  => $totalRevenue / 100,  // naira
            'today_revenue'  => $todayRevenue / 100,
            'month_revenue'  => $monthRevenue / 100,
            'total_paid'     => $totalPaid,
            'open_feedback'  => $openFeedback,
        ];

        $payments = LegalSearchOnlinePayment::with('user')
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('legal_search_online.admin', compact('PageTitle', 'stats', 'payments'));
    }

    // ── Feedback (admin view) ────────────────────────────────────────

    public function feedbackIndex()
    {
        $PageTitle = 'Online Legal Search — Feedback & Complaints';
        $isAdmin   = auth()->user()?->assign_role === 'Supper Admin';

        if ($isAdmin) {
            $feedbacks = LegalSearchOnlineFeedback::with(['user', 'resolver'])
                ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'in_progress' THEN 1 ELSE 2 END")
                ->orderByDesc('created_at')
                ->paginate(40);
        } else {
            $feedbacks = LegalSearchOnlineFeedback::where('user_id', auth()->id())
                ->orderByDesc('created_at')
                ->paginate(20);
        }

        return view('legal_search_online.feedback', compact('PageTitle', 'feedbacks', 'isAdmin'));
    }

    public function feedbackStore(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name'      => 'required|string|max:150',
            'email'     => 'required|email|max:255',
            'phone'     => 'nullable|string|max:30',
            'subject'   => 'required|string|max:200',
            'message'   => 'required|string|max:3000',
            'reference' => 'nullable|string|max:60',
        ]);

        LegalSearchOnlineFeedback::create(array_merge($validated, [
            'user_id' => $user?->id,
        ]));

        return back()->with('success', 'Your feedback has been submitted. We will respond within 2 business days.');
    }

    public function feedbackUpdate(Request $request, int $id)
    {
        abort_unless(auth()->user()?->assign_role === 'Supper Admin', 403);

        $feedback = LegalSearchOnlineFeedback::findOrFail($id);

        $validated = $request->validate([
            'status'         => 'required|in:open,in_progress,resolved',
            'admin_response' => 'nullable|string|max:3000',
        ]);

        $feedback->update(array_merge($validated, [
            'resolved_by' => $validated['status'] === 'resolved' ? auth()->id() : $feedback->resolved_by,
            'resolved_at' => $validated['status'] === 'resolved' ? ($feedback->resolved_at ?? now()) : $feedback->resolved_at,
        ]));

        return response()->json(['success' => true, 'message' => 'Feedback updated.']);
    }

    // ── Search Request Approval (Director / Deputy Director) ─────────

    /**
     * Approval queue for public Online Legal Search requests.
     *
     * Visible to any signed-in staff member so the queue can be monitored, but
     * only Directors / Deputy Directors (and super admins) may act on it.
     */
    public function requestsIndex(Request $request, LegalSearchApprovalService $approvalService)
    {
        $PageTitle = 'Online Legal Search — Search Requests';

        $status = $request->query('status', 'pending');
        if (!in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
            $status = 'pending';
        }

        $query = LegalSearchOnlineRequest::with('reviewer')->orderByDesc('created_at');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('request_no', 'like', '%' . $search . '%')
                    ->orWhere('file_number', 'like', '%' . $search . '%')
                    ->orWhere('requester_email', 'like', '%' . $search . '%')
                    ->orWhere('tracking_id', 'like', '%' . $search . '%');
            });
        }

        $requests = $query->paginate(30)->withQueryString();

        $counts = [
            'pending'  => LegalSearchOnlineRequest::where('status', 'pending')->count(),
            'approved' => LegalSearchOnlineRequest::where('status', 'approved')->count(),
            'rejected' => LegalSearchOnlineRequest::where('status', 'rejected')->count(),
        ];

        return view('legal_search_online.requests', [
            'PageTitle'  => $PageTitle,
            'requests'   => $requests,
            'counts'     => $counts,
            'status'     => $status,
            'q'          => $request->query('q', ''),
            'highlight'  => (int) $request->query('highlight', 0),
            'canApprove' => $approvalService->isApprover(auth()->user()),
        ]);
    }

    /**
     * Preview the report a request would release, so an approver can read it
     * before signing off. Reuses the on-screen Legal Search print template.
     */
    public function requestPreview(int $id, LegalSearchApprovalService $approvalService)
    {
        abort_unless($approvalService->isApprover(auth()->user()), 403, 'Only a Director or Deputy Director may review Online Legal Search requests.');

        $searchRequest = LegalSearchOnlineRequest::findOrFail($id);
        $report = $approvalService->buildReport($searchRequest);

        return view('legal_search_online.request_preview', [
            'searchRequest' => $searchRequest,
            'report'        => $report,
        ]);
    }

    /**
     * Approve a request: generates the report and emails it to the requester.
     */
    public function requestApprove(Request $request, int $id, LegalSearchApprovalService $approvalService)
    {
        $user = auth()->user();
        abort_unless($approvalService->isApprover($user), 403, 'Only a Director or Deputy Director may approve Online Legal Search requests.');

        $validated = $request->validate([
            'review_note' => 'nullable|string|max:1000',
        ]);

        $searchRequest = LegalSearchOnlineRequest::findOrFail($id);

        if (!$searchRequest->isPending()) {
            return back()->with('error', 'Request ' . $searchRequest->request_no . ' has already been ' . $searchRequest->status . '.');
        }

        try {
            $emailed = $approvalService->approve($searchRequest, $user, $validated['review_note'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with(
            $emailed ? 'success' : 'error',
            $emailed
                ? 'Request ' . $searchRequest->request_no . ' approved. The report was emailed to ' . $searchRequest->requester_email . '.'
                : 'Request ' . $searchRequest->request_no . ' was approved, but the email to ' . $searchRequest->requester_email . ' failed to send. Use "Resend report" to try again.'
        );
    }

    /**
     * Decline a request and tell the requester why.
     */
    public function requestReject(Request $request, int $id, LegalSearchApprovalService $approvalService)
    {
        $user = auth()->user();
        abort_unless($approvalService->isApprover($user), 403, 'Only a Director or Deputy Director may decline Online Legal Search requests.');

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $searchRequest = LegalSearchOnlineRequest::findOrFail($id);

        if (!$searchRequest->isPending()) {
            return back()->with('error', 'Request ' . $searchRequest->request_no . ' has already been ' . $searchRequest->status . '.');
        }

        $approvalService->reject($searchRequest, $user, $validated['rejection_reason']);

        return back()->with('success', 'Request ' . $searchRequest->request_no . ' declined. The requester has been notified.');
    }

    /**
     * Re-send the report for a request that was approved but whose email failed
     * (or that the requester never received).
     */
    public function requestResend(int $id, LegalSearchApprovalService $approvalService)
    {
        abort_unless($approvalService->isApprover(auth()->user()), 403);

        $searchRequest = LegalSearchOnlineRequest::findOrFail($id);

        if (!$searchRequest->isApproved()) {
            return back()->with('error', 'Only an approved request can have its report re-sent.');
        }

        try {
            $sent = $approvalService->resend($searchRequest);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with(
            $sent ? 'success' : 'error',
            $sent
                ? 'Report re-sent to ' . $searchRequest->requester_email . '.'
                : 'The report could not be emailed to ' . $searchRequest->requester_email . '. See the recorded error on the request.'
        );
    }
}

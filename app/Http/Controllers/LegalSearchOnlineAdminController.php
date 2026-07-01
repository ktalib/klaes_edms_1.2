<?php

namespace App\Http\Controllers;
 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\LegalSearchOnlinePayment;
use App\Models\LegalSearchOnlineFeedback;

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
}

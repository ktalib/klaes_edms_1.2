<?php

namespace App\Http\Controllers\Phs;

use App\Http\Controllers\Controller;
use App\Mail\PhsRequestApproved;
use App\Mail\PhsRequestRejected;
use App\Models\Phs\PhsInstitution;
use App\Models\Phs\PhsOnboardingRequest;
use App\Models\Phs\PhsTokenTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * KLAES staff-facing administration of the PHS programme.
 * Routes live in routes/app3.php inside the staff `auth` group.
 */
class PhsAdminController extends Controller
{
    public function index()
    {
        $PageTitle = 'PHS Administration — Institutions';
        $institutions = PhsInstitution::withCount('members')
            ->orderBy('name')
            ->get();

        $stats = [
            'institutions' => $institutions->count(),
            'active' => $institutions->where('status', 'active')->count(),
            'total_tokens' => (int) $institutions->sum('token_balance'),
            'pending_invoices' => PhsTokenTransaction::where('status', 'pending')->count(),
        ];

        return view('system-admin.phs.institutions', compact('PageTitle', 'institutions', 'stats'));
    }

    public function show($id)
    {
        $institution = PhsInstitution::with('members')->findOrFail($id);
        $transactions = $institution->transactions()->orderByDesc('id')->limit(100)->get();
        $searchLogs = $institution->searchLogs()->with('member')->orderByDesc('id')->limit(100)->get();
        $PageTitle = 'PHS Institution — ' . $institution->name;

        return view('system-admin.phs.institution-show', compact('PageTitle', 'institution', 'transactions', 'searchLogs'));
    }

    public function allocateTokens(Request $request, $id)
    {
        $data = $request->validate([
            'tokens' => ['required', 'integer'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $institution = PhsInstitution::findOrFail($id);
        $amount = (int) $data['tokens'];

        if ($amount === 0) {
            return back()->with('error', 'Token amount must be non-zero.');
        }

        if ($amount > 0) {
            $institution->addTokens($amount, 'adjustment', [
                'notes' => $data['notes'] ?? 'Manual allocation by KLAES staff',
                'approved_by' => Auth::id(),
            ]);
        } else {
            // Negative adjustment (manual debit) — write a signed ledger row directly.
            DB::connection('sqlsrv')->transaction(function () use ($institution, $amount, $data) {
                $institution->refresh();
                $newBalance = max(0, (int) $institution->token_balance + $amount);
                $institution->token_balance = $newBalance;
                $institution->save();
                $institution->transactions()->create([
                    'type' => 'adjustment',
                    'tokens' => $amount,
                    'balance_after' => $newBalance,
                    'status' => 'completed',
                    'notes' => $data['notes'] ?? 'Manual debit by KLAES staff',
                    'approved_by' => Auth::id(),
                ]);
            });
        }

        return back()->with('success', 'Token balance updated.');
    }

    public function suspend($id)
    {
        $institution = PhsInstitution::findOrFail($id);
        $institution->update(['status' => 'suspended']);
        return back()->with('success', 'Institution suspended. Its members can no longer log in.');
    }

    public function activate($id)
    {
        $institution = PhsInstitution::findOrFail($id);
        $institution->update(['status' => 'active']);
        return back()->with('success', 'Institution reactivated.');
    }

    public function invoices()
    {
        $PageTitle = 'PHS — Pending Invoices';
        $pending = PhsTokenTransaction::with('institution')
            ->where('status', 'pending')
            ->where('payment_method', 'invoice')
            ->orderByDesc('id')
            ->get();

        return view('system-admin.phs.invoices', compact('PageTitle', 'pending'));
    }

    public function approveInvoice($txnId)
    {
        $txn = PhsTokenTransaction::where('status', 'pending')->findOrFail($txnId);
        $institution = $txn->institution;

        DB::connection('sqlsrv')->transaction(function () use ($txn, $institution) {
            $institution->refresh();
            $newBalance = (int) $institution->token_balance + (int) $txn->tokens;
            $institution->token_balance = $newBalance;
            $institution->save();

            $txn->update([
                'status' => 'completed',
                'balance_after' => $newBalance,
                'approved_by' => Auth::id(),
                'notes' => trim(($txn->notes ? $txn->notes . ' | ' : '') . 'Approved by staff'),
            ]);
        });

        return back()->with('success', 'Invoice approved and tokens credited.');
    }

    public function usage(Request $request)
    {
        $PageTitle = 'PHS — Usage & Revenue';

        $revenue = (float) PhsTokenTransaction::where('status', 'completed')
            ->whereIn('payment_method', ['online', 'invoice'])
            ->sum('amount');

        $tokensSold = (int) PhsTokenTransaction::where('type', 'purchase')
            ->where('status', 'completed')
            ->sum('tokens');

        $searchesRun = (int) DB::connection('sqlsrv')->table('phs_search_logs')->count();

        $recentSearches = DB::connection('sqlsrv')->table('phs_search_logs as l')
            ->leftJoin('phs_institutions as i', 'i.id', '=', 'l.phs_institution_id')
            ->orderByDesc('l.id')
            ->limit(100)
            ->get(['l.*', 'i.name as institution_name']);

        return view('system-admin.phs.usage', compact('PageTitle', 'revenue', 'tokensSold', 'searchesRun', 'recentSearches'));
    }

    public function onboardingRequests(Request $request)
    {
        $PageTitle = 'PHS — Onboarding Requests';
        $statusFilter = $request->query('status');

        $query = PhsOnboardingRequest::orderByDesc('created_at');

        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        $requests = $query->get();

        $statsByStatus = [
            'pending' => PhsOnboardingRequest::where('status', PhsOnboardingRequest::STATUS_PENDING)->count(),
            'payment_received' => PhsOnboardingRequest::where('status', PhsOnboardingRequest::STATUS_PAYMENT_RECEIVED)->count(),
            'approved' => PhsOnboardingRequest::where('status', PhsOnboardingRequest::STATUS_APPROVED)->count(),
            'activated' => PhsOnboardingRequest::where('status', PhsOnboardingRequest::STATUS_ACTIVATED)->count(),
            'rejected' => PhsOnboardingRequest::where('status', PhsOnboardingRequest::STATUS_REJECTED)->count(),
        ];

        return view('system-admin.phs.onboarding-requests', compact('PageTitle', 'requests', 'statusFilter', 'statsByStatus'));
    }

    public function showRequest($id)
    {
        $request = PhsOnboardingRequest::findOrFail($id);
        $PageTitle = 'Onboarding Request — ' . $request->organization_name;

        return view('system-admin.phs.onboarding-request-show', compact('PageTitle', 'request'));
    }

    public function approveRequest(Request $request, $id)
    {
        $onboardingRequest = PhsOnboardingRequest::findOrFail($id);

        if ($onboardingRequest->status !== PhsOnboardingRequest::STATUS_PENDING &&
            $onboardingRequest->status !== PhsOnboardingRequest::STATUS_PAYMENT_RECEIVED) {
            return back()->with('error', 'Request cannot be approved in its current status.');
        }

        $onboardingRequest->generateActivationToken();

        $onboardingRequest->update([
            'status' => PhsOnboardingRequest::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => Auth::id(),
        ]);

        Mail::to($onboardingRequest->contact_email)
            ->send(new PhsRequestApproved($onboardingRequest));

        return back()->with('success', 'Request approved. Activation email sent to organization.');
    }

    public function rejectRequest(Request $request, $id)
    {
        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        $onboardingRequest = PhsOnboardingRequest::findOrFail($id);

        $onboardingRequest->update([
            'status' => PhsOnboardingRequest::STATUS_REJECTED,
            'rejection_reason' => $data['rejection_reason'],
        ]);

        Mail::to($onboardingRequest->contact_email)
            ->send(new PhsRequestRejected($onboardingRequest));

        return back()->with('success', 'Request rejected. Notification email sent to organization.');
    }
}


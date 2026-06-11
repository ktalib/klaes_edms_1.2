<?php

namespace App\Http\Controllers\Phs;

use App\Http\Controllers\Controller;
use App\Mail\PhsPaymentConfirmed;
use App\Mail\PhsRequestApproved;
use App\Mail\PhsRequestRejected;
use App\Mail\PhsTopupConfirmed;
use App\Models\Phs\PhsInstitution;
use App\Models\Phs\PhsOnboardingRequest;
use App\Models\Phs\PhsTokenTransaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * KLAES staff-facing administration of the PHS programme.
 * Routes live in routes/app3.php inside the staff `auth` group.
 */
class PhsAdminController extends Controller
{
    public function index()
    {
        $PageTitle = 'PHS Administration — Organizations';
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
        $PageTitle = 'PHS Organization — ' . $institution->name;

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
        return back()->with('success', 'Organization suspended. Its members can no longer log in.');
    }

    public function activate($id)
    {
        $institution = PhsInstitution::findOrFail($id);
        $institution->update(['status' => 'active']);
        return back()->with('success', 'Organization reactivated.');
    }

    public function invoices()
    {
        $PageTitle = 'PHS — Pending Invoices';
        // Treat this page as Subscriptions: show completed invoice transactions
        $PageTitle = 'PHS — Subscriptions';
        $pending = PhsTokenTransaction::with('institution')
            ->where('status', 'completed')
            ->where('payment_method', 'invoice')
            ->orderByDesc('id')
            ->get();

        return view('system-admin.phs.invoices', compact('PageTitle', 'pending'));
    }

    /**
     * Pending invoice (bank-transfer) purchases awaiting payment verification.
     */
    public function pendingInvoices()
    {
        $PageTitle = 'PHS — Invoices';
        // The bill awaiting payment in this flow is the onboarding request, so
        // list every request that has an invoice — pending and verified alike —
        // so approved ones never disappear from the list.
        $pending = PhsOnboardingRequest::whereNotNull('invoice_number')
            ->orderByDesc('id')
            ->get();

        return view('system-admin.phs.pending-invoices', compact('PageTitle', 'pending'));
    }

    /**
     * Stream an invoice PDF for a token-purchase transaction (inline / printable).
     * Works whether the transaction is pending or already approved/completed.
     */
    public function transactionInvoice($txnId)
    {
        $txn = PhsTokenTransaction::with('institution')->findOrFail($txnId);

        $invoiceNumber = 'PHS-INV-' . optional($txn->created_at)->format('Ymd') . '-' . str_pad((string) $txn->id, 4, '0', STR_PAD_LEFT);

        $pdf = Pdf::loadView('phs.transaction-invoice-template', [
            'txn' => $txn,
            'institution' => $txn->institution,
            'invoice_number' => $invoiceNumber,
            'invoice_date' => $txn->created_at ?? now(),
        ]);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $invoiceNumber . '.pdf"',
        ]);
    }

    public function approveInvoice(Request $request, $txnId)
    {
        $txn = PhsTokenTransaction::where('status', 'pending')->findOrFail($txnId);
        $institution = $txn->institution;

        $data = $request->validate([
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'bank_reference' => ['nullable', 'string', 'max:255'],
            'payment_date' => ['nullable', 'date'],
            'validation_notes' => ['nullable', 'string', 'max:1000'],
            'payment_proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        // Expected amount is the invoice amount; paid defaults to expected when
        // staff don't enter a different figure (e.g. exact transfer).
        $expected = (float) ($txn->amount ?? 0);
        $paid = array_key_exists('paid_amount', $data) && $data['paid_amount'] !== null
            ? (float) $data['paid_amount']
            : $expected;
        $variance = round($paid - $expected, 2);
        $validationStatus = PhsTokenTransaction::deriveValidationStatus($expected, $paid);

        $proofPath = null;
        if ($request->hasFile('payment_proof')) {
            $proofPath = $request->file('payment_proof')->store('phs/payment-proofs', 'public');
        }

        DB::connection('sqlsrv')->transaction(function () use ($txn, $institution, $expected, $paid, $variance, $validationStatus, $data, $proofPath) {
            $institution->refresh();
            $newBalance = (int) $institution->token_balance + (int) $txn->tokens;
            $institution->token_balance = $newBalance;
            $institution->save();

            // mark transaction completed and record approval/expiry + validation
            $txn->status = 'completed';
            $txn->balance_after = $newBalance;
            $txn->approved_by = Auth::id();
            $txn->approved_at = now();
            $txn->expires_at = now()->addYear();
            $txn->expected_amount = $expected;
            $txn->paid_amount = $paid;
            $txn->payment_variance = $variance;
            $txn->validation_status = $validationStatus;
            $txn->validation_notes = $data['validation_notes'] ?? null;
            $txn->bank_reference = $data['bank_reference'] ?? null;
            $txn->payment_date = $data['payment_date'] ?? null;
            if ($proofPath) {
                $txn->payment_proof_path = $proofPath;
            }
            $txn->validated_by = Auth::id();
            $txn->validated_at = now();
            $txn->notes = trim(($txn->notes ? $txn->notes . ' | ' : '') . 'Approved by staff');
            $txn->save();
        });

        $msg = 'Invoice approved and tokens credited.';
        if ($validationStatus === 'incomplete') {
            $msg = 'Invoice approved with a payment shortfall of ₦' . number_format(abs($variance), 2) . '. Tokens credited.';
        } elseif ($validationStatus === 'overpaid') {
            $msg = 'Invoice approved. Overpayment of ₦' . number_format(abs($variance), 2) . ' recorded. Tokens credited.';
        }

        return back()->with('success', $msg);
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

        $topupCount = (int) PhsTokenTransaction::where('type', 'topup')->where('status', 'completed')->count();
        $topupRevenue = (float) PhsTokenTransaction::where('type', 'topup')->where('status', 'completed')->sum('amount');

        // Monthly trend over the last 6 months (built in PHP to stay DB-agnostic).
        $trend = collect(range(5, 0))->map(function ($i) {
            $month = now()->startOfMonth()->subMonths($i);
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();

            return [
                'label' => $month->format('M Y'),
                'searches' => (int) DB::connection('sqlsrv')->table('phs_search_logs')
                    ->whereBetween('created_at', [$start, $end])->count(),
                'revenue' => (float) PhsTokenTransaction::where('status', 'completed')
                    ->whereIn('payment_method', ['online', 'invoice'])
                    ->whereBetween('created_at', [$start, $end])->sum('amount'),
            ];
        })->values();

        // Top organizations by search volume.
        $topOrgs = DB::connection('sqlsrv')->table('phs_search_logs as l')
            ->leftJoin('phs_institutions as i', 'i.id', '=', 'l.phs_institution_id')
            ->selectRaw('i.name as name, COUNT(*) as searches')
            ->groupBy('i.name')
            ->orderByDesc('searches')
            ->limit(5)
            ->get();

        $recentSearches = DB::connection('sqlsrv')->table('phs_search_logs as l')
            ->leftJoin('phs_institutions as i', 'i.id', '=', 'l.phs_institution_id')
            ->orderByDesc('l.id')
            ->limit(100)
            ->get(['l.*', 'i.name as institution_name']);

        return view('system-admin.phs.usage', compact(
            'PageTitle', 'revenue', 'tokensSold', 'searchesRun',
            'topupCount', 'topupRevenue', 'trend', 'topOrgs', 'recentSearches'
        ));
    }

    /** All token topups (auto-credited via the stubbed gateway). */
    public function topups()
    {
        $PageTitle = 'PHS — Token Topups';
        $topups = PhsTokenTransaction::with('institution')
            ->where('type', 'topup')
            ->orderByDesc('id')
            ->get();

        return view('system-admin.phs.topups', compact('PageTitle', 'topups'));
    }

    /** Confirm payment for a pending topup and credit the wallet. */
    public function approveTopup($txnId)
    {
        $txn = PhsTokenTransaction::where('status', 'pending')->where('type', 'topup')->findOrFail($txnId);
        $institution = $txn->institution;

        $newBalance = DB::connection('sqlsrv')->transaction(function () use ($txn, $institution) {
            $institution->refresh();
            $balance = (int) $institution->token_balance + (int) $txn->tokens;
            $institution->token_balance = $balance;
            $institution->save();

            $txn->status = 'completed';
            $txn->balance_after = $balance;
            $txn->approved_by = Auth::id();
            $txn->payment_confirmed_at = now();
            $txn->credited_at = now();
            $txn->notes = trim(($txn->notes ? $txn->notes . ' | ' : '') . 'Payment confirmed by staff');
            $txn->save();

            return $balance;
        });

        // Notify the organization's admins (best-effort).
        try {
            $txn->setRelation('institution', $institution);
            $recipients = $institution->members()
                ->where('user_type', 'super_admin')->where('status', 'active')
                ->pluck('email')->filter()->values()->all();
            if ($recipients) {
                Mail::to($recipients)->send(new PhsTopupConfirmed($txn, (int) $newBalance));
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', number_format((int) $txn->tokens) . ' tokens credited to ' . $institution->name . '.');
    }

    /** Reject a pending topup (no tokens credited). */
    public function rejectTopup(Request $request, $txnId)
    {
        $txn = PhsTokenTransaction::where('status', 'pending')->where('type', 'topup')->findOrFail($txnId);
        $txn->status = 'failed';
        $txn->notes = trim(($txn->notes ? $txn->notes . ' | ' : '') . 'Rejected by staff' . ($request->input('reason') ? ': ' . $request->input('reason') : ''));
        $txn->save();

        return back()->with('success', 'Top-up request rejected.');
    }

    /** Organization wallets: balances, lifetime allocated/used, last topup. */
    public function wallets()
    {
        $PageTitle = 'PHS — Organization Wallets';

        $wallets = PhsInstitution::orderBy('name')->get()->map(function ($inst) {
            return [
                'inst' => $inst,
                'balance' => (int) $inst->token_balance,
                'allocated' => (int) $inst->transactions()->where('tokens', '>', 0)->where('status', 'completed')->sum('tokens'),
                'used' => (int) abs((int) $inst->transactions()->where('type', 'search_debit')->sum('tokens')),
                'last_topup' => $inst->transactions()->where('type', 'topup')->max('created_at'),
            ];
        });

        return view('system-admin.phs.wallets', compact('PageTitle', 'wallets'));
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

    /**
     * Stream the onboarding request's e-invoice PDF inline (for viewing/printing).
     * Generates the PDF first if it hasn't been created yet — available for any
     * request regardless of its approval status.
     */
    public function requestInvoice($id)
    {
        $onboardingRequest = PhsOnboardingRequest::findOrFail($id);

        // Always regenerate so the printed copy reflects the current payment status.
        app(\App\Http\Controllers\Phs\PhsOnboardingController::class)->generateInvoice($onboardingRequest);
        $onboardingRequest->refresh();

        abort_unless(
            $onboardingRequest->invoice_pdf_path && Storage::disk('public')->exists($onboardingRequest->invoice_pdf_path),
            404,
            'Invoice not available.'
        );

        $filename = 'Invoice-' . ($onboardingRequest->invoice_number ?: $onboardingRequest->id) . '.pdf';

        return response(Storage::disk('public')->get($onboardingRequest->invoice_pdf_path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
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

    /**
     * Verify/confirm the payment on an onboarding request. Independent of the
     * approval workflow — records how much was actually received and derives a
     * payment status (not_paid / incomplete / completed / overpaid).
     */
    public function verifyRequestPayment(Request $request, $id)
    {
        $onboardingRequest = PhsOnboardingRequest::findOrFail($id);

        $data = $request->validate([
            'verified_amount' => ['required', 'numeric', 'min:0'],
            'expected_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_verification_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Expected = package price (snapshot), falling back to the amount the
        // organization entered at submission.
        $package = \App\Http\Controllers\Phs\PhsTokenController::packages()[strtolower((string) $onboardingRequest->initial_token_package)] ?? null;
        $expected = $data['expected_amount']
            ?? $onboardingRequest->expected_amount
            ?? ($package['price'] ?? $onboardingRequest->payment_amount ?? 0);
        $expected = (float) $expected;
        $verified = (float) $data['verified_amount'];
        $outstanding = max(0, round($expected - $verified, 2));
        $status = PhsOnboardingRequest::derivePaymentStatus($expected, $verified);

        $onboardingRequest->update([
            'expected_amount' => $expected,
            'verified_amount' => $verified,
            'outstanding_amount' => $outstanding,
            'payment_status' => $status,
            'payment_verified_at' => now(),
            'payment_verified_by' => Auth::id(),
            'payment_verification_notes' => $data['payment_verification_notes'] ?? null,
        ]);

        // Rebuild the invoice PDF so its payment status reflects this verification.
        try {
            app(\App\Http\Controllers\Phs\PhsOnboardingController::class)->generateInvoice($onboardingRequest->fresh());
            $onboardingRequest->refresh();
        } catch (\Throwable $e) {
            report($e);
        }

        // Notify the organization of the payment outcome (best-effort).
        try {
            Mail::to($onboardingRequest->contact_email)->send(new PhsPaymentConfirmed($onboardingRequest));
        } catch (\Throwable $e) {
            report($e);
        }

        $labels = [
            PhsOnboardingRequest::PAYMENT_COMPLETED => 'Payment confirmed in full. Confirmation email sent.',
            PhsOnboardingRequest::PAYMENT_OVERPAID => 'Payment confirmed — overpayment of ₦' . number_format($verified - $expected, 2) . ' recorded. Email sent.',
            PhsOnboardingRequest::PAYMENT_INCOMPLETE => 'Payment recorded as incomplete. Outstanding balance: ₦' . number_format($outstanding, 2) . '. Email sent.',
            PhsOnboardingRequest::PAYMENT_NOT_PAID => 'Marked as not paid. Email sent.',
        ];

        return back()->with('success', $labels[$status] ?? 'Payment status updated.');
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

    /* ===================== Token Package Management ===================== */

    public function packages()
    {
        $PageTitle = 'PHS — Token Packages';
        $packages = \App\Models\Phs\PhsTokenPackage::orderBy('display_order')->orderBy('id')->get();

        return view('system-admin.phs.packages', compact('PageTitle', 'packages'));
    }

    public function storePackage(Request $request)
    {
        $data = $this->validatePackage($request);
        $data['slug'] = $this->uniqueSlug($data['name'], $data['slug'] ?? null);

        \App\Models\Phs\PhsTokenPackage::create($data);

        return back()->with('success', "Package “{$data['name']}” created.");
    }

    public function updatePackage(Request $request, $id)
    {
        $package = \App\Models\Phs\PhsTokenPackage::findOrFail($id);
        $data = $this->validatePackage($request, $package->id);
        $data['slug'] = $this->uniqueSlug($data['name'], $data['slug'] ?? $package->slug, $package->id);

        $package->update($data);

        return back()->with('success', "Package “{$package->name}” updated.");
    }

    public function destroyPackage($id)
    {
        $package = \App\Models\Phs\PhsTokenPackage::findOrFail($id);
        $name = $package->name;
        $package->delete();

        return back()->with('success', "Package “{$name}” deleted.");
    }

    private function validatePackage(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9\-]+$/'],
            'bundles' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0'],
            'team_members' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ], [
            'bundles.required' => 'Enter the number of bundles.',
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['display_order'] = (int) $request->input('display_order', 0);
        $data['bundles'] = (int) $data['bundles'];
        // Tokens are derived from bundles — bundles are the source of truth.
        $data['tokens'] = $data['bundles'] * \App\Models\Phs\PhsTokenPackage::TOKENS_PER_BUNDLE;

        return $data;
    }

    /** Build a URL-safe slug that is unique within phs_token_packages. */
    private function uniqueSlug(string $name, ?string $preferred, ?int $ignoreId = null): string
    {
        $base = \Str::slug($preferred ?: $name) ?: 'package';
        $slug = $base;
        $i = 1;
        while (\App\Models\Phs\PhsTokenPackage::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }
        return $slug;
    }
}


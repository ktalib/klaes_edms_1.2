<?php

namespace App\Http\Controllers\Phs;

use App\Http\Controllers\Controller;
use App\Models\Phs\PhsSearchLog;
use App\Services\LegalSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PhsDashboardController extends Controller
{
    protected LegalSearchService $searchService;

    public function __construct(LegalSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    public function index()
    {
        $member = Auth::guard('phs')->user();
        $institution = $member->institution;

        $searchLogs = PhsSearchLog::where('phs_institution_id', $institution->id);

        $stats = [
            'token_balance' => $member->isSuperAdmin() ? (int) $institution->token_balance : (int) $member->allocated_tokens,
            'total_searches' => (clone $searchLogs)->count(),
            'searches_this_month' => (clone $searchLogs)->where('created_at', '>=', now()->startOfMonth())->count(),
            'member_count' => $institution->members()->count(),
        ];

        $recentSearches = PhsSearchLog::with('member')
            ->where('phs_institution_id', $institution->id)
            ->latest()
            ->limit(5)
            ->get();

        return view('phs.dashboard.index', [
            'member' => $member,
            'institution' => $institution,
            'packages' => PhsTokenController::packages(),
            'stats' => $stats,
            'recentSearches' => $recentSearches,
        ]);
    }

    /**
     * Run a property history search. Consumes 1 token per search.
     */
    public function search(Request $request)
    {
        $member = Auth::guard('phs')->user();
        $institution = $member->institution;

        if (!$member->canSearch()) {
            return response()->json([
                'success' => false,
                'message' => 'Your role does not permit running searches.',
            ], 403);
        }

        $query = trim((string) $request->input('query', ''));
        if ($query === '') {
            return response()->json([
                'success' => false,
                'message' => 'Please enter a file number, KANGIS number, owner or plot number.',
            ], 422);
        }

        // Pre-flight: reject immediately if the member has no tokens at all (avoids
        // a wasted search query when the balance is already zero).
        $currentBalance = $member->isSuperAdmin()
            ? (int) $institution->token_balance
            : (int) $member->allocated_tokens;

        if ($currentBalance < 1) {
            return response()->json([
                'success' => false,
                'insufficient_tokens' => true,
                'message' => $member->isSuperAdmin()
                    ? 'Insufficient organization tokens. Please purchase more to continue.'
                    : 'You have no tokens left. Please contact your organization administrator for more.',
                'token_balance' => $currentBalance,
            ], 422);
        }

        // Run the search BEFORE deducting — tokens are only spent when results exist.
        $results = $this->searchService->search(['query' => $query]);
        $transactions = $results['transactions'] ?? [];

        // No results → return immediately, no charge.
        if (empty($transactions)) {
            PhsSearchLog::create([
                'phs_institution_id' => $institution->id,
                'phs_member_id' => $member->id,
                'query' => Str::limit($query, 250),
                'file_number' => $results['file_index_number'] ?? $query,
                'result_count' => 0,
                'reference_no' => null,
                'tokens_used' => 0,
            ]);

            return response()->json([
                'success' => true,
                'reference_no' => null,
                'token_balance' => $currentBalance,
                'transactions' => [],
                'file_title' => $results['file_title'] ?? null,
                'file_district' => $results['file_district'] ?? null,
                'file_lga' => $results['file_lga'] ?? null,
                'file_land_use' => $results['file_land_use'] ?? null,
                'file_plot_number' => $results['file_plot_number'] ?? null,
                'file_tp_no' => $results['file_tp_no'] ?? null,
                'file_size' => $results['file_size'] ?? null,
                'file_index_number' => $results['file_index_number'] ?? null,
                'total_count' => 0,
                'no_charge' => true,
            ]);
        }

        // Results found — now deduct 1 token.
        $reference = 'PHS/' . now()->format('Y') . '/' . strtoupper(Str::random(6));

        if ($member->isSuperAdmin()) {
            $debit = $institution->deductTokens(1, $member->id, [
                'reference_no' => $reference,
                'notes' => 'Search: ' . Str::limit($query, 100),
            ]);
            $debited = $debit !== null;
        } else {
            $debited = \DB::connection('sqlsrv')->table('phs_members')
                ->where('id', $member->id)
                ->where('allocated_tokens', '>=', 1)
                ->update([
                    'allocated_tokens' => \DB::raw('allocated_tokens - 1'),
                    'tokens_used' => \DB::raw('tokens_used + 1'),
                ]) > 0;
        }

        // Edge case: balance was consumed by a concurrent request between the
        // pre-flight check and the actual deduction. Return results anyway — the
        // search already ran and the user should see what they asked for.
        if ($debited && $member->isSuperAdmin()) {
            $member->increment('tokens_used');
        }
        $member->refresh();
        $institution->refresh();

        // Build the certified-slip report so the on-screen Property Timeline shows
        // the exact same LS-weighed set the printed slip will (deduplicated,
        // scored, and ordered). Falls back to the raw transactions if the report
        // engine can't produce rows for any reason.
        $resolvedFileNo = $results['file_index_number'] ?? $query;
        $timeline = $transactions;
        try {
            $report = $this->searchService->buildPrintReport(['file_number' => $resolvedFileNo]);
            if (($report['status'] ?? null) === 200 && !empty($report['payload']['data']['rows'])) {
                $timeline = $report['payload']['data']['rows'];
            }
        } catch (\Throwable $e) {
            report($e);
        }

        PhsSearchLog::create([
            'phs_institution_id' => $institution->id,
            'phs_member_id' => $member->id,
            'query' => Str::limit($query, 250),
            'file_number' => $resolvedFileNo,
            'result_count' => count($timeline),
            'reference_no' => $reference,
            'tokens_used' => $debited ? 1 : 0,
        ]);

        return response()->json([
            'success' => true,
            'reference_no' => $reference,
            'token_balance' => $member->isSuperAdmin() ? (int) $institution->token_balance : (int) $member->allocated_tokens,
            'transactions' => $timeline,
            'file_title' => $results['file_title'] ?? null,
            'file_district' => $results['file_district'] ?? null,
            'file_lga' => $results['file_lga'] ?? null,
            'file_land_use' => $results['file_land_use'] ?? null,
            'file_plot_number' => $results['file_plot_number'] ?? null,
            'file_tp_no' => $results['file_tp_no'] ?? null,
            'file_size' => $results['file_size'] ?? null,
            'file_index_number' => $results['file_index_number'] ?? null,
            'total_count' => count($timeline),
        ]);
    }
}

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

        return view('phs.dashboard.index', [
            'member' => $member,
            'institution' => $institution,
            'packages' => PhsTokenController::packages(),
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

        // Deduct 1 token BEFORE running the search (atomic; null = insufficient balance).
        $reference = 'PHS/' . now()->format('Y') . '/' . strtoupper(Str::random(6));
        $debit = $institution->deductTokens(1, $member->id, [
            'reference_no' => $reference,
            'notes' => 'Search: ' . Str::limit($query, 100),
        ]);

        if ($debit === null) {
            return response()->json([
                'success' => false,
                'insufficient_tokens' => true,
                'message' => 'Insufficient tokens. Please purchase more tokens to continue.',
                'token_balance' => (int) $institution->fresh()->token_balance,
            ], 422);
        }

        // member.tokens_used counter
        $member->increment('tokens_used');

        $results = $this->searchService->search(['query' => $query]);
        $transactions = $results['transactions'] ?? [];

        PhsSearchLog::create([
            'phs_institution_id' => $institution->id,
            'phs_member_id' => $member->id,
            'query' => Str::limit($query, 250),
            'file_number' => $results['file_index_number'] ?? $query,
            'result_count' => count($transactions),
            'reference_no' => $reference,
            'tokens_used' => 1,
        ]);

        return response()->json([
            'success' => true,
            'reference_no' => $reference,
            'token_balance' => (int) $institution->fresh()->token_balance,
            'transactions' => $transactions,
            'file_title' => $results['file_title'] ?? null,
            'file_district' => $results['file_district'] ?? null,
            'file_lga' => $results['file_lga'] ?? null,
            'file_land_use' => $results['file_land_use'] ?? null,
            'file_plot_number' => $results['file_plot_number'] ?? null,
            'file_tp_no' => $results['file_tp_no'] ?? null,
            'file_size' => $results['file_size'] ?? null,
            'file_index_number' => $results['file_index_number'] ?? null,
            'total_count' => $results['total_count'] ?? count($transactions),
        ]);
    }
}

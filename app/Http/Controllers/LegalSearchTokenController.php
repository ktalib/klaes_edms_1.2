<?php

namespace App\Http\Controllers;

use App\Models\LegalSearchToken;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LegalSearchTokenController extends Controller
{
    protected $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    public function index()
    {
        $tokens = LegalSearchToken::with('creator')
            ->orderBy('created_at', 'desc')
            ->paginate(15);
            
        $paymentReasons = DB::connection('sqlsrv')->table('payment_reasons')
            ->where('is_active', true)
            ->get();

        return view('system-admin.legal-search-tokens.index', compact('tokens', 'paymentReasons'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'file_number' => 'required|string',
            'applicant_name' => 'required|string',
            'client_name' => 'nullable|string',
            'client_address' => 'nullable|string',
            'receipt_number' => 'required|string',
            'date_paid' => 'required|date',
            'payment_reason' => 'required|string',
        ]);

        $token = LegalSearchToken::create([
            'token' => Str::upper(Str::random(12)),
            'file_number' => $request->file_number,
            'applicant_name' => $request->applicant_name,
            'client_name' => $request->client_name ?? null,
            'property_location' => $request->property_location,
            'client_address' => $request->client_address ?? null,
            'amount_paid' => $request->amount_paid ?? 0,
            'receipt_number' => $request->receipt_number,
            'date_paid' => $request->date_paid,
            'payment_reason' => $request->payment_reason,
            'created_by' => Auth::id(),
        ]);

        $this->auditService->logAction(
            'Legal Search Token Generated',
            'LegalSearchToken',
            $token->id,
            null,
            $token->toArray(),
            "Token generated for file number {$token->file_number} with token {$token->token}"
        );

        return response()->json([
            'success' => true,
            'message' => 'Token generated successfully!',
            'token' => $token->token,
        ]);
    }

    public function checkAvailableToken(Request $request)
    {
        // Admin Bypass
        if (Auth::check() && Auth::user()->assign_role === 'Supper Admin') {
            return response()->json([
                'success' => true,
                'bypass' => true,
                'message' => 'Super Admin Bypass: Proceeding with search.'
            ]);
        }

        $fileNumber = $request->file_number;
        
        if (empty($fileNumber)) {
            return response()->json(['success' => false, 'message' => 'File number is required.']);
        }

        // A token is valid while it still has at least one of its MAX_USES left.
        $token = LegalSearchToken::where('file_number', $fileNumber)
            ->where('usage_count', '<', LegalSearchToken::MAX_USES)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => "No valid search token found for File No: {$fileNumber}. Please generate a token before proceeding."
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => "Valid token found for File No: {$fileNumber}. You may proceed with the search.",
            'token' => $token->token,
            'token_preview' => substr($token->token, 0, 4) . '****',
            'applicant_name' => $token->applicant_name,
            'client_name' => $token->client_name,
            'client_address' => $token->client_address,
            'property_location' => $token->property_location,
            'usage_count' => $token->usage_count,
            'remaining_uses' => $token->remaining_uses,
        ]);
    }

    /**
     * Return the client details (name + address) for a file number, drawn from the
     * most recent token — regardless of whether it has been used. Used to prefill the
     * Client Details fields on the legal search page across every flow (incl. Super
     * Admin bypass and already-used tokens).
     */
    public function clientDetails(Request $request)
    {
        $fileNumber = trim((string) $request->query('file_number', ''));

        if ($fileNumber === '') {
            return response()->json(['success' => false, 'message' => 'File number is required.']);
        }

        $token = LegalSearchToken::where('file_number', $fileNumber)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$token) {
            return response()->json(['success' => false, 'client_name' => '', 'client_address' => '']);
        }

        return response()->json([
            'success' => true,
            'client_name' => $token->client_name ?? '',
            'client_address' => $token->client_address ?? '',
        ]);
    }

    /**
     * Persist edited Client Name / Client Address back onto the file's latest
     * legal_search_token so they survive a page reload and print correctly on the
     * Pay-Per-Search report.
     */
    public function updateClientDetails(Request $request)
    {
        $validated = $request->validate([
            'file_number' => 'required|string',
            'client_name' => 'nullable|string',
            'client_address' => 'nullable|string',
        ]);

        $fileNumber = trim((string) $validated['file_number']);

        $token = LegalSearchToken::where('file_number', $fileNumber)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'No search token exists for this file, so client details cannot be saved.',
            ]);
        }

        $before = $token->only(['client_name', 'client_address']);

        $token->client_name = $validated['client_name'] ?? null;
        $token->client_address = $validated['client_address'] ?? null;
        $token->save();

        $this->auditService->logAction(
            'Legal Search Client Details Updated',
            'LegalSearchToken',
            $token->id,
            $before,
            $token->only(['client_name', 'client_address']),
            "Client details updated for file number {$token->file_number}"
        );

        return response()->json([
            'success' => true,
            'message' => 'Client details updated.',
            'client_name' => $token->client_name ?? '',
            'client_address' => $token->client_address ?? '',
        ]);
    }

    public function useToken(Request $request)
    {
        $fileNumber = $request->file_number;
        $tokenStr = $request->token;

        $token = LegalSearchToken::where('file_number', $fileNumber)
            ->where('token', $tokenStr)
            ->where('usage_count', '<', LegalSearchToken::MAX_USES)
            ->first();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or fully-used token.'
            ], 422);
        }

        $before = ['usage_count' => $token->usage_count, 'is_used' => $token->is_used];

        $newCount = (int) $token->usage_count + 1;
        $token->update([
            'usage_count' => $newCount,
            'is_used' => $newCount >= LegalSearchToken::MAX_USES,
            'used_at' => now(),
        ]);

        $this->auditService->logAction(
            'Legal Search Token Used',
            'LegalSearchToken',
            $token->id,
            $before,
            ['usage_count' => $token->usage_count, 'is_used' => $token->is_used],
            "Token {$token->token} used (count {$newCount}/" . LegalSearchToken::MAX_USES . ") for search on file {$token->file_number}"
        );

        return response()->json([
            'success' => true,
            'usage_count' => $token->usage_count,
            'remaining_uses' => $token->remaining_uses,
        ]);
    }

    /**
     * Reset a token's usage counter to a specific count so it can be spent again.
     *
     * count = 1  → reset to the 1st count (usage_count = 0, both uses available)
     * count = 2  → reset to the 2nd count (usage_count = 1, one use available)
     */
    public function resetToken(Request $request, $id)
    {
        $validated = $request->validate([
            'count' => 'required|integer|min:1|max:' . LegalSearchToken::MAX_USES,
        ]);

        $token = LegalSearchToken::findOrFail($id);

        $before = ['usage_count' => $token->usage_count, 'is_used' => $token->is_used];

        // "Nth count" means the token is positioned to be spent as its Nth use,
        // i.e. (N - 1) uses have already been consumed.
        $newCount = (int) $validated['count'] - 1;

        $token->update([
            'usage_count' => $newCount,
            'is_used' => $newCount >= LegalSearchToken::MAX_USES,
            'used_at' => $newCount > 0 ? $token->used_at : null,
        ]);

        $this->auditService->logAction(
            'Legal Search Token Reset',
            'LegalSearchToken',
            $token->id,
            $before,
            ['usage_count' => $token->usage_count, 'is_used' => $token->is_used],
            "Token {$token->token} reset to {$validated['count']} count for file {$token->file_number}"
        );

        return response()->json([
            'success' => true,
            'message' => 'Token reset successfully!',
            'usage_count' => $token->usage_count,
            'remaining_uses' => $token->remaining_uses,
        ]);
    }

    public function destroy($id)
    {
        $token = LegalSearchToken::findOrFail($id);
        
        if ($token->is_used) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a token that has already been used.',
            ], 400);
        }

        $token->delete();

        $this->auditService->logAction(
            'Legal Search Token Deleted',
            'LegalSearchToken',
            $id,
            $token->toArray(),
            null,
            "Token {$token->token} for file number {$token->file_number} deleted"
        );

        return response()->json([
            'success' => true,
            'message' => 'Token deleted successfully!',
        ]);
    }

    private function generateUniqueToken()
    {
        do {
            $token = strtoupper(Str::random(12));
            $exists = LegalSearchToken::where('token', $token)->exists();
        } while ($exists);

        return $token;
    }
}

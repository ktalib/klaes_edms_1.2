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
            'receipt_number' => 'required|string',
            'date_paid' => 'required|date',
            'payment_reason' => 'required|string',
        ]);

        $token = LegalSearchToken::create([
            'token' => Str::upper(Str::random(12)),
            'file_number' => $request->file_number,
            'applicant_name' => $request->applicant_name,
            'property_location' => $request->property_location,
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

        $token = LegalSearchToken::where('file_number', $fileNumber)
            ->where('is_used', false)
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
            'property_location' => $token->property_location,
        ]);
    }

    public function useToken(Request $request)
    {
        $fileNumber = $request->file_number;
        $tokenStr = $request->token;

        $token = LegalSearchToken::where('file_number', $fileNumber)
            ->where('token', $tokenStr)
            ->where('is_used', false)
            ->first();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token.'
            ], 422);
        }

        $token->update([
            'is_used' => true,
            'used_at' => now(),
        ]);

        $this->auditService->logAction(
            'Legal Search Token Used',
            'LegalSearchToken',
            $token->id,
            ['is_used' => false],
            ['is_used' => true],
            "Token {$token->token} used for search on file {$token->file_number}"
        );

        return response()->json(['success' => true]);
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

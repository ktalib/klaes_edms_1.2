<?php

namespace App\Http\Controllers\Phs;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PhsTokenController extends Controller
{
    /**
     * Subscription token packages. Single source of truth shared by landing,
     * dashboard modal, and purchase validation.
     */
    public static function packages(): array
    {
        return [
            'starter'      => ['name' => 'Starter',      'tokens' => 2000,  'price' => 50000],
            'professional' => ['name' => 'Professional', 'tokens' => 5000,  'price' => 100000],
            'enterprise'   => ['name' => 'Enterprise',   'tokens' => 10000, 'price' => 180000],
        ];
    }

    private function resolvePackage(string $key): ?array
    {
        return self::packages()[strtolower($key)] ?? null;
    }

    /**
     * Stubbed online payment — credits the selected package immediately.
     */
    public function payOnline(Request $request)
    {
        $request->validate(['package' => ['required', 'string']]);

        $member = Auth::guard('phs')->user();
        $institution = $member->institution;
        $package = $this->resolvePackage($request->input('package'));

        if (!$package) {
            return response()->json(['success' => false, 'message' => 'Invalid package selected.'], 422);
        }

        $reference = 'PAY/' . now()->format('Ymd') . '/' . strtoupper(Str::random(6));
        $institution->addTokens($package['tokens'], 'purchase', [
            'package_name' => $package['name'],
            'amount' => $package['price'],
            'payment_method' => 'online',
            'reference_no' => $reference,
            'status' => 'completed',
            'notes' => 'Online payment (stub)',
        ], $member->id);

        return response()->json([
            'success' => true,
            'message' => "Payment processed. {$package['tokens']} tokens added.",
            'reference_no' => $reference,
            'token_balance' => (int) $institution->fresh()->token_balance,
        ]);
    }

    /**
     * Stubbed invoice request — records a PENDING purchase awaiting KLAES approval.
     * Tokens are credited only when staff approves the invoice.
     */
    public function requestInvoice(Request $request)
    {
        $request->validate(['package' => ['required', 'string']]);

        $member = Auth::guard('phs')->user();
        $institution = $member->institution;
        $package = $this->resolvePackage($request->input('package'));

        if (!$package) {
            return response()->json(['success' => false, 'message' => 'Invalid package selected.'], 422);
        }

        $reference = 'INV/' . now()->format('Ymd') . '/' . strtoupper(Str::random(6));

        // Pending row — does NOT change balance_after / token_balance yet.
        $institution->transactions()->create([
            'phs_member_id' => $member->id,
            'type' => 'purchase',
            'tokens' => $package['tokens'],
            'balance_after' => (int) $institution->token_balance,
            'package_name' => $package['name'],
            'amount' => $package['price'],
            'payment_method' => 'invoice',
            'status' => 'pending',
            'reference_no' => $reference,
            'notes' => 'Invoice requested — awaiting KLAES approval',
        ]);

        return response()->json([
            'success' => true,
            'message' => "Invoice request submitted ({$reference}). Tokens will be credited after KLAES approves payment.",
            'reference_no' => $reference,
        ]);
    }

    /**
     * Wallet ledger for the signed-in institution.
     */
    public function transactions(Request $request)
    {
        $member = Auth::guard('phs')->user();
        $rows = $member->institution->transactions()
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }
}

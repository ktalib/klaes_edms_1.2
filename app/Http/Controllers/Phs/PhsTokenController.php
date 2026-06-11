<?php

namespace App\Http\Controllers\Phs;

use App\Http\Controllers\Controller;
use App\Mail\PhsTopupConfirmed;
use App\Models\Phs\PhsTokenPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PhsTokenController extends Controller
{
    /**
     * Subscription token packages. Single source of truth shared by landing,
     * dashboard modal, and purchase validation. Backed by the
     * phs_token_packages table; falls back to baked-in defaults if the table
     * is missing/empty so the portal never breaks.
     */
    public static function packages(): array
    {
        try {
            $rows = PhsTokenPackage::active()->get();
            if ($rows->isNotEmpty()) {
                return $rows->keyBy('slug')->map(fn ($p) => [
                    'name' => $p->name,
                    'tokens' => (int) $p->tokens,
                    'price' => (float) $p->price,
                    'team_members' => (int) $p->team_members,
                    'description' => $p->description,
                ])->toArray();
            }
        } catch (\Throwable $e) {
            // Table not migrated yet / DB unreachable — fall through to defaults.
        }

        return [
            'starter'      => ['name' => 'Starter',      'tokens' => 2000,  'price' => 50000,  'team_members' => 2,  'description' => null],
            'professional' => ['name' => 'Professional', 'tokens' => 5000,  'price' => 100000, 'team_members' => 5,  'description' => null],
            'enterprise'   => ['name' => 'Enterprise',   'tokens' => 10000, 'price' => 180000, 'team_members' => 10, 'description' => null],
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
     * Token topup: buy N bundles of a package. Online payment is stubbed, so
     * tokens are credited immediately. Reference: TOPUP/YYYYMMDD/RANDOM6.
     */
    public function requestTopup(Request $request)
    {
        $member = Auth::guard('phs')->user();
        $institution = $member->institution;

        if (!$member->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Only an administrator can top up tokens.'], 403);
        }

        if (!$institution->isActive()) {
            return response()->json(['success' => false, 'message' => 'Your organization is suspended. Top-ups are disabled.'], 422);
        }

        $data = $request->validate([
            'package' => ['required', 'string'],
            'bundle_count' => ['required', 'integer', 'min:1', 'max:10'],
            'payment_method' => ['nullable', 'in:bank_transfer,interswitch,paystack'],
        ]);

        $method = $data['payment_method'] ?? 'bank_transfer';

        // Auto gateways are not integrated yet — surface the option but don't process.
        if (in_array($method, ['interswitch', 'paystack'], true)) {
            return response()->json([
                'success' => false,
                'message' => ucfirst($method) . ' payments are coming soon. Please use Bank Transfer for now.',
            ], 422);
        }

        $package = $this->resolvePackage($data['package']);
        if (!$package) {
            return response()->json(['success' => false, 'message' => 'Invalid bundle selected.'], 422);
        }

        $bundles = (int) $data['bundle_count'];
        $tokens = $bundles * (int) $package['tokens'];
        $amount = $bundles * (float) $package['price'];
        $reference = 'TOPUP/' . now()->format('Ymd') . '/' . strtoupper(Str::random(6));

        // Bank transfer → record a PENDING topup; tokens are credited only when
        // staff confirm the payment (same model as onboarding bank transfers).
        $institution->transactions()->create([
            'phs_member_id' => $member->id,
            'type' => 'topup',
            'tokens' => $tokens,
            'balance_after' => (int) $institution->token_balance, // unchanged while pending
            'package_name' => $package['name'],
            'amount' => $amount,
            'payment_method' => 'bank_transfer',
            'status' => 'pending',
            'reference_no' => $reference,
            'topup_bundle_count' => $bundles,
            'topup_unit_price' => $package['price'],
            'notes' => $bundles . ' × ' . $package['name'] . ' bundle (bank transfer — awaiting payment confirmation)',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Top-up request submitted (' . $reference . '). ' . number_format($tokens) . ' tokens will be credited once your bank transfer is confirmed by staff.',
            'reference_no' => $reference,
            'pending' => true,
            'token_balance' => (int) $institution->token_balance,
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

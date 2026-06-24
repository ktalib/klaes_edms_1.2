<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\LegalSearchOnlinePayment;
use App\Services\LegalSearchService;

class LegalSearchOnlineController extends Controller
{
    protected LegalSearchService $searchService;

    public function __construct(LegalSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    public function index()
    {
        $districtOptions = DB::connection('sqlsrv')->table('districts')->pluck('name')->toArray();

        $config = [
            'paystackPublicKey' => config('services.paystack.public'),
            'paymentAmount'     => 100000, // kobo (₦1,000)
            'searchUrl'         => route('legalsearch.online.search'),
            'verifyUrl'         => route('legal_search.online.payment.verify'),
        ];

        return view('legal_search_online.index', compact('districtOptions', 'config'));
    }

    /**
     * Public search — no auth logging, just returns results.
     */
    public function publicSearch(Request $request)
    {
        $results = $this->searchService->search($request->all());
        return response()->json($results);
    }

    /**
     * Verify a Paystack payment reference and record the transaction.
     */
    public function verifyPayment(Request $request)
    {
        $request->validate(['reference' => 'required|string|max:100']);

        $reference     = trim($request->input('reference'));
        $email         = $request->input('email', '');
        $fileNumber    = $request->input('file_number', '');

        // Idempotent check
        $existing = LegalSearchOnlinePayment::where('reference', $reference)->first();
        if ($existing && $existing->isPaid()) {
            return response()->json(['success' => true, 'already_paid' => true]);
        }

        // Verify with Paystack
        $verify = Http::withToken(config('services.paystack.secret'))
            ->get(config('services.paystack.base_url') . '/transaction/verify/' . $reference);

        if (!$verify->successful() || $verify->json('data.status') !== 'success') {
            if ($existing) {
                $existing->update(['status' => 'failed']);
            }
            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed. Please contact support if you were charged.',
            ], 422);
        }

        $amountPaid    = (int) $verify->json('data.amount');
        $customerEmail = $verify->json('data.customer.email') ?? $email;

        LegalSearchOnlinePayment::updateOrCreate(
            ['reference' => $reference],
            [
                'user_id'     => auth()->id() ?? null,
                'email'       => $customerEmail,
                'file_number' => $fileNumber,
                'amount'      => $amountPaid,
                'status'      => 'paid',
                'paid_at'     => now(),
            ]
        );

        return response()->json(['success' => true, 'reference' => $reference]);
    }
}

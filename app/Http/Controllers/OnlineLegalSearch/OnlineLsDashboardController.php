<?php

namespace App\Http\Controllers\OnlineLegalSearch;

use App\Http\Controllers\Controller;
use App\Models\LegalSearchOnlineFeedback;
use App\Models\LegalSearchOnlinePayment;
use App\Services\LegalSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class OnlineLsDashboardController extends Controller
{
    protected LegalSearchService $searchService;

    public function __construct(LegalSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * Show the authenticated user's dashboard.
     */
    public function index()
    {
        $user = Auth::guard('online_ls')->user();

        $districtOptions = DB::connection('sqlsrv')->table('districts')->pluck('name')->toArray();

        $recentSearches = DB::connection('sqlsrv')
            ->table('legal_search_online_payments')
            ->where('online_ls_user_id', $user->id)
            ->where('status', 'paid')
            ->orderByDesc('paid_at')
            ->limit(10)
            ->get();

        $config = [
            'paystackPublicKey' => config('services.paystack.public'),
            'paymentAmount'     => 100000, // kobo (₦1,000)
            'searchUrl'         => route('ols.search'),
            'verifyUrl'         => route('ols.payment.verify'),
        ];

        return view('online_legal_search.dashboard', compact('user', 'districtOptions', 'recentSearches', 'config'));
    }

    /**
     * Perform a legal search (authenticated user).
     */
    public function search(Request $request)
    {
        $user = Auth::guard('online_ls')->user();

        $results = $this->searchService->search($request->all());

        // Log the search
        if (!empty($results)) {
            DB::connection('sqlsrv')->table('online_ls_search_logs')->insert([
                'online_ls_user_id' => $user->id,
                'file_number'       => $request->input('file_number', ''),
                'search_params'     => json_encode($request->except(['_token'])),
                'results_count'     => is_array($results) ? count($results) : 0,
                'ip_address'        => $request->ip(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }

        return response()->json($results);
    }

    /**
     * Verify a Paystack payment reference and record the transaction.
     */
    public function verifyPayment(Request $request)
    {
        $request->validate(['reference' => 'required|string|max:100']);

        $reference  = trim($request->input('reference'));
        $email      = $request->input('email', '');
        $fileNumber = $request->input('file_number', '');

        $user = Auth::guard('online_ls')->user();

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
                'online_ls_user_id' => $user?->id,
                'email'             => $customerEmail,
                'file_number'       => $fileNumber,
                'amount'            => $amountPaid,
                'status'            => 'paid',
                'paid_at'           => now(),
            ]
        );

        return response()->json(['success' => true, 'reference' => $reference]);
    }

    /**
     * Get slip data for a search result.
     */
    public function slipData(Request $request)
    {
        $request->validate(['payment_id' => 'required|integer']);

        $user = Auth::guard('online_ls')->user();

        $payment = LegalSearchOnlinePayment::where('id', $request->payment_id)
            ->where('online_ls_user_id', $user->id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data'    => $payment,
        ]);
    }

    /**
     * Print slip view.
     */
    public function slipPrint(Request $request)
    {
        $user = Auth::guard('online_ls')->user();

        $payment = LegalSearchOnlinePayment::where('id', $request->payment_id)
            ->where('online_ls_user_id', $user->id)
            ->firstOrFail();

        return view('online_legal_search.print.slip', compact('payment', 'user'));
    }

    /**
     * Show search history.
     */
    public function history()
    {
        $user = Auth::guard('online_ls')->user();

        $searches = DB::connection('sqlsrv')
            ->table('online_ls_search_logs')
            ->where('online_ls_user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('online_legal_search.history', compact('user', 'searches'));
    }

    /**
     * Submit feedback.
     */
    public function feedback(Request $request)
    {
        $user = Auth::guard('online_ls')->user();

        $validated = $request->validate([
            'payment_id' => ['required', 'integer'],
            'subject'    => ['required', 'string', 'max:200'],
            'message'    => ['required', 'string', 'max:1000'],
        ]);

        LegalSearchOnlineFeedback::create([
            'online_ls_user_id'         => $user->id,
            'legal_search_online_payment_id' => $validated['payment_id'],
            'subject'                   => $validated['subject'],
            'message'                   => $validated['message'],
            'status'                    => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Your feedback has been submitted successfully. We will review it shortly.',
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\PhoneOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The two endpoints behind the phone-confirmation card: ask for a code, hand one back.
 *
 * Both stay reachable while RequirePhoneVerification has the rest of the system locked —
 * they are the only way out of it — so both are deliberately narrow: they act on the
 * SIGNED-IN user and nobody else. There is no user_id parameter to be tampered with,
 * because a request that could verify another account would be a way of switching the
 * gate off for somebody else's login.
 */
class PhoneVerificationController extends Controller
{
    public function __construct(private PhoneOtpService $otp)
    {
    }

    /**
     * Send a code to the number the user confirmed or corrected.
     *
     * The number is accepted from the request rather than read off the row on purpose:
     * the card exists precisely because the stored number may be wrong, and a user who
     * cannot correct it there has no way through at all.
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate(
            ['phone' => ['nullable', 'string', 'max:20']],
            ['phone.max' => __('That does not look like a phone number.')]
        );

        $user = Auth::user();
        $result = $this->otp->sendCode($user, $validated['phone'] ?? null);

        return response()->json([
            'success' => $result['sent'],
            'message' => $result['message'],
            'data' => [
                'masked' => $result['masked'],
                // Seconds the button stays disabled for. On a failure this is the
                // cooldown that now applies anyway — the code was written to the row
                // and the wallet may have been charged, whatever the gateway said.
                'retryAfter' => $result['retry_after'],
                'expiresIn' => $result['expires_in'],
            ],
        ], $result['sent'] ? 200 : 422);
    }

    /**
     * Check the code and, if it matches, let the user into the system.
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate(
            ['code' => ['required', 'string', 'max:10']],
            ['code.required' => __('Enter the code that was sent to your phone.')]
        );

        $user = Auth::user();
        $result = $this->otp->verifyCode($user, $validated['code']);

        return response()->json([
            'success' => $result['verified'],
            'message' => $result['message'],
            'data' => [
                'attemptsLeft' => $result['attempts_left'],
                // The client reloads on true, so the card, the sidebar lock and the
                // banner all clear at once instead of at the next navigation.
                'verified' => $result['verified'],
            ],
        ], $result['verified'] ? 200 : 422);
    }
}

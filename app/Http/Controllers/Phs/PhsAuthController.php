<?php

namespace App\Http\Controllers\Phs;

use App\Http\Controllers\Controller;
use App\Mail\PhsRequestApproved;
use App\Models\Phs\PhsInstitution;
use App\Models\Phs\PhsMember;
use App\Models\Phs\PhsOnboardingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PhsAuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::guard('phs')->check()) {
            return redirect()->route('phs.dashboard');
        }
        return view('phs.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $institution = PhsInstitution::where('username', $credentials['username'])->first();

        $member = $institution
            ? $institution->members()->where('user_type', 'super_admin')->first()
            : null;

        if (!$member || !Hash::check($credentials['password'], $member->password)) {
            throw ValidationException::withMessages([
                'username' => 'These credentials do not match our records.',
            ]);
        }

        if (!$member->isActive()) {
            throw ValidationException::withMessages([
                'username' => 'This account has been suspended. Please contact your administrator.',
            ]);
        }

        if (!$member->institution || !$member->institution->isActive()) {
            throw ValidationException::withMessages([
                'username' => 'Your organization account is suspended. Please contact KLAES support.',
            ]);
        }

        Auth::guard('phs')->login($member, $request->boolean('remember'));
        $member->forceFill(['last_login_at' => now()])->save();
        $request->session()->regenerate();

        return redirect()->intended(route('phs.dashboard'));
    }

    public function showRegister()
    {
        if (Auth::guard('phs')->check()) {
            return redirect()->route('phs.dashboard');
        }
        return redirect()->route('phs.request.form')
            ->with('info', 'Please submit an onboarding request to get started.');
    }

    public function register(Request $request)
    {
        return redirect()->route('phs.request.form')
            ->with('info', 'Please submit an onboarding request to get started.');
    }

    public function showRegisterWithToken($token, Request $request)
    {
        if (Auth::guard('phs')->check()) {
            return redirect()->route('phs.dashboard');
        }

        $onboardingRequest = PhsOnboardingRequest::where('activation_token', $token)
            ->whereIn('status', [PhsOnboardingRequest::STATUS_APPROVED, PhsOnboardingRequest::STATUS_ACTIVATED])
            ->first();

        if (!$onboardingRequest || !$onboardingRequest->canRegister()) {
            return redirect()->route('phs.landing')
                ->withErrors(['token' => 'Invalid or expired registration link. Please submit a new onboarding request.']);
        }

        // Prefer the username carried in the onboarding email link so it
        // backfills the form input; fall back to a freshly suggested one.
        $urlUsername = $request->query('username');
        $suggestedUsername = (is_string($urlUsername) && preg_match('/^[a-z0-9_]{3,100}$/', $urlUsername)
            && !PhsInstitution::where('username', $urlUsername)->exists())
                ? $urlUsername
                : PhsInstitution::suggestUsername($onboardingRequest->organization_name);

        return view('phs.auth.register-with-token', [
            'onboardingRequest' => $onboardingRequest,
            'token' => $token,
            'suggestedUsername' => $suggestedUsername,
        ]);
    }

    public function registerWithToken($token, Request $request)
    {
        $onboardingRequest = PhsOnboardingRequest::where('activation_token', $token)
            ->whereIn('status', [PhsOnboardingRequest::STATUS_APPROVED, PhsOnboardingRequest::STATUS_ACTIVATED])
            ->first();

        if (!$onboardingRequest || !$onboardingRequest->canRegister()) {
            return redirect()->route('phs.landing')
                ->withErrors(['token' => 'Invalid or expired registration link.']);
        }

        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'min:3',
                'max:100',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('sqlsrv.phs_institutions', 'username'),
            ],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'agree_terms' => ['accepted'],
        ], [
            'username.regex' => 'The username may only contain lowercase letters, numbers, and underscores.',
        ]);

        $member = DB::connection('sqlsrv')->transaction(function () use ($onboardingRequest, $validated) {
            $institution = PhsInstitution::create([
                'name' => $onboardingRequest->organization_name,
                'username' => $validated['username'],
                'type' => $onboardingRequest->organization_type,
                'email' => $onboardingRequest->contact_email,
                'phone' => $onboardingRequest->phone,
                'token_balance' => 0,
                'status' => 'active',
            ]);

            $member = $institution->members()->create([
                'name' => $onboardingRequest->contact_name,
                'email' => $onboardingRequest->contact_email,
                'password' => Hash::make($validated['password']),
                'job_title' => $onboardingRequest->job_title ?? 'Administrator',
                'user_type' => 'super_admin',
                'access_role' => 'search_only',
                'status' => 'active',
            ]);

            // Credit the token package the institution selected during onboarding.
            // Use 'paystack' when paid online (reference present), 'invoice' for
            // bank-transfer / legacy paths. Either way addTokens() sets status=completed.
            $package = PhsTokenController::packages()[strtolower((string) $onboardingRequest->initial_token_package)] ?? null;
            if ($package) {
                $paymentMethod = $onboardingRequest->paystack_reference ? 'paystack' : 'invoice';
                $referenceNo   = $onboardingRequest->paystack_reference ?: $onboardingRequest->payment_reference;

                $institution->addTokens($package['tokens'], 'purchase', [
                    'package_name'   => $package['name'],
                    'amount'         => $onboardingRequest->payment_amount ?? $package['price'],
                    'payment_method' => $paymentMethod,
                    'reference_no'   => $referenceNo,
                    'approved_at'    => now(),
                    'expires_at'     => now()->addYear(),
                    'notes'          => 'Initial token package from onboarding',
                ], $member->id);
            }

            $onboardingRequest->update([
                'status' => PhsOnboardingRequest::STATUS_ACTIVATED,
                'created_phs_institution_id' => $institution->id,
            ]);

            return $member;
        });

        Auth::guard('phs')->login($member);
        $member->forceFill(['last_login_at' => now()])->save();
        $request->session()->regenerate();

        return redirect()->to(route('phs.org.index') . '?tab=branding')
            ->with('status', 'Welcome! Your organization is now registered. Set up your branding to get started.');
    }

    public function logout(Request $request)
    {
        Auth::guard('phs')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('phs.landing');
    }
}

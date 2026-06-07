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
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $member = PhsMember::where('email', $credentials['email'])->first();

        if (!$member || !Hash::check($credentials['password'], $member->password)) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        if (!$member->isActive()) {
            throw ValidationException::withMessages([
                'email' => 'This account has been suspended. Please contact your administrator.',
            ]);
        }

        if (!$member->institution || !$member->institution->isActive()) {
            throw ValidationException::withMessages([
                'email' => 'Your organization account is suspended. Please contact KLAES support.',
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

    public function showRegisterWithToken($token)
    {
        if (Auth::guard('phs')->check()) {
            return redirect()->route('phs.dashboard');
        }

        $onboardingRequest = PhsOnboardingRequest::where('activation_token', $token)
            ->where('status', PhsOnboardingRequest::STATUS_APPROVED)
            ->first();

        if (!$onboardingRequest || !$onboardingRequest->canRegister()) {
            return redirect()->route('phs.landing')
                ->withErrors(['token' => 'Invalid or expired registration link. Please submit a new onboarding request.']);
        }

        return view('phs.auth.register-with-token', [
            'onboardingRequest' => $onboardingRequest,
            'token' => $token,
        ]);
    }

    public function registerWithToken($token, Request $request)
    {
        $onboardingRequest = PhsOnboardingRequest::where('activation_token', $token)
            ->where('status', PhsOnboardingRequest::STATUS_APPROVED)
            ->first();

        if (!$onboardingRequest || !$onboardingRequest->canRegister()) {
            return redirect()->route('phs.landing')
                ->withErrors(['token' => 'Invalid or expired registration link.']);
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $member = DB::connection('sqlsrv')->transaction(function () use ($onboardingRequest, $validated) {
            $institution = PhsInstitution::create([
                'name' => $onboardingRequest->organization_name,
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

            $onboardingRequest->update([
                'status' => PhsOnboardingRequest::STATUS_ACTIVATED,
                'created_phs_institution_id' => $institution->id,
            ]);

            return $member;
        });

        Auth::guard('phs')->login($member);
        $member->forceFill(['last_login_at' => now()])->save();
        $request->session()->regenerate();

        return redirect()->route('phs.dashboard')
            ->with('status', 'Welcome! Your organization is now registered. Please purchase tokens to begin searching.');
    }

    public function logout(Request $request)
    {
        Auth::guard('phs')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('phs.landing');
    }
}

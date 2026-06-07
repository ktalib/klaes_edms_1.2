<?php

namespace App\Http\Controllers\Phs;

use App\Http\Controllers\Controller;
use App\Models\Phs\PhsInstitution;
use App\Models\Phs\PhsMember;
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
        return view('phs.auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'institution_name' => ['required', 'string', 'max:255'],
            'institution_type' => ['required', 'in:bank,law_firm,corporate'],
            'email' => ['required', 'email', 'max:255', 'unique:sqlsrv.phs_institutions,email', 'unique:sqlsrv.phs_members,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $member = DB::connection('sqlsrv')->transaction(function () use ($data) {
            $institution = PhsInstitution::create([
                'name' => $data['institution_name'],
                'type' => $data['institution_type'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'token_balance' => 0,
                'status' => 'active',
            ]);

            $member = $institution->members()->create([
                'name' => $data['institution_name'] . ' Administrator',
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'job_title' => 'Administrator',
                'user_type' => 'super_admin',
                'access_role' => 'search_only',
                'status' => 'active',
            ]);

            // No signup bonus — the institution must purchase tokens before searching.
            return $member;
        });

        Auth::guard('phs')->login($member);
        $member->forceFill(['last_login_at' => now()])->save();
        $request->session()->regenerate();

        return redirect()->route('phs.dashboard')
            ->with('status', 'Registration successful! Please purchase tokens to begin searching.');
    }

    public function logout(Request $request)
    {
        Auth::guard('phs')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('phs.landing');
    }
}

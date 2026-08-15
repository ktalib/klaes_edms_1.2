<?php

namespace App\Http\Controllers\Laas;

use App\Http\Controllers\Controller;
use App\Models\Laas\LaasApplicant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Registration and sign-in for public LAAS applicants (guard: laas).
 *
 * Applicants sign in with the phone number they will be texted on, which keeps
 * the delivery address for every workflow SMS identical to their login and
 * removes a whole class of "I never got the message" support cases.
 */
class LaasAuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::guard('laas')->check()) {
            return redirect()->route('laas.dashboard');
        }

        return view('laas.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'phone'    => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $applicant = LaasApplicant::where('phone', $this->normalizePhone($credentials['phone']))
            ->orWhere('email', $credentials['phone'])
            ->first();

        if (!$applicant || !Hash::check($credentials['password'], $applicant->password)) {
            throw ValidationException::withMessages([
                'phone' => 'These credentials do not match our records.',
            ]);
        }

        if (!$applicant->isActive()) {
            throw ValidationException::withMessages([
                'phone' => 'This account has been suspended. Please contact the Lands office.',
            ]);
        }

        Auth::guard('laas')->login($applicant, $request->boolean('remember'));
        $applicant->forceFill(['last_login_at' => now()])->save();
        $request->session()->regenerate();

        return redirect()->intended(route('laas.dashboard'));
    }

    public function showRegister()
    {
        if (Auth::guard('laas')->check()) {
            return redirect()->route('laas.dashboard');
        }

        return view('laas.auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:200'],
            'email'    => ['required', 'email', 'max:150'],
            'phone'    => ['required', 'string', 'max:30'],
            'nin'      => ['nullable', 'string', 'max:30'],
            'address'  => ['nullable', 'string', 'max:500'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $phone = $this->normalizePhone($data['phone']);

        if (!$phone) {
            throw ValidationException::withMessages([
                'phone' => 'Enter a valid Nigerian phone number, e.g. 08031234567.',
            ]);
        }

        // Checked by hand rather than with the `unique` rule: both columns live
        // on the sqlsrv connection, and the phone must be compared in its
        // normalised form, not as the applicant typed it.
        if (LaasApplicant::where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages(['email' => 'An account already exists for this email address.']);
        }

        if (LaasApplicant::where('phone', $phone)->exists()) {
            throw ValidationException::withMessages(['phone' => 'An account already exists for this phone number.']);
        }

        $applicant = LaasApplicant::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'phone'    => $phone,
            'nin'      => $data['nin'] ?? null,
            'address'  => $data['address'] ?? null,
            'password' => Hash::make($data['password']),
            'status'   => 'active',
        ]);

        Auth::guard('laas')->login($applicant);
        $request->session()->regenerate();

        return redirect()->route('laas.apply.form')
            ->with('status', 'Your account is ready. You can now fill your land allocation application.');
    }

    public function logout(Request $request)
    {
        Auth::guard('laas')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('laas.landing');
    }

    /**
     * Storage form for phone numbers — see LaasApplicant::normalizePhone().
     * A thin pass-through so sign-in, registration and the profile screen can
     * never drift apart on what counts as the same number.
     */
    private function normalizePhone(string $phone): ?string
    {
        return LaasApplicant::normalizePhone($phone);
    }
}

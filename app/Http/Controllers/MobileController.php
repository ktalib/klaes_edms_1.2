<?php

namespace App\Http\Controllers;

use App\Models\Office;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class MobileController extends Controller
{
    /**
     * Show the mobile login form.
     */
    public function loginForm()
    {
        if (Auth::check()) {
            return redirect()->route('mobile.dashboard');
        }
        return view('mobile.login');
    }

    /**
     * Handle login form submission using standard web session auth.
     */
    public function login(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'password'   => 'required|string',
        ]);

        $identifier = trim($request->input('identifier'));

        // Find by username or email
        $user = User::where('username', $identifier)
            ->orWhere('email', $identifier)
            ->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            return back()->withInput()->withErrors([
                'identifier' => 'Invalid username or password.',
            ]);
        }

        if ((string) $user->is_active === '0') {
            return back()->withInput()->withErrors([
                'identifier' => 'Your account is disabled. Contact support.',
            ]);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->route('mobile.dashboard');
    }

    /**
     * Render the main dashboard Blade view, injecting data needed for dropdowns.
     */
    public function dashboard()
    {
        $offices = Office::where('is_active', true)
            ->orderBy('office_name')
            ->get(['id', 'office_code', 'office_name', 'office_abbreviation', 'department']);

        $officers = User::where('is_active', 1)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'username']);

        $user = Auth::user();

        return view('mobile.dashboard', compact('offices', 'officers', 'user'));
    }

    /**
     * Log the user out and return to the mobile login page.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('mobile.login');
    }
}

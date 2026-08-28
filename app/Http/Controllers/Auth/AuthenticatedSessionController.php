<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\LoggedHistory;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        if (!file_exists(setup())) {
            header('location:install');
            die;
        }

        $user = \App\Models\User::find(1);
        \App::setLocale($user->lang);

        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     *
     * @param  \App\Http\Requests\Auth\LoginRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(LoginRequest $request)
    {
        $google_recaptcha = getSettingsValByName('google_recaptcha');
        if ($google_recaptcha == 'on') {
            $validation['g-recaptcha-response'] = 'required|captcha';
        } else {
            $validation = [];
        }
        $this->validate($request, $validation);

        // Manually authenticate using username instead of email
        $credentials = [
            'username' => $request->input('username'),
            'password' => $request->input('password'),
        ];

        if (!Auth::attempt($credentials, $request->filled('remember'))) {
            return redirect()->route('login')->with('error', __('The provided credentials do not match our records.'));
        }

        $request->session()->regenerate();
        $loginUser = Auth::user();
        if ($loginUser->is_active == 0) {
            auth()->logout();
            return redirect()->route('login')->with('error', __('Your account is temporarily inactive. Please contact your administrator to reactivate your account.'));
        }
        if (empty($loginUser->email_verified_at)) {
            auth()->logout();
            return redirect()->route('login')->with('error', __('Verification required: Please check your email to verify your account before continuing.'));
        }
        userLoggedHistory();

        // Show the welcome card once, on the first page loaded after login
        session(['show_welcome_popup' => true]);

        // Clear intended URL if it points to a JSON/utility endpoint
        // (e.g. /world-time, /api/*, etc.) to prevent redirecting there after login
        $intended = session()->get('url.intended');
        if ($intended) {
            $path = parse_url($intended, PHP_URL_PATH);
            $blockedPaths = ['/world-time', '/api/', '/get-next-temp-fileno'];
            foreach ($blockedPaths as $blocked) {
                if ($path && str_contains($path, $blocked)) {
                    session()->forget('url.intended');
                    break;
                }
            }
        }

        if ($loginUser->type == 'owner') {

            if ($loginUser->subscription_expire_date != null && date('Y-m-d') > $loginUser->subscription_expire_date) {
                assignSubscription(1);
                return redirect()->intended(RouteServiceProvider::HOME)->with('error', __('Your subscription has ended, and access to premium features is now restricted. To continue using our services without interruption, please renew your plan or upgrade to a higher-tier package.'));
            }
        }
        return redirect()->intended(RouteServiceProvider::HOME);
    }

    /**
     * Destroy an authenticated session.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        // Check if this was an auto logout or manual logout with home redirect request
        if ($request->has('auto_logout') || $request->has('redirect_to_home')) {
            return redirect('/')->with('message', 'You have been logged out due to inactivity.');
        }
        
        // Default logout behavior - redirect to home page
        return redirect('/');
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * A passport photo is mandatory: until the signed-in user has one, the only things
 * they may reach are the landing page that carries the upload card, the upload
 * endpoint itself, and logout. Greying out the sidebar is not enough on its own —
 * without this the requirement is bypassed by typing a URL.
 *
 * Only the `web` guard is gated; the public portals (laas, phs, ols) run on their
 * own guards and are untouched.
 */
class RequireProfilePhoto
{
    /**
     * Routes that must stay reachable while the account is locked, otherwise the
     * user could neither satisfy the requirement nor sign out.
     */
    private const ALLOWED_ROUTE_NAMES = [
        'home',
        'dashboard',
        'profile.picture.store',
        'logout',
        'login',
        'markWelcomePopupShown',
    ];

    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('web')->user();

        if (!$user || $user->has_profile_photo) {
            return $next($request);
        }

        if ($this->isAllowed($request)) {
            return $next($request);
        }

        $message = __('Upload your profile picture to continue using the system.');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'profile_photo_required' => true,
            ], 403);
        }

        // Land on the page that carries the upload card, with it opened for them.
        return redirect()
            ->route('home')
            ->with('open_profile_photo_card', true)
            ->with('error', $message);
    }

    private function isAllowed(Request $request): bool
    {
        // Public portals run on their own guards; a staff member browsing one is
        // not "using the system" in the sense this gate is about.
        if ($request->is('laas', 'laas/*', 'phs', 'phs/*', 'online-legal-search', 'online-legal-search/*')) {
            return true;
        }

        $routeName = optional($request->route())->getName();

        if ($routeName && in_array($routeName, self::ALLOWED_ROUTE_NAMES, true)) {
            return true;
        }

        // Logout is a POST on some layouts and a GET link on others.
        return $request->is('logout', 'login', 'home', 'dashboard');
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdminTwoFactor
{
    /**
     * Pages an administrator must still reach in order to finish setting up two-factor authentication.
     */
    private const ALLOWED_PATHS = [
        'settings/*',
        'user/*',
        'two-factor-challenge',
        'confirm-password',
        'logout',
        'passkeys*',
        '.well-known/*',
        'livewire*',
        'livewire-*/*',
        'up',
        'healthcheck',
        'status',
    ];

    /**
     * When REQUIRE_ADMIN_2FA is on, an administrator without confirmed two-factor
     * authentication is sent to the security settings page.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('security.require_admin_2fa')) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user || ! $user->isAdmin() || $user->two_factor_confirmed_at !== null) {
            return $next($request);
        }

        if ($request->is(...self::ALLOWED_PATHS)) {
            return $next($request);
        }

        return redirect()
            ->route('security.edit')
            ->with('status', 'Administrators must turn on two-factor authentication before continuing.');
    }
}

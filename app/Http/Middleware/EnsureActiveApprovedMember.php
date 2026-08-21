<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureActiveApprovedMember
{
    public function handle(Request $request, Closure $next, string $guard = 'api')
    {
        $user = $guard === 'web'
            ? Auth::guard('web')->user()
            : $request->user();

        if (!$user) {
            return $guard === 'web'
                ? $next($request)
                : $this->apiDeniedResponse();
        }

        if (!$user->isAuthenticationEligible()) {
            $user->revokeAuthenticationArtifacts();

            if ($guard === 'web') {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson()) {
                    return $this->apiDeniedResponse();
                }

                return redirect()->route('showLogin')->with('message', [
                    'type' => 'error',
                    'text' => 'This account is inactive or awaiting approval.',
                ]);
            }

            return $this->apiDeniedResponse();
        }

        return $next($request);
    }

    private function apiDeniedResponse()
    {
        return response()->json([
            'status' => false,
            'message' => 'This account is inactive or awaiting approval.',
        ], 403);
    }
}

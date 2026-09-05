<?php

namespace App\Http\Middleware;

use App\Services\SiteSettingService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureActiveApprovedMember
{
    public function __construct(private SiteSettingService $siteSettings)
    {
    }

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
                    'text' => $this->accountUnavailableMessage(),
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
            'message' => $this->accountUnavailableMessage(),
        ], 403);
    }

    private function accountUnavailableMessage(): string
    {
        $settings = $this->siteSettings->values(app()->getLocale(), true)['member_area'] ?? [];
        $fallback = 'This account is inactive or awaiting approval.';
        $value = is_scalar($settings['account_unavailable_message'] ?? null)
            ? (string) $settings['account_unavailable_message']
            : $fallback;
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        return $value !== '' ? mb_substr($value, 0, 500) : $fallback;
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MemberCredentialVerifier;
use App\Services\PublicSystemPageMetaService;
use App\Services\SiteSettingService;
use App\Services\TwoFactorChallengeService;
use Auth;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Redirect;
use Session;
use Inertia\Inertia;

class AuthController extends Controller {

    public function __construct(
        private SiteSettingService $siteSettings,
        private PublicSystemPageMetaService $systemMeta,
    ) {
    }

    public function showLogin(Request $request) {
        $pageMeta = $this->memberPageMeta(
            $request,
            'member_area.title',
            'member_area.introduction',
            'Member login',
            'Secure member access.'
        );

        return Inertia::render('auth/login')->with([
            'status' => true,
            ...$pageMeta,
        ]);
    }

    public function showRegister(Request $request) {
        $memberSettings = $this->memberSettings();
        abort_unless((bool) ($memberSettings['registration_enabled'] ?? false), 404);
        $pageMeta = $this->memberPageMeta(
            $request,
            'member_area.registration_title',
            'member_area.registration_introduction',
            'Apply for member access',
            'Apply for administrator-approved member access.'
        );

        return Inertia::render('auth/register')->with([
            'status' => true,
            ...$pageMeta,
        ]);
    }

    public function login(Request $request, MemberCredentialVerifier $credentials, $locale = 'en') {

        $this->validate($request, [
            'phone_no' => 'required|numeric|digits:11',
            'password' => 'required|string|min:6',
        ]);

        try {
            $user = User::query()
                ->where('phone_no', $request->phone_no)
                ->where('provider_type', 'local')
                ->first();

            if (!$credentials->passes($user, (string) $request->password)) {
                return $this->invalidCredentialResponse();
            }

            if ($user->hasTwoFactorEnabled()) {
                return Redirect::route('login2fa')->with('message', [
                    'type' => 'warning',
                    'text' => $this->memberMessage(
                        'two_factor_required_message',
                        'Two-factor authentication is enabled. Continue with secure login.'
                    ),
                ]);
            }

            $isValidUrl = filter_var($user->avatar, FILTER_VALIDATE_URL);
            if (empty($isValidUrl)) {
                $user->avatar = $user->avatarUrl();
            }

            Auth::guard('web')->login($user, $request->boolean('remember'));
            $request->session()->regenerate();
            $response = [
                'type' => 'success',
                'text' => $this->memberMessage('login_success_message', 'You are signed in.'),
            ];
            return Redirect::route('frontend.home')->with('message', $response);
        } catch (Exception $e) {
            Log::warning('Member authentication failed unexpectedly.', [
                'exception_class' => $e::class,
            ]);

            return $this->invalidCredentialResponse();
        }
    }

    public function register(Request $request, $locale = 'en') {
        $memberSettings = $this->memberSettings();
        abort_unless((bool) ($memberSettings['registration_enabled'] ?? false), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'phone_no' => ['required', 'numeric', 'digits:11', 'unique:users,phone_no'],
            'email' => ['required', 'email', 'max:50', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'org' => ['required', 'string', 'max:150'],
            'designation' => ['required', 'string', 'max:150'],
        ]);

        try {
            $data = User::create([
                'name' => $validated['name'],
                'phone_no' => $validated['phone_no'],
                'email' => $validated['email'],
                'org' => $validated['org'],
                'designation' => $validated['designation'],
                'password' => Hash::make($validated['password']),
                'status' => 1,
                'provider_type' => 'local',
                'is_approved' => 0, // 0 -> pending, 1-> approved, 2-> Rejected
            ]);
            if ($data) {
                $response = ['type' => 'success', 'text' => $memberSettings['registration_success_message'] ?? 'Your member application was submitted for administrator approval.'];
                return Redirect::route('frontend.home')->with('message', $response);
            } else {
                $response = [
                    'type' => 'error',
                    'text' => $this->memberMessage(
                        'registration_failure_message',
                        'Your member application could not be submitted. Please try again later.'
                    ),
                ];
                return back()->with('message', $response);
            }
        } catch (Exception $e) {
            report($e);
            $response = [
                'type' => 'error',
                'text' => $this->memberMessage(
                    'registration_failure_message',
                    'Your member application could not be submitted. Please try again later.'
                ),
            ];
            return back()->with('message', $response);
        }
    }

    private function memberSettings(): array
    {
        return $this->siteSettings->values(app()->getLocale(), true)['member_area'] ?? [];
    }

    public function showLogin2fa(Request $request) {
        $pageMeta = $this->memberPageMeta(
            $request,
            'member_area.two_factor_title',
            'member_area.two_factor_introduction',
            'Secure login',
            'Two-factor protected member access.'
        );

        return Inertia::render('auth/login-2fa')->with([
            'status' => true,
            ...$pageMeta,
        ]);
    }

    public function showLogin2faVerify(Request $request) {
        $response = Session::get('data');
        if (!is_array($response) || empty($response['access_token'])) {
            return Redirect::route('login2fa')->with('message', [
                'type' => 'warning',
                'text' => $this->memberMessage(
                    'verification_restart_message',
                    'Start a new secure login challenge.'
                ),
            ]);
        }

        $enrollment = (bool) ($response['enrollment_required'] ?? false);
        $response = array_merge($response, $this->memberPageMeta(
            $request,
            $enrollment ? 'member_area.verification_setup_title' : 'member_area.verification_code_title',
            $enrollment ? 'member_area.verification_setup_body' : 'member_area.verification_code_body',
            $enrollment ? 'Set up your authenticator' : 'Enter your security code',
            'Complete your secure member login.'
        ));

        return Inertia::render('auth/login-2fa-verify')->with($response);
    }

    private function memberPageMeta(
        Request $request,
        string $titlePath,
        string $descriptionPath,
        string $fallbackTitle,
        string $fallbackDescription
    ): array {
        $pageMeta = $this->systemMeta->resolve(
            $request,
            $titlePath,
            $descriptionPath,
            [
                'title' => $fallbackTitle,
                'meta_title' => $fallbackTitle,
                'description' => $fallbackDescription,
            ]
        );
        $pageMeta['meta_tag']['robots'] = 'noindex,nofollow,noarchive';

        return $pageMeta;
    }

    public function login2fa(
        Request $request,
        TwoFactorChallengeService $challenges,
        MemberCredentialVerifier $credentials
    ) {
        $this->validate($request, [
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:6',
        ]);

        try {
            $user = User::query()
                ->where('email', $request->email)
                ->where('provider_type', 'local')
                ->first();

            if (!$credentials->passes($user, (string) $request->password)) {
                return $this->invalidCredentialResponse();
            }

            $google2fa = app('pragmarx.google2fa');
            $isEnrollment = empty($user->google2fa_secret);
            $secret = $isEnrollment
                ? $google2fa->generateSecretKey()
                : $user->google2fa_secret;
            $qrImage = null;

            if ($isEnrollment) {
                $qrUrl = $google2fa->getQRCodeUrl(config('app.name'), $user->email, $secret);
                $renderer = new ImageRenderer(new RendererStyle(200), new SvgImageBackEnd());
                $writer = new Writer($renderer);
                $qrImage = "data:image/svg+xml;base64," . base64_encode($writer->writeString($qrUrl));
            }

            $accessToken = $challenges->create($user, $isEnrollment ? $secret : null);
            $response = [
                'status' => true,
                'access_token' => $accessToken,
                'enrollment_required' => $isEnrollment,
                'qr_image' => $qrImage,
            ];
            Session::put('data', $response);
            return Redirect::route('login2fa.verify');
        } catch (Exception $e) {
            Log::warning('Member two-factor authentication failed unexpectedly.', [
                'exception_class' => $e::class,
            ]);

            return $this->invalidCredentialResponse();
        }
    }

    public function verify2fa(Request $request, TwoFactorChallengeService $challenges) {
        $this->validate($request, [
            'access_token' => 'required|string|size:64',
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        try {
            $challenge = $challenges->consume($request->string('access_token')->toString());
            if ($challenge === null) {
                return back()->with('message', [
                    'type' => 'error',
                    'text' => $this->memberMessage(
                        'verification_expired_message',
                        'The verification challenge is invalid or expired.'
                    ),
                ]);
            }

            $user = User::find($challenge['user_id']);
            if (empty($user) || empty($user->status) || (int) $user->is_approved !== 1) {
                return back()->with('message', [
                    'type' => 'error',
                    'text' => $this->memberMessage(
                        'verification_expired_message',
                        'The verification challenge is invalid or expired.'
                    ),
                ]);
            }

            $secret = $user->google2fa_secret ?: $challenge['pending_secret'];
            if (empty($secret)) {
                return back()->with('message', [
                    'type' => 'error',
                    'text' => $this->memberMessage(
                        'verification_expired_message',
                        'The verification challenge is invalid or expired.'
                    ),
                ]);
            }

            $google2fa = app('pragmarx.google2fa');
            if ($google2fa->verifyGoogle2FA($secret, $request->code)) {
                if (empty($user->google2fa_secret)) {
                    $user->google2fa_secret = $secret;
                    $user->save();
                }
                $user->encryptLegacyTwoFactorSecretIfNeeded();

                $isValidUrl = filter_var($user->avatar, FILTER_VALIDATE_URL);
                if (empty($isValidUrl)) {
                    $user->avatar = $user->avatarUrl();
                }

                Auth::login($user, false);
                $request->session()->regenerate();
                Session::forget('data');

                return Redirect::route('frontend.home')->with('message', [
                    'type' => 'success',
                    'text' => $this->memberMessage('login_success_message', 'You are signed in.'),
                ]);
            }

            return back()->with('message', [
                'type' => 'error',
                'text' => $this->memberMessage(
                    'verification_mismatch_message',
                    'Verification code mismatch. Start a new login challenge.'
                ),
            ]);
        } catch (Exception $e) {
            report($e);
            $response = [
                'type' => 'error',
                'text' => $this->memberMessage(
                    'verification_failure_message',
                    'Verification could not be completed. Please try again later.'
                ),
            ];
            return back()->with('message', $response);
        }
    }

    private function invalidCredentialResponse()
    {
        return back()->with('message', [
            'type' => 'error',
            'text' => $this->memberMessage(
                'invalid_credentials_message',
                MemberCredentialVerifier::FAILURE_MESSAGE
            ),
        ]);
    }

    public function logout(Request $request, $locale = 'en') {
        try {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $response = [
                'type' => 'success',
                'text' => $this->memberMessage('logout_success_message', 'You are signed out.'),
            ];
            return Redirect::route('frontend.home')->with('message', $response);
        } catch (Exception $e) {
            $response = [
                'type' => 'error',
                'text' => $this->memberMessage(
                    'logout_failure_message',
                    'Sign out could not be completed. Please try again.'
                ),
            ];
            return back()->with('message', $response);
        }
    }

    public function changePassword(Request $request, $locale = 'en')
    {
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ]);

        if ($validator->fails()) {
            $response = [
                'type' => 'error',
                'text' => $this->memberMessage(
                    'password_validation_message',
                    'Please correct the highlighted password fields.'
                ),
            ];
            return back()->withErrors($validator)->with('message', $response);
        }

        try {

            $user = Auth::user();
            $currentPassword = $user->password;

            if (Hash::check($request->current_password, $currentPassword)) {
                $user->password = Hash::make($request->password);
                $user->save();
                $user->revokeAuthenticationArtifacts();

                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                $response = [
                    'type' => 'success',
                    'text' => $this->memberMessage(
                        'password_success_message',
                        'Password changed successfully.'
                    ),
                ];
                return Redirect::route('frontend.home')->with('message', $response);                
            } else {
                $response = [
                    'type' => 'error',
                    'text' => $this->memberMessage(
                        'password_incorrect_message',
                        'Incorrect current password.'
                    ),
                ];
                return back()
                    ->withErrors([
                        'current_password' => $this->memberMessage(
                            'password_current_field_error',
                            'The current password is incorrect.'
                        ),
                    ])
                    ->with('message', $response);
            }
        } catch (Exception $e) {
            $response = [
                'type' => 'error',
                'text' => $this->memberMessage(
                    'password_failure_message',
                    'Password could not be changed. Please try again later.'
                ),
            ];
            return back()->with('message', $response);
        }
    }

    private function memberMessage(string $key, string $fallback): string
    {
        $value = $this->memberSettings()[$key] ?? $fallback;
        $value = is_scalar($value) ? (string) $value : '';
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        return $value !== '' ? mb_substr($value, 0, 500) : $fallback;
    }

}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SiteSettingService;
use App\Services\TwoFactorChallengeService;
use Auth;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Redirect;
use Session;
use Inertia\Inertia;

class AuthController extends Controller {

    public function showLogin(Request $request) {
        return Inertia::render('auth/login')->with([
            'status' => true,
            'title' => 'Member login',
            'meta_tag' => [
                'meta_title' => 'Member Login | Ignite Global Foundation',
                'meta_description' => 'Secure member access for Ignite Global Foundation.',
                'robots' => 'noindex,nofollow,noarchive',
            ],
        ]);
    }

    public function showRegister(Request $request) {
        $memberSettings = $this->memberSettings();
        abort_unless((bool) ($memberSettings['registration_enabled'] ?? false), 404);

        return Inertia::render('auth/register')->with([
            'status' => true,
            'title' => $memberSettings['registration_title'] ?? 'Apply for member access',
            'meta_tag' => [
                'meta_title' => 'Member Application | Ignite Global Foundation',
                'meta_description' => 'Apply for administrator-approved member access to Ignite Global Foundation.',
                'robots' => 'noindex,nofollow,noarchive',
            ],
        ]);
    }

    public function login(Request $request, $locale = 'en') {

        $this->validate($request, [
            'phone_no' => 'required|numeric|digits:11',
            'password' => 'required|string|min:6',
        ]);

        try {

            $user = User::where('phone_no', @$request->phone_no)->where('provider_type', 'local')->where('is_approved',1)->first();

            if (empty($user)) {
                $response = ['type' => 'error', 'text' => 'Your account is not registered or approved yet.'];
                return back()->with('message', $response);
            }

            if (empty($user->status)) {
                $response = ['type' => 'error', 'text' => 'You are not active for login.'];
                return back()->with('message', $response);
            }

            if ($user->hasTwoFactorEnabled()) {
                if (!Hash::check($request->password, $user->password)) {
                    $response = ['type' => 'error', 'text' => 'password mismatch. please try again'];
                    return back()->with('message', $response);
                }

                return Redirect::route('login2fa')->with('message', [
                    'type' => 'warning',
                    'text' => 'Two-factor authentication is enabled. Continue with secure login.',
                ]);
            }

            $isValidUrl = filter_var($user->avatar, FILTER_VALIDATE_URL);
            if (empty($isValidUrl)) {
                $user->avatar = $user->avatarUrl();
            }

            if (Auth::attempt(['phone_no' => $request->phone_no, 'password' => $request->password], $request->remember)) {
                $request->session()->regenerate();
                $response = ['type' => 'success', 'text' => 'Success Login'];
                return Redirect::route('frontend.home')->with('message', $response);
            } else {
                $response = ['type' => 'error', 'text' => 'password mismatch. please try again'];
                return back()->with('message', $response);
            }
        } catch (Exception $e) {
            $response = ['type' => 'error', 'text' => 'You are not sign in. Please try again later.'];
            return back()->with('message', $response);
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
                $response = ['type' => 'error', 'text' => 'Signup Failed'];
                return back()->with('message', $response);
            }
        } catch (Exception $e) {
            report($e);
            $response = ['type' => 'error', 'text' => 'You are not signup in. Please try again later.'];
            return back()->with('message', $response);
        }
    }

    private function memberSettings(): array
    {
        return app(SiteSettingService::class)->values(app()->getLocale(), true)['member_area'] ?? [];
    }

    public function showLogin2fa(Request $request) {
        return Inertia::render('auth/login-2fa')->with([
            'status' => true,
            'title' => 'Secure login',
            'meta_tag' => [
                'meta_title' => 'Secure Login | Ignite Global Foundation',
                'meta_description' => 'Two-factor protected member access for Ignite Global Foundation.',
                'robots' => 'noindex,nofollow,noarchive',
            ],
        ]);
    }

    public function showLogin2faVerify(Request $request) {
        $response = Session::get('data');
        if (!is_array($response) || empty($response['access_token'])) {
            return Redirect::route('login2fa')->with('message', [
                'type' => 'warning',
                'text' => 'Start a new secure login challenge.',
            ]);
        }

        $response['meta_tag'] = [
            'meta_title' => 'Verify Secure Login | Ignite Global Foundation',
            'meta_description' => 'Complete your secure Ignite Global Foundation login.',
            'robots' => 'noindex,nofollow,noarchive',
        ];
        return Inertia::render('auth/login-2fa-verify')->with($response);
    }

    public function login2fa(Request $request, TwoFactorChallengeService $challenges) {

        $this->validate($request, [
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:6',
        ]);

        try {
            $user = User::where('email', @$request->email)->where('is_approved', 1)->first();

            if (empty($user)) {
                $response = ['type' => 'error', "text" => 'You are not sign in. Please try again later.'];
                return back()->with('message', $response);
            }

            if (empty($user->status)) {
                $response = ['type' => 'error', "text" => "You are not active for login." . $user->status];
                return back()->with('message', $response);
            }

            if (!Hash::check($request->password, $user->password)) {
                $response = ['type' => 'error', 'text' => 'password mismatch. please try again'];
                return back()->with('message', $response);
            }

            $google2fa = app('pragmarx.google2fa');
            $isEnrollment = empty($user->google2fa_secret);
            $secret = $isEnrollment
                ? $google2fa->generateSecretKey()
                : $user->google2fa_secret;
            $qrImage = null;

            if ($isEnrollment) {
                $qrUrl = $google2fa->getQRCodeUrl(config('app.name'), $user->email, $secret);
                $renderer = new ImageRenderer(new RendererStyle(200), new ImagickImageBackEnd());
                $writer = new Writer($renderer);
                $qrImage = "data:image/png;base64," . base64_encode($writer->writeString($qrUrl));
            }

            $accessToken = $challenges->create($user, $isEnrollment ? $secret : null);
            $response = [
                'status' => true,
                'title' => 'Login Verify',
                'access_token' => $accessToken,
                'enrollment_required' => $isEnrollment,
                'qr_image' => $qrImage,
            ];
            Session::put('data', $response);
            return Redirect::route('login2fa.verify');
        } catch (Exception $e) {
            report($e);
            $response = ['type' => 'error', 'text' => 'You are not sign in. Please try again later.'];
            return back()->with('message', $response);
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
                return back()->with('message', ['type' => 'error', 'text' => 'The verification challenge is invalid or expired.']);
            }

            $user = User::find($challenge['user_id']);
            if (empty($user) || empty($user->status) || (int) $user->is_approved !== 1) {
                return back()->with('message', ['type' => 'error', 'text' => 'The verification challenge is invalid or expired.']);
            }

            $secret = $user->google2fa_secret ?: $challenge['pending_secret'];
            if (empty($secret)) {
                return back()->with('message', ['type' => 'error', 'text' => 'The verification challenge is invalid or expired.']);
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

                return Redirect::route('frontend.home')->with('message', ['type' => 'success', 'text' => 'Success Login']);
            }

            return back()->with('message', ['type' => 'error', 'text' => 'Verification code mismatch. Start a new login challenge.']);
        } catch (Exception $e) {
            report($e);
            $response = ['type' => 'error', 'text' => 'Verification code mismatch. Please try again later.'];
            return back()->with('message', $response);
        }
    }

    public function logout(Request $request, $locale = 'en') {
        try {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $response = ['type' => 'success', 'text' => 'successfully logout'];
            return Redirect::route('frontend.home')->with('message', $response);
        } catch (Exception $e) {
            $response = ['type' => 'error', 'text' => 'Something wrong'];
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
            $response = ['type' => 'error', 'text' => 'Please fill all required fields correctly'];
            return back()->with('message', $response);
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

                $response = ['type' => 'success', 'text' => 'Password changed successfully.'];
                return Redirect::route('frontend.home')->with('message', $response);                
            } else {
                $response = ['type' => 'error', 'text' => 'Incorrect current password.'];
                return back()->with('message', $response);
            }
        } catch (Exception $e) {
            $response = ['type' => 'error', 'text' => 'Password change Failed. Please try again later.'];
            return back()->with('message', $response);
        }
    }

}

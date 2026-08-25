<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MemberCredentialVerifier;
use App\Services\TwoFactorChallengeService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Socialite\Facades\Socialite;

class ApiAuthController extends Controller {

    public function login(Request $request, MemberCredentialVerifier $credentials) {

        $validator = Validator::make($request->all(), [
                    'phone_no' => 'required|string|max:30',
                    'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response(['status' => false, 'message' => implode(",", $validator->errors()->all())], 422);
        }

        try {
            $user = User::query()
                ->where('phone_no', $request->phone_no)
                ->where('provider_type', 'local')
                ->first();

            if (!$credentials->passes($user, (string) $request->password)) {
                return response([
                    'status' => false,
                    'message' => MemberCredentialVerifier::FAILURE_MESSAGE,
                ], 200);
            }

            if ($user->hasTwoFactorEnabled()) {
                return response([
                    'status' => false,
                    'requires_2fa' => true,
                    'message' => 'Two-factor authentication is required. Continue with secure login.',
                ], 200);
            }

            $isValidUrl = filter_var($user->avatar, FILTER_VALIDATE_URL);
            if (empty($isValidUrl)) {
                $user->avatar = $user->avatarUrl();
            }

            $token = $user->createToken('cyberTeen')->accessToken;

            $response = [
                'status' => true,
                'token' => $token,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone_no' => $user->phone_no,
                    'email' => @$user->email,
                    'gender' => @$user->gender,
                    'dob' => @$user->dob,
                    'address' => @$user->address,
                    'study_type' => @$user->study_type,
                    'institute_name' => @$user->institute_name,
                    'division_id' => @$user->division_id,
                    'district_id' => @$user->district_id,
                    'upazila_id' => @$user->upazila_id,
                    'post_code' => @$user->post_code,
                    'avatar' => @$user->avatar,
                ],
            ];
            return response($response, 200);
        } catch (Exception $e) {
            Log::warning('API member authentication failed unexpectedly.', [
                'exception_class' => $e::class,
            ]);

            return response(['status' => false, 'message' => 'Authentication is temporarily unavailable.'], 500);
        }
    }

    public function login2fa(
        Request $request,
        TwoFactorChallengeService $challenges,
        MemberCredentialVerifier $credentials
    ) {

        $validator = Validator::make($request->all(), [
                    'phone_no' => 'required|string|max:30',
                    'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response(['status' => false, 'message' => implode(",", $validator->errors()->all())], 422);
        }

        try {
            $user = User::query()
                ->where('phone_no', $request->phone_no)
                ->where('provider_type', 'local')
                ->first();

            if (!$credentials->passes($user, (string) $request->password)) {
                return response([
                    'status' => false,
                    'message' => MemberCredentialVerifier::FAILURE_MESSAGE,
                ], 200);
            }

            $google2fa = app('pragmarx.google2fa');
            $isEnrollment = empty($user->google2fa_secret);
            $secret = $isEnrollment
                ? $google2fa->generateSecretKey()
                : $user->google2fa_secret;
            $qrImage = $isEnrollment
                ? $google2fa->getQRCodeInline(config('app.name'), $user->phone_no, $secret)
                : null;
            $accessToken = $challenges->create($user, $isEnrollment ? $secret : null);

            $response = [
                'status' => true,
                'access_token' => $accessToken,
                'enrollment_required' => $isEnrollment,
                'qr_image' => $qrImage,
            ];
            return response($response, 200);
        } catch (Exception $e) {
            Log::warning('API member two-factor authentication failed unexpectedly.', [
                'exception_class' => $e::class,
            ]);

            return response(['status' => false, 'message' => 'Authentication is temporarily unavailable.'], 500);
        }
    }

    public function verify2fa(Request $request, TwoFactorChallengeService $challenges) {
        $validator = Validator::make($request->all(), [
                    'access_token' => 'required|string|size:64',
                    'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);
        if ($validator->fails()) {
            return response(['status' => false, 'message' => implode(",", $validator->errors()->all())], 422);
        }

        try {
            $challenge = $challenges->consume($request->string('access_token')->toString());
            if ($challenge === null) {
                return response(['status' => false, 'message' => 'The verification challenge is invalid or expired.'], 422);
            }

            $user = User::find($challenge['user_id']);
            if (empty($user) || empty($user->status) || (int) $user->is_approved !== 1) {
                return response(['status' => false, 'message' => 'The verification challenge is invalid or expired.'], 422);
            }

            $secret = $user->google2fa_secret ?: $challenge['pending_secret'];
            if (empty($secret)) {
                return response(['status' => false, 'message' => 'The verification challenge is invalid or expired.'], 422);
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

                $token = $user->createToken('cyberTeen')->accessToken;
                $response = [
                    'status' => true,
                    'token' => $token,
                    'data' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'phone_no' => $user->phone_no,
                        'email' => @$user->email,
                        'gender' => @$user->gender,
                        'dob' => @$user->dob,
                        'address' => @$user->address,
                        'study_type' => @$user->study_type,
                        'institute_name' => @$user->institute_name,
                        'division_id' => @$user->division_id,
                        'district_id' => @$user->district_id,
                        'upazila_id' => @$user->upazila_id,
                        'post_code' => @$user->post_code,
                        'avatar' => @$user->avatar,
                    ],
                ];
                return response($response, 200);
            }

            return response(['status' => false, 'message' => 'Verification code mismatch. Start a new login challenge.'], 422);
        } catch (Exception $e) {
            report($e);
            return response(['status' => false, 'message' => 'Authentication is temporarily unavailable.'], 500);
        }
    }

    public function social(Request $request)
    {
        $validated = $request->validate([
            'access_token' => ['required', 'string', 'max:4096'],
            'provider' => ['required', Rule::in(['google', 'facebook'])],
        ]);

        try {
            $provider = $validated['provider'];
            $providerUser = Socialite::with($provider)
                ->stateless()
                ->userFromToken($validated['access_token']);
            $providerId = trim((string) $providerUser->getId());
            $email = Str::lower(trim((string) $providerUser->getEmail()));

            if ($providerId === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 50) {
                return response(['status' => false, 'message' => 'The provider did not return a usable account.'], 422);
            }

            $user = User::query()
                ->where('provider_type', $provider)
                ->where('social_id', $providerId)
                ->first();
            $isUser = $user ? 'old' : 'new';

            if (!$user) {
                if (User::query()->where('email', $email)->exists()) {
                    return response(['status' => false, 'message' => 'That email is already linked to another account.'], 422);
                }

                $name = trim((string) $providerUser->getName());
                $user = User::create([
                    'social_id' => $providerId,
                    'name' => Str::limit($name !== '' ? $name : Str::before($email, '@'), 50, ''),
                    'email' => $email,
                    'provider_type' => $provider,
                    'avatar' => $providerUser->getAvatar(),
                    'password' => Hash::make(Str::random(64)),
                    'status' => 1,
                    'is_approved' => config('security.social_registration_auto_approve') ? 1 : 0,
                    'email_verified_at' => now(),
                ]);
            }

            if (!(bool) $user->status) {
                return response(['status' => false, 'message' => 'This member account is inactive.'], 403);
            }

            if ((int) $user->is_approved !== 1) {
                return response([
                    'status' => false,
                    'approval_status' => 'pending',
                    'message' => 'Your account is awaiting administrator approval.',
                ], 202);
            }

            $token = $user->createToken('ignite-global-foundation')->accessToken;

            return response([
                'status' => true,
                'is_user' => $isUser,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone_no' => $user->phone_no,
                    'email' => $user->email,
                    'gender' => $user->gender,
                    'dob' => $user->dob,
                    'address' => $user->address,
                    'study_type' => $user->study_type,
                    'institute_name' => $user->institute_name,
                    'division_id' => $user->division_id,
                    'district_id' => $user->district_id,
                    'upazila_id' => $user->upazila_id,
                    'post_code' => $user->post_code,
                    'avatar' => $user->avatar,
                ],
                'token' => $token,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('API social authentication failed.', [
                'provider' => $validated['provider'],
                'exception_class' => $exception::class,
            ]);

            return response(['status' => false, 'message' => 'Invalid credentials. Please try again later.'], 422);
        }
    }

    public function logout(Request $request) {
        $token = $request->user()->token();
        $token->revoke();
        $response = ['status' => true, 'message' => 'You have been successfully logged out!'];
        return response($response, 200);
    }

    public function image(int $id, string $size, string $img)
    {
        $avatar = $id . '/' . $size . '/' . $img;
        User::query()->whereKey($id)->where('avatar', $avatar)->firstOrFail();

        $path = 'uploads/users/' . $avatar;
        abort_unless(Storage::disk('local')->exists($path), 404);
        $mime = match (strtolower(pathinfo($img, PATHINFO_EXTENSION))) {
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => abort(404),
        };

        return Storage::disk('local')->response($path, $img, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=86400, immutable',
        ]);
    }

}

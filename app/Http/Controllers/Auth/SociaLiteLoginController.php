<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use RuntimeException;
use Throwable;

class SociaLiteLoginController extends Controller
{
    public function redirectToGithub(): RedirectResponse
    {
        return Socialite::driver('github')->redirect();
    }

    public function handleGithubCallback(): RedirectResponse
    {
        return $this->handleCallback('github');
    }

    private function handleCallback(string $provider): RedirectResponse
    {
        try {
            $providerUser = Socialite::driver($provider)->user();
            $user = $this->registerOrLoginUser($providerUser, $provider);

            if (!(bool) $user->status) {
                return redirect()->route('showLogin')->with('message', [
                    'type' => 'error',
                    'text' => 'This member account is inactive.',
                ]);
            }

            if ((int) $user->is_approved !== 1) {
                return redirect()->route('showLogin')->with('message', [
                    'type' => 'info',
                    'text' => 'Your account is awaiting approval. You can sign in after an administrator approves it.',
                ]);
            }

            Auth::login($user);

            return redirect()->route('frontend.home')->with('message', [
                'type' => 'success',
                'text' => 'You are now signed in.',
            ]);
        } catch (Throwable $exception) {
            // Provider exceptions can contain authorization codes or profile data.
            // Record only the provider and exception class, never the message/context.
            Log::warning('Social login callback failed.', [
                'provider' => $provider,
                'exception_class' => $exception::class,
            ]);

            return redirect()->route('showLogin')->with('message', [
                'type' => 'error',
                'text' => 'Social sign-in could not be completed. Please try again.',
            ]);
        }
    }

    private function registerOrLoginUser(object $data, string $provider): User
    {
        $providerId = trim((string) ($data->id ?? ''));
        $email = Str::lower(trim((string) ($data->email ?? '')));

        if ($providerId === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 50) {
            throw new RuntimeException('The identity provider did not return a usable account.');
        }

        $user = User::query()
            ->where('provider_type', $provider)
            ->where('social_id', $providerId)
            ->first();

        if (!$user) {
            $emailOwner = User::query()->where('email', $email)->first();
            if ($emailOwner) {
                throw new RuntimeException('That email address is already linked to another account.');
            }

            $name = trim((string) ($data->name ?? ''));
            $user = User::create([
                'name' => Str::limit($name !== '' ? $name : Str::before($email, '@'), 50, ''),
                'email' => $email,
                'social_id' => $providerId,
                'avatar' => isset($data->avatar) ? (string) $data->avatar : null,
                'provider_type' => $provider,
                'status' => 1,
                'is_approved' => config('security.social_registration_auto_approve') ? 1 : 0,
                'email_verified_at' => now(),
                // Social-only accounts still require a non-predictable local credential.
                'password' => Hash::make(Str::random(64)),
            ]);
        }

        return $user;
    }
}

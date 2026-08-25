<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
     public const HOME = '/home';

    /**
     * The controller namespace for the application.
     *
     * When present, controller route declarations will automatically be prefixed with this namespace.
     *
     * @var string|null
     */
    protected $namespace = 'App\\Http\\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        });

        RateLimiter::for('chat-read', function (Request $request) {
            $key = $request->user()
                ? 'member:' . $request->user()->id
                : 'guest:' . hash('sha256', $request->ip() . '|' . $request->session()->getId());

            return Limit::perMinute(60)->by($key);
        });

        RateLimiter::for('chat-write', function (Request $request) {
            $conversation = $request->route('conversation');
            $conversationKey = is_object($conversation) ? ($conversation->uuid ?? '') : (string) $conversation;
            $guestIdentity = $conversationKey !== ''
                ? $conversationKey
                : mb_strtolower(trim((string) ($request->input('email') ?: $request->input('phone') ?: $request->input('name'))));
            $key = $request->user()
                ? 'member:' . $request->user()->id
                : 'guest:' . hash('sha256', $request->ip() . '|' . $guestIdentity);
            $ipKey = 'chat-ip:' . hash('sha256', $request->ip());

            return [
                Limit::perMinute(5)->by($key . ':minute'),
                Limit::perHour(30)->by($key . ':hour'),
                Limit::perMinute(30)->by($ipKey . ':minute'),
                Limit::perHour(200)->by($ipKey . ':hour'),
            ];
        });

        RateLimiter::for('chat-faq-click', function (Request $request) {
            $key = 'chat-faq-click:' . hash('sha256', $request->ip());

            return [
                Limit::perMinute(20)->by($key . ':minute'),
                Limit::perHour(200)->by($key . ':hour'),
            ];
        });

        RateLimiter::for('newsletter-subscribe', function (Request $request) {
            $keyMaterial = (string) config('app.key', '');
            $email = Str::lower(trim((string) $request->input('email')));
            $emailKey = hash_hmac('sha256', $email, $keyMaterial);
            $ipKey = hash_hmac('sha256', (string) $request->ip(), $keyMaterial);

            return [
                Limit::perHour(5)->by('newsletter-email:' . $emailKey),
                Limit::perMinute(10)->by('newsletter-ip-minute:' . $ipKey),
                Limit::perHour(50)->by('newsletter-ip-hour:' . $ipKey),
            ];
        });

        RateLimiter::for('newsletter-confirm', function (Request $request) {
            $keyMaterial = (string) config('app.key', '');
            $subscriber = (string) $request->route('subscriber');
            $subscriberKey = hash_hmac('sha256', $subscriber, $keyMaterial);
            $ipKey = hash_hmac('sha256', (string) $request->ip(), $keyMaterial);

            return [
                Limit::perMinute(10)->by('newsletter-confirm-link:' . $subscriberKey),
                Limit::perMinute(20)->by('newsletter-confirm-ip:' . $ipKey),
            ];
        });
    }
}

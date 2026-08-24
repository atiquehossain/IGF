<?php

namespace App\Http\Middleware;

use Closure;

class XSS {

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next) {
        $request->merge($this->sanitize($request->all()));
        return $next($request);
    }

    private function sanitize(array $input, bool $credentialContext = false): array
    {
        foreach ($input as $key => $value) {
            $isCredential = $credentialContext || $this->isCredentialLike((string) $key);

            if (is_array($value)) {
                $input[$key] = $this->sanitize($value, $isCredential);
            } elseif (is_string($value) && ! $isCredential) {
                $input[$key] = strip_tags($value);
            }
        }

        return $input;
    }

    private function isCredentialLike(string $key): bool
    {
        return preg_match(
            '/(?:^|_)(?:passwords?|passphrases?|secrets?|tokens?|otp|codes?|credentials?|pins?|api_key|authorization)(?:_|$)/i',
            $key
        ) === 1;
    }

}

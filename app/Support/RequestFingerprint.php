<?php

namespace App\Support;

use Illuminate\Http\Request;

final class RequestFingerprint
{
    public static function for(Request $request): string
    {
        return hash_hmac(
            'sha256',
            (string) ($request->ip() ?: 'unknown'),
            (string) config('app.key')
        );
    }
}

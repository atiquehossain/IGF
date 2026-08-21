<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Throwable;

class Handler extends ExceptionHandler {

    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
            //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register() {
        $this->reportable(function (QueryException $exception): bool {
            // QueryException messages interpolate SQL and bindings, which can
            // include contact details or other private form values. Record
            // only non-sensitive diagnostics and suppress the raw exception.
            Log::error('Database operation failed.', [
                'exception_class' => $exception::class,
                'sql_state' => $exception->errorInfo[0] ?? null,
                'driver_code' => $exception->errorInfo[1] ?? null,
                'connection' => $exception->getConnectionName(),
            ]);

            return false;
        });
        $this->reportable(function (Throwable $e) {
            //
        });
    }
    
    // use coustome message
    protected function unauthenticated($request, AuthenticationException $exception) {

        if ($request->expectsJson()) {
            return response()->json(['status' => false, 'message' => 'Unauthenticated'], 401);
        }
        if ($request->is('admin') || $request->is('admin/*')) {
            return redirect()->guest('/admin/login');
        }
        if ($request->is('api') || $request->is('api/*')) {
            return response()->json(['status' => false, 'message' => 'Unauthenticated'], 401);
        }
        return redirect()->guest('login');
    }

}

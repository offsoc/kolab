<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\DB;

class Handler extends ExceptionHandler
{
    /** @var array<int, class-string<\Throwable>> A list of the exception types that are not reported */
    protected $dontReport = [
        \Laravel\Passport\Exceptions\OAuthServerException::class,
        \League\OAuth2\Server\Exception\OAuthServerException::class
    ];

    /** @var array<int, string> A list of the inputs that are never flashed for validation exceptions */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];


    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (\Throwable $e) {
            // Rollback uncommitted transactions
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        });
    }

    /**
     * Convert an authentication exception into a response.
     *
     * @param \Illuminate\Http\Request                 $request
     * @param \Illuminate\Auth\AuthenticationException $exception
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 401);
        }

        abort(401);
    }
}

<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\DB;

class Handler extends ExceptionHandler
{
    /** @var string[] A list of the exception types that are not reported */
    protected $dontReport = [
        \Laravel\Passport\Exceptions\OAuthServerException::class,
        \League\OAuth2\Server\Exception\OAuthServerException::class
    ];

    /** @var string[] A list of the inputs that are never flashed for validation exceptions */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];


    /**
     * Render an exception into an HTTP response.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Throwable               $exception
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function render($request, \Throwable $exception)
    {
        // Rollback uncommitted transactions
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        return parent::render($request, $exception);
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

<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class MeetController extends BaseController
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;

    public function index()
    {
        return view('meet')->with('env', \App\Utils::uiEnv());
    }

    public function room($id)
    {
        return view('meet.room', ['room' => $id])->with('env', \App\Utils::uiEnv());
    }

    /**
     * Common error response builder for API (JSON) responses
     *
     * @param int    $code    Error code
     * @param string $message Error message
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function errorResponse(int $code, string $message = null)
    {
        $errors = [
            400 => "Bad request",
            401 => "Unauthorized",
            403 => "Access denied",
            404 => "Not found",
            422 => "Input validation error",
            405 => "Method not allowed",
            500 => "Internal server error",
        ];

        $response = [
            'status' => 'error',
            'message' => $message ?: (isset($errors[$code]) ? $errors[$code] : "Server error"),
        ];

        return response()->json($response, $code);
    }
}

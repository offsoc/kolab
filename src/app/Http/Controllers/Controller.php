<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;


    /**
     * Common error response builder for API (JSON) responses
     *
     * @param int    $code    Error code
     * @param string $message Error message
     * @param array  $data    Additional response data
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function errorResponse(int $code, string $message = null, array $data = [])
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

        if (!empty($data)) {
            $response = $response + $data;
        }

        return response()->json($response, $code);
    }
}

<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * Liveness probe
     *
     * @return JsonResponse The response
     */
    public function liveness()
    {
        $response = response()->json('success', 200);
        $response->noLogging = true; // @phpstan-ignore-line
        return $response;
    }

    /**
     * Readiness probe
     *
     * @return JsonResponse The response
     */
    public function readiness()
    {
        $response = response()->json('success', 200);
        $response->noLogging = true; // @phpstan-ignore-line
        return $response;
    }

    /**
     * Run a health status check
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function status()
    {
        $code = \Artisan::call("status:health --check=DB --check=Redis");
        if ($code != 0) {
            \Log::info("Healthcheck failed");

            $result = [
                'status' => 'error',
                'output' => \Artisan::output()
            ];

            return response()->json($result, 500);
        }

        $result = [
            'status' => 'ok',
            'output' => \Artisan::output()
        ];

        return response()->json($result, 200);
    }
}

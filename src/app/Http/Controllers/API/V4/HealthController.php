<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\Controller;

class HealthController extends Controller
{
    /**
     * Liveness probe
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function liveness()
    {
        $response = response()->json('success', 200);
        $response->noLogging = true;
        return $response;
    }

    /**
     * Readiness probe
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function readiness()
    {
        $response = response()->json('success', 200);
        $response->noLogging = true;
        return $response;
    }
}

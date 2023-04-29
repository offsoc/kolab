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
        return response()->json('success', 200);
    }

    /**
     * Readiness probe
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function readiness()
    {
        return response()->json('success', 200);
    }
}

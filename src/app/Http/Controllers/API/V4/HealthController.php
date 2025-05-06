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
}

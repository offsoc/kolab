<?php

namespace App\Http\Controllers\API\V4;

use App\AuthAttempt;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;

class AuthAttemptsController extends Controller
{

    /**
     * Confirm the authentication attempt.
     *
     * @param string $id Id of AuthAttempt attempt
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirm($id)
    {
        $authAttempt = AuthAttempt::find($id);
        if (!$authAttempt) {
            return $this->errorResponse(404);
        }

        $user = $this->guard()->user();
        if ($user->id != $authAttempt->user_id) {
            return $this->errorResponse(403);
        }

        \Log::debug("Confirm on {$authAttempt->id}");
        $authAttempt->accept();
        return response()->json([], 200);
    }

    /**
     * Deny the authentication attempt.
     *
     * @param string $id Id of AuthAttempt attempt
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function deny($id)
    {
        $authAttempt = AuthAttempt::find($id);
        if (!$authAttempt) {
            return $this->errorResponse(404);
        }

        $user = $this->guard()->user();
        if ($user->id != $authAttempt->user_id) {
            return $this->errorResponse(403);
        }

        \Log::debug("Deny on {$authAttempt->id}");
        $authAttempt->deny();
        return response()->json([], 200);
    }

    /**
     * Return details of authentication attempt.
     *
     * @param string $id Id of AuthAttempt attempt
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function details($id)
    {
        $authAttempt = AuthAttempt::find($id);
        if (!$authAttempt) {
            return $this->errorResponse(404);
        }

        $user = $this->guard()->user();
        if ($user->id != $authAttempt->user_id) {
            return $this->errorResponse(403);
        }

        return response()->json([
            'status' => 'success',
            'username' => $user->email,
            'country' => \App\Utils::countryForIP($authAttempt->ip),
            'entry' => $authAttempt->toArray()
        ]);
    }

    /**
     * Listing of client authAttempts.
     *
     * All authAttempt attempts from the current user
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = $this->guard()->user();

        $pageSize = 10;
        $page = intval($request->input('page')) ?: 1;
        $hasMore = false;

        $result = \App\AuthAttempt::where('user_id', $user->id)
            ->orderBy('updated_at', 'desc')
            ->limit($pageSize + 1)
            ->offset($pageSize * ($page - 1))
            ->get();

        if (count($result) > $pageSize) {
            $result->pop();
            $hasMore = true;
        }

        $result = $result->map(function ($authAttempt) {
            return $authAttempt->toArray();
        });

        return response()->json($result);
    }
}

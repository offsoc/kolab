<?php

namespace App\Http\Controllers\API\V4;

use App\AuthAttempt;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthAttemptsController extends Controller
{

    public function confirm($id)
    {
        $authAttempt = AuthAttempt::findOrFail($id);

        $user = Auth::guard()->user();
        if ($user->id != $authAttempt->user_id) {
            return $this->errorResponse(403);
        }

        \Log::debug("Confirm on {$authAttempt->id}");
        $authAttempt->accept();
        $authAttempt->save();
        return response("", 200);
    }

    public function deny($id)
    {
        $authAttempt = AuthAttempt::findOrFail($id);

        $user = Auth::guard()->user();
        if ($user->id != $authAttempt->user_id) {
            return $this->errorResponse(403);
        }

        \Log::debug("Deny on {$authAttempt->id}");
        $authAttempt->deny();
        $authAttempt->save();
        return response("", 200);
    }

    public function details($id)
    {
        $authAttempt = AuthAttempt::findOrFail($id);
        $user = Auth::guard()->user();

        \Log::debug("Getting details {$authAttempt->user_id} {$user->id}");
        if ($user->id != $authAttempt->user_id) {
            return $this->errorResponse(403);
        }

        \Log::debug("Details on {$authAttempt->id}");
        return response()->json([
            'status' => 'success',
            'username' => $user->email,
            'ip' => $authAttempt->ip,
            'timestamp' => $authAttempt->updated_at,
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
        $user = Auth::guard()->user();

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

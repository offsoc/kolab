<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Rules\Password;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PasswordPolicyController extends Controller
{
    /**
     * Validate the password regarding the defined policies.
     *
     * @return JsonResponse
     */
    public function check(Request $request)
    {
        $userId = $request->input('user');

        $user = !empty($userId) ? User::find($userId) : null;

        // Get the policy
        $policy = new Password($user ? $user->walletOwner() : null, $user);

        // Check the password
        $status = $policy->check($request->input('password'));

        $passed = array_filter(
            $status,
            static function ($rule) {
                return !empty($rule['status']);
            }
        );

        return response()->json([
            'status' => count($passed) == count($status) ? 'success' : 'error',
            'list' => array_values($status),
            'count' => count($status),
        ]);
    }
}

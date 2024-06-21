<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Rules\Password;
use Illuminate\Http\Request;

class PasswordPolicyController extends Controller
{
    /**
     * Fetch the password policy for the current user account.
     * The result includes all supported policy rules.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Get the account owner
        $owner = $this->guard()->user()->walletOwner();

        // Get the policy
        $policy = new Password($owner);
        $rules = $policy->rules(true);

        // Get the account's password retention config
        $config = [
            'max_password_age' => $owner->getSetting('max_password_age'),
        ];

        return response()->json([
                'list' => array_values($rules),
                'count' => count($rules),
                'config' => $config,
        ]);
    }

    /**
     * Validate the password regarding the defined policies.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function check(Request $request)
    {
        $userId = $request->input('user');

        $user = !empty($userId) ? \App\User::find($userId) : null;

        // Get the policy
        $policy = new Password($user ? $user->walletOwner() : null, $user);

        // Check the password
        $status = $policy->check($request->input('password'));

        $passed = array_filter(
            $status,
            function ($rule) {
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

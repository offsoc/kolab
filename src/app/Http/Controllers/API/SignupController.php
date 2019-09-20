<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SignupController extends Controller
{
    /**
     * Starts signup process.
     *
     * Verifies user name and email/phone, sends verification email/sms message.
     * Returns the verification code.
     *
     * @param Illuminate\Http\Request HTTP request
     *
     * @return \Illuminate\Http\JsonResponse JSON response
     */
    public function init(Request $request)
    {
        // TODO: validate user name and email/phone
        // TODO: generate the verification code
        // TODO: send email/sms message

        return response()->json(['status' => 'success']);
    }

    /**
     * Validation of the verification code.
     *
     * @param Illuminate\Http\Request HTTP request
     *
     * @return \Illuminate\Http\JsonResponse JSON response
     */
    public function verify(Request $request)
    {
        // TODO: validate the code
        // TODO: return user name and email/phone from the codes database on success

        return response()->json(['status' => 'success']);
    }

    /**
     * Finishes the signup process by creating the user account.
     *
     * @param Illuminate\Http\Request HTTP request
     *
     * @return \Illuminate\Http\JsonResponse JSON response
     */
    public function register(Request $request)
    {
        $v = Validator::make(
            $request->all(),
            [
                'email' => 'required|email|unique:users',
                'password'  => 'required|min:3|confirmed',
            ]
        );

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        $user = \App\User::create(
            [
                'email' => $request->email,
                'password' => $request->password,
            ]
        );

        $token = auth()->login($user);

        return $this->respondWithToken($token);
    }

    /**
     * Get the token array structure.
     *
     * @param string $token Respond with this token.
     *
     * @return \Illuminate\Http\JsonResponse JSON response
     */
    protected function respondWithToken($token)
    {
        return response()->json([
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => $this->guard()->factory()->getTTL() * 60,
        ]);
    }
}

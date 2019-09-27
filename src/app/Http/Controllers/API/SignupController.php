<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\SignupCode;
use App\User;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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
        // Validate user name and email
        // TODO: Extended validation and support for phone number
        $v = Validator::make(
            $request->all(),
            [
                'email' => 'required|email',
                'name' => 'required',
            ]
        );

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        // Generate the verification code
        $code = SignupCode::create([
                'data' => [
                    'email' => $request->email,
                    'name' => $request->name,
                ]
        ]);

        // TODO: send email/sms message

        return response()->json(['status' => 'success', 'code' => $code->code]);
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
        // Validate the request args
        $v = Validator::make(
            $request->all(),
            [
                'code' => 'required',
                'short_code' => 'required',
            ]
        );

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        // Validate the code
        $code = SignupCode::find($request->code);

        if (empty($code)
            || $code->isExpired()
            || Str::upper($request->short_code) !== Str::upper($code->short_code)
        ) {
            $errors = ['short_code' => "The code is invalid or expired."];
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        // Return user name and email/phone from the codes database on success
        return response()->json([
            'status' => 'success',
            'email' => $code->data['email'],
            'name' => $code->data['name'],
        ]);
    }

    /**
     * Finishes the signup process by creating the user account.
     *
     * @param Illuminate\Http\Request HTTP request
     *
     * @return \Illuminate\Http\JsonResponse JSON response
     */
    public function signup(Request $request)
    {
        // Validate input
        $v = Validator::make(
            $request->all(),
            [
                'domain' => 'required|min:3',
                'login' => 'required|min:2',
                'password' => 'required|min:3|confirmed',
            ]
        );

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        $login = $request->login . '@' . $request->domain;

        // TODO: check if specified domain is ours
        // TODO: validate login

        // Validate verification codes (again)
        $v = $this->verify($request);
        if ($v->status() !== 200) {
            return $v;
        }

        $code_data  = $v->getData();
        $user_name  = $code_data->name;
        $user_email = $code_data->email;

        // TODO: check if user with specified login already exists

        $user = User::create(
            [
                // TODO: Save the external email (or phone) ?
                'name' => $user_name,
                'email' => $login,
                'password' => $request->password,
            ]
        );

        $token = auth()->login($user);

        // Remove the verification code
        SignupCode::destroy($request->code);

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
                'expires_in' => Auth::guard()->factory()->getTTL() * 60,
        ]);
    }
}

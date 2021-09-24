<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Jobs\PasswordResetEmail;
use App\User;
use App\VerificationCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Password reset API
 */
class PasswordResetController extends Controller
{
    /** @var \App\VerificationCode A verification code object */
    protected $code;


    /**
     * Sends password reset code to the user's external email
     *
     * Verifies user email, sends verification email message.
     *
     * @param \Illuminate\Http\Request $request HTTP request
     *
     * @return \Illuminate\Http\JsonResponse JSON response
     */
    public function init(Request $request)
    {
        // Check required fields
        $v = Validator::make($request->all(), ['email' => 'required|email']);

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        // Find a user by email
        $user = User::findByEmail($request->email);

        if (!$user) {
            $errors = ['email' => \trans('validation.usernotexists')];
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        if (!$user->getSetting('external_email')) {
            $errors = ['email' => \trans('validation.noextemail')];
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        // Generate the verification code
        $code = new VerificationCode(['mode' => 'password-reset']);
        $user->verificationcodes()->save($code);

        // Send email/sms message
        PasswordResetEmail::dispatch($code);

        return response()->json(['status' => 'success', 'code' => $code->code]);
    }

    /**
     * Validation of the verification code.
     *
     * @param \Illuminate\Http\Request $request HTTP request
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

        // Validate the verification code
        $code = VerificationCode::find($request->code);

        if (
            empty($code)
            || $code->isExpired()
            || $code->mode !== 'password-reset'
            || Str::upper($request->short_code) !== Str::upper($code->short_code)
        ) {
            $errors = ['short_code' => "The code is invalid or expired."];
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        // For last-step remember the code object, so we can delete it
        // with single SQL query (->delete()) instead of two (::destroy())
        $this->code = $code;

        // Return user name and email/phone from the codes database on success
        return response()->json(['status' => 'success']);
    }

    /**
     * Password change
     *
     * @param \Illuminate\Http\Request $request HTTP request
     *
     * @return \Illuminate\Http\JsonResponse JSON response
     */
    public function reset(Request $request)
    {
        // Validate the request args
        $v = Validator::make(
            $request->all(),
            [
                'password' => 'required|min:4|confirmed',
            ]
        );

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        $v = $this->verify($request);
        if ($v->status() !== 200) {
            return $v;
        }

        $user = $this->code->user;

        // Change the user password
        $user->setPasswordAttribute($request->password);
        $user->save();

        // Remove the verification code
        $this->code->delete();

        return AuthController::logonResponse($user, $request->password);
    }
}

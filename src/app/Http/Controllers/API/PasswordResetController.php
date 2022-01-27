<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Jobs\PasswordResetEmail;
use App\Rules\Password;
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
        $code = VerificationCode::where('code', $request->code)->where('active', true)->first();

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

        return response()->json([
                'status' => 'success',
                // we need user's ID for e.g. password policy checks
                'userId' => $code->user_id,
        ]);
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
        $v = $this->verify($request);
        if ($v->status() !== 200) {
            return $v;
        }

        $user = $this->code->user;

        // Validate the password
        $v = Validator::make(
            $request->all(),
            ['password' => ['required', 'confirmed', new Password($user->walletOwner())]]
        );

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        // Change the user password
        $user->setPasswordAttribute($request->password);
        $user->save();

        // Remove the verification code
        $this->code->delete();

        return AuthController::logonResponse($user, $request->password);
    }

    /**
     * Create a verification code for the current user.
     *
     * @param \Illuminate\Http\Request $request HTTP request
     *
     * @return \Illuminate\Http\JsonResponse JSON response
     */
    public function codeCreate(Request $request)
    {
        // Generate the verification code
        $code = new VerificationCode();
        $code->mode = 'password-reset';

        // These codes are valid for 24 hours
        $code->expires_at = now()->addHours(24);

        // The code is inactive until it is submitted via a different endpoint
        $code->active = false;

        $this->guard()->user()->verificationcodes()->save($code);

        return response()->json([
                'status' => 'success',
                'code' => $code->code,
                'short_code' => $code->short_code,
                'expires_at' => $code->expires_at->toDateTimeString(),
        ]);
    }

    /**
     * Delete a verification code.
     *
     * @param string $id Code identifier
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function codeDelete($id)
    {
        // Accept <short-code>-<code> input
        if (strpos($id, '-')) {
            $id = explode('-', $id)[1];
        }

        $code = VerificationCode::find($id);

        if (!$code) {
            return $this->errorResponse(404);
        }

        $current_user = $this->guard()->user();

        if (empty($code->user) || !$current_user->canUpdate($code->user)) {
            return $this->errorResponse(403);
        }

        $code->delete();

        return response()->json([
                'status' => 'success',
                'message' => \trans('app.password-reset-code-delete-success'),
        ]);
    }
}

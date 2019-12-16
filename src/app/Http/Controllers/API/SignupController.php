<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Jobs\SignupVerificationEmail;
use App\Jobs\SignupVerificationSMS;
use App\SignupCode;
use App\User;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Signup process API
 */
class SignupController extends Controller
{
    /** @var SignupCode A verification code object */
    protected $code;


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
        // Check required fields
        $v = Validator::make(
            $request->all(),
            [
                'email' => 'required',
                'name' => 'required',
            ]
        );

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        // Validate user email (or phone)
        if ($error = $this->validatePhoneOrEmail($request->email, $is_phone)) {
            return response()->json(['status' => 'error', 'errors' => ['email' => __($error)]], 422);
        }

        // Generate the verification code
        $code = SignupCode::create([
                'data' => [
                    'email' => $request->email,
                    'name' => $request->name,
                ]
        ]);

        // Send email/sms message
        if ($is_phone) {
            SignupVerificationSMS::dispatch($code);
        } else {
            SignupVerificationEmail::dispatch($code);
        }

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

        // Validate the verification code
        $code = SignupCode::find($request->code);

        if (empty($code)
            || $code->isExpired()
            || Str::upper($request->short_code) !== Str::upper($code->short_code)
        ) {
            $errors = ['short_code' => "The code is invalid or expired."];
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        // For signup last-step mode remember the code object, so we can delete it
        // with single SQL query (->delete()) instead of two (::destroy())
        $this->code = $code;

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
                'login' => 'required|min:2',
                'password' => 'required|min:4|confirmed',
            ]
        );

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        $login = $request->login . '@' . \config('app.domain');

        // Validate login (email)
        if ($error = $this->validateEmail($login, true)) {
            return response()->json(['status' => 'error', 'errors' => ['login' => $error]], 422);
        }

        // Validate verification codes (again)
        $v = $this->verify($request);
        if ($v->status() !== 200) {
            return $v;
        }

        $code_data  = $v->getData();
        $user_name  = $code_data->name;
        $user_email = $code_data->email;

        // We allow only ASCII, so we can safely lower-case the email address
        $login = Str::lower($login);

        $user = User::create(
            [
                // TODO: Save the external email (or phone)
                'name' => $user_name,
                'email' => $login,
                'password' => $request->password,
            ]
        );

        // Remove the verification code
        $this->code->delete();

        $token = auth()->login($user);

        return response()->json([
                'status' => 'success',
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => Auth::guard()->factory()->getTTL() * 60,
        ]);
    }

    /**
     * Checks if the input string is a valid email address or a phone number
     *
     * @param string $email     Email address or phone number
     * @param bool   &$is_phone Will be set to True if the string is valid phone number
     *
     * @return string Error message label on validation error
     */
    protected function validatePhoneOrEmail($input, &$is_phone = false)
    {
        $is_phone = false;

        return $this->validateEmail($input);

        // TODO: Phone number support
/*
        if (strpos($input, '@')) {
            return $this->validateEmail($input);
        }

        $input = str_replace(array('-', ' '), '', $input);

        if (!preg_match('/^\+?[0-9]{9,12}$/', $input)) {
            return 'validation.noemailorphone';
        }

        $is_phone = true;
*/
    }

    /**
     * Email address validation
     *
     * @param string $email  Email address
     * @param bool   $signup Enables additional checks for signup mode
     *
     * @return string Error message label on validation error
     */
    protected function validateEmail($email, $signup = false)
    {
        $v = Validator::make(['email' => $email], ['email' => 'required|email']);

        if ($v->fails()) {
            return 'validation.emailinvalid';
        }

        list($local, $domain) = explode('@', $email);

        // don't allow @localhost and other no-fqdn
        if (strpos($domain, '.') === false) {
            return 'validation.emailinvalid';
        }

        // Extended checks for an address that is supposed to become a login to Kolab
        if ($signup) {
            // Local part validation
            if (!preg_match('/^[A-Za-z0-9_.-]+$/', $local)) {
                return 'validation.emailinvalid';
            }

            // Check if specified domain is allowed for signup
            if ($domain != \config('app.domain')) {
                return 'validation.emailinvalid';
            }

            // Check if the local part is not one of exceptions
            $exceptions = '/^(admin|administrator|sales|root)$/i';
            if (preg_match($exceptions, $local)) {
                return 'validation.emailexists';
            }

            // Check if user with specified login already exists
            // TODO: Aliases
            if (User::where('email', $email)->first()) {
                return 'validation.emailexists';
            }
        }
    }
}

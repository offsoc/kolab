<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Jobs\SignupVerificationEmail;
use App\Jobs\SignupVerificationSMS;
use App\Domain;
use App\Plan;
use App\SignupCode;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Signup process API
 */
class SignupController extends Controller
{
    /** @var \App\SignupCode A verification code object */
    protected $code;

    /** @var \App\Plan Signup plan object */
    protected $plan;


    /**
     * Returns plans definitions for signup.
     *
     * @param Illuminate\Http\Request HTTP request
     *
     * @return \Illuminate\Http\JsonResponse JSON response
     */
    public function plans(Request $request)
    {
        $plans = [];

        Plan::all()->map(function ($plan) use (&$plans) {
            // TODO: Localization
            $plans[] = [
                'title' => $plan->title,
                'description' => $plan->description,
            ];
        });

        return response()->json(['status' => 'success', 'plans' => $plans]);
    }

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
                'name' => 'required|max:512',
                'plan' => 'nullable|alpha_num|max:128',
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
                    'plan' => $request->plan,
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

        if (
            empty($code)
            || $code->isExpired()
            || Str::upper($request->short_code) !== Str::upper($code->short_code)
        ) {
            $errors = ['short_code' => "The code is invalid or expired."];
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        // For signup last-step mode remember the code object, so we can delete it
        // with single SQL query (->delete()) instead of two (::destroy())
        $this->code = $code;

        $has_domain = $this->getPlan()->hasDomain();

        // Return user name and email/phone from the codes database,
        // domains list for selection and "plan type" flag
        return response()->json([
                'status' => 'success',
                'email' => $code->data['email'],
                'name' => $code->data['name'],
                'is_domain' => $has_domain,
                'domains' => $has_domain ? [] : Domain::getPublicDomains(),
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
                'domain' => 'required',
            ]
        );

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        // Validate verification codes (again)
        $v = $this->verify($request);
        if ($v->status() !== 200) {
            return $v;
        }

        // Get the plan
        $plan = $this->getPlan();
        $is_domain = $plan->hasDomain();

        $login  = $request->login;
        $domain = $request->domain;

        // Validate login
        if ($errors = $this->validateLogin($login, $domain, $is_domain)) {
            $errors = $this->resolveErrors($errors);
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        // Get user name/email from the verification code database
        $code_data  = $v->getData();
        $user_name  = $code_data->name;
        $user_email = $code_data->email;

        // We allow only ASCII, so we can safely lower-case the email address
        $login = Str::lower($login);
        $domain = Str::lower($domain);

        DB::beginTransaction();

        // Create user record
        $user = User::create([
                'name' => $user_name,
                'email' => $login . '@' . $domain,
                'password' => $request->password,
        ]);

        // Create domain record
        // FIXME: Should we do this in UserObserver::created()?
        if ($is_domain) {
            $domain = Domain::create([
                    'namespace' => $domain,
                    'status' => Domain::STATUS_NEW,
                    'type' => Domain::TYPE_EXTERNAL,
            ]);
        }

        // Create SKUs (after domain)
        foreach ($plan->packages as $package) {
            foreach ($package->skus as $sku) {
                $sku->registerEntitlement($user, is_object($domain) ? [$domain] : []);
            }
        }

        // Save the external email and plan in user settings
        $user->setSettings([
            'external_email' => $user_email,
            'plan' => $plan->id,
        ]);

        // Remove the verification code
        $this->code->delete();

        DB::commit();

        return UsersController::logonResponse($user);
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
     * @param string $email Email address
     *
     * @return string Error message label on validation error
     */
    protected function validateEmail($email)
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
    }

    /**
     * Login (kolab identity) validation
     *
     * @param string $email    Login (local part of an email address)
     * @param string $domain   Domain name
     * @param bool   $external Enables additional checks for domain part
     *
     * @return array Error messages on validation error
     */
    protected function validateLogin($login, $domain, $external = false)
    {
        // don't allow @localhost and other no-fqdn
        if (empty($domain) || strpos($domain, '.') === false || stripos($domain, 'www.') === 0) {
            return ['domain' => 'validation.domaininvalid'];
        }

        // Local part validation
        if (!preg_match('/^[A-Za-z0-9_.-]+$/', $login)) {
            return ['login' => 'validation.logininvalid'];
        }

        $domain = Str::lower($domain);

        if (!$external) {
            // Check if the local part is not one of exceptions
            $exceptions = '/^(admin|administrator|sales|root)$/i';
            if (preg_match($exceptions, $login)) {
                return ['login' => 'validation.loginexists'];
            }

            // Check if specified domain is allowed for signup
            if (!in_array($domain, Domain::getPublicDomains())) {
                return ['domain' => 'validation.domaininvalid'];
            }
        } else {
            // Use email validator to validate the domain part
            $v = Validator::make(['email' => 'user@' . $domain], ['email' => 'required|email']);
            if ($v->fails()) {
                return ['domain' => 'validation.domaininvalid'];
            }

            // TODO: DNS registration check - maybe after signup?

            // Check if domain is already registered with us
            if (Domain::where('namespace', $domain)->first()) {
                return ['domain' => 'validation.domainexists'];
            }
        }

        // Check if user with specified login already exists
        // TODO: Aliases
        $email = $login . '@' . $domain;
        if (User::findByEmail($email)) {
            return ['login' => 'validation.loginexists'];
        }
    }

    /**
     * Returns plan for the signup process
     *
     * @returns \App\Plan Plan object selected for current signup process
     */
    protected function getPlan()
    {
        if (!$this->plan) {
            // Get the plan if specified and exists...
            if ($this->code && $this->code->data['plan']) {
                $plan = Plan::where('title', $this->code->data['plan'])->first();
            }

            // ...otherwise use the default plan
            if (empty($plan)) {
                // TODO: Get default plan title from config
                $plan = Plan::where('title', 'individual')->first();
            }

            $this->plan = $plan;
        }

        return $this->plan;
    }

    /**
     * Convert error labels to actual (localized) text
     */
    protected function resolveErrors(array $errors): array
    {
        $result = [];

        foreach ($errors as $idx => $label) {
            $result[$idx] = __($label);
        }

        return $result;
    }
}

<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Jobs\SignupVerificationEmail;
use App\Jobs\SignupVerificationSMS;
use App\Discount;
use App\Domain;
use App\Plan;
use App\Rules\ExternalEmail;
use App\Rules\UserEmailDomain;
use App\Rules\UserEmailLocal;
use App\SignupCode;
use App\SignupInvitation;
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
    /** @var ?\App\SignupCode A verification code object */
    protected $code;

    /** @var ?\App\Plan Signup plan object */
    protected $plan;


    /**
     * Returns plans definitions for signup.
     *
     * @param \Illuminate\Http\Request $request HTTP request
     *
     * @return \Illuminate\Http\JsonResponse JSON response
     */
    public function plans(Request $request)
    {
        $plans = [];

        // Use reverse order just to have individual on left, group on right ;)
        Plan::withEnvTenantContext()->orderByDesc('title')->get()
            ->map(function ($plan) use (&$plans) {
                $plans[] = [
                    'title' => $plan->title,
                    'name' => $plan->name,
                    'button' => __('app.planbutton', ['plan' => $plan->name]),
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
     * @param \Illuminate\Http\Request $request HTTP request
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
                'first_name' => 'max:128',
                'last_name' => 'max:128',
                'plan' => 'nullable|alpha_num|max:128',
                'voucher' => 'max:32',
            ]
        );

        $is_phone = false;
        $errors = $v->fails() ? $v->errors()->toArray() : [];

        // Validate user email (or phone)
        if (empty($errors['email'])) {
            if ($error = $this->validatePhoneOrEmail($request->email, $is_phone)) {
                $errors['email'] = $error;
            }
        }

        if (!empty($errors)) {
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        // Generate the verification code
        $code = SignupCode::create([
                'email' => $request->email,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'plan' => $request->plan,
                'voucher' => $request->voucher,
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
     * Returns signup invitation information.
     *
     * @param string $id Signup invitation identifier
     *
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function invitation($id)
    {
        $invitation = SignupInvitation::withEnvTenantContext()->find($id);

        if (empty($invitation) || $invitation->isCompleted()) {
            return $this->errorResponse(404);
        }

        $has_domain = $this->getPlan()->hasDomain();

        $result = [
            'id' => $id,
            'is_domain' => $has_domain,
            'domains' => $has_domain ? [] : Domain::getPublicDomains(),
        ];

        return response()->json($result);
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

        // Return user name and email/phone/voucher from the codes database,
        // domains list for selection and "plan type" flag
        return response()->json([
                'status' => 'success',
                'email' => $code->email,
                'first_name' => $code->first_name,
                'last_name' => $code->last_name,
                'voucher' => $code->voucher,
                'is_domain' => $has_domain,
                'domains' => $has_domain ? [] : Domain::getPublicDomains(),
        ]);
    }

    /**
     * Finishes the signup process by creating the user account.
     *
     * @param \Illuminate\Http\Request $request HTTP request
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
                'voucher' => 'max:32',
            ]
        );

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        // Signup via invitation
        if ($request->invitation) {
            $invitation = SignupInvitation::withEnvTenantContext()->find($request->invitation);

            if (empty($invitation) || $invitation->isCompleted()) {
                return $this->errorResponse(404);
            }

            // Check required fields
            $v = Validator::make(
                $request->all(),
                [
                    'first_name' => 'max:128',
                    'last_name' => 'max:128',
                    'voucher' => 'max:32',
                ]
            );

            $errors = $v->fails() ? $v->errors()->toArray() : [];

            if (!empty($errors)) {
                return response()->json(['status' => 'error', 'errors' => $errors], 422);
            }

            $settings = [
                'external_email' => $invitation->email,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
            ];
        } else {
            // Validate verification codes (again)
            $v = $this->verify($request);
            if ($v->status() !== 200) {
                return $v;
            }

            // Get user name/email from the verification code database
            $code_data = $v->getData();

            $settings = [
                'external_email' => $code_data->email,
                'first_name' => $code_data->first_name,
                'last_name' => $code_data->last_name,
            ];
        }

        // Find the voucher discount
        if ($request->voucher) {
            $discount = Discount::where('code', \strtoupper($request->voucher))
                ->where('active', true)->first();

            if (!$discount) {
                $errors = ['voucher' => \trans('validation.voucherinvalid')];
                return response()->json(['status' => 'error', 'errors' => $errors], 422);
            }
        }

        // Get the plan
        $plan = $this->getPlan();
        $is_domain = $plan->hasDomain();

        $login = $request->login;
        $domain_name = $request->domain;

        // Validate login
        if ($errors = self::validateLogin($login, $domain_name, $is_domain)) {
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        // We allow only ASCII, so we can safely lower-case the email address
        $login = Str::lower($login);
        $domain_name = Str::lower($domain_name);
        $domain = null;

        DB::beginTransaction();

        // Create domain record
        if ($is_domain) {
            $domain = Domain::create([
                    'namespace' => $domain_name,
                    'status' => Domain::STATUS_NEW,
                    'type' => Domain::TYPE_EXTERNAL,
            ]);
        }

        // Create user record
        $user = User::create([
                'email' => $login . '@' . $domain_name,
                'password' => $request->password,
        ]);

        if (!empty($discount)) {
            $wallet = $user->wallets()->first();
            $wallet->discount()->associate($discount);
            $wallet->save();
        }

        $user->assignPlan($plan, $domain);

        // Save the external email and plan in user settings
        $user->setSettings($settings);

        // Update the invitation
        if (!empty($invitation)) {
            $invitation->status = SignupInvitation::STATUS_COMPLETED;
            $invitation->user_id = $user->id;
            $invitation->save();
        }

        // Remove the verification code
        if ($this->code) {
            $this->code->delete();
        }

        DB::commit();

        return AuthController::logonResponse($user);
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
            if ($this->code && $this->code->plan) {
                $plan = Plan::withEnvTenantContext()->where('title', $this->code->plan)->first();
            }

            // ...otherwise use the default plan
            if (empty($plan)) {
                // TODO: Get default plan title from config
                $plan = Plan::withEnvTenantContext()->where('title', 'individual')->first();
            }

            $this->plan = $plan;
        }

        return $this->plan;
    }

    /**
     * Checks if the input string is a valid email address or a phone number
     *
     * @param string $input    Email address or phone number
     * @param bool   $is_phone Will have been set to True if the string is valid phone number
     *
     * @return string Error message on validation error
     */
    protected static function validatePhoneOrEmail($input, &$is_phone = false): ?string
    {
        $is_phone = false;

        $v = Validator::make(
            ['email' => $input],
            ['email' => ['required', 'string', new ExternalEmail()]]
        );

        if ($v->fails()) {
            return $v->errors()->toArray()['email'][0];
        }

        // TODO: Phone number support
/*
        $input = str_replace(array('-', ' '), '', $input);

        if (!preg_match('/^\+?[0-9]{9,12}$/', $input)) {
            return \trans('validation.noemailorphone');
        }

        $is_phone = true;
*/
        return null;
    }

    /**
     * Login (kolab identity) validation
     *
     * @param string $login    Login (local part of an email address)
     * @param string $domain   Domain name
     * @param bool   $external Enables additional checks for domain part
     *
     * @return array Error messages on validation error
     */
    protected static function validateLogin($login, $domain, $external = false): ?array
    {
        // Validate login part alone
        $v = Validator::make(
            ['login' => $login],
            ['login' => ['required', 'string', new UserEmailLocal($external)]]
        );

        if ($v->fails()) {
            return ['login' => $v->errors()->toArray()['login'][0]];
        }

        $domains = $external ? null : Domain::getPublicDomains();

        // Validate the domain
        $v = Validator::make(
            ['domain' => $domain],
            ['domain' => ['required', 'string', new UserEmailDomain($domains)]]
        );

        if ($v->fails()) {
            return ['domain' => $v->errors()->toArray()['domain'][0]];
        }

        $domain = Str::lower($domain);

        // Check if domain is already registered with us
        if ($external) {
            if (Domain::where('namespace', $domain)->first()) {
                return ['domain' => \trans('validation.domainexists')];
            }
        }

        // Check if user with specified login already exists
        $email = $login . '@' . $domain;
        if (User::emailExists($email) || User::aliasExists($email) || \App\Group::emailExists($email)) {
            return ['login' => \trans('validation.loginexists')];
        }

        return null;
    }
}

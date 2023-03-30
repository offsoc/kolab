<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Jobs\SignupVerificationEmail;
use App\Discount;
use App\Domain;
use App\Plan;
use App\Providers\PaymentProvider;
use App\Rules\SignupExternalEmail;
use App\Rules\SignupToken;
use App\Rules\Password;
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
    /**
     * Returns plans definitions for signup.
     *
     * @param \Illuminate\Http\Request $request HTTP request
     *
     * @return \Illuminate\Http\JsonResponse JSON response
     */
    public function plans(Request $request)
    {
        // Use reverse order just to have individual on left, group on right ;)
        // But prefer monthly on left, yearly on right
        $plans = Plan::withEnvTenantContext()->orderBy('months')->orderByDesc('title')->get()
            ->map(function ($plan) {
                $button = self::trans("app.planbutton-{$plan->title}");
                if (strpos($button, 'app.planbutton') !== false) {
                    $button = self::trans('app.planbutton', ['plan' => $plan->name]);
                }

                return [
                    'title' => $plan->title,
                    'name' => $plan->name,
                    'button' => $button,
                    'description' => $plan->description,
                    'mode' => $plan->mode ?: Plan::MODE_EMAIL,
                    'isDomain' => $plan->hasDomain(),
                ];
            })
            ->all();

        return response()->json(['status' => 'success', 'plans' => $plans]);
    }

    /**
     * Returns list of public domains for signup.
     *
     * @param \Illuminate\Http\Request $request HTTP request
     *
     * @return \Illuminate\Http\JsonResponse JSON response
     */
    public function domains(Request $request)
    {
        return response()->json(['status' => 'success', 'domains' => Domain::getPublicDomains()]);
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
        $rules = [
            'first_name' => 'max:128',
            'last_name' => 'max:128',
            'voucher' => 'max:32',
        ];

        $plan = $this->getPlan();

        if ($plan->mode == Plan::MODE_TOKEN) {
            $rules['token'] = ['required', 'string', new SignupToken()];
        } else {
            $rules['email'] = ['required', 'string', new SignupExternalEmail()];
        }

        // Check required fields, validate input
        $v = Validator::make($request->all(), $rules);

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()->toArray()], 422);
        }

        // Generate the verification code
        $code = SignupCode::create([
                'email' => $plan->mode == Plan::MODE_TOKEN ? $request->token : $request->email,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'plan' => $plan->title,
                'voucher' => $request->voucher,
        ]);

        $response = [
            'status' => 'success',
            'code' => $code->code,
            'mode' => $plan->mode ?: 'email',
        ];

        if ($plan->mode == Plan::MODE_TOKEN) {
            // Token verification, jump to the last step
            $has_domain = $plan->hasDomain();

            $response['short_code'] = $code->short_code;
            $response['is_domain'] = $has_domain;
            $response['domains'] = $has_domain ? [] : Domain::getPublicDomains();
        } else {
            // External email verification, send an email message
            SignupVerificationEmail::dispatch($code);
        }

        return response()->json($response);
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
     * @param bool                     $update  Update the signup code record
     *
     * @return \Illuminate\Http\JsonResponse JSON response
     */
    public function verify(Request $request, $update = true)
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
        // with single SQL query (->delete()) instead of two
        $request->code = $code;

        if ($update) {
            $code->verify_ip_address = $request->ip();
            $code->save();
        }

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
     * Validates the input to the final signup request.
     *
     * @param \Illuminate\Http\Request $request HTTP request
     *
     * @return \Illuminate\Http\JsonResponse JSON response
     */
    public function signupValidate(Request $request)
    {
        // Validate input
        $v = Validator::make(
            $request->all(),
            [
                'login' => 'required|min:2',
                'password' => ['required', 'confirmed', new Password()],
                'domain' => 'required',
                'voucher' => 'max:32',
            ]
        );

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        $settings = [];

        // Plan parameter is required/allowed in mandate mode
        if (!empty($request->plan) && empty($request->code) && empty($request->invitation)) {
            $plan = Plan::withEnvTenantContext()->where('title', $request->plan)->first();

            if (!$plan || $plan->mode != Plan::MODE_MANDATE) {
                $msg = \trans('validation.exists', ['attribute' => 'plan']);
                return response()->json(['status' => 'error', 'errors' => ['plan' => $msg]], 422);
            }
        } elseif ($request->invitation) {
            // Signup via invitation
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
            $v = $this->verify($request, false);
            if ($v->status() !== 200) {
                return $v;
            }

            $plan = $this->getPlan();

            // Get user name/email from the verification code database
            $code_data = $v->getData();

            $settings = [
                'first_name' => $code_data->first_name,
                'last_name' => $code_data->last_name,
            ];

            if ($plan->mode == Plan::MODE_TOKEN) {
                $settings['signup_token'] = $code_data->email;
            } else {
                $settings['external_email'] = $code_data->email;
            }
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

        if (empty($plan)) {
            $plan = $this->getPlan();
        }

        $is_domain = $plan->hasDomain();

        // Validate login
        if ($errors = self::validateLogin($request->login, $request->domain, $is_domain)) {
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        // Set some properties for signup() method
        $request->settings = $settings;
        $request->plan = $plan;
        $request->discount = $discount ?? null;
        $request->invitation = $invitation ?? null;

        $result = [];

        if ($plan->mode == Plan::MODE_MANDATE) {
            $result = $this->mandateForPlan($plan, $request->discount);
        }

        return response()->json($result + ['status' => 'success']);
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
        $v = $this->signupValidate($request);
        if ($v->status() !== 200) {
            return $v;
        }

        $is_domain = $request->plan->hasDomain();

        // We allow only ASCII, so we can safely lower-case the email address
        $login = Str::lower($request->login);
        $domain_name = Str::lower($request->domain);
        $domain = null;

        DB::beginTransaction();

        // Create domain record
        if ($is_domain) {
            $domain = Domain::create([
                    'namespace' => $domain_name,
                    'type' => Domain::TYPE_EXTERNAL,
            ]);
        }

        // Create user record
        $user = User::create([
                'email' => $login . '@' . $domain_name,
                'password' => $request->password,
                'status' => User::STATUS_RESTRICTED,
        ]);

        if ($request->discount) {
            $wallet = $user->wallets()->first();
            $wallet->discount()->associate($request->discount);
            $wallet->save();
        }

        $user->assignPlan($request->plan, $domain);

        // Save the external email and plan in user settings
        $user->setSettings($request->settings);

        // Update the invitation
        if ($request->invitation) {
            $request->invitation->status = SignupInvitation::STATUS_COMPLETED;
            $request->invitation->user_id = $user->id;
            $request->invitation->save();
        }

        // Soft-delete the verification code, and store some more info with it
        if ($request->code) {
            $request->code->user_id = $user->id;
            $request->code->submit_ip_address = $request->ip();
            $request->code->deleted_at = \now();
            $request->code->timestamps = false;
            $request->code->save();
        }

        DB::commit();

        $response = AuthController::logonResponse($user, $request->password);

        if ($request->plan->mode == Plan::MODE_MANDATE) {
            $data = $response->getData(true);
            $data['checkout'] = $this->mandateForPlan($request->plan, $request->discount, $user);
            $response->setData($data);
        }

        return $response;
    }

    /**
     * Collects some content to display to the user before redirect to a checkout page.
     * Optionally creates a recurrent payment mandate for specified user/plan.
     */
    protected function mandateForPlan(Plan $plan, Discount $discount = null, User $user = null): array
    {
        $result = [];

        $min = \App\Payment::MIN_AMOUNT;
        $planCost = $plan->cost() * $plan->months;

        if ($discount) {
            $planCost = (int) ($planCost * (100 - $discount->discount) / 100);
        }

        if ($planCost > $min) {
            $min = $planCost;
        }

        if ($user) {
            $wallet = $user->wallets()->first();
            $wallet->setSettings([
                'mandate_amount' => sprintf('%.2f', round($min / 100, 2)),
                'mandate_balance' => 0,
            ]);

            $mandate = [
                'currency' => $wallet->currency,
                'description' => \App\Tenant::getConfig($user->tenant_id, 'app.name') . ' Auto-Payment Setup',
                'methodId' => PaymentProvider::METHOD_CREDITCARD,
                'redirectUrl' => \App\Utils::serviceUrl('/payment/status', $user->tenant_id),
            ];

            $provider = PaymentProvider::factory($wallet);

            $result = $provider->createMandate($wallet, $mandate);
        }

        $params = [
            'cost' => \App\Utils::money($planCost, \config('app.currency')),
            'period' => \trans($plan->months == 12 ? 'app.period-year' : 'app.period-month'),
        ];

        $content = '<b>' . self::trans('app.signup-account-tobecreated') . '</b><br><br>'
            . self::trans('app.signup-account-summary', $params) . '<br><br>'
            . self::trans('app.signup-account-mandate', $params);

        $result['content'] = $content;

        return $result;
    }

    /**
     * Returns plan for the signup process
     *
     * @returns \App\Plan Plan object selected for current signup process
     */
    protected function getPlan()
    {
        $request = request();

        if (!$request->plan || !$request->plan instanceof Plan) {
            // Get the plan if specified and exists...
            if (($request->code instanceof SignupCode) && $request->code->plan) {
                $plan = Plan::withEnvTenantContext()->where('title', $request->code->plan)->first();
            } elseif ($request->plan) {
                $plan = Plan::withEnvTenantContext()->where('title', $request->plan)->first();
            }

            // ...otherwise use the default plan
            if (empty($plan)) {
                // TODO: Get default plan title from config
                $plan = Plan::withEnvTenantContext()->where('title', 'individual')->first();
            }

            $request->plan = $plan;
        }

        return $request->plan;
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
            if (Domain::withTrashed()->where('namespace', $domain)->exists()) {
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

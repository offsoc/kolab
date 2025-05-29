<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\Controller;
use App\Policy\Greylist;
use App\Policy\Mailfilter;
use App\Policy\RateLimit;
use App\Policy\SmtpAccess;
use App\Policy\SPF;
use App\Rules\Password;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PolicyController extends Controller
{
    /**
     * Take a greylist policy request
     *
     * @return JsonResponse The response
     */
    public function greylist()
    {
        $response = Greylist::handle(\request()->input());

        return $response->jsonResponse();
    }

    /**
     * Fetch the account policies for the current user account.
     * The result includes all supported policy rules.
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $user = $this->guard()->user();

        if (!$this->checkTenant($user)) {
            return $this->errorResponse(404);
        }

        $owner = $user->walletOwner();

        if (!$user->canDelete($owner)) {
            return $this->errorResponse(403);
        }

        $config = $owner->getConfig();
        $policy_config = [];

        // Get the password policies
        $policy = new Password($owner);
        $password_policy = $policy->rules(true);
        $policy_config['max_password_age'] = $config['max_password_age'];

        // Get the mail delivery policies
        $mail_delivery_policy = [];
        if (config('app.with_mailfilter')) {
            foreach (['itip_policy', 'externalsender_policy'] as $name) {
                $mail_delivery_policy[] = $name;
                $policy_config[$name] = $config[$name] ?? null;
            }
        }

        return response()->json([
            'password' => array_values($password_policy),
            'mailDelivery' => $mail_delivery_policy,
            'config' => $policy_config,
        ]);
    }

    /**
     * SMTP Content Filter
     *
     * @param Request $request the API request
     *
     * @return Response The response
     */
    public function mailfilter(Request $request)
    {
        return Mailfilter::handle($request);
    }

    /*
     * Apply a sensible rate limitation to a request.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function ratelimit()
    {
        $response = RateLimit::handle(\request()->input());

        return $response->jsonResponse();
    }

    /*
     * Apply the sender policy framework to a request.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function senderPolicyFramework()
    {
        $response = SPF::handle(\request()->input());

        return $response->jsonResponse();
    }

    /*
     * Validate sender/recipients in an SMTP submission request.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function submission()
    {
        $response = SmtpAccess::submission(\request()->input());

        return $response->jsonResponse();
    }
}

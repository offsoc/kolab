<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\Controller;
use App\Policy\Greylist;
use App\Policy\Mailfilter;
use App\Policy\RateLimit;
use App\Policy\SmtpAccess;
use App\Policy\SPF;
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

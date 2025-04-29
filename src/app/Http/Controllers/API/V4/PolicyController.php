<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\Controller;
use App\Policy\Greylist;
use App\Policy\Mailfilter;
use App\Policy\RateLimit;
use App\Policy\SPF;
use Illuminate\Http\Request;

class PolicyController extends Controller
{
    /**
     * Take a greylist policy request
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function greylist()
    {
        $response = Greylist::handle(\request()->input());

        return $response->jsonResponse();
    }

    /**
     * SMTP Content Filter
     *
     * @param Request $request The API request.
     *
     * @return \Illuminate\Http\Response The response
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
}

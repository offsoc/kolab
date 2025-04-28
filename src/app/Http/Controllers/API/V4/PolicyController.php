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
        $request = new Greylist(\request()->input());

        $shouldDefer = $request->shouldDefer();

        if ($shouldDefer) {
            return response()->json(
                ['response' => 'DEFER_IF_PERMIT', 'reason' => "Greylisted for 5 minutes. Try again later."],
                403
            );
        }

        $prependGreylist = $request->headerGreylist();

        $result = [
            'response' => 'DUNNO',
            'prepend' => [$prependGreylist]
        ];

        return response()->json($result, 200);
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
        $data = \request()->input();

        list($local, $domain) = \App\Utils::normalizeAddress($data['sender'], true);

        if (empty($local) || empty($domain)) {
            return response()->json(['response' => 'HOLD', 'reason' => 'Invalid sender email'], 403);
        }

        $sender = $local . '@' . $domain;

        if (in_array($sender, \config('app.ratelimit_whitelist', []), true)) {
            return response()->json(['response' => 'DUNNO'], 200);
        }

        // Find the Kolab user
        $user = \App\User::withTrashed()->where('email', $sender)->first();

        if (!$user) {
            $alias = \App\UserAlias::where('alias', $sender)->first();

            if (!$alias) {
                // TODO: How about sender is a distlist address?

                // external sender through where this policy is applied
                return response()->json(['response' => 'DUNNO'], 200);
            }

            $user = $alias->user()->withTrashed()->first();
        }

        $result = RateLimit::verifyRequest($user, (array) $data['recipients']);

        return $result->jsonResponse();
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

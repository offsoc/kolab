<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\Controller;
use App\Providers\PaymentProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PolicyController extends Controller
{
    /**
     * Take a greylist policy request
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function greylist()
    {
        $data = \request()->input();

        list($local, $domainName) = explode('@', $data['recipient']);

        $request = new \App\Policy\Greylist\Request($data);

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

    /*
     * Apply a sensible rate limitation to a request.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function ratelimit()
    {
        /*
        $data = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@spf-pass.kolab.org',
            'client_name' => 'mx.kolabnow.com',
            'client_address' => '212.103.80.148',
            'recipient' => $this->domainOwner->email
        ];

        $response = $this->post('/api/webhooks/spf', $data);
        */
/*
        $data = \request()->input();

        // TODO: normalize sender address
        $sender = strtolower($data['sender']);

        $alias = \App\UserAlias::where('alias', $sender)->first();

        if (!$alias) {
            $user = \App\User::where('email', $sender)->first();

            if (!$user) {
                // what's the situation here?
            }
        } else {
            $user = $alias->user;
        }

        // TODO time-limit
        $userRates = \App\Policy\Ratelimit::where('user_id', $user->id);

        // TODO message vs. recipient limit
        if ($userRates->count() > 10) {
            // TODO
        }

        // this is the wallet to which the account is billed
        $wallet = $user->wallet;

        // TODO: consider $wallet->payments;

        $owner = $wallet->user;

        // TODO time-limit
        $ownerRates = \App\Policy\Ratelimit::where('owner_id', $owner->id);

        // TODO message vs. recipient limit (w/ user counts)
        if ($ownerRates->count() > 10) {
            // TODO
        }
*/
    }

    /*
     * Apply the sender policy framework to a request.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function senderPolicyFramework()
    {
        $data = \request()->input();

        if (!array_key_exists('client_address', $data)) {
            \Log::error("SPF: Request without client_address: " . json_encode($data));

            return response()->json(
                [
                    'response' => 'DEFER_IF_PERMIT',
                    'reason' => 'Temporary error. Please try again later (' . __LINE__ . ')'
                ],
                403
            );
        }

        list($netID, $netType) = \App\Utils::getNetFromAddress($data['client_address']);

        // This network can not be recognized.
        if (!$netID) {
            \Log::error("SPF: Request without recognizable network: " . json_encode($data));

            return response()->json(
                [
                    'response' => 'DEFER_IF_PERMIT',
                    'reason' => 'Temporary error. Please try again later (' . __LINE__ . ')'
                ],
                403
            );
        }

        $senderLocal = 'unknown';
        $senderDomain = 'unknown';

        if (strpos($data['sender'], '@') !== false) {
            list($senderLocal, $senderDomain) = explode('@', $data['sender']);
        }

        // Compose the cache key we want.
        $cacheKey = "{$netType}_{$netID}_{$senderDomain}";

        $result = \App\Policy\SPF\Cache::get($cacheKey);

        if (!$result) {
            $environment = new \SPFLib\Check\Environment(
                $data['client_address'],
                $data['client_name'],
                $data['sender']
            );

            $result = (new \SPFLib\Checker())->check($environment);

            \App\Policy\SPF\Cache::set($cacheKey, serialize($result));
        } else {
            $result = unserialize($result);
        }

        $fail = false;
        $prependSPF = '';

        switch ($result->getCode()) {
            case \SPFLib\Check\Result::CODE_ERROR_PERMANENT:
                $fail = true;
                $prependSPF = "Received-SPF: Permerror";
                break;

            case \SPFLib\Check\Result::CODE_ERROR_TEMPORARY:
                $prependSPF = "Received-SPF: Temperror";
                break;

            case \SPFLib\Check\Result::CODE_FAIL:
                $fail = true;
                $prependSPF = "Received-SPF: Fail";
                break;

            case \SPFLib\Check\Result::CODE_SOFTFAIL:
                $prependSPF = "Received-SPF: Softfail";
                break;

            case \SPFLib\Check\Result::CODE_NEUTRAL:
                $prependSPF = "Received-SPF: Neutral";
                break;

            case \SPFLib\Check\Result::CODE_PASS:
                $prependSPF = "Received-SPF: Pass";
                break;

            case \SPFLib\Check\Result::CODE_NONE:
                $prependSPF = "Received-SPF: None";
                break;
        }

        $prependSPF .= " identity=mailfrom;";
        $prependSPF .= " client-ip={$data['client_address']};";
        $prependSPF .= " helo={$data['client_name']};";
        $prependSPF .= " envelope-from={$data['sender']};";

        if ($fail) {
            // TODO: check the recipient's policy, such as using barracuda for anti-spam and anti-virus as a relay for
            // inbound mail to a local recipient address.
            $objects = \App\Utils::findObjectsByRecipientAddress($data['recipient']);

            if (!empty($objects)) {
                // check if any of the recipient objects have whitelisted the helo, first one wins.
                foreach ($objects as $object) {
                    if (method_exists($object, 'senderPolicyFrameworkWhitelist')) {
                        $result = $object->senderPolicyFrameworkWhitelist($data['client_name']);

                        if ($result) {
                            $response = [
                                'response' => 'DUNNO',
                                'prepend' => ["Received-SPF: Pass Check skipped at recipient's discretion"],
                                'reason' => 'HELO name whitelisted'
                            ];

                            return response()->json($response, 200);
                        }
                    }
                }
            }

            $result = [
                'response' => 'REJECT',
                'prepend' => [$prependSPF],
                'reason' => "Prohibited by Sender Policy Framework"
            ];

            return response()->json($result, 403);
        }

        $result = [
            'response' => 'DUNNO',
            'prepend' => [$prependSPF],
            'reason' => "Don't know"
        ];

        return response()->json($result, 200);
    }
}

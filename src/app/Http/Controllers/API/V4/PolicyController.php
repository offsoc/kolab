<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\Controller;
use App\Providers\PaymentProvider;
use App\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PolicyController extends Controller
{
    /**
     * Take a greylist policy request
     *
     * @return \Illuminate\Http\Response The response
     */
    public function greylist()
    {
        $data = \request()->input();

        list($local, $domainName) = explode('@', $data['recipient']);

        $request = new \App\Greylist\Request($data);

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
     * Apply the sender policy framework to a request.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function senderPolicyFramework()
    {
        $data = \request()->input();

        list($netID, $netType) = \App\Utils::getNetFromAddress($data['client_address']);
        list($senderLocal, $senderDomain) = explode('@', $data['sender']);

        // This network can not be recognized.
        if (!$netID) {
            return response()->json(
                [
                    'response' => 'DEFER_IF_PERMIT',
                    'reason' => 'Temporary error. Please try again later.'
                ],
                403
            );
        }

        // Compose the cache key we want.
        $cacheKey = "{$netType}_{$netID}_{$senderDomain}";

        $result = \App\SPF\Cache::get($cacheKey);

        if (!$result) {
            $environment = new \SPFLib\Check\Environment(
                $data['client_address'],
                $data['client_name'],
                $data['sender']
            );

            $result = (new \SPFLib\Checker())->check($environment);

            \App\SPF\Cache::set($cacheKey, serialize($result));
        } else {
            $result = unserialize($result);
        }

        $fail = false;

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

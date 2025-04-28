<?php

namespace App\Policy;

use App\Policy\SPF\Cache;

class SPF
{
    /**
     * Handle a policy request
     *
     * @param array $data Input data (client_address, client_name, sender, recipient)
     */
    public static function handle($data): Response
    {
        if (!array_key_exists('client_address', $data)) {
            \Log::error("SPF: Request without client_address: " . json_encode($data));

            $response = new Response(Response::ACTION_DEFER_IF_PERMIT, 'Temporary error. Please try again later.', 403);
            $response->logs[] = "SPF: Request without client_address: " . json_encode($data);

            return $response;
        }

        list($netID, $netType) = \App\Utils::getNetFromAddress($data['client_address']);

        // This network can not be recognized.
        if (!$netID) {
            \Log::error("SPF: Request without recognizable network: " . json_encode($data));

            $response = new Response(Response::ACTION_DEFER_IF_PERMIT, 'Temporary error. Please try again later.', 403);
            $response->logs[] = "SPF: Request without recognizable network: " . json_encode($data);

            return $response;
        }

        $senderLocal = 'unknown';
        $senderDomain = 'unknown';

        if (!isset($data['sender'])) {
            $data['sender'] = '';
        }

        if (strpos($data['sender'], '@') !== false) {
            list($senderLocal, $senderDomain) = explode('@', $data['sender']);

            if (strlen($senderLocal) >= 255) {
                $senderLocal = substr($senderLocal, 0, 255);
            }
        }

        // Compose the cache key we want
        $cacheKey = "{$netType}_{$netID}_{$senderDomain}";

        $result = Cache::get($cacheKey);

        if (!$result) {
            $environment = new \SPFLib\Check\Environment(
                $data['client_address'],
                $data['client_name'],
                $data['sender']
            );

            $result = (new \SPFLib\Checker())->check($environment);

            Cache::set($cacheKey, serialize($result));
        } else {
            $result = unserialize($result);
        }

        $fail = false;
        $prependSPF = '';

        switch ($result->getCode()) {
            case \SPFLib\Check\Result::CODE_ERROR_PERMANENT:
                $fail = true;
                $prependSPF = 'Received-SPF: Permerror';
                break;

            case \SPFLib\Check\Result::CODE_ERROR_TEMPORARY:
                $prependSPF = 'Received-SPF: Temperror';
                break;

            case \SPFLib\Check\Result::CODE_FAIL:
                $fail = true;
                $prependSPF = 'Received-SPF: Fail';
                break;

            case \SPFLib\Check\Result::CODE_SOFTFAIL:
                $prependSPF = 'Received-SPF: Softfail';
                break;

            case \SPFLib\Check\Result::CODE_NEUTRAL:
                $prependSPF = 'Received-SPF: Neutral';
                break;

            case \SPFLib\Check\Result::CODE_PASS:
                $prependSPF = 'Received-SPF: Pass';
                break;

            case \SPFLib\Check\Result::CODE_NONE:
                $prependSPF = 'Received-SPF: None';
                break;
        }

        $prependSPF .= " identity=mailfrom;"
            . " client-ip={$data['client_address']};"
            . " helo={$data['client_name']};"
            . " envelope-from={$data['sender']};";

        if ($fail) {
            // TODO: check the recipient's policy, such as using barracuda for anti-spam and anti-virus as a relay for
            // inbound mail to a local recipient address.
            $objects = null;
            if (array_key_exists('recipient', $data)) {
                $objects = \App\Utils::findObjectsByRecipientAddress($data['recipient']);
            }

            if (!empty($objects)) {
                // check if any of the recipient objects have whitelisted the helo, first one wins.
                foreach ($objects as $object) {
                    if (method_exists($object, 'senderPolicyFrameworkWhitelist')) {
                        $result = $object->senderPolicyFrameworkWhitelist($data['client_name']);

                        if ($result) {
                            $response = new Response(Response::ACTION_DUNNO, 'HELO name whitelisted');
                            $response->prepends[] = "Received-SPF: Pass Check skipped at recipient's discretion";

                            return $response;
                        }
                    }
                }
            }

            $response = new Response(Response::ACTION_REJECT, 'Prohibited by Sender Policy Framework', 403);
            $response->prepends[] = $prependSPF;

            return $response;
        }

        $response = new Response(Response::ACTION_DUNNO);
        $response->prepends[] = $prependSPF;

        return $response;
    }
}

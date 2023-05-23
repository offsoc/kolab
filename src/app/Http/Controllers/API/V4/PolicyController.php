<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\Controller;
use App\Policy\RateLimit;
use App\Policy\RateLimitWhitelist;
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
        $data = \request()->input();

        list($local, $domain) = \App\Utils::normalizeAddress($data['sender'], true);

        if (empty($local) || empty($domain)) {
            return response()->json(['response' => 'HOLD', 'reason' => 'Invalid sender email'], 403);
        }

        $sender = $local . '@' . $domain;

        if (in_array($sender, \config('app.ratelimit_whitelist', []), true)) {
            return response()->json(['response' => 'DUNNO'], 200);
        }

        //
        // Examine the individual sender
        //
        $user = \App\User::withTrashed()->where('email', $sender)->first();

        if (!$user) {
            $alias = \App\UserAlias::where('alias', $sender)->first();

            if (!$alias) {
                // external sender through where this policy is applied
                return response()->json(['response' => 'DUNNO'], 200);
            }

            $user = $alias->user;
        }

        if (empty($user) || $user->trashed() || $user->isSuspended()) {
            // use HOLD, so that it is silent (as opposed to REJECT)
            return response()->json(['response' => 'HOLD', 'reason' => 'Sender deleted or suspended'], 403);
        }

        //
        // Examine the domain
        //
        $domain = \App\Domain::withTrashed()->where('namespace', $domain)->first();

        if (!$domain) {
            // external sender through where this policy is applied
            return response()->json(['response' => 'DUNNO'], 200);
        }

        if ($domain->trashed() || $domain->isSuspended()) {
            // use HOLD, so that it is silent (as opposed to REJECT)
            return response()->json(['response' => 'HOLD', 'reason' => 'Sender domain deleted or suspended'], 403);
        }

        // see if the user or domain is whitelisted
        // use ./artisan policy:ratelimit:whitelist:create <email|namespace>
        if (RateLimitWhitelist::isListed($user) || RateLimitWhitelist::isListed($domain)) {
            return response()->json(['response' => 'DUNNO'], 200);
        }

        // user nor domain whitelisted, continue scrutinizing the request
        $recipients = $data['recipients'];
        sort($recipients);

        $recipientCount = count($recipients);
        $recipientHash = hash('sha256', implode(',', $recipients));

        //
        // Retrieve the wallet to get to the owner
        //
        $wallet = $user->wallet();

        // wait, there is no wallet?
        if (!$wallet || !$wallet->owner) {
            return response()->json(['response' => 'HOLD', 'reason' => 'Sender without a wallet'], 403);
        }

        $owner = $wallet->owner;

        // find or create the request
        $request = RateLimit::where('recipient_hash', $recipientHash)
            ->where('user_id', $user->id)
            ->where('updated_at', '>=', \Carbon\Carbon::now()->subHour())
            ->first();

        if (!$request) {
            $request = RateLimit::create([
                    'user_id' => $user->id,
                    'owner_id' => $owner->id,
                    'recipient_hash' => $recipientHash,
                    'recipient_count' => $recipientCount
            ]);
        } else {
            // ensure the request has an up to date timestamp
            $request->updated_at = \Carbon\Carbon::now();
            $request->save();
        }

        // exempt owners that have made at least two payments and currently maintain a positive balance.
        if ($wallet->balance > 0) {
            $payments = $wallet->payments()->where('amount', '>', 0)->where('status', 'paid');

            if ($payments->count() >= 2) {
                return response()->json(['response' => 'DUNNO'], 200);
            }
        }

        //
        // Examine the rates at which the owner (or its users) is sending
        //
        $ownerRates = RateLimit::where('owner_id', $owner->id)
            ->where('updated_at', '>=', \Carbon\Carbon::now()->subHour());

        if (($count = $ownerRates->count()) >= 10) {
            $result = [
                'response' => 'DEFER_IF_PERMIT',
                'reason' => 'The account is at 10 messages per hour, cool down.'
            ];

            // automatically suspend (recursively) if 2.5 times over the original limit and younger than two months
            $ageThreshold = \Carbon\Carbon::now()->subMonthsWithoutOverflow(2);

            if ($count >= 25 && $owner->created_at > $ageThreshold) {
                $owner->suspendAccount();
            }

            return response()->json($result, 403);
        }

        if (($recipientCount = $ownerRates->sum('recipient_count')) >= 100) {
            $result = [
                'response' => 'DEFER_IF_PERMIT',
                'reason' => 'The account is at 100 recipients per hour, cool down.'
            ];

            // automatically suspend if 2.5 times over the original limit and younger than two months
            $ageThreshold = \Carbon\Carbon::now()->subMonthsWithoutOverflow(2);

            if ($recipientCount >= 250 && $owner->created_at > $ageThreshold) {
                $owner->suspendAccount();
            }

            return response()->json($result, 403);
        }

        //
        // Examine the rates at which the user is sending (if not also the owner)
        //
        if ($user->id != $owner->id) {
            $userRates = RateLimit::where('user_id', $user->id)
                ->where('updated_at', '>=', \Carbon\Carbon::now()->subHour());

            if (($count = $userRates->count()) >= 10) {
                $result = [
                    'response' => 'DEFER_IF_PERMIT',
                    'reason' => 'User is at 10 messages per hour, cool down.'
                ];

                // automatically suspend if 2.5 times over the original limit and younger than two months
                $ageThreshold = \Carbon\Carbon::now()->subMonthsWithoutOverflow(2);

                if ($count >= 25 && $user->created_at > $ageThreshold) {
                    $user->suspend();
                }

                return response()->json($result, 403);
            }

            if (($recipientCount = $userRates->sum('recipient_count')) >= 100) {
                $result = [
                    'response' => 'DEFER_IF_PERMIT',
                    'reason' => 'User is at 100 recipients per hour, cool down.'
                ];

                // automatically suspend if 2.5 times over the original limit
                $ageThreshold = \Carbon\Carbon::now()->subMonthsWithoutOverflow(2);

                if ($recipientCount >= 250 && $user->created_at > $ageThreshold) {
                    $user->suspend();
                }

                return response()->json($result, 403);
            }
        }

        return response()->json(['response' => 'DUNNO'], 200);
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
                    'reason' => 'Temporary error. Please try again later.'
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
                    'reason' => 'Temporary error. Please try again later.'
                ],
                403
            );
        }

        $senderLocal = 'unknown';
        $senderDomain = 'unknown';

        if (strpos($data['sender'], '@') !== false) {
            list($senderLocal, $senderDomain) = explode('@', $data['sender']);

            if (strlen($senderLocal) >= 255) {
                $senderLocal = substr($senderLocal, 0, 255);
            }
        }

        if ($data['sender'] === null) {
            $data['sender'] = '';
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

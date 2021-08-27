<?php

namespace App\Policy\Greylist;

use Illuminate\Support\Facades\DB;

class Request
{
    protected $header;
    protected $netID;
    protected $netType;
    protected $recipientHash;
    protected $recipientID = null;
    protected $recipientType = null;
    protected $sender;
    protected $senderLocal = 'unknown';
    protected $senderDomain = 'unknown';
    protected $timestamp;
    protected $whitelist;
    protected $request = [];

    public function __construct($request)
    {
        $this->request = $request;

        if (array_key_exists('timestamp', $this->request)) {
            $this->timestamp = \Carbon\Carbon::parse($this->request['timestamp']);
        } else {
            $this->timestamp = \Carbon\Carbon::now();
        }
    }

    public function headerGreylist()
    {
        if ($this->whitelist) {
            if ($this->whitelist->sender_local) {
                return sprintf(
                    "Received-Greylist: sender %s whitelisted since %s",
                    $this->sender,
                    $this->whitelist->created_at->toDateString()
                );
            }

            return sprintf(
                "Received-Greylist: domain %s from %s whitelisted since %s (UTC)",
                $this->senderDomain,
                $this->request['client_address'],
                $this->whitelist->created_at->toDateTimeString()
            );
        }

        $connect = $this->findConnectsCollection()->orderBy('created_at')->first();

        if ($connect) {
            return sprintf(
                "Received-Greylist: greylisted from %s until %s.",
                $connect->created_at,
                $this->timestamp
            );
        }

        return "Received-Greylist: no opinion here";
    }

    public function shouldDefer()
    {
        list($this->netID, $this->netType) = \App\Utils::getNetFromAddress($this->request['client_address']);

        if (!$this->netID) {
            return true;
        }

        $recipient = $this->recipientFromRequest();

        $this->sender = $this->senderFromRequest();

        if (strpos($this->sender, '@') !== false) {
            list($this->senderLocal, $this->senderDomain) = explode('@', $this->sender);
        }

        if (strlen($this->senderLocal) > 255) {
            $this->senderLocal = substr($this->senderLocal, 0, 255);
        }

        $entry = $this->findConnectsCollectionRecent()->orderBy('updated_at')->first();

        if (!$entry) {
            // purge all entries to avoid a unique constraint violation.
            $this->findConnectsCollection()->delete();

            $entry = Connect::create(
                [
                    'sender_local' => $this->senderLocal,
                    'sender_domain' => $this->senderDomain,
                    'net_id' => $this->netID,
                    'net_type' => $this->netType,
                    'recipient_hash' => $this->recipientHash,
                    'recipient_id' => $this->recipientID,
                    'recipient_type' => $this->recipientType,
                    'connect_count' => 1,
                    'created_at' => $this->timestamp,
                    'updated_at' => $this->timestamp
                ]
            );
        }

        // See if the recipient opted-out of the feature
        $enabled = true;
        if ($recipient) {
            $enabled = $recipient->getSetting('greylist_enabled') !== 'false';
        }

        // FIXME: Shouldn't we bail-out (return early) if there's no $recipient?

        // the following block is to maintain statistics and state ...
        $entries = Connect::where(
            [
                'sender_domain' => $this->senderDomain,
                'net_id' => $this->netID,
                'net_type' => $this->netType
            ]
        )
            ->whereDate('updated_at', '>=', $this->timestamp->copy()->subDays(7));

        // determine if the sender domain is a whitelist from this network
        $this->whitelist = Whitelist::where(
            [
                'sender_domain' => $this->senderDomain,
                'net_id' => $this->netID,
                'net_type' => $this->netType
            ]
        )->first();

        if ($this->whitelist) {
            if ($this->whitelist->updated_at < $this->timestamp->copy()->subMonthsWithoutOverflow(1)) {
                $this->whitelist->delete();
            } else {
                $this->whitelist->updated_at = $this->timestamp;
                $this->whitelist->save(['timestamps' => false]);

                $entries->update(
                    [
                        'greylisting' => false,
                        'updated_at' => $this->timestamp
                    ]
                );

                return false;
            }
        } else {
            if ($entries->count() >= 5) {
                $this->whitelist = Whitelist::create(
                    [
                        'sender_domain' => $this->senderDomain,
                        'net_id' => $this->netID,
                        'net_type' => $this->netType,
                        'created_at' => $this->timestamp,
                        'updated_at' => $this->timestamp
                    ]
                );

                $entries->update(
                    [
                        'greylisting' => false,
                        'updated_at' => $this->timestamp
                    ]
                );
            }
        }

        // TODO: determine if the sender (individual) is a whitelist

        // TODO: determine if the sender is a penpal of any of the recipients. First recipient wins.

        if (!$enabled) {
            return false;
        }

        // determine if the sender, net and recipient combination has existed before, for each recipient
        // any one recipient matching should supersede the other recipients not having matched
        $connect = Connect::where(
            [
                'sender_local' => $this->senderLocal,
                'sender_domain' => $this->senderDomain,
                'recipient_hash' => $this->recipientHash,
                'net_id' => $this->netID,
                'net_type' => $this->netType,
            ]
        )
            ->whereDate('updated_at', '>=', $this->timestamp->copy()->subMonthsWithoutOverflow(1))
            ->orderBy('updated_at')
            ->first();

        $deferIfPermit = true;

        if ($connect) {
            $connect->connect_count += 1;

            // TODO: The period of time for which the greylisting persists is configurable.
            if ($connect->created_at < $this->timestamp->copy()->subMinutes(5)) {
                $deferIfPermit = false;

                $connect->greylisting = false;
            }

            $connect->save();
        } else {
            Connect::create(
                [
                    'sender_local' => $this->senderLocal,
                    'sender_domain' => $this->senderDomain,
                    'net_id' => $this->netID,
                    'net_type' => $this->netType,
                    'recipient_hash' => $this->recipientHash,
                    'recipient_id' => $this->recipientID,
                    'recipient_type' => $this->recipientType,
                    'created_at' => $this->timestamp,
                    'updated_at' => $this->timestamp
                ]
            );
        }

        return $deferIfPermit;
    }

    private function findConnectsCollection()
    {
        $collection = Connect::where(
            [
                'sender_local' => $this->senderLocal,
                'sender_domain' => $this->senderDomain,
                'recipient_hash' => $this->recipientHash,
                'net_id' => $this->netID,
                'net_type' => $this->netType,
            ]
        );

        return $collection;
    }

    private function findConnectsCollectionRecent()
    {
        return $this->findConnectsCollection()
            ->where('updated_at', '>=', $this->timestamp->copy()->subDays(7));
    }

    private function recipientFromRequest()
    {
        $recipients = \App\Utils::findObjectsByRecipientAddress($this->request['recipient']);

        if (sizeof($recipients) > 1) {
            \Log::warning(
                "Only taking the first recipient from the request for {$this->request['recipient']}"
            );
        }

        if (count($recipients) >= 1) {
            foreach ($recipients as $recipient) {
                if ($recipient) {
                    $this->recipientID = $recipient->id;
                    $this->recipientType = get_class($recipient);
                    break;
                }
            }
        } else {
            $recipient = null;
        }

        $this->recipientHash = hash('sha256', \App\Utils::normalizeAddress($this->request['recipient']));

        return $recipient;
    }

    public function senderFromRequest()
    {
        return \App\Utils::normalizeAddress($this->request['sender']);
    }
}

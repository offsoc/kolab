<?php

namespace App\Policy\Mailfilter\Modules;

use App\Policy\Mailfilter\MailParser;
use App\Policy\Mailfilter\Result;

class ExternalSenderModule
{
    /**
     * Handle the email message
     */
    public function handle(MailParser $parser): ?Result
    {
        $sender = $parser->getSender();

        $user = $parser->getUser();

        [, $sender_domain] = explode('@', $sender);
        [, $user_domain] = explode('@', $user->email);

        $sender_domain = strtolower($sender_domain);

        // Sender and recipient in the same domain
        if ($sender_domain === $user_domain) {
            return null; // just accept the message as-is
        }

        $account = $user->wallet()->owner;

        // Check against the account domains list
        // TODO: Use a per-account/per-user list of additional domains
        if ($account->domains(false, false)->where('namespace', $sender_domain)->exists()) {
            return null; // just accept the message as-is
        }

        $subject = $parser->getHeader('subject');

        // Update the subject with a prefix
        if (is_string($subject)) {
            $subject = '[EXTERNAL] ' . $subject;
        } else {
            $subject = '[EXTERNAL]';
        }

        $parser->setHeader('Subject', $subject);

        return null;
    }
}

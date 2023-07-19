<?php

namespace App\Mail;

use App\EventLog;
use App\Tenant;
use Illuminate\Support\Facades\Mail;

class Helper
{
    /**
     * Render the mail template.
     *
     * @param Mailable $mail The mailable object
     * @param string   $type Output format ('html' or 'text')
     *
     * @return string HTML or Plain Text output
     */
    public static function render(Mailable $mail, string $type = 'html'): string
    {
        // Plain text output
        if ($type == 'text') {
            $mail->build(); // @phpstan-ignore-line

            $mailer = \Illuminate\Container\Container::getInstance()->make('mailer');

            return $mailer->render(['text' => $mail->textView], $mail->buildViewData());
        }

        if ($type != 'html') {
            throw new \Exception("Unsupported output format");
        }

        // HTML output
        return $mail->build()->render(); // @phpstan-ignore-line
    }

    /**
     * Sends an email
     *
     * @param Mailable $mail     Email content generator
     * @param int|null $tenantId Tenant identifier
     * @param array    $params   Email parameters: to, cc
     *
     * @throws \Exception
     */
    public static function sendMail(Mailable $mail, $tenantId = null, array $params = []): void
    {
        $class = class_basename(get_class($mail));
        $recipients = [];

        // For now we do not support addresses + names, only addresses
        foreach (['to', 'cc'] as $idx) {
            if (!empty($params[$idx])) {
                if (is_array($params[$idx])) {
                    $recipients = array_merge($recipients, $params[$idx]);
                } else {
                    $recipients[] = $params[$idx];
                }
            }
        }

        try {
            if (!empty($params['to'])) {
                $mail->to($params['to']);
            }

            if (!empty($params['cc'])) {
                $mail->cc($params['cc']);
            }

            $fromAddress = Tenant::getConfig($tenantId, 'mail.from.address');
            $fromName = Tenant::getConfig($tenantId, 'mail.from.name');
            $replytoAddress = Tenant::getConfig($tenantId, 'mail.reply_to.address');
            $replytoName = Tenant::getConfig($tenantId, 'mail.reply_to.name');

            if ($fromAddress) {
                $mail->from($fromAddress, $fromName);
            }

            if ($replytoAddress) {
                $mail->replyTo($replytoAddress, $replytoName);
            }

            Mail::send($mail);

            if ($user = $mail->getUser()) {
                $comment = "[{$class}] " . $mail->getSubject();
                EventLog::createFor($user, EventLog::TYPE_MAILSENT, $comment, ['recipients' => $recipients]);
            }
        } catch (\Exception $e) {
            $format = "[%s] Failed to send mail to %s%s: %s";
            $msg = sprintf($format, $class, implode(', ', $recipients), $params['add'] ?? '', $e->getMessage());

            \Log::error($msg);
            throw $e;
        }
    }

    /**
     * Return user's email addresses, separately for use in To and Cc.
     *
     * @param \App\User $user     The user
     * @param bool      $external Include users's external email
     *
     * @return array To address as the first element, Cc address(es) as the second.
     */
    public static function userEmails(\App\User $user, bool $external = false): array
    {
        $active = $user->isLdapReady() && $user->isImapReady();

        // Sending an email to non-(ldap|imap)-ready user will fail, skip it
        // (or send to the external email only, when appropriate)
        $to = $active ? $user->email : null;
        $cc = [];

        // If user has no mailbox entitlement we should not send
        // the email to his main address, but use external address, if defined
        if ($active && !$user->hasSku('mailbox')) {
            $to = $user->getSetting('external_email');
        } elseif ($external) {
            $ext_email = $user->getSetting('external_email');

            if ($ext_email && $ext_email != $to) {
                $cc[] = $ext_email;
            }
        }

        return [$to, $cc];
    }
}

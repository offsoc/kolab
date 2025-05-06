<?php

namespace App\Jobs\Mail;

use App\Jobs\MailJob;
use App\Mail\Helper;
use App\Mail\PasswordExpirationReminder;
use App\User;

class PasswordRetentionJob extends MailJob
{
    /** @var string Password expiration date */
    protected $expiresOn;

    /** @var User User object */
    protected $user;

    /**
     * Create a new job instance.
     *
     * @param User   $user      User object
     * @param string $expiresOn Password expiration date
     */
    public function __construct(User $user, string $expiresOn)
    {
        $this->user = $user;
        $this->expiresOn = $expiresOn;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        if (!$this->user->isLdapReady() || !$this->user->isImapReady()) {
            // The account isn't ready for mail delivery
            return;
        }

        // TODO: Should we check if the password didn't update since
        //       the job has been created?

        Helper::sendMail(
            new PasswordExpirationReminder($this->user, $this->expiresOn),
            $this->user->tenant_id,
            ['to' => $this->user->email]
        );

        // Remember when we sent the email notification
        $this->user->setSetting('password_expiration_warning', \now()->toDateTimeString());
    }
}

<?php

namespace App\Jobs\Mail;

use App\Mail\PasswordExpirationReminder;

class PasswordRetentionJob extends \App\Jobs\MailJob
{
    /** @var string Password expiration date */
    protected $expiresOn;

    /** @var \App\User User object */
    protected $user;


    /**
     * Create a new job instance.
     *
     * @param \App\User $user      User object
     * @param string    $expiresOn Password expiration date
     *
     * @return void
     */
    public function __construct(\App\User $user, string $expiresOn)
    {
        $this->user = $user;
        $this->expiresOn = $expiresOn;
        $this->onQueue(self::QUEUE);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (!$this->user->isLdapReady() || !$this->user->isImapReady()) {
            // The account isn't ready for mail delivery
            return;
        }

        // TODO: Should we check if the password didn't update since
        //       the job has been created?

        \App\Mail\Helper::sendMail(
            new PasswordExpirationReminder($this->user, $this->expiresOn),
            $this->user->tenant_id,
            ['to' => $this->user->email]
        );

        // Remember when we sent the email notification
        $this->user->setSetting('password_expiration_warning', \now()->toDateTimeString());
    }
}

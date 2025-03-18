<?php

namespace App\Jobs\Mail;

use App\Mail\SignupInvitation as SignupInvitationMail;
use App\SignupInvitation;

class SignupInvitationJob extends \App\Jobs\MailJob
{
    /** @var SignupInvitation Signup invitation object */
    protected $invitation;


    /**
     * Create a new job instance.
     *
     * @param SignupInvitation $invitation Invitation object
     *
     * @return void
     */
    public function __construct(SignupInvitation $invitation)
    {
        $this->invitation = $invitation;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        \App\Mail\Helper::sendMail(
            new SignupInvitationMail($this->invitation),
            $this->invitation->tenant_id,
            ['to' => $this->invitation->email]
        );

        // Update invitation status
        $this->invitation->status = SignupInvitation::STATUS_SENT;
        $this->invitation->save();
    }

    /**
     * The job failed to process.
     *
     * @param \Exception $exception
     *
     * @return void
     */
    public function failed(\Exception $exception)
    {
        if ($this->attempts() >= $this->tries) {
            // Update invitation status
            $this->invitation->status = SignupInvitation::STATUS_FAILED;
            $this->invitation->save();
        }
    }
}

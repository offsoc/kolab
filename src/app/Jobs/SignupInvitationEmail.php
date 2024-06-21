<?php

namespace App\Jobs;

use App\SignupInvitation;
use App\Mail\SignupInvitation as SignupInvitationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;

class SignupInvitationEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int The number of times the job may be attempted. */
    public $tries = 3;

    /** @var bool Delete the job if its models no longer exist. */
    public $deleteWhenMissingModels = true;

    /** @var int The number of seconds to wait before retrying the job. */
    public $backoff = 10;

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

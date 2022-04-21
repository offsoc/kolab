<?php

namespace App\Jobs\Password;

use App\Mail\PasswordExpirationReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;

class RetentionEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var bool Delete the job if its models no longer exist. */
    public $deleteWhenMissingModels = true;

    /** @var int The number of times the job may be attempted. */
    public $tries = 2;

    /** @var int The number of seconds to wait before retrying the job. */
    public $retryAfter = 10;

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
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
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

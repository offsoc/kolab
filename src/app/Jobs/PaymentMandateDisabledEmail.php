<?php

namespace App\Jobs;

use App\Mail\PaymentMandateDisabled;
use App\User;
use App\Wallet;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;

class PaymentMandateDisabledEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int The number of times the job may be attempted. */
    public $tries = 2;

    /** @var int The number of seconds to wait before retrying the job. */
    public $retryAfter = 10;

    /** @var bool Delete the job if the wallet no longer exist. */
    public $deleteWhenMissingModels = true;

    /** @var \App\Wallet A wallet object */
    protected $wallet;

    /** @var \App\User A wallet controller */
    protected $controller;


    /**
     * Create a new job instance.
     *
     * @param \App\Wallet $wallet     A wallet object
     * @param \App\User   $controller An email recipient (wallet controller)
     *
     * @return void
     */
    public function __construct(Wallet $wallet, User $controller = null)
    {
        $this->wallet = $wallet;
        $this->controller = $controller;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (empty($this->controller)) {
            $this->controller = $this->wallet->owner;
        }

        $ext_email = $this->controller->getSetting('external_email');
        $cc = [];

        if ($ext_email && $ext_email != $this->controller->email) {
            $cc[] = $ext_email;
        }

        $mail = new PaymentMandateDisabled($this->wallet, $this->controller);

        Mail::to($this->controller->email)->cc($cc)->send($mail);

        /*
        // Send the email to all controllers too
        if ($this->controller->id == $this->wallet->owner->id) {
            $this->wallet->controllers->each(function ($controller) {
                self::dispatch($this->wallet, $controller);
            }
        });
        */
    }
}

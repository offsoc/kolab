<?php

namespace App\Jobs;

use App\Backends\IMAP;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UserVerify implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $user;

    public $tries = 5;

    /** @var bool Delete the job if its models no longer exist. */
    public $deleteWhenMissingModels = true;


    /**
     * Create a new job instance.
     *
     * @param User $user The user to create.
     *
     * @return void
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Verify a mailbox sku is among the user entitlements.
        $skuMailbox = \App\Sku::where('title', 'mailbox')->first();

        if (!$skuMailbox) {
            return;
        }

        $mailbox = \App\Entitlement::where(
            [
                'sku_id' => $skuMailbox->id,
                'entitleable_id' => $this->user->id,
                'entitleable_type' => User::class
            ]
        )->first();

        if (!$mailbox) {
            return;
        }

        // The user has a mailbox
        if (!$this->user->isImapReady()) {
            if (IMAP::verifyAccount($this->user->email)) {
                $this->user->status |= User::STATUS_IMAP_READY;
                $this->user->status |= User::STATUS_ACTIVE;
                $this->user->save();
            }
        }
    }
}

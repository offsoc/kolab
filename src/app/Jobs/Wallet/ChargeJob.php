<?php

namespace App\Jobs\Wallet;

use App\Jobs\CommonJob;
use App\Wallet;

class ChargeJob extends CommonJob
{
    /** @var int How many times retry the job if it fails. */
    public $tries = 5;

    /** @var string|null The name of the queue the job should be sent to. */
    public $queue = \App\Enums\Queue::Background->value;

    /** @var string A wallet identifier */
    protected $walletId;

    /**
     * Create a new job instance.
     *
     * @param string $walletId The wallet that has been charged.
     *
     * @return void
     */
    public function __construct(string $walletId)
    {
        $this->walletId = $walletId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->logJobStart($this->walletId);

        if ($wallet = Wallet::find($this->walletId)) {
            $wallet->topUp();
        }
    }
}

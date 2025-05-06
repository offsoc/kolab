<?php

namespace App\Jobs\Wallet;

use App\Enums\Queue;
use App\Jobs\CommonJob;
use App\Wallet;

class ChargeJob extends CommonJob
{
    /** @var int How many times retry the job if it fails. */
    public $tries = 5;

    /** @var string|null The name of the queue the job should be sent to. */
    public $queue = Queue::Background->value;

    /** @var string A wallet identifier */
    protected $walletId;

    /**
     * Create a new job instance.
     *
     * @param string $walletId the wallet that has been charged
     */
    public function __construct(string $walletId)
    {
        $this->walletId = $walletId;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $this->logJobStart($this->walletId);

        if ($wallet = Wallet::find($this->walletId)) {
            $wallet->topUp();
        }
    }
}

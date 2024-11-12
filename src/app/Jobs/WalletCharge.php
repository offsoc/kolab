<?php

namespace App\Jobs;

use App\Wallet;
use App\Http\Controllers\API\V4\PaymentsController;

class WalletCharge extends CommonJob
{
    /** @var int How many times retry the job if it fails. */
    public $tries = 5;

    /** @var bool Delete the job if the wallet no longer exist. */
    public $deleteWhenMissingModels = true;

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
     * Number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120, 300];
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
            PaymentsController::topUpWallet($wallet);
        }
    }
}

<?php

namespace App\Jobs;

use App\Wallet;

class WalletCharge extends CommonJob
{
    public const QUEUE = 'background';

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
        $this->onQueue(self::QUEUE);
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

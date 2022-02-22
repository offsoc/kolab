<?php

namespace App\Jobs;

use App\Wallet;
use App\Http\Controllers\API\V4\PaymentsController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WalletCharge implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var \App\Wallet A wallet object */
    protected $wallet;

    /** @var int The number of seconds to wait before retrying the job. */
    public $backoff = 10;

    /** @var int How many times retry the job if it fails. */
    public $tries = 5;

    /** @var bool Delete the job if the wallet no longer exist. */
    public $deleteWhenMissingModels = true;


    /**
     * Create a new job instance.
     *
     * @param \App\Wallet $wallet The wallet that has been charged.
     *
     * @return void
     */
    public function __construct(Wallet $wallet)
    {
        $this->wallet = $wallet;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        PaymentsController::topUpWallet($this->wallet);
    }
}

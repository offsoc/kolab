<?php

namespace App\Jobs;

use App\Wallet;
use App\Http\Controllers\API\PaymentsController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WalletPayment implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $wallet;

    public $tries = 5;

    /** @var bool Delete the job if its models no longer exist. */
    public $deleteWhenMissingModels = true;


    /**
     * Create a new job instance.
     *
     * @param \App\Wallet $wallet The wallet to charge.
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
        if (!$this->wallet->balance < 0) {
            PaymentsController::directCharge($this->wallet, $this->wallet->balance * -1);
        }
    }
}

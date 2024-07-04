<?php

namespace App\Console\Commands\User;

use App\Console\Command;

class GreylistCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:greylist {user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List currently greylisted delivery attempts for the user.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // pretend that all users are local;
        $recipientAddress = $this->argument('user');
        $recipientHash = hash('sha256', $recipientAddress);

        $lastConnect = \App\Policy\Greylist\Connect::where('recipient_hash', $recipientHash)
            ->orderBy('updated_at', 'desc')
            ->first();

        if ($lastConnect) {
            $timestamp = $lastConnect->updated_at->copy();
            $this->info("Going from timestamp (last connect) {$timestamp}");
        } else {
            $timestamp = \Carbon\Carbon::now();
            $this->info("Going from timestamp (now) {$timestamp}");
        }

        \App\Policy\Greylist\Connect::where('recipient_hash', $recipientHash)
            ->where('greylisting', true)
            ->whereDate('updated_at', '>=', $timestamp->copy()->subDays(7))
            ->orderBy('created_at')->each(
                function ($connect) {
                    $this->info(
                        sprintf(
                            "From %s@%s since %s",
                            $connect->sender_local,
                            $connect->sender_domain,
                            $connect->created_at
                        )
                    );
                }
            );
    }
}

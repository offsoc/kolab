<?php

namespace App\Console\Commands\Status;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Backends\LDAP;
use App\Backends\IMAP;
use App\Backends\Roundcube;
use App\Backends\OpenExchangeRates;
use App\Providers\Payment\Mollie;

//TODO stripe
//TODO firebase

class Health extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'status:health {user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check health of backends';

    private function checkDB()
    {
        try {
            $result = DB::select("SELECT 1");
            return true;
        } catch (\Exception $exception) {
            $this->line($exception);
            return false;
        }
    }

    private function checkOpenExchangeRates()
    {
        try {
            OpenExchangeRates::validate();
            return true;
        } catch (\Exception $exception) {
            $this->line($exception);
            return false;
        }
    }

    private function checkMollie()
    {
        try {
            return Mollie::validate();
        } catch (\Exception $exception) {
            $this->line($exception);
            return false;
        }
    }

    private function checkLDAP()
    {
        try {
            LDAP::validate();
            //TODO validate domain part of email?
            return true;
        } catch (\Exception $exception) {
            $this->line($exception);
            return false;
        }
    }

    private function checkIMAP($email)
    {
        try {
            IMAP::verifyAccount($email);
            return true;
        } catch (\Exception $exception) {
            $this->line($exception);
            return false;
        }
    }

    private function checkRoundcube()
    {
        try {
            //TODO maybe run a select?
            Roundcube::dbh();
            return true;
        } catch (\Exception $exception) {
            $this->line($exception);
            return false;
        }
    }

    private function checkMeet()
    {
        try {
            $urls = \config('meet.api_urls');
            foreach ($urls as $url) {
                $this->line("Checking $url");

                $client = new \GuzzleHttp\Client(
                    [
                        'http_errors' => false, // No exceptions from Guzzle
                        'base_uri' => $url,
                        'verify' => \config('meet.api_verify_tls'),
                        'headers' => [
                            'X-Auth-Token' => \config('meet.api_token'),
                        ],
                        'connect_timeout' => 10,
                        'timeout' => 10,
                        'on_stats' => function (\GuzzleHttp\TransferStats $stats) {
                            $threshold = \config('logging.slow_log');
                            if ($threshold && ($sec = $stats->getTransferTime()) > $threshold) {
                                $url = $stats->getEffectiveUri();
                                $method = $stats->getRequest()->getMethod();
                                \Log::warning(sprintf("[STATS] %s %s: %.4f sec.", $method, $url, $sec));
                            }
                        },
                    ]
                );

                $response = $client->request('GET', "ping");
                if ($response->getStatusCode() != 200) {
                    $this->line("Backend not avialable: " . var_export($response, true));
                    return false;
                }
            }
            return true;
        } catch (\Exception $exception) {
            $this->line($exception);
            return false;
        }
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $email = $this->argument('user');

        $this->line("Checking DB...");
        if ($this->checkDB()) {
            $this->info("OK");
        } else {
            $this->error("Not found");
        }
        $this->line("Checking LDAP...");
        if ($this->checkLDAP()) {
            $this->info("OK");
        } else {
            $this->error("Not found");
        }
        $this->line("Checking IMAP...");
        if ($this->checkIMAP($email)) {
            $this->info("OK");
        } else {
            $this->error("Not found");
        }
        $this->line("Checking Roundcube...");
        if ($this->checkRoundcube()) {
            $this->info("OK");
        } else {
            $this->error("Not found");
        }
        $this->line("Checking Meet...");
        if ($this->checkMeet()) {
            $this->info("OK");
        } else {
            $this->error("Not found");
        }
        $this->line("Checking OpenExchangeRates...");
        if ($this->checkOpenExchangeRates()) {
            $this->info("OK");
        } else {
            $this->error("Not found");
        }
        $this->line("Checking Mollie...");
        if ($this->checkMollie()) {
            $this->info("OK");
        } else {
            $this->error("Not found");
        }
    }
}

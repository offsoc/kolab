<?php

namespace App\Console\Commands\Status;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Backends\DAV;
use App\Backends\IMAP;
use App\Backends\LDAP;
use App\Backends\OpenExchangeRates;
use App\Backends\Roundcube;
use App\Backends\Storage;
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
    protected $signature = 'status:health
        {--check=* : One of DB, Redis, IMAP, LDAP, Roundcube, Meet, DAV, Mollie, OpenExchangeRates, Storage}';

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
            OpenExchangeRates::healthcheck();
            return true;
        } catch (\Exception $exception) {
            $this->line($exception);
            return false;
        }
    }

    private function checkMollie()
    {
        try {
            return Mollie::healthcheck();
        } catch (\Exception $exception) {
            $this->line($exception);
            return false;
        }
    }

    private function checkDAV()
    {
        try {
            DAV::healthcheck();
            return true;
        } catch (\Exception $exception) {
            $this->line($exception);
            return false;
        }
    }

    private function checkLDAP()
    {
        try {
            LDAP::healthcheck();
            return true;
        } catch (\Exception $exception) {
            $this->line($exception);
            return false;
        }
    }

    private function checkIMAP()
    {
        try {
            IMAP::healthcheck();
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

    private function checkRedis()
    {
        try {
            Redis::connection();
            return true;
        } catch (\Exception $exception) {
            $this->line($exception);
            return false;
        }
    }

    private function checkStorage()
    {
        try {
            Storage::healthcheck();
            return true;
        } catch (\Exception $exception) {
            $this->line($exception);
            return false;
        }
    }

    private function checkMeet()
    {
        $urls = \config('meet.api_urls');
        $success = true;

        foreach ($urls as $url) {
            $this->line("Checking $url");

            try {
                $response = \App\Meet\Service::client($url)->get('ping');
                if (!$response->ok()) {
                    $success = false;
                    $this->line("Backend {$url} not available. Status: " . $response->status());
                }
            } catch (\Exception $exception) {
                $success = false;
                $this->line("Backend {$url} not available. Error: " . $exception->getMessage());
            }
        }

        return $success;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $result = 0;
        $steps = $this->option('check');
        if (empty($steps)) {
            $steps = [
                'DB', 'Redis', 'IMAP', 'Roundcube', 'Meet', 'DAV', 'Mollie', 'OpenExchangeRates'
            ];
            if (\config('app.with_ldap')) {
                array_unshift($steps, 'LDAP');
            }
            if (\config('app.with_imap')) {
                array_unshift($steps, 'IMAP');
            }
            if (\config('app.with_files')) {
                array_unshift($steps, 'Storage');
            }
        }

        foreach ($steps as $step) {
            $func = "check{$step}";

            $this->line("Checking {$step}...");

            if ($this->{$func}()) {
                $this->info("OK");
            } else {
                $this->error("Not found");
                $result = 1;
            }
        }

        return $result;
    }
}

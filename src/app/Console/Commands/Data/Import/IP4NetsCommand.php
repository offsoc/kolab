<?php

namespace App\Console\Commands\Data\Import;

use App\Console\Command;
use Carbon\Carbon;

class IP4NetsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:import:ip4nets';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import IP4 Networks';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $rirs = [
            'afrinic' => 'http://ftp.afrinic.net/stats/afrinic/delegated-afrinic-latest',
            'apnic' => 'http://ftp.apnic.net/apnic/stats/apnic/delegated-apnic-latest',
            'arin' => 'http://ftp.arin.net/pub/stats/arin/delegated-arin-extended-latest',
            'lacnic' => 'http://ftp.lacnic.net/pub/stats/lacnic/delegated-lacnic-latest',
            'ripencc' => 'https://ftp.ripe.net/ripe/stats/delegated-ripencc-latest'
        ];

        $today = Carbon::now()->toDateString();

        foreach ($rirs as $rir => $url) {
            $file = storage_path("{$rir}-{$today}");

            \App\Utils::downloadFile($url, $file);

            $serial = $this->serialFromStatsFile($file);

            if (!$serial) {
                \Log::error("Can not derive serial from {$file}");
                continue;
            }

            $numLines = $this->countLines($file);

            if (!$numLines) {
                \Log::error("No relevant lines could be found in {$file}");
                continue;
            }

            $bar = $this->createProgressBar($numLines, "Importing IPv4 Networks from {$rir}-{$today}");

            $fp = fopen($file, 'r');

            $nets = [];

            while (!feof($fp)) {
                $line = trim(fgets($fp));

                if ($line == "") {
                    continue;
                }

                if ((int)$line) {
                    continue;
                }

                if ($line[0] == "#") {
                    continue;
                }

                $items = explode('|', $line);

                if (sizeof($items) < 7) {
                    continue;
                }

                if ($items[1] == "*" || $items[1] == "" || $items[1] == "ZZ") {
                    continue;
                }

                if ($items[2] != "ipv4") {
                    continue;
                }

                if ($items[5] == "00000000") {
                    $items[5] = "19700102";
                }

                $bar->advance();

                $mask = 32 - log($items[4], 2);
                $broadcast = long2ip((ip2long($items[3]) + 2 ** (32 - $mask)) - 1);

                $net = \App\IP4Net::where(
                    [
                        'net_number' => inet_pton($items[3]),
                        'net_mask' => $mask,
                        'net_broadcast' => inet_pton($broadcast),
                    ]
                )->first();

                if ($net) {
                    if ($net->updated_at > Carbon::now()->subDays(1)) {
                        continue;
                    }

                    // don't use ->update() method because it doesn't update updated_at which we need for expiry
                    $net->rir_name = $rir;
                    $net->country = $items[1];
                    $net->serial = $serial;
                    $net->updated_at = Carbon::now();

                    $net->save();

                    continue;
                }

                $nets[] = [
                    'rir_name' => $rir,
                    'net_number' => inet_pton($items[3]),
                    'net_mask' => $mask,
                    'net_broadcast' => inet_pton($broadcast),
                    'country' => $items[1],
                    'serial' => $serial,
                    'created_at' => Carbon::parse($items[5], 'UTC'),
                    'updated_at' => Carbon::now()
                ];

                if (sizeof($nets) >= 100) {
                    \App\IP4Net::insert($nets);
                    $nets = [];
                }
            }

            if (sizeof($nets) > 0) {
                \App\IP4Net::insert($nets);
                $nets = [];
            }

            $bar->finish();

            $this->info("DONE");
        }

        return 0;
    }

    private function countLines($file)
    {
        $numLines = 0;

        $fh = fopen($file, 'r');

        while (!feof($fh)) {
            $line = trim(fgets($fh));

            $items = explode('|', $line);

            if (sizeof($items) < 3) {
                continue;
            }

            if ($items[2] == "ipv4") {
                $numLines++;
            }
        }

        fclose($fh);

        return $numLines;
    }

    private function serialFromStatsFile($file)
    {
        $serial = null;

        $fh = fopen($file, 'r');

        while (!feof($fh)) {
            $line = trim(fgets($fh));

            $items = explode('|', $line);

            if (sizeof($items) < 2) {
                continue;
            }

            if ((int)$items[2]) {
                $serial = (int)$items[2];
                break;
            }
        }

        fclose($fh);

        return $serial;
    }
}

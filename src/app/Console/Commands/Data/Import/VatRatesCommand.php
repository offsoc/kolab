<?php

namespace App\Console\Commands\Data\Import;

use App\Console\Command;
use App\VatRate;

class VatRatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:import:vat-rates {file} {date}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Loads VAT rates from a file';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $file = $this->argument('file');
        $date = $this->argument('date');

        if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $date)) {
            $this->error("Invalid start date");
            return 1;
        }

        if (!file_exists($file)) {
            $this->error("Invalid file location");
            return 1;
        }

        $rates = json_decode(file_get_contents($file), true);

        if (!is_array($rates) || empty($rates)) {
            $this->error("Invalid or empty input data format");
            return 1;
        }

        $date .= ' 00:00:00';

        foreach ($rates as $country => $rate) {
            if (is_string($country) && strlen($country)) {
                if (strlen($country) != 2) {
                    $this->info("Invalid country code: {$country}");
                    continue;
                }

                if (!is_numeric($rate) || $rate < 0 || $rate > 100) {
                    $this->info("Invalid VAT rate for {$country}: {$rate}");
                    continue;
                }

                $existing = VatRate::where('country', $country)
                    ->where('start', '<=', $date)
                    ->limit(1)
                    ->first();

                if (!$existing || $existing->rate != $rate) {
                    VatRate::create([
                        'start' => $date,
                        'rate' => $rate,
                        'country' => strtoupper($country),
                    ]);

                    $this->info("Added {$country}:{$rate}");
                    continue;
                }
            }

            $this->info("Skipped {$country}:{$rate}");
        }
    }
}

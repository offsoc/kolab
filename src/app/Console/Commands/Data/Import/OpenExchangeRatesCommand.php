<?php

namespace App\Console\Commands\Data\Import;

use App\Backends\OpenExchangeRates;
use App\Console\Command;

class OpenExchangeRatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:import:open-exchange-rates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetches exchangerates from openexchangerates.org';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        foreach (['CHF', 'EUR'] as $sourceCurrency) {
            try {
                $rates = OpenExchangeRates::retrieveRates($sourceCurrency);

                $file = resource_path("exchangerates-{$sourceCurrency}.php");

                $out = "<?php return [\n";

                foreach ($rates as $countryCode => $rate) {
                    $out .= sprintf("  '%s' => '%s',\n", $countryCode, $rate);
                }

                $out .= "];\n";

                file_put_contents($file, $out);
            } catch (\Exception $exception) {
                $this->error("Failed to import {$sourceCurrency}: " . $exception->getMessage());
            }
        }
    }
}

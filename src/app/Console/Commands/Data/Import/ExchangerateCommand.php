<?php

namespace App\Console\Commands\Data\Import;

use App\Console\Command;

class ExchangerateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:import:exchangerates {sourceCurrency}';

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
        $sourceCurrency = strtoupper($this->argument('sourceCurrency'));
        $rates = \App\Backends\OpenExchangeRates::retrieveRates($sourceCurrency);

        //
        // export
        //
        $file = resource_path("exchangerates-$sourceCurrency.php");

        $out = "<?php return [\n";

        foreach ($rates as $countryCode => $rate) {
            $out .= sprintf("  '%s' => '%s',\n", $countryCode, $rate);
        }

        $out .= "];\n";

        file_put_contents($file, $out);
    }
}

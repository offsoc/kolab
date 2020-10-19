<?php

namespace App\Console\Commands\Data\Import;

use App\Console\Command;
use Carbon\Carbon;

class CountriesCommand extends Command
{
    private $currency_fixes = [
        // Country code => currency
        'LT' => 'EUR',
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:import:countries';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetches countries map from country.io';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $today = Carbon::now()->toDateString();

        $countries = [];
        $currencies = [];
        $currencySource = 'http://country.io/currency.json';
        $countrySource = 'http://country.io/names.json';

        //
        // countries
        //
        $file = storage_path("countries-{$today}.json");

        \App\Utils::downloadFile($countrySource, $file);

        $countryJson = file_get_contents($file);

        if (!$countryJson) {
            $this->error("Failed to fetch countries");
            return 1;
        }

        $countries = json_decode($countryJson, true);

        if (!is_array($countries) || empty($countries)) {
            $this->error("Invalid countries data");
            return 1;
        }

        //
        // currencies
        //
        $file = storage_path("currencies-{$today}.json");

        \App\Utils::downloadFile($currencySource, $file);

        // fetch currency table and create an index by country page url
        $currencyJson = file_get_contents($file);

        if (!$currencyJson) {
            $this->error("Failed to fetch currencies");
            return;
        }

        $currencies = json_decode($currencyJson, true);

        if (!is_array($currencies) || empty($currencies)) {
            $this->error("Invalid currencies data");
            return 1;
        }

        //
        // export
        //
        $file = resource_path('countries.php');

        asort($countries);

        $out = "<?php return [\n";

        foreach ($countries as $code => $name) {
            $currency = $currencies[$code] ?? null;

            if (!empty($this->currency_fixes[$code])) {
                $currency = $this->currency_fixes[$code];
            }

            if (!$currency) {
                $this->warn("Unknown currency for {$name} ({$code}). Skipped.");
                continue;
            }

            $out .= sprintf("  '%s' => ['%s','%s'],\n", $code, $currency, addslashes($name));
        }

        $out .= "];\n";

        file_put_contents($file, $out);
    }
}

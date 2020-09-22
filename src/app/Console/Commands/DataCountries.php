<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DataCountries extends Command
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
    protected $signature = 'data:countries';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetches countries map from wikipedia';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $countries = [];
        $currencies = [];
        $currencies_url = 'http://country.io/currency.json';
        $countries_url = 'http://country.io/names.json';

        $this->info("Fetching currencies from $currencies_url...");

        // fetch currency table and create an index by country page url
        $currencies_json = file_get_contents($currencies_url);

        if (!$currencies_json) {
            $this->error("Failed to fetch currencies");
            return;
        }

        $this->info("Fetching countries from $countries_url...");

        $countries_json = file_get_contents($countries_url);

        if (!$countries_json) {
            $this->error("Failed to fetch countries");
            return;
        }

        $currencies = json_decode($currencies_json, true);
        $countries = json_decode($countries_json, true);

        if (!is_array($countries) || empty($countries)) {
            $this->error("Invalid countries data");
            return;
        }

        if (!is_array($currencies) || empty($currencies)) {
            $this->error("Invalid currencies data");
            return;
        }

        $file = resource_path('countries.php');

        $this->info("Generating resource file $file...");

        asort($countries);

        $out = "<?php return [\n";
        foreach ($countries as $code => $name) {
            $currency = $currencies[$code] ?? null;

            if (!empty($this->currency_fixes[$code])) {
                $currency = $this->currency_fixes[$code];
            }

            if (!$currency) {
                $this->error("Unknown currency for {$name} ({$code}). Skipped.");
                continue;
            }

            $out .= sprintf("  '%s' => ['%s','%s'],\n", $code, $currency, addslashes($name));
        }
        $out .= "];\n";

        file_put_contents($file, $out);
    }
}

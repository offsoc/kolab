<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DataCountries extends Command
{
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
        $currencies_url = 'http://en.wikipedia.org/wiki/ISO_4217';
        $countries_url = 'http://en.wikipedia.org/wiki/ISO_3166-1';

        $this->info("Fetching currencies from $currencies_url...");

        // fetch currency table and create an index by country page url
        $page = file_get_contents($currencies_url);

        if (!$page) {
            $this->error("Failed to fetch currencies");
            return;
        }

        $table_regexp = '!<table class="(wikitable|prettytable) sortable".+</table>!ims';
        if (preg_match_all($table_regexp, $page, $matches, PREG_PATTERN_ORDER)) {
            foreach ($matches[0] as $currency_table) {
                preg_match_all('!<tr>\s*<td>(.+)</td>\s*</tr>!Ums', $currency_table, $rows);

                foreach ($rows[1] as $row) {
                    $cells = preg_split('!</td>\s*<td[^>]*>!', $row);

                    if (count($cells) == 5) {
                        // actual currency table
                        $currency = preg_match('/([A-Z]{3})/', $cells[0], $m) ? $m[1] : '';

                        if (preg_match('/(\d+)/', $cells[1], $m)) {
                            $isocode = $m[1];
                            $currencies[$m[1]] = $currency;
                        }

                        preg_match_all('!<a[^>]+href="(/wiki/[^"]+)"[^>]*>!', $cells[4], $links, PREG_PATTERN_ORDER);

                        foreach ($links[1] as $link) {
                            $currencies[strtolower($link)] = $currency;
                        }
                    } elseif (count($cells) == 7) {
                        // replacements table
                        $currency = preg_match('/([A-Z]{3})/', $cells[6], $m) ? $m[1] : '';

                        if (preg_match('/(\d+)/', $cells[1], $m)) {
                            $currencies[$m[1]] = $currency;
                        }
                    }
                }
            }
        }

        $namecol = 0;
        $codecol = 1;
        $numcol = 3;
        $lang = 'en';

        $this->info("Fetching countries from $countries_url...");

        $page = file_get_contents($countries_url);

        if (!$page) {
            $this->error("Failed to fetch countries");
            return;
        }

        if (preg_match($table_regexp, $page, $matches)) {
            preg_match_all('!<tr>\s*<td>(.+)</td>\s*</tr>!Ums', $matches[0], $rows);

            foreach ($rows[1] as $row) {
                $cells = preg_split('!</td>\s*<td[^>]*>!', $row);

                if (count($cells) < 5) {
                    continue;
                }

                $regexp = '!<a[^>]+href="(/wiki/[^"]+)"[^>]*>([^>]+)</a>!i';
                $content = preg_match($regexp, $cells[$namecol], $m) ? $m : null;

                if (preg_match('/>([A-Z]{2})</', $cells[$codecol], $m)) {
                    $code = $m[1];
                } elseif (preg_match('/^([A-Z]{2})/', $cells[$codecol], $m)) {
                    $code = $m[1];
                } else {
                    continue;
                }

                if ($content) {
                    $isocode = preg_match('/(\d+)/', $cells[$numcol], $m) ? $m[1] : '';
                    list(, $link, $name) = $content;
                    $countries[$code][$lang] = $name;

                    if (!empty($currencies[$isocode])) {
                        $countries[$code]['currency'] = $currencies[$isocode];
                    } elseif (!empty($currencies[strtolower($link)])) {
                        $countries[$code]['currency'] = $currencies[strtolower($link)];
                    }
                }
            }
        }

        $file = resource_path('countries.php');

        $this->info("Generating resource file $file...");

        $out = "<?php return [\n";
        foreach ($countries as $code => $names) {
            if (!empty($names['en']) && !empty($names['currency'])) {
                $out .= sprintf("  '%s' => ['%s','%s'],\n", $code, $names['currency'], addslashes($names['en']));
            }
        }
        $out .= "];\n";

        file_put_contents($file, $out);
    }
}

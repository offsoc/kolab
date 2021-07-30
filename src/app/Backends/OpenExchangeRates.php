<?php

namespace App\Backends;

class OpenExchangeRates
{
    /**
     * Import exchange rates from openexchangerates.org
     *
     * @param string $baseCurrency Base currency
     *
     * @return array exchange rates
     */
    public static function retrieveRates($baseCurrency)
    {
        $baseCurrency = strtoupper($baseCurrency);
        $apiKey       = \config('services.openexchangerates.api_key');
        $query        = http_build_query(['app_id' => $apiKey, 'base' => 'USD']);
        $url          = 'https://openexchangerates.org/api/latest.json?' . $query;
        $html         = file_get_contents($url, false);
        $rates        = [];

        if ($html && ($result = json_decode($html, true)) && !empty($result['rates'])) {
            foreach ($result['rates'] as $code => $rate) {
                $rates[strtoupper($code)] = $rate;
            }

            if ($baseCurrency != 'USD') {
                if ($base = $rates[$baseCurrency]) {
                    foreach ($rates as $code => $rate) {
                        $rates[$code] = $rate / $base;
                    }
                } else {
                    $rates = [];
                }
            }

            foreach ($rates as $code => $rate) {
                \Log::debug(sprintf("Update %s: %0.8f", $code, $rate));
            }
        } else {
            throw new \Exception("Failed to parse exchange rates");
        }

        if (count($rates) > 1) {
            $rates[$baseCurrency] = 1;
            return $rates;
        }

        throw new \Exception("Failed to retrieve exchange rates");
    }
}

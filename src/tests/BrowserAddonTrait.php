<?php

namespace Tests;

use App\Wallet;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Laravel\Dusk\Chrome\SupportsChrome;
use Mollie\Laravel\Facades\Mollie;
use Tests\Browser\Pages\PaymentMollie;

trait BrowserAddonTrait
{
    use SupportsChrome;

    protected static $browser;

    /**
     * Initialize and start Chrome driver and browser
     *
     * @returns Browser The browser
     */
    public static function startBrowser(): Browser
    {
        $driver = retry(5, function () {
            return self::driver();
        }, 50);

        self::$browser = new Browser($driver);

        $screenshots_dir = __DIR__ . '/Browser/screenshots/';
        Browser::$storeScreenshotsAt = $screenshots_dir;
        if (!file_exists($screenshots_dir)) {
            mkdir($screenshots_dir, 0o777, true);
        }

        return self::$browser;
    }

    /**
     * (Automatically) stop the browser and driver process
     *
     * @afterClass
     */
    public static function stopBrowser(): void
    {
        if (self::$browser) {
            self::$browser->quit();
            static::stopChromeDriver();
            self::$browser = null;
        }
    }

    /**
     * Initialize and start Chrome driver
     */
    protected static function driver()
    {
        static::startChromeDriver(['--port=9515']);

        $options = (new ChromeOptions())->addArguments([
            '--lang=en_US',
            '--disable-gpu',
            '--headless',
            '--no-sandbox',
        ]);

        return RemoteWebDriver::create(
            'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY,
                $options
            )
        );
    }

    /**
     * Register an "after class" tear down callback.
     */
    public static function afterClass(\Closure $callback): void
    {
        // This method is required by SupportsChrome trait
    }

    /**
     * Create Mollie's auto-payment mandate using our API and Chrome browser
     */
    public function createMollieMandate(Wallet $wallet, array $params)
    {
        $wallet->setSetting('mollie_mandate_id', null);

        // Use the API to create a first payment with a mandate
        $response = $this->actingAs($wallet->owner)->post("api/v4/payments/mandate", $params);
        $response->assertStatus(200);
        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertMatchesRegularExpression('|^https://www.mollie.com|', $json['redirectUrl']);

        // There's no easy way to confirm a created mandate.
        // The only way seems to be to fire up Chrome on checkout page
        // and do actions with use of Dusk browser.
        $this->startBrowser()
            ->visit($json['redirectUrl'])
            ->on(new PaymentMollie())
            ->submitPayment('paid');
        $this->stopBrowser();

        // Because of https://github.com/mollie/mollie-api-php/issues/649 mandate does not
        // exist until payment is paid. As we do not expect a webhook to be handled, we
        // manually get the mandate ID from Mollie.
        if (!$wallet->getSetting('mollie_mandate_id')) {
            $mollie_payment = Mollie::api()->payments->get($json['id']);

            $this->assertTrue(!empty($mollie_payment->mandateId));
            $wallet->setSetting('mollie_mandate_id', $mollie_payment->mandateId);
            $json['mandateId'] = $mollie_payment->mandateId;
        }

        return $json;
    }
}

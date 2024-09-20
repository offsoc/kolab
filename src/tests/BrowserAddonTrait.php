<?php

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Laravel\Dusk\Chrome\SupportsChrome;

trait BrowserAddonTrait
{
    use SupportsChrome;

    protected $browser;


    /**
     * Initialize and start Chrome driver and browser
     *
     * @returns Browser The browser
     */
    protected function startBrowser(): Browser
    {
        $driver = retry(5, function () {
            return $this->driver();
        }, 50);

        $this->browser = new Browser($driver);

        $screenshots_dir = __DIR__ . '/Browser/screenshots/';
        Browser::$storeScreenshotsAt = $screenshots_dir;
        if (!file_exists($screenshots_dir)) {
            mkdir($screenshots_dir, 0777, true);
        }

        return $this->browser;
    }

    /**
     * (Automatically) stop the browser and driver process
     *
     * @afterClass
     */
    protected function stopBrowser(): void
    {
        if ($this->browser) {
            $this->browser->quit();
            static::stopChromeDriver();
            $this->browser = null;
        }
    }

    /**
     * Initialize and start Chrome driver
     */
    protected function driver()
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
     *
     * @param \Closure $callback
     */
    public static function afterClass(\Closure $callback): void
    {
        // This method is required by SupportsChrome trait
    }
}

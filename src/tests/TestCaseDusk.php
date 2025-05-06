<?php

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Laravel\Dusk\TestCase as BaseTestCase;

abstract class TestCaseDusk extends BaseTestCase
{
    use TestCaseTrait;

    /**
     * Prepare for Dusk test execution.
     *
     * @beforeClass
     */
    public static function prepare()
    {
        static::startChromeDriver([
            // For troubleshooting
            // '--verbose',
            // '--log-path=/tmp/chromedriver.log',
            '--port=9515',
        ]);
    }

    /**
     * Tear down the Dusk test case class.
     *
     * Note: This is copied here from Dusk's ProvidesBrowser trait to properly
     * close Chrome when using Dusk >= 8 with PHPUnit v9. It can be removed
     * when we switch to PHPUnit v10.
     *
     * @afterClass
     */
    public static function tearDownDuskClass()
    {
        static::closeAll();

        foreach (static::$afterClassCallbacks as $callback) {
            $callback();
        }
    }

    /**
     * Create the RemoteWebDriver instance.
     *
     * @return RemoteWebDriver
     */
    protected function driver()
    {
        $download_dir = __DIR__ . '/Browser/downloads';

        $options = (new ChromeOptions())->addArguments([
            '--lang=en_US',
            '--disable-gpu',
            '--headless=new',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--disable-web-security',
            '--use-fake-ui-for-media-stream',
            '--use-fake-device-for-media-stream',
            '--enable-usermedia-screen-capturing',
            // '--auto-select-desktop-capture-source="Entire screen"',
            '--ignore-certificate-errors',
            '--incognito',
        ]);

        // For file download handling
        $prefs = [
            'profile.default_content_settings.popups' => 0,
            'download.default_directory' => $download_dir,
            // 'download.prompt_for_download' => false,
            // 'download.directory_upgrade' => true,
        ];

        $options->setExperimentalOption('prefs', $prefs);

        if (getenv('TESTS_MODE') == 'phone') {
            // Fake User-Agent string for mobile mode
            $ua = 'Mozilla/5.0 (Linux; Android 4.0.4; Galaxy Nexus Build/IMM76B) AppleWebKit/537.36'
                . ' (KHTML, like Gecko) Chrome/60.0.3112.90 Mobile Safari/537.36';
            $options->setExperimentalOption('mobileEmulation', ['userAgent' => $ua]);
            $options->addArguments(['--window-size=375,667']);
        } elseif (getenv('TESTS_MODE') == 'tablet') {
            // Fake User-Agent string for tablet mode
            $ua = 'Mozilla/5.0 (Linux; Android 6.0.1; vivo 1603 Build/MMB29M) AppleWebKit/537.36 '
                . ' (KHTML, like Gecko) Chrome/58.0.3029.83 Mobile Safari/537.36';
            $options->setExperimentalOption('mobileEmulation', ['userAgent' => $ua]);
            $options->addArguments(['--window-size=800,640']);
        } else {
            $options->addArguments(['--window-size=1280,1024']);
        }

        // Make sure downloads dir exists and is empty
        if (!file_exists($download_dir)) {
            mkdir($download_dir, 0o777, true);
        } else {
            foreach (glob("{$download_dir}/*") as $file) {
                @unlink($file);
            }
        }

        $driver = RemoteWebDriver::create(
            'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(ChromeOptions::CAPABILITY, $options)
        );

        // Note: Looks like this is the only way to set the downloads directory, options above do not work anymore
        $result = $driver->executeCustomCommand(
            '/session/:sessionId/chromium/send_command',
            'POST',
            ['cmd' => 'Page.setDownloadBehavior', 'params' => ['behavior' => 'allow', 'downloadPath' => $download_dir]]
        );

        return $driver;
    }

    /**
     * Replace Dusk's Browser with our (extended) Browser
     */
    protected function newBrowser($driver)
    {
        return new Browser($driver);
    }

    /**
     * Set baseURL to the admin UI location
     */
    protected static function useAdminUrl(): void
    {
        // This will set baseURL for all tests in this file
        // If we wanted to visit both user and admin in one test
        // we can also just call visit() with full url
        Browser::$baseUrl = str_replace('//', '//admin.', \config('app.url'));
    }

    /**
     * Set baseURL to the reseller UI location
     */
    protected static function useResellerUrl(): void
    {
        // This will set baseURL for all tests in this file
        // If we wanted to visit both user and admin in one test
        // we can also just call visit() with full url
        Browser::$baseUrl = str_replace('//', '//reseller.', \config('app.url'));
    }
}

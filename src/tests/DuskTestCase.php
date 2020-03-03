<?php

namespace Tests;

use App\Domain;
use App\User;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Illuminate\Support\Facades\Queue;
use Laravel\Dusk\TestCase as BaseTestCase;

abstract class DuskTestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function deleteTestDomain($name)
    {
        Queue::fake();
        $domain = Domain::withTrashed()->where('namespace', $name)->first();
        if (!$domain) {
            return;
        }

        $job = new \App\Jobs\DomainDelete($domain);
        $job->handle();

        $domain->forceDelete();
    }

    protected function deleteTestUser($email)
    {
        Queue::fake();
        $user = User::withTrashed()->where('email', $email)->first();

        if (!$user) {
            return;
        }

        $job = new \App\Jobs\UserDelete($user->id);
        $job->handle();

        $user->forceDelete();
    }

    /**
     * Get Domain object by namespace, create it if needed.
     * Skip LDAP jobs.
     */
    protected function getTestDomain($name, $attrib = [])
    {
        // Disable jobs (i.e. skip LDAP oprations)
        Queue::fake();
        return Domain::firstOrCreate(['namespace' => $name], $attrib);
    }

    /**
     * Get User object by email, create it if needed.
     * Skip LDAP jobs.
     */
    protected function getTestUser($email, $attrib = [])
    {
        // Disable jobs (i.e. skip LDAP oprations)
        Queue::fake();
        return User::firstOrCreate(['email' => $email], $attrib);
    }

    /**
     * Prepare for Dusk test execution.
     *
     * @beforeClass
     * @return void
     */
    public static function prepare()
    {
        static::startChromeDriver();
    }

    /**
     * Create the RemoteWebDriver instance.
     *
     * @return \Facebook\WebDriver\Remote\RemoteWebDriver
     */
    protected function driver()
    {
        $options = (new ChromeOptions())->addArguments([
            '--lang=en_US',
            '--disable-gpu',
            '--headless',
            '--window-size=1280,720',
        ]);

        // For file download handling
        $prefs = [
            'profile.default_content_settings.popups' => 0,
            'download.default_directory' => __DIR__ . '/Browser/downloads',
        ];

        $options->setExperimentalOption('prefs', $prefs);

        if (getenv('TESTS_MODE') == 'phone') {
            // Fake User-Agent string for mobile mode
            $ua = 'Mozilla/5.0 (Linux; Android 4.0.4; Galaxy Nexus Build/IMM76B) AppleWebKit/537.36'
                . ' (KHTML, like Gecko) Chrome/60.0.3112.90 Mobile Safari/537.36';
            $options->setExperimentalOption('mobileEmulation', ['userAgent' => $ua]);
            $options->addArguments(['--window-size=375,667']);
        } elseif (getenv('TESTS_MODE') == 'tablet') {
            // Fake User-Agent string for mobile mode
            $ua = 'Mozilla/5.0 (Linux; Android 6.0.1; vivo 1603 Build/MMB29M) AppleWebKit/537.36 '
                . ' (KHTML, like Gecko) Chrome/58.0.3029.83 Mobile Safari/537.36';
            $options->setExperimentalOption('mobileEmulation', ['userAgent' => $ua]);
            $options->addArguments(['--window-size=800,640']);
        } else {
            $options->addArguments(['--window-size=1280,720']);
        }

        // Make sure downloads dir exists and is empty
        if (!file_exists(__DIR__ . '/Browser/downloads')) {
            mkdir(__DIR__ . '/Browser/downloads', 0777, true);
        } else {
            foreach (glob(__DIR__ . '/Browser/downloads/*') as $file) {
                @unlink($file);
            }
        }

        return RemoteWebDriver::create(
            'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY,
                $options
            )
        );
    }

    /**
     * Replace Dusk's Browser with our (extended) Browser
     */
    protected function newBrowser($driver)
    {
        return new Browser($driver);
    }
}

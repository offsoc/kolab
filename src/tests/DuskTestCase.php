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
            '--disable-gpu',
            '--headless',
            '--window-size=1280,720',
        ]);

        return RemoteWebDriver::create(
            'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY,
                $options
            )
        );
    }
}

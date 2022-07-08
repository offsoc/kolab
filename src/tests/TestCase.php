<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;

abstract class TestCase extends BaseTestCase
{
    use TestCaseTrait;

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        // Disable throttling
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    /**
     * Set baseURL to the regular UI location
     */
    protected static function useRegularUrl(): void
    {
        // This will set base URL for all tests in a file.
        // If we wanted to access both user and admin in one test
        // we can also just call post/get/whatever with full url
        \config(
            [
                'app.url' => str_replace(
                    ['//admin.', '//reseller.', '//services.'],
                    ['//', '//', '//'],
                    \config('app.url')
                )
            ]
        );

        url()->forceRootUrl(config('app.url'));
    }

    /**
     * Set baseURL to the admin UI location
     */
    protected static function useAdminUrl(): void
    {
        // This will set base URL for all tests in a file.
        // If we wanted to access both user and admin in one test
        // we can also just call post/get/whatever with full url

        // reset to base
        self::useRegularUrl();

        // then modify it
        \config(['app.url' => str_replace('//', '//admin.', \config('app.url'))]);
        url()->forceRootUrl(config('app.url'));
    }

    /**
     * Set baseURL to the reseller UI location
     */
    protected static function useResellerUrl(): void
    {
        // This will set base URL for all tests in a file.
        // If we wanted to access both user and admin in one test
        // we can also just call post/get/whatever with full url

        // reset to base
        self::useRegularUrl();

        // then modify it
        \config(['app.url' => str_replace('//', '//reseller.', \config('app.url'))]);
        url()->forceRootUrl(config('app.url'));
    }

    /**
     * Set baseURL to the services location
     */
    protected static function useServicesUrl(): void
    {
        // This will set base URL for all tests in a file.
        // If we wanted to access both user and admin in one test
        // we can also just call post/get/whatever with full url

        // reset to base
        self::useRegularUrl();

        // then modify it
        \config(['app.url' => str_replace('//', '//services.', \config('app.url'))]);
        url()->forceRootUrl(config('app.url'));
    }
}

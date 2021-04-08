<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use TestCaseTrait;
    use TestCaseMeetTrait;

    /**
     * Set baseURL to the admin UI location
     */
    protected static function useAdminUrl(): void
    {
        // This will set base URL for all tests in a file.
        // If we wanted to access both user and admin in one test
        // we can also just call post/get/whatever with full url
        \config(['app.url' => str_replace('//', '//admin.', \config('app.url'))]);
        url()->forceRootUrl(config('app.url'));
    }

    /**
     * Set baseURL to the normal location
     */
    protected static function useServicesUrl(): void
    {
        // This will set base URL for all tests in a file.
        \config(['app.url' => 'https://' . \config('app.domain'))]);
        url()->forceRootUrl(config('app.url'));
    }

    /**
     * Set baseURL to the services location
     */
    protected static function useServicesUrl(): void
    {
        // This will set base URL for all tests in a file.
        \config(['app.url' => str_replace('//', '//services.', \config('app.url'))]);
        url()->forceRootUrl(config('app.url'));
    }
}

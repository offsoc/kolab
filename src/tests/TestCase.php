<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use TestCaseTrait;

    protected function backdateEntitlements($entitlements, $targetDate)
    {
        foreach ($entitlements as $entitlement) {
            $entitlement->created_at = $targetDate;
            $entitlement->updated_at = $targetDate;
            $entitlement->save();
        }
    }

    /**
     * Set baseURL to the admin UI location
     */
    protected static function useAdminUrl(): void
    {
        // This will set base URL for all tests in a file.
        // If we wanted to access both user and admin in one test
        // we can also just call post/get/whatever with full url
        \config(['app.url' => str_replace('//', '//admin.', \config('app.url'))]);
        url()->forceRootUrl(config('app.url')); // @phpstan-ignore-line
    }
}

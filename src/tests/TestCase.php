<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;

abstract class TestCase extends BaseTestCase
{
    use TestCaseTrait;
    use TestCaseMeetTrait;

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        // Disable throttling
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    protected function backdateEntitlements($entitlements, $targetDate)
    {
        $wallets = [];
        $ids = [];

        foreach ($entitlements as $entitlement) {
            $ids[] = $entitlement->id;
            $wallets[] = $entitlement->wallet_id;
        }

        \App\Entitlement::whereIn('id', $ids)->update([
                'created_at' => $targetDate,
                'updated_at' => $targetDate,
        ]);

        if (!empty($wallets)) {
            $wallets = array_unique($wallets);
            $owners = \App\Wallet::whereIn('id', $wallets)->pluck('user_id')->all();

            \App\User::whereIn('id', $owners)->update(['created_at' => $targetDate]);
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
        \config(['app.url' => str_replace('//', '//reseller.', \config('app.url'))]);
        url()->forceRootUrl(config('app.url'));
    }
}

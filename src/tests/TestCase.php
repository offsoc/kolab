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
}

<?php

namespace Tests\Feature;

use App\Domain;
use App\Entitlement;
use App\Handlers;
use App\Sku;
use App\User;
use Tests\TestCase;

class SkuTest extends TestCase
{

    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('sku-test-user@custom-domain.com');
        $this->deleteTestDomain('custom-domain.com');
    }

    public function tearDown(): void
    {
        $this->deleteTestUser('sku-test-user@custom-domain.com');
        $this->deleteTestDomain('custom-domain.com');

        parent::tearDown();
    }

    /**
     * Tests for Sku::registerEntitlements()
     */
    public function testRegisterEntitlement(): void
    {
        // TODO: This test depends on seeded SKUs, but probably should not
        $domain = $this->getTestDomain(
            'custom-domain.com',
            [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_EXTERNAL,
            ]
        );

        \Log::debug(var_export($domain->toArray(), true));

        $user = $this->getTestUser('sku-test-user@custom-domain.com');
        $wallet = $user->wallets()->first();

        // \App\Handlers\Mailbox SKU
        // Note, we're testing mailbox SKU before domain SKU as it may potentially fail in that
        // order
        $sku = Sku::where('title', 'mailbox')->first();
        Entitlement::create(
            [
                'owner_id' => $user->id,
                'wallet_id' => $wallet->id,
                'sku_id' => $sku->id,
                'entitleable_id' => $user->id,
                'entitleable_type' => User::class
            ]
        );

        $entitlements = $sku->entitlements()->where('owner_id', $user->id)->get();
        $wallet->refresh();

        if ($sku->active) {
            $balance = -$sku->cost;
            $this->assertCount(1, $entitlements);
            $this->assertEquals($user->id, $entitlements[0]->entitleable_id);
            $this->assertSame(
                Handlers\Mailbox::entitleableClass(),
                $entitlements[0]->entitleable_type
            );
        } else {
            $this->assertCount(0, $entitlements);
        }

        $this->assertEquals($balance, $wallet->balance);

        // \App\Handlers\Domain SKU
        $sku = Sku::where('title', 'domain')->first();
        Entitlement::create(
            [
                'owner_id' => $user->id,
                'wallet_id' => $wallet->id,
                'sku_id' => $sku->id,
                'entitleable_id' => $domain->id,
                'entitleable_type' => Domain::class
            ]
        );

        $entitlements = $sku->entitlements->where('owner_id', $user->id);

        foreach ($entitlements as $entitlement) {
            \Log::debug(var_export($entitlement->toArray(), true));
        }

        $wallet->refresh();

        if ($sku->active) {
            $balance -= $sku->cost;
            $this->assertCount(1, $entitlements);

            $_domain = Domain::find($entitlements->first()->entitleable_id);

            $this->assertEquals(
                $domain->id,
                $entitlements->first()->entitleable_id,
                var_export($_domain->toArray(), true)
            );

            $this->assertSame(
                Handlers\Domain::entitleableClass(),
                $entitlements->first()->entitleable_type
            );
        } else {
            $this->assertCount(0, $entitlements);
        }

        $this->assertEquals($balance, $wallet->balance);

        // \App\Handlers\DomainRegistration SKU
        $sku = Sku::where('title', 'domain-registration')->first();
        Entitlement::create(
            [
                'owner_id' => $user->id,
                'wallet_id' => $user->wallets()->get()[0]->id,
                'sku_id' => $sku->id,
                'entitleable_id' => $domain->id,
                'entitleable_type' => Domain::class
            ]
        );

        $entitlements = $sku->entitlements->where('owner_id', $user->id);
        $wallet->refresh();

        if ($sku->active) {
            $balance -= $sku->cost;
            $this->assertCount(1, $entitlements);
            $this->assertEquals($domain->id, $entitlements->first()->entitleable_id);
            $this->assertSame(
                Handlers\DomainRegistration::entitleableClass(),
                $entitlements->first()->entitleable_type
            );
        } else {
            $this->assertCount(0, $entitlements);
        }

        $this->assertEquals($balance, $wallet->balance);

        // \App\Handlers\DomainHosting SKU
        $sku = Sku::where('title', 'domain-hosting')->first();
        Entitlement::create(
            [
                'owner_id' => $user->id,
                'wallet_id' => $wallet->id,
                'sku_id' => $sku->id,
                'entitleable_id' => $domain->id,
                'entitleable_type' => Domain::class
            ]
        );

        $entitlements = $sku->entitlements->where('owner_id', $user->id);
        $wallet->refresh();

        if ($sku->active) {
            $balance -= $sku->cost;
            $this->assertCount(1, $entitlements);
            $this->assertEquals($domain->id, $entitlements->first()->entitleable_id);
            $this->assertSame(
                Handlers\DomainHosting::entitleableClass(),
                $entitlements->first()->entitleable_type
            );
        } else {
            $this->assertCount(0, $entitlements);
        }

        $this->assertEquals($balance, $wallet->balance);

        // \App\Handlers\Groupware SKU
        $sku = Sku::where('title', 'groupware')->first();
        Entitlement::create(
            [
                'owner_id' => $user->id,
                'wallet_id' => $user->wallets()->get()[0]->id,
                'sku_id' => $sku->id,
                'entitleable_id' => $user->id,
                'entitleable_type' => User::class
            ]
        );

        $entitlements = $sku->entitlements->where('owner_id', $user->id);
        $wallet->refresh();

        if ($sku->active) {
            $balance -= $sku->cost;
            $this->assertCount(1, $entitlements);
            $this->assertEquals($user->id, $entitlements->first()->entitleable_id);
            $this->assertSame(
                Handlers\Mailbox::entitleableClass(),
                $entitlements->first()->entitleable_type
            );
        } else {
            $this->assertCount(0, $entitlements);
        }

        $this->assertEquals($balance, $wallet->balance);

        // \App\Handlers\Storage SKU
        $sku = Sku::where('title', 'storage')->first();
        Entitlement::create(
            [
                'owner_id' => $user->id,
                'wallet_id' => $wallet->id,
                'sku_id' => $sku->id,
                'entitleable_id' => $user->id,
                'entitleable_type' => User::class
            ]
        );

        $entitlements = $sku->entitlements->where('owner_id', $user->id);
        $wallet->refresh();

        if ($sku->active) {
            $balance -= $sku->cost;
            $this->assertCount(1, $entitlements);
        } else {
            $this->assertCount(0, $entitlements);
        }

        $this->assertEquals($balance, $wallet->balance);
    }

    public function testSkuPackages(): void
    {
        $sku = Sku::where('title', 'mailbox')->first();

        $packages = $sku->packages;

        $this->assertCount(2, $packages);
    }
}

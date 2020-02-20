<?php

namespace Tests\Feature;

use App\Domain;
use App\Entitlement;
use App\Sku;
use App\User;
use Tests\TestCase;

class EntitlementTest extends TestCase
{

    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('entitlement-test@kolabnow.com');
        $this->deleteTestUser('entitled-user@custom-domain.com');
    }

    public function tearDown(): void
    {
        $this->deleteTestUser('entitlement-test@kolabnow.com');
        $this->deleteTestUser('entitled-user@custom-domain.com');

        parent::tearDown();
    }

    /**
     * Tests for User::AddEntitlement()
     */
    public function testUserAddEntitlement(): void
    {
        $sku_domain = Sku::firstOrCreate(['title' => 'domain']);
        $sku_mailbox = Sku::firstOrCreate(['title' => 'mailbox']);
        $owner = $this->getTestUser('entitlement-test@kolabnow.com');
        $user = $this->getTestUser('entitled-user@custom-domain.com');
        $domain = $this->getTestDomain(
            'custom-domain.com',
            [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_EXTERNAL,
            ]
        );

        $wallet = $owner->wallets()->first();

        $entitlement_own_mailbox = new Entitlement(
            [
                'owner_id' => $owner->id,
                'entitleable_id' => $owner->id,
                'entitleable_type' => User::class,
                'wallet_id' => $wallet->id,
                'sku_id' => $sku_mailbox->id,
                'description' => "Owner Mailbox Entitlement Test"
            ]
        );

        $entitlement_domain = new Entitlement(
            [
                'owner_id' => $owner->id,
                'entitleable_id' => $domain->id,
                'entitleable_type' => Domain::class,
                'wallet_id' => $wallet->id,
                'sku_id' => $sku_domain->id,
                'description' => "User Domain Entitlement Test"
            ]
        );

        $entitlement_mailbox = new Entitlement(
            [
                'owner_id' => $owner->id,
                'entitleable_id' => $user->id,
                'entitleable_type' => User::class,
                'wallet_id' => $wallet->id,
                'sku_id' => $sku_mailbox->id,
                'description' => "User Mailbox Entitlement Test"
            ]
        );

        $owner->addEntitlement($entitlement_own_mailbox);
        $owner->addEntitlement($entitlement_domain);
        $owner->addEntitlement($entitlement_mailbox);

        $this->assertTrue($owner->entitlements()->count() == 3);
        $this->assertTrue($sku_domain->entitlements()->where('owner_id', $owner->id)->count() == 1);
        $this->assertTrue($sku_mailbox->entitlements()->where('owner_id', $owner->id)->count() == 2);
        $this->assertTrue($wallet->entitlements()->count() == 3);
        $this->assertTrue($wallet->fresh()->balance < 0.00);
    }

    public function testAddExistingEntitlement(): void
    {
        $this->markTestIncomplete();
    }

    public function testEntitlementFunctions(): void
    {
        $user = $this->getTestUser('entitlement-test@kolabnow.com');

        $package = \App\Package::where('title', 'kolab')->first();

        $user->assignPackage($package);

        $wallet = $user->wallets()->first();
        $this->assertNotNull($wallet);

        $sku = \App\Sku::where('title', 'mailbox')->first();
        $this->assertNotNull($sku);

        $entitlement = Entitlement::where('owner_id', $user->id)->where('sku_id', $sku->id)->first();
        $this->assertNotNull($entitlement);

        $e_sku = $entitlement->sku;
        $this->assertSame($sku->id, $e_sku->id);

        $e_wallet = $entitlement->wallet;
        $this->assertSame($wallet->id, $e_wallet->id);

        $e_owner = $entitlement->owner;
        $this->assertSame($user->id, $e_owner->id);

        $e_entitleable = $entitlement->entitleable;
        $this->assertSame($user->id, $e_entitleable->id);
        $this->assertTrue($e_entitleable instanceof \App\User);
    }
}

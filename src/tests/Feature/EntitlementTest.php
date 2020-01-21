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

        User::where('email', 'entitlement-test@kolabnow.com')
            ->orWhere('email', 'entitled-user@custom-domain.com')
            ->delete();
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

        $this->assertTrue($owner->id != $user->id);

        $wallets = $owner->wallets()->get();

        $entitlement_own_mailbox = new Entitlement(
            [
                'owner_id' => $owner->id,
                'entitleable_id' => $owner->id,
                'entitleable_type' => User::class,
                'wallet_id' => $wallets[0]->id,
                'sku_id' => $sku_mailbox->id,
                'description' => "Owner Mailbox Entitlement Test"
            ]
        );

        $entitlement_domain = new Entitlement(
            [
                'owner_id' => $owner->id,
                'entitleable_id' => $domain->id,
                'entitleable_type' => Domain::class,
                'wallet_id' => $wallets[0]->id,
                'sku_id' => $sku_domain->id,
                'description' => "User Domain Entitlement Test"
            ]
        );

        $entitlement_mailbox = new Entitlement(
            [
                'owner_id' => $owner->id,
                'entitleable_id' => $user->id,
                'entitleable_type' => User::class,
                'wallet_id' => $wallets[0]->id,
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
        $this->assertTrue($wallets[0]->entitlements()->count() == 3);
        $this->assertTrue($wallets[0]->fresh()->balance < 0.00);

        // TODO: Test case of adding entitlement that already exists
    }
}

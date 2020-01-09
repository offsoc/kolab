<?php

namespace Tests\Feature;

use App\Domain;
use App\Entitlement;
use App\Sku;
use App\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EntitlementTest extends TestCase
{

    public function setUp(): void
    {
        parent::setUp();

        $owner = User::firstOrCreate(
            ['email' => 'entitlement-test@kolabnow.com']
        );

        $user = User::firstOrCreate(
            ['email' => 'entitled-user@custom-domain.com']
        );

        $entitlement = Entitlement::firstOrCreate(
            [
                'owner_id' => $owner->id,
                'user_id' => $user->id
            ]
        );

        $entitlement->delete();
        $user->delete();
        $owner->delete();
    }

    public function testUserAddEntitlement()
    {
        $sku_domain = Sku::firstOrCreate(
            ['title' => 'domain']
        );

        $sku_mailbox = Sku::firstOrCreate(
            ['title' => 'mailbox']
        );

        $owner = User::firstOrCreate(
            ['email' => 'entitlement-test@kolabnow.com']
        );

        $user = User::firstOrCreate(
            ['email' => 'entitled-user@custom-domain.com']
        );

        $this->assertTrue($owner->id != $user->id);

        $wallets = $owner->wallets()->get();

        $domain = Domain::firstOrCreate(
            [
                'namespace' => 'custom-domain.com',
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_EXTERNAL,
            ]
        );

        $entitlement_own_mailbox = Entitlement::firstOrCreate(
            [
                'owner_id' => $owner->id,
                'entitleable_id' => $owner->id,
                'entitleable_type' => User::class,
                'wallet_id' => $wallets[0]->id,
                'sku_id' => $sku_mailbox->id,
                'description' => "Owner Mailbox Entitlement Test"
            ]
        );

        $entitlement_domain = Entitlement::firstOrCreate(
            [
                'owner_id' => $owner->id,
                'entitleable_id' => $domain->id,
                'entitleable_type' => Domain::class,
                'wallet_id' => $wallets[0]->id,
                'sku_id' => $sku_domain->id,
                'description' => "User Domain Entitlement Test"
            ]
        );

        $entitlement_mailbox = Entitlement::firstOrCreate(
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
        $this->assertTrue($sku_domain->entitlements()->count() == 2);
        $this->assertTrue($sku_mailbox->entitlements()->count() == 3);
        $this->assertTrue($wallets[0]->entitlements()->count() == 3);
        $this->assertTrue($wallets[0]->fresh()->balance < 0.00);
    }
}

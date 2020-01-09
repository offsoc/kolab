<?php

namespace Tests\Feature;

use App\Domain;
use App\Entitlement;
use App\Handlers;
use App\Quota;
use App\Sku;
use App\User;
use Tests\TestCase;

class SkuTest extends TestCase
{

    public function setUp(): void
    {
        parent::setUp();

        Domain::firstOrCreate(
            [
                'namespace' => 'custom-domain.com',
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_EXTERNAL,
            ]
        );
        User::firstOrCreate(
            ['email' => 'sku-test-user@custom-domain.com']
        );
    }

    public function tearDown(): void
    {
        User::where('email', 'sku-test-user@custom-domain.com')->delete();
        Domain::where('namespace', 'custom-domain.com')->delete();
    }

    /**
     * Tests for Sku::registerEntitlements()
     */
    public function testRegisterEntitlement(): void
    {
        // TODO: This test depends on seeded SKUs, but probably should not

        $user = User::where('email', 'sku-test-user@custom-domain.com')->first();
        $domain = Domain::where('namespace', 'custom-domain.com')->first();
        $wallet = $user->wallets()->first();

        // \App\Handlers\Mailbox SKU
        // Note, we're testing mailbox SKU before domain SKU as it may potentially fail in that order
        $sku = Sku::where('title', 'mailbox')->first();
        $sku->registerEntitlement($user);

        $entitlements = $sku->entitlements()->where('owner_id', $user->id)->get();
        $wallet->refresh();

        if ($sku->active) {
            $balance = -$sku->cost;
            $this->assertCount(1, $entitlements);
            $this->assertSame($user->id, $entitlements[0]->entitleable_id);
            $this->assertSame(Handlers\Mailbox::entitleableClass(), $entitlements[0]->entitleable_type);
        } else {
            $this->assertCount(0, $entitlements);
        }

        $this->assertEquals($balance, $wallet->balance);

        // \App\Handlers\Domain SKU
        $sku = Sku::where('title', 'domain')->first();
        $sku->registerEntitlement($user, [$domain]);

        $entitlements = $sku->entitlements()->where('owner_id', $user->id)->get();
        $wallet->refresh();

        if ($sku->active) {
            $balance -= $sku->cost;
            $this->assertCount(1, $entitlements);
            $this->assertSame($domain->id, $entitlements[0]->entitleable_id);
            $this->assertSame(Handlers\Domain::entitleableClass(), $entitlements[0]->entitleable_type);
        } else {
            $this->assertCount(0, $entitlements);
        }

        $this->assertEquals($balance, $wallet->balance);

        // \App\Handlers\DomainRegistration SKU
        $sku = Sku::where('title', 'domain-registration')->first();
        $sku->registerEntitlement($user, [$domain]);

        $entitlements = $sku->entitlements()->where('owner_id', $user->id)->get();
        $wallet->refresh();

        if ($sku->active) {
            $balance -= $sku->cost;
            $this->assertCount(1, $entitlements);
            $this->assertSame($domain->id, $entitlements[0]->entitleable_id);
            $this->assertSame(Handlers\DomainRegistration::entitleableClass(), $entitlements[0]->entitleable_type);
        } else {
            $this->assertCount(0, $entitlements);
        }

        $this->assertEquals($balance, $wallet->balance);

        // \App\Handlers\DomainHosting SKU
        $sku = Sku::where('title', 'domain-hosting')->first();
        $sku->registerEntitlement($user, [$domain]);

        $entitlements = $sku->entitlements()->where('owner_id', $user->id)->get();
        $wallet->refresh();

        if ($sku->active) {
            $balance -= $sku->cost;
            $this->assertCount(1, $entitlements);
            $this->assertSame($domain->id, $entitlements[0]->entitleable_id);
            $this->assertSame(Handlers\DomainHosting::entitleableClass(), $entitlements[0]->entitleable_type);
        } else {
            $this->assertCount(0, $entitlements);
        }

        $this->assertEquals($balance, $wallet->balance);

        // \App\Handlers\Groupware SKU
        $sku = Sku::where('title', 'groupware')->first();
        $sku->registerEntitlement($user, [$domain]);

        $entitlements = $sku->entitlements()->where('owner_id', $user->id)->get();
        $wallet->refresh();

        if ($sku->active) {
            $balance -= $sku->cost;
            $this->assertCount(1, $entitlements);
            $this->assertSame($user->id, $entitlements[0]->entitleable_id);
            $this->assertSame(Handlers\Mailbox::entitleableClass(), $entitlements[0]->entitleable_type);
        } else {
            $this->assertCount(0, $entitlements);
        }

        $this->assertEquals($balance, $wallet->balance);

        // \App\Handlers\Storage SKU
        $sku = Sku::where('title', 'storage')->first();
        $sku->registerEntitlement($user, [$domain]);

        $entitlements = $sku->entitlements()->where('owner_id', $user->id)->get();
        $wallet->refresh();

        if ($sku->active) {
            $balance -= $sku->cost;
            // For Storage entitlement we expect additional Quota record
            $quota = Quota::where('user_id', $user->id)->first();
            $this->assertTrue(!empty($quota));
            // TODO: This should be a constant and/or config option, and probably
            //       quota should not be in bytes
            $this->assertSame(2147483648, $quota->value);

            $this->assertCount(1, $entitlements);
            $this->assertSame($quota->id, $entitlements[0]->entitleable_id);
            $this->assertSame(Handlers\Storage::entitleableClass(), $entitlements[0]->entitleable_type);
        } else {
            $this->assertCount(0, $entitlements);
        }

        $this->assertEquals($balance, $wallet->balance);
    }
}

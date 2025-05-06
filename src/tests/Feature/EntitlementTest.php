<?php

namespace Tests\Feature;

use App\Domain;
use App\Entitlement;
use App\Jobs\User\UpdateJob;
use App\Package;
use App\Sku;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EntitlementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('entitlement-test@kolabnow.com');
        $this->deleteTestUser('entitled-user@custom-domain.com');
        $this->deleteTestGroup('test-group@custom-domain.com');
        $this->deleteTestDomain('custom-domain.com');
    }

    protected function tearDown(): void
    {
        $this->deleteTestUser('entitlement-test@kolabnow.com');
        $this->deleteTestUser('entitled-user@custom-domain.com');
        $this->deleteTestGroup('test-group@custom-domain.com');
        $this->deleteTestDomain('custom-domain.com');

        parent::tearDown();
    }

    /**
     * Tests for EntitlementObserver
     */
    public function testEntitlementObserver(): void
    {
        $skuStorage = Sku::withEnvTenantContext()->where('title', 'storage')->first();
        $skuMailbox = Sku::withEnvTenantContext()->where('title', 'mailbox')->first();
        $skuGroupware = Sku::withEnvTenantContext()->where('title', 'groupware')->first();
        $skuActivesync = Sku::withEnvTenantContext()->where('title', 'activesync')->first();
        $sku2FA = Sku::withEnvTenantContext()->where('title', '2fa')->first();
        $skuBeta = Sku::withEnvTenantContext()->where('title', 'beta')->first();
        $user = $this->getTestUser('entitlement-test@kolabnow.com');
        $wallet = $user->wallets->first();

        $assertPushedUserUpdateJob = static function ($ifLdap = false) use ($user) {
            if ($ifLdap && !\config('app.with_ldap')) {
                Queue::assertPushed(UpdateJob::class, 0);
                return;
            }

            Queue::assertPushed(UpdateJob::class, 1);
            Queue::assertPushed(
                UpdateJob::class,
                static function ($job) use ($user) {
                    return $user->id === TestCase::getObjectProperty($job, 'userId');
                }
            );
        };

        // Note: This also is testing SKU handlers

        // 'mailbox' SKU should not dispatch update jobs
        $this->fakeQueueReset();
        $user->assignSku($skuMailbox, 1, $wallet);
        Queue::assertPushed(UpdateJob::class, 0);
        $this->fakeQueueReset();
        $user->entitlements()->where('sku_id', $skuMailbox->id)->first()->delete();
        Queue::assertPushed(UpdateJob::class, 0);

        // Test dispatching update jobs for the user - 'storage' SKU
        $this->fakeQueueReset();
        $user->assignSku($skuStorage, 1, $wallet);
        $assertPushedUserUpdateJob();
        $this->fakeQueueReset();
        $user->entitlements()->where('sku_id', $skuStorage->id)->first()->delete();
        $assertPushedUserUpdateJob();

        // Test dispatching update jobs for the user - 'groupware' SKU
        $this->fakeQueueReset();
        $user->assignSku($skuGroupware, 1, $wallet);
        $assertPushedUserUpdateJob(true);
        $this->fakeQueueReset();
        $user->entitlements()->where('sku_id', $skuGroupware->id)->first()->delete();
        $assertPushedUserUpdateJob(true);

        // Test dispatching update jobs for the user - 'activesync' SKU
        $this->fakeQueueReset();
        $user->assignSku($skuActivesync, 1, $wallet);
        $assertPushedUserUpdateJob(true);
        $this->fakeQueueReset();
        $user->entitlements()->where('sku_id', $skuActivesync->id)->first()->delete();
        $assertPushedUserUpdateJob(true);

        // Test dispatching update jobs for the user - '2fa' SKU
        $this->fakeQueueReset();
        $user->assignSku($sku2FA, 1, $wallet);
        $assertPushedUserUpdateJob(true);
        $this->fakeQueueReset();
        $user->entitlements()->where('sku_id', $sku2FA->id)->first()->delete();
        $assertPushedUserUpdateJob(true);

        // Beta SKU should not trigger a user update job
        $this->fakeQueueReset();
        $user->assignSku($skuBeta, 1, $wallet);
        Queue::assertPushed(UpdateJob::class, 0);
        $this->fakeQueueReset();
        $user->entitlements()->where('sku_id', $skuBeta->id)->first()->delete();
        Queue::assertPushed(UpdateJob::class, 0);

        // TODO: Assert 'creating' checks
        // TODO: Assert transaction records being created
        // TODO: Assert timestamps not updated on delete
        $this->markTestIncomplete();
    }

    /**
     * Tests for entitlements
     *
     * @todo This really should be in User or Wallet tests file
     */
    public function testEntitlements(): void
    {
        $packageDomain = Package::withEnvTenantContext()->where('title', 'domain-hosting')->first();
        $packageKolab = Package::withEnvTenantContext()->where('title', 'kolab')->first();

        $skuDomain = Sku::withEnvTenantContext()->where('title', 'domain-hosting')->first();
        $skuMailbox = Sku::withEnvTenantContext()->where('title', 'mailbox')->first();

        $owner = $this->getTestUser('entitlement-test@kolabnow.com');
        $user = $this->getTestUser('entitled-user@custom-domain.com');

        $domain = $this->getTestDomain(
            'custom-domain.com',
            [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_EXTERNAL,
            ]
        );

        $domain->assignPackage($packageDomain, $owner);
        $owner->assignPackage($packageKolab);
        $owner->assignPackage($packageKolab, $user);

        $wallet = $owner->wallets->first();

        $this->assertCount(7, $owner->entitlements()->get());
        $this->assertCount(1, $skuDomain->entitlements()->where('wallet_id', $wallet->id)->get());
        $this->assertCount(2, $skuMailbox->entitlements()->where('wallet_id', $wallet->id)->get());
        $this->assertCount(15, $wallet->entitlements);

        $this->backdateEntitlements(
            $owner->entitlements,
            Carbon::now()->subMonthsWithoutOverflow(1)
        );

        $wallet->chargeEntitlements();

        $this->assertTrue($wallet->fresh()->balance < 0);
    }

    /**
     * Test EntitleableTrait::toString()
     */
    public function testEntitleableTitle(): void
    {
        $packageDomain = Package::withEnvTenantContext()->where('title', 'domain-hosting')->first();
        $packageKolab = Package::withEnvTenantContext()->where('title', 'kolab')->first();
        $user = $this->getTestUser('entitled-user@custom-domain.com');
        $group = $this->getTestGroup('test-group@custom-domain.com');

        $domain = $this->getTestDomain(
            'custom-domain.com',
            [
                'status' => Domain::STATUS_NEW,
                'type' => Domain::TYPE_EXTERNAL,
            ]
        );

        $wallet = $user->wallets->first();
        $domain->assignPackage($packageDomain, $user);
        $user->assignPackage($packageKolab);
        $group->assignToWallet($wallet);

        $sku_mailbox = Sku::withEnvTenantContext()->where('title', 'mailbox')->first();
        $sku_group = Sku::withEnvTenantContext()->where('title', 'group')->first();
        $sku_domain = Sku::withEnvTenantContext()->where('title', 'domain-hosting')->first();

        $entitlement = Entitlement::where('wallet_id', $wallet->id)
            ->where('sku_id', $sku_mailbox->id)->first();

        $this->assertSame($user->email, $entitlement->entitleable->toString());

        $entitlement = Entitlement::where('wallet_id', $wallet->id)
            ->where('sku_id', $sku_group->id)->first();

        $this->assertSame($group->email, $entitlement->entitleable->toString());

        $entitlement = Entitlement::where('wallet_id', $wallet->id)
            ->where('sku_id', $sku_domain->id)->first();

        $this->assertSame($domain->namespace, $entitlement->entitleable->toString());

        // Make sure it still works if the entitleable is deleted
        $domain->delete();

        $entitlement->refresh();

        $this->assertSame($domain->namespace, $entitlement->entitleable->toString());
        $this->assertNotNull($entitlement->entitleable);
    }

    /**
     * Test for EntitleableTrait::removeSku()
     */
    public function testEntitleableRemoveSku(): void
    {
        $user = $this->getTestUser('entitlement-test@kolabnow.com');
        $storage = Sku::withEnvTenantContext()->where('title', 'storage')->first();

        $user->assignSku($storage, 6);

        $this->assertCount(6, $user->fresh()->entitlements);

        $backdate = Carbon::now()->subWeeks(7);
        $this->backdateEntitlements($user->entitlements, $backdate);

        $user->removeSku($storage, 2);

        // Expect free units to be not deleted
        $this->assertCount(5, $user->fresh()->entitlements);

        // Here we make sure that updated_at does not change on delete
        $this->assertSame(6, $user->entitlements()->withTrashed()->whereDate('updated_at', $backdate)->count());
    }
}

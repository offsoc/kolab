<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Queue;
use Mollie\Laravel\Facades\Mollie;
use Tests\TestCase;

class OwnerSwapTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('user1@owner-swap.com');
        $this->deleteTestUser('user2@owner-swap.com');
        $this->deleteTestDomain('owner-swap.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('user1@owner-swap.com');
        $this->deleteTestUser('user2@owner-swap.com');
        $this->deleteTestDomain('owner-swap.com');

        parent::tearDown();
    }

    /**
     * Test the command
     *
     * @group mollie
     */
    public function testHandle(): void
    {
        Queue::fake();

        // Create some sample account
        $owner = $this->getTestUser('user1@owner-swap.com');
        $user = $this->getTestUser('user2@owner-swap.com');
        $domain = $this->getTestDomain('owner-swap.com', [
                'status' => \App\Domain::STATUS_NEW,
                'type' => \App\Domain::TYPE_HOSTED,
        ]);
        $package_kolab = \App\Package::withEnvTenantContext()->where('title', 'kolab')->first();
        $package_domain = \App\Package::withEnvTenantContext()->where('title', 'domain-hosting')->first();
        $owner->assignPackage($package_kolab);
        $owner->assignPackage($package_kolab, $user);
        $domain->assignPackage($package_domain, $owner);
        $wallet = $owner->wallets()->first();
        $wallet->currency = 'USD';
        $wallet->balance = 100;
        $wallet->save();
        $wallet->setSetting('test', 'test');
        $target_wallet = $user->wallets()->first();
        $owner->created_at = \now()->subMonths(1);
        $owner->save();
        $owner->setSetting('plan_id', 'test');

        $entitlements = $wallet->entitlements()->orderBy('id')->pluck('id')->all();
        $this->assertCount(15, $entitlements);
        $this->assertSame(0, $target_wallet->entitlements()->count());

        $customer = $this->createMollieCustomer($wallet);

        // Non-existing target user
        $code = \Artisan::call("owner:swap user1@owner-swap.com unknown@unknown.org");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("User not found.", $output);

        // The same user
        $code = \Artisan::call("owner:swap user1@owner-swap.com user1@owner-swap.com");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("Users cannot be the same.", $output);

        // Success
        $code = \Artisan::call("owner:swap user1@owner-swap.com user2@owner-swap.com");
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertSame("", $output);

        $user->refresh();
        $target_wallet->refresh();
        $target_entitlements = $target_wallet->entitlements()->orderBy('id')->pluck('id')->all();

        $this->assertSame($target_entitlements, $entitlements);
        $this->assertSame(0, $wallet->entitlements()->count());
        $this->assertSame($wallet->balance, $target_wallet->balance);
        $this->assertSame($wallet->currency, $target_wallet->currency);
        $this->assertTrue($user->created_at->toDateTimeString() === $owner->created_at->toDateTimeString());
        $this->assertSame('test', $target_wallet->getSetting('test'));
        $this->assertSame('test', $user->getSetting('plan_id'));

        $wallet->refresh();
        $this->assertSame(null, $wallet->getSetting('test'));
        $this->assertSame(0, $wallet->balance);

        $target_customer = $this->getMollieCustomer($target_wallet->getSetting('mollie_id'));
        $this->assertSame($customer->id, $target_customer->id);
        $this->assertTrue($customer->email != $target_customer->email);
        $this->assertSame($target_wallet->id . '@private.' . \config('app.domain'), $target_customer->email);

        // Test case when the target user does not belong to the same account
        $john = $this->getTestUser('john@kolab.org');
        $owner->entitlements()->update(['wallet_id' => $john->wallets->first()->id]);

        $code = \Artisan::call("owner:swap user2@owner-swap.com user1@owner-swap.com");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("The target user does not belong to the same account.", $output);
    }

    /**
     * Create a Mollie customer
     */
    private function createMollieCustomer($wallet)
    {
        $customer = Mollie::api()->customers->create([
                'name'  => $wallet->owner->name(),
                'email' => $wallet->id . '@private.' . \config('app.domain'),
        ]);

        $customer_id = $customer->id;

        $wallet->setSetting('mollie_id', $customer->id);

        return $customer;
    }

    /**
     * Get a Mollie customer
     */
    private function getMollieCustomer(string $mollie_id)
    {
        return Mollie::api()->customers->get($mollie_id);
    }
}

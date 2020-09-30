<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UserForceDeleteTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('user@force-delete.com');
        $this->deleteTestDomain('force-delete.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('user@force-delete.com');
        $this->deleteTestDomain('force-delete.com');

        parent::tearDown();
    }

    /**
     * Test the command
     */
    public function testHandle(): void
    {
        // Non-existing user
        $this->artisan('user:force-delete unknown@unknown.org')
             ->assertExitCode(1);

        Queue::fake();
        $user = $this->getTestUser('user@force-delete.com');
        $domain = $this->getTestDomain('force-delete.com', [
                'status' => \App\Domain::STATUS_NEW,
                'type' => \App\Domain::TYPE_HOSTED,
        ]);
        $package_kolab = \App\Package::where('title', 'kolab')->first();
        $package_domain = \App\Package::where('title', 'domain-hosting')->first();
        $user->assignPackage($package_kolab);
        $domain->assignPackage($package_domain, $user);
        $wallet = $user->wallets()->first();
        $entitlements = $wallet->entitlements->pluck('id')->all();

        $this->assertCount(5, $entitlements);

        // Non-deleted user
        $this->artisan('user:force-delete user@force-delete.com')
             ->assertExitCode(1);

        $user->delete();

        $this->assertTrue($user->trashed());
        $this->assertTrue($domain->fresh()->trashed());

        // Deleted user
        $this->artisan('user:force-delete user@force-delete.com')
             ->assertExitCode(0);

        $this->assertCount(
            0,
            \App\User::withTrashed()->where('email', 'user@force-delete.com')->get()
        );
        $this->assertCount(
            0,
            \App\Domain::withTrashed()->where('namespace', 'force-delete.com')->get()
        );
        $this->assertCount(
            0,
            \App\Wallet::where('id', $wallet->id)->get()
        );
        $this->assertCount(
            0,
            \App\Entitlement::withTrashed()->where('wallet_id', $wallet->id)->get()
        );
        $this->assertCount(
            0,
            \App\Entitlement::withTrashed()->where('entitleable_id', $user->id)->get()
        );
        $this->assertCount(
            0,
            \App\Transaction::whereIn('object_id', $entitlements)
                ->where('object_type', \App\Entitlement::class)
                ->get()
        );

        // TODO: Test that it also deletes users in a group account
    }
}

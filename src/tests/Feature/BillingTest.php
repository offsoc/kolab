<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BillingTest extends TestCase
{
    /** @property \App\Package $package */
    private $package;

    /** @property \App\User $user */
    private $user;

    /** @property \App\Wallet $wallet */
    private $wallet;

    /** @property string $wallet_id */
    private $wallet_id;

    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('jane@kolabnow.com');
        $this->deleteTestUser('jack@kolabnow.com');

        \App\Package::where('title', 'kolab-kube')->delete();

        $this->user = $this->getTestUser('jane@kolabnow.com');
        $this->package = \App\Package::where('title', 'kolab')->first();
        $this->user->assignPackage($this->package);

        $this->wallet = $this->user->wallets->first();

        $this->wallet_id = $this->wallet->id;
    }

    public function tearDown(): void
    {
        $this->deleteTestUser('jane@kolabnow.com');
        $this->deleteTestUser('jack@kolabnow.com');

        \App\Package::where('title', 'kolab-kube')->delete();

        parent::tearDown();
    }

    /**
     * Test the expected results for a user that registers and is almost immediately gone.
     */
    public function testTouchAndGo(): void
    {
        $this->assertCount(4, $this->wallet->entitlements);

        $this->assertEquals(0, $this->wallet->expectedCharges());

        $this->user->delete();

        $this->assertCount(0, $this->wallet->fresh()->entitlements->where('deleted_at', null));

        $this->assertCount(4, $this->wallet->entitlements);
    }

    /**
     * Verify the last day before the end of a full month's trial.
     */
    public function testNearFullTrial(): void
    {
        $this->backdateEntitlements(
            $this->wallet->entitlements,
            Carbon::now()->subMonthsWithoutOverflow(1)->addDays(1)
        );

        $this->assertEquals(0, $this->wallet->expectedCharges());
    }

    /**
     * Verify the exact end of the month's trial.
     */
    public function testFullTrial(): void
    {
        $this->backdateEntitlements($this->wallet->entitlements, Carbon::now()->subMonthsWithoutOverflow(1));

        $this->assertEquals(999, $this->wallet->expectedCharges());
    }

    /**
     * Verify that over-running the trial by a single day causes charges to be incurred.
     */
    public function testOutRunTrial(): void
    {
        $this->backdateEntitlements(
            $this->wallet->entitlements,
            Carbon::now()->subMonthsWithoutOverflow(1)->subDays(1)
        );

        $this->assertEquals(999, $this->wallet->expectedCharges());
    }

    /**
     * Verify additional storage configuration entitlement created 'early' does incur additional
     * charges to the wallet.
     */
    public function testAddtStorageEarly(): void
    {
        $this->backdateEntitlements(
            $this->wallet->entitlements,
            Carbon::now()->subMonthsWithoutOverflow(1)->subDays(1)
        );

        $this->assertEquals(999, $this->wallet->expectedCharges());

        $sku = \App\Sku::where(['title' => 'storage'])->first();

        $entitlement = \App\Entitlement::create(
            [
                'wallet_id' => $this->wallet_id,
                'sku_id' => $sku->id,
                'cost' => $sku->cost,
                'entitleable_id' => $this->user->id,
                'entitleable_type' => \App\User::class
            ]
        );

        $this->backdateEntitlements(
            [$entitlement],
            Carbon::now()->subMonthsWithoutOverflow(1)->subDays(1)
        );

        $this->assertEquals(1024, $this->wallet->expectedCharges());
    }

    /**
     * Verify additional storage configuration entitlement created 'late' does not incur additional
     * charges to the wallet.
     */
    public function testAddtStorageLate(): void
    {
        $this->backdateEntitlements($this->wallet->entitlements, Carbon::now()->subMonthsWithoutOverflow(1));

        $this->assertEquals(999, $this->wallet->expectedCharges());

        $sku = \App\Sku::where(['title' => 'storage'])->first();

        $entitlement = \App\Entitlement::create(
            [
                'wallet_id' => $this->wallet_id,
                'sku_id' => $sku->id,
                'cost' => $sku->cost,
                'entitleable_id' => $this->user->id,
                'entitleable_type' => \App\User::class
            ]
        );

        $this->backdateEntitlements([$entitlement], Carbon::now()->subDays(14));

        $this->assertEquals(999, $this->wallet->expectedCharges());
    }

    public function testFifthWeek(): void
    {
        $targetDateA = Carbon::now()->subWeeks(5);
        $targetDateB = $targetDateA->copy()->addMonthsWithoutOverflow(1);

        $this->backdateEntitlements($this->wallet->entitlements, $targetDateA);

        $this->assertEquals(999, $this->wallet->expectedCharges());

        $this->wallet->chargeEntitlements();

        $this->assertEquals(-999, $this->wallet->balance);

        foreach ($this->wallet->entitlements()->get() as $entitlement) {
            $this->assertTrue($entitlement->created_at->isSameSecond($targetDateA));
            $this->assertTrue($entitlement->updated_at->isSameSecond($targetDateB));
        }
    }

    public function testSecondMonth(): void
    {
        $this->backdateEntitlements($this->wallet->entitlements, Carbon::now()->subMonthsWithoutOverflow(2));

        $this->assertCount(4, $this->wallet->entitlements);

        $this->assertEquals(1998, $this->wallet->expectedCharges());

        $sku = \App\Sku::where(['title' => 'storage'])->first();

        $entitlement = \App\Entitlement::create(
            [
                'entitleable_id' => $this->user->id,
                'entitleable_type' => \App\User::class,
                'cost' => $sku->cost,
                'sku_id' => $sku->id,
                'wallet_id' => $this->wallet_id
            ]
        );

        $this->backdateEntitlements([$entitlement], Carbon::now()->subMonthsWithoutOverflow(1));

        $this->assertEquals(2023, $this->wallet->expectedCharges());
    }

    public function testWithDiscount(): void
    {
        $package = \App\Package::create(
            [
                'title' => 'kolab-kube',
                'name' => 'Kolab for Kuba Fans',
                'description' => 'Kolab for Kube fans',
                'discount_rate' => 50
            ]
        );

        $skus = [
            \App\Sku::firstOrCreate(['title' => 'mailbox']),
            \App\Sku::firstOrCreate(['title' => 'storage']),
            \App\Sku::firstOrCreate(['title' => 'groupware'])
        ];

        $package->skus()->saveMany($skus);

        $package->skus()->updateExistingPivot(
            \App\Sku::firstOrCreate(['title' => 'storage']),
            ['qty' => 2],
            false
        );

        $user = $this->getTestUser('jack@kolabnow.com');

        $user->assignPackage($package);

        $wallet = $user->wallets->first();

        $wallet_id = $wallet->id;

        $this->backdateEntitlements($wallet->entitlements, Carbon::now()->subMonthsWithoutOverflow(1));

        $this->assertEquals(500, $wallet->expectedCharges());
    }
}

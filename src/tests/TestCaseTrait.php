<?php

namespace Tests;

use App\Domain;
use App\Group;
use App\Transaction;
use App\User;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Assert;

trait TestCaseTrait
{
    /**
     * Assert user entitlements state
     */
    protected function assertUserEntitlements($user, $expected)
    {
        // Assert the user entitlements
        $skus = $user->entitlements()->get()
            ->map(function ($ent) {
                return $ent->sku->title;
            })
            ->toArray();

        sort($skus);

        Assert::assertSame($expected, $skus);
    }

    /**
     * Removes all beta entitlements from the database
     */
    protected function clearBetaEntitlements(): void
    {
        $betas = \App\Sku::where('handler_class', 'like', 'App\\Handlers\\Beta\\%')
            ->orWhere('handler_class', 'App\Handlers\Beta')
            ->pluck('id')->all();

        \App\Entitlement::whereIn('sku_id', $betas)->delete();
    }

    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__ . '/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    /**
     * Create a set of transaction log entries for a wallet
     */
    protected function createTestTransactions($wallet)
    {
        $result = [];
        $date = Carbon::now();
        $debit = 0;
        $entitlementTransactions = [];

        foreach ($wallet->entitlements as $entitlement) {
            if ($entitlement->cost) {
                $debit += $entitlement->cost;
                $entitlementTransactions[] = $entitlement->createTransaction(
                    Transaction::ENTITLEMENT_BILLED,
                    $entitlement->cost
                );
            }
        }

        $transaction = Transaction::create(
            [
                'user_email' => 'jeroen@jeroen.jeroen',
                'object_id' => $wallet->id,
                'object_type' => \App\Wallet::class,
                'type' => Transaction::WALLET_DEBIT,
                'amount' => $debit * -1,
                'description' => 'Payment',
            ]
        );

        $result[] = $transaction;

        Transaction::whereIn('id', $entitlementTransactions)->update(['transaction_id' => $transaction->id]);

        $transaction = Transaction::create(
            [
                'user_email' => null,
                'object_id' => $wallet->id,
                'object_type' => \App\Wallet::class,
                'type' => Transaction::WALLET_CREDIT,
                'amount' => 2000,
                'description' => 'Payment',
            ]
        );

        $transaction->created_at = $date->next(Carbon::MONDAY);
        $transaction->save();

        $result[] = $transaction;

        $types = [
            Transaction::WALLET_AWARD,
            Transaction::WALLET_PENALTY,
        ];

        // The page size is 10, so we generate so many to have at least two pages
        $loops = 10;
        while ($loops-- > 0) {
            $type = $types[count($result) % count($types)];
            $transaction = Transaction::create([
                'user_email' => 'jeroen.@jeroen.jeroen',
                'object_id' => $wallet->id,
                'object_type' => \App\Wallet::class,
                'type' => $type,
                'amount' => 11 * (count($result) + 1) * ($type == Transaction::WALLET_PENALTY ? -1 : 1),
                'description' => 'TRANS' . $loops,
            ]);
            $transaction->created_at = $date->next(Carbon::MONDAY);
            $transaction->save();

            $result[] = $transaction;
        }

        return $result;
    }

    /**
     * Delete a test domain whatever it takes.
     *
     * @coversNothing
     */
    protected function deleteTestDomain($name)
    {
        Queue::fake();

        $domain = Domain::withTrashed()->where('namespace', $name)->first();

        if (!$domain) {
            return;
        }

        $job = new \App\Jobs\Domain\DeleteJob($domain->id);
        $job->handle();

        $domain->forceDelete();
    }

    protected function deleteTestGroup($email)
    {
        Queue::fake();

        $group = Group::withTrashed()->where('email', $email)->first();

        if (!$group) {
            return;
        }

        $job = new \App\Jobs\Group\DeleteJob($group->id);
        $job->handle();

        $group->forceDelete();
    }

    protected function deleteTestUser($email)
    {
        Queue::fake();

        $user = User::withTrashed()->where('email', $email)->first();

        if (!$user) {
            return;
        }

        $job = new \App\Jobs\User\DeleteJob($user->id);
        $job->handle();

        $user->forceDelete();
    }

    /**
     * Helper to access protected property of an object
     */
    protected static function getObjectProperty($object, $property_name)
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($property_name);
        $property->setAccessible(true);

        return $property->getValue($object);
    }

    /**
     * Get Domain object by namespace, create it if needed.
     * Skip LDAP jobs.
     *
     * @coversNothing
     */
    protected function getTestDomain($name, $attrib = [])
    {
        // Disable jobs (i.e. skip LDAP oprations)
        Queue::fake();
        return Domain::firstOrCreate(['namespace' => $name], $attrib);
    }

    /**
     * Get Group object by email, create it if needed.
     * Skip LDAP jobs.
     */
    protected function getTestGroup($email, $attrib = [])
    {
        // Disable jobs (i.e. skip LDAP oprations)
        Queue::fake();
        return Group::firstOrCreate(['email' => $email], $attrib);
    }

    /**
     * Get User object by email, create it if needed.
     * Skip LDAP jobs.
     *
     * @coversNothing
     */
    protected function getTestUser($email, $attrib = [])
    {
        // Disable jobs (i.e. skip LDAP oprations)
        Queue::fake();
        $user = User::firstOrCreate(['email' => $email], $attrib);

        if ($user->trashed()) {
            // Note: we do not want to use user restore here
            User::where('id', $user->id)->forceDelete();
            $user = User::create(['email' => $email] + $attrib);
        }

        return $user;
    }

    /**
     * Call protected/private method of a class.
     *
     * @param object $object     Instantiated object that we will run method on.
     * @param string $methodName Method name to call
     * @param array  $parameters Array of parameters to pass into method.
     *
     * @return mixed Method return.
     */
    protected function invokeMethod($object, $methodName, array $parameters = array())
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->userPassword = \App\Utils::generatePassphrase();

        $this->domainHosted = $this->getTestDomain(
            'test.domain',
            [
                'type' => \App\Domain::TYPE_EXTERNAL,
                'status' => \App\Domain::STATUS_ACTIVE | \App\Domain::STATUS_CONFIRMED | \App\Domain::STATUS_VERIFIED
            ]
        );

        $packageKolab = \App\Package::where('title', 'kolab')->first();

        $this->domainOwner = $this->getTestUser('john@test.domain', ['password' => $this->userPassword]);
        $this->domainOwner->assignPackage($packageKolab);
        $this->domainOwner->setSettings($this->domainOwnerSettings);

        // separate for regular user
        $this->jack = $this->getTestUser('jack@test.domain', ['password' => $this->userPassword]);

        // separate for wallet controller
        $this->jane = $this->getTestUser('jane@test.domain', ['password' => $this->userPassword]);

        $this->joe = $this->getTestUser('joe@test.domain', ['password' => $this->userPassword]);

        $this->domainUsers[] = $this->jack;
        $this->domainUsers[] = $this->jane;
        $this->domainUsers[] = $this->joe;
        $this->domainUsers[] = $this->getTestUser('jill@test.domain', ['password' => $this->userPassword]);

        foreach ($this->domainUsers as $user) {
            $this->domainOwner->assignPackage($packageKolab, $user);
        }

        $this->domainUsers[] = $this->domainOwner;

        // assign second factor to joe
        $this->joe->assignSku(\App\Sku::where('title', '2fa')->first());
        \App\Auth\SecondFactor::seed($this->joe->email);

        usort(
            $this->domainUsers,
            function ($a, $b) {
                return $a->email > $b->email;
            }
        );

        $this->domainHosted->assignPackage(
            \App\Package::where('title', 'domain-hosting')->first(),
            $this->domainOwner
        );

        $wallet = $this->domainOwner->wallets()->first();

        $wallet->addController($this->jane);

        $this->publicDomain = \App\Domain::where('type', \App\Domain::TYPE_PUBLIC)->first();
        $this->publicDomainUser = $this->getTestUser(
            'john@' . $this->publicDomain->namespace,
            ['password' => $this->userPassword]
        );

        $this->publicDomainUser->assignPackage($packageKolab);
    }

    public function tearDown(): void
    {
        foreach ($this->domainUsers as $user) {
            if ($user == $this->domainOwner) {
                continue;
            }

            $this->deleteTestUser($user->email);
        }

        $this->deleteTestUser($this->domainOwner->email);
        $this->deleteTestDomain($this->domainHosted->namespace);

        $this->deleteTestUser($this->publicDomainUser->email);

        parent::tearDown();
    }
}

<?php

namespace Tests;

use App\Backends\LDAP;
use App\Domain;
use App\Group;
use App\Resource;
use App\SharedFolder;
use App\Sku;
use App\Transaction;
use App\User;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Assert;

trait TestCaseTrait
{
    /**
     * A domain that is hosted.
     *
     * @var ?\App\Domain
     */
    protected $domainHosted;

    /**
     * The hosted domain owner.
     *
     * @var ?\App\User
     */
    protected $domainOwner;

    /**
     * Some profile details for an owner of a domain
     *
     * @var array
     */
    protected $domainOwnerSettings = [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'organization' => 'Test Domain Owner',
    ];

    /**
     * Some users for the hosted domain, ultimately including the owner.
     *
     * @var \App\User[]
     */
    protected $domainUsers = [];

    /**
     * A specific user that is a regular user in the hosted domain.
     *
     * @var ?\App\User
     */
    protected $jack;

    /**
     * A specific user that is a controller on the wallet to which the hosted domain is charged.
     *
     * @var ?\App\User
     */
    protected $jane;

    /**
     * A specific user that has a second factor configured.
     *
     * @var ?\App\User
     */
    protected $joe;

    /**
     * One of the domains that is available for public registration.
     *
     * @var ?\App\Domain
     */
    protected $publicDomain;

    /**
     * A newly generated user in a public domain.
     *
     * @var ?\App\User
     */
    protected $publicDomainUser;

    /**
     * A placeholder for a password that can be generated.
     *
     * Should be generated with `\App\Utils::generatePassphrase()`.
     *
     * @var ?string
     */
    protected $userPassword;

    /**
     * Register the beta entitlement for a user
     */
    protected function addBetaEntitlement($user, $titles = []): void
    {
        // Add beta + $title entitlements
        $beta_sku = Sku::withEnvTenantContext()->where('title', 'beta')->first();
        $user->assignSku($beta_sku);

        if (!empty($titles)) {
            Sku::withEnvTenantContext()->whereIn('title', (array) $titles)->get()
                ->each(function ($sku) use ($user) {
                    $user->assignSku($sku);
                });
        }
    }

    /**
     * Assert that the entitlements for the user match the expected list of entitlements.
     *
     * @param \App\User|\App\Domain $object   The object for which the entitlements need to be pulled.
     * @param array                 $expected An array of expected \App\Sku titles.
     */
    protected function assertEntitlements($object, $expected)
    {
        // Assert the user entitlements
        $skus = $object->entitlements()->get()
            ->map(function ($ent) {
                return $ent->sku->title;
            })
            ->toArray();

        sort($skus);

        Assert::assertSame($expected, $skus);
    }

    protected function backdateEntitlements($entitlements, $targetDate, $targetCreatedDate = null)
    {
        $wallets = [];
        $ids = [];

        foreach ($entitlements as $entitlement) {
            $ids[] = $entitlement->id;
            $wallets[] = $entitlement->wallet_id;
        }

        \App\Entitlement::whereIn('id', $ids)->update([
                'created_at' => $targetCreatedDate ?: $targetDate,
                'updated_at' => $targetDate,
        ]);

        if (!empty($wallets)) {
            $wallets = array_unique($wallets);
            $owners = \App\Wallet::whereIn('id', $wallets)->pluck('user_id')->all();

            \App\User::whereIn('id', $owners)->update([
                    'created_at' => $targetCreatedDate ?: $targetDate
            ]);
        }
    }

    /**
     * Removes all beta entitlements from the database
     */
    protected function clearBetaEntitlements(): void
    {
        $beta_handlers = [
            'App\Handlers\Beta',
            'App\Handlers\Beta\Distlists',
            'App\Handlers\Beta\Resources',
            'App\Handlers\Beta\SharedFolders',
        ];

        $betas = Sku::whereIn('handler_class', $beta_handlers)->pluck('id')->all();

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

    /**
     * Delete a test group whatever it takes.
     *
     * @coversNothing
     */
    protected function deleteTestGroup($email)
    {
        Queue::fake();

        $group = Group::withTrashed()->where('email', $email)->first();

        if (!$group) {
            return;
        }

        LDAP::deleteGroup($group);

        $group->forceDelete();
    }

    /**
     * Delete a test resource whatever it takes.
     *
     * @coversNothing
     */
    protected function deleteTestResource($email)
    {
        Queue::fake();

        $resource = Resource::withTrashed()->where('email', $email)->first();

        if (!$resource) {
            return;
        }

        LDAP::deleteResource($resource);

        $resource->forceDelete();
    }

    /**
     * Delete a test shared folder whatever it takes.
     *
     * @coversNothing
     */
    protected function deleteTestSharedFolder($email)
    {
        Queue::fake();

        $folder = SharedFolder::withTrashed()->where('email', $email)->first();

        if (!$folder) {
            return;
        }

        LDAP::deleteSharedFolder($folder);

        $folder->forceDelete();
    }

    /**
     * Delete a test user whatever it takes.
     *
     * @coversNothing
     */
    protected function deleteTestUser($email)
    {
        Queue::fake();

        $user = User::withTrashed()->where('email', $email)->first();

        if (!$user) {
            return;
        }

        LDAP::deleteUser($user);

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
     * Get Resource object by email, create it if needed.
     * Skip LDAP jobs.
     */
    protected function getTestResource($email, $attrib = [])
    {
        // Disable jobs (i.e. skip LDAP oprations)
        Queue::fake();

        $resource = Resource::where('email', $email)->first();

        if (!$resource) {
            list($local, $domain) = explode('@', $email, 2);

            $resource = new Resource();
            $resource->email = $email;
            $resource->domain = $domain;

            if (!isset($attrib['name'])) {
                $resource->name = $local;
            }
        }

        foreach ($attrib as $key => $val) {
            $resource->{$key} = $val;
        }

        $resource->save();

        return $resource;
    }

    /**
     * Get SharedFolder object by email, create it if needed.
     * Skip LDAP jobs.
     */
    protected function getTestSharedFolder($email, $attrib = [])
    {
        // Disable jobs (i.e. skip LDAP oprations)
        Queue::fake();

        $folder = SharedFolder::where('email', $email)->first();

        if (!$folder) {
            list($local, $domain) = explode('@', $email, 2);

            $folder = new SharedFolder();
            $folder->email = $email;
            $folder->domain = $domain;

            if (!isset($attrib['name'])) {
                $folder->name = $local;
            }
        }

        foreach ($attrib as $key => $val) {
            $folder->{$key} = $val;
        }

        $folder->save();

        return $folder;
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

    protected function setUpTest()
    {
        $this->userPassword = \App\Utils::generatePassphrase();

        $this->domainHosted = $this->getTestDomain(
            'test.domain',
            [
                'type' => \App\Domain::TYPE_EXTERNAL,
                'status' => \App\Domain::STATUS_ACTIVE | \App\Domain::STATUS_CONFIRMED | \App\Domain::STATUS_VERIFIED
            ]
        );

        $this->getTestDomain(
            'test2.domain2',
            [
                'type' => \App\Domain::TYPE_EXTERNAL,
                'status' => \App\Domain::STATUS_ACTIVE | \App\Domain::STATUS_CONFIRMED | \App\Domain::STATUS_VERIFIED
            ]
        );

        $packageKolab = \App\Package::where('title', 'kolab')->first();

        $this->domainOwner = $this->getTestUser('john@test.domain', ['password' => $this->userPassword]);
        $this->domainOwner->assignPackage($packageKolab);
        $this->domainOwner->setSettings($this->domainOwnerSettings);
        $this->domainOwner->setAliases(['alias1@test2.domain2']);

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
        $this->joe->assignSku(Sku::where('title', '2fa')->first());
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

        if ($this->domainOwner) {
            $this->deleteTestUser($this->domainOwner->email);
        }

        if ($this->domainHosted) {
            $this->deleteTestDomain($this->domainHosted->namespace);
        }

        if ($this->publicDomainUser) {
            $this->deleteTestUser($this->publicDomainUser->email);
        }

        parent::tearDown();
    }
}

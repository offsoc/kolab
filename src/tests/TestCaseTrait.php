<?php

namespace Tests;

use App\Domain;
use App\Transaction;
use App\User;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Assert;

trait TestCaseTrait
{
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

        $transaction = Transaction::create([
                'user_email' => 'jeroen@jeroen.jeroen',
                'object_id' => $wallet->id,
                'object_type' => \App\Wallet::class,
                'type' => Transaction::WALLET_DEBIT,
                'amount' => $debit,
                'description' => 'Payment',
        ]);
        $result[] = $transaction;

        Transaction::whereIn('id', $entitlementTransactions)->update(['transaction_id' => $transaction->id]);

        $transaction = Transaction::create([
                'user_email' => null,
                'object_id' => $wallet->id,
                'object_type' => \App\Wallet::class,
                'type' => Transaction::WALLET_CREDIT,
                'amount' => 2000,
                'description' => 'Payment',
        ]);
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
            $transaction = Transaction::create([
                'user_email' => 'jeroen.@jeroen.jeroen',
                'object_id' => $wallet->id,
                'object_type' => \App\Wallet::class,
                'type' => $types[count($result) % count($types)],
                'amount' => 11 * (count($result) + 1),
                'description' => 'TRANS' . $loops,
            ]);
            $transaction->created_at = $date->next(Carbon::MONDAY);
            $transaction->save();
            $result[] = $transaction;
        }

        return $result;
    }

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
     * Get Domain object by namespace, create it if needed.
     * Skip LDAP jobs.
     */
    protected function getTestDomain($name, $attrib = [])
    {
        // Disable jobs (i.e. skip LDAP oprations)
        Queue::fake();
        return Domain::firstOrCreate(['namespace' => $name], $attrib);
    }

    /**
     * Get User object by email, create it if needed.
     * Skip LDAP jobs.
     */
    protected function getTestUser($email, $attrib = [])
    {
        // Disable jobs (i.e. skip LDAP oprations)
        Queue::fake();
        $user = User::withTrashed()->where('email', $email)->first();

        if (!$user) {
            return User::firstOrCreate(['email' => $email], $attrib);
        }

        if ($user->deleted_at) {
            $user->restore();
        }

        return $user;
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
}

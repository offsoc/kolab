<?php

namespace Tests;

use App\Domain;
use App\User;
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

    protected function deleteTestDomain($name)
    {
        Queue::fake();

        $domain = Domain::withTrashed()->where('namespace', $name)->first();

        if (!$domain) {
            return;
        }

        $job = new \App\Jobs\DomainDelete($domain->id);
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

        $job = new \App\Jobs\UserDelete($user->id);
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

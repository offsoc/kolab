<?php

namespace Tests;

use App\Domain;
use App\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Queue;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function deleteTestDomain($name)
    {
        Queue::fake();
        $domain = Domain::withTrashed()->where('namespace', $name)->first();
        if (!$domain) {
            return;
        }

        $job = new \App\Jobs\DomainDelete($domain);
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
        return User::firstOrCreate(['email' => $email], $attrib);
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
    public function invokeMethod($object, $methodName, array $parameters = array())
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}

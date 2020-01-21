<?php

namespace Tests;

use App\Domain;
use App\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Queue;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

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
}

<?php

namespace Tests\Functional\Methods\Auth;

use Tests\TestCase;

class SecondFactorTest extends TestCase
{
    /**
     * A test user with a second factor configured.
     *
     * @var \App\User;
     */
    private $testUser;

    /**
     * A test user without a second factor configured.
     *
     * @var \App\User;
     */
    private $testUserNone;

    public function setUp(): void
    {
        parent::setUp();

        // select any user with a second factor
        foreach ($this->domainUsers as $user) {
            if ($user->hasSku('2fa')) {
                $this->testUser = $user;
                break;
            }
        }

        // select any user without a second factor
        foreach ($this->domainUsers as $user) {
            if (!$user->hasSku('2fa')) {
                $this->testUserNone = $user;
                break;
            }
        }
    }

    /**
     * Verify factors exist for the test user.
     */
    public function testFactors()
    {
        $mf = new \App\Auth\SecondFactor($this->testUser);
        $factors = $mf->factors();
        $this->assertNotEmpty($factors);
    }

    /**
     * Verify no factors exist for the test user without factors.
     */
    public function testFactorsNone()
    {
        $mf = new \App\Auth\SecondFactor($this->testUserNone);
        $factors = $mf->factors();
        $this->assertEmpty($factors);
    }
}

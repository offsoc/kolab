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

    public function testFactorDriverValid()
    {
        $this->markTestIncomplete();
    }

    public function testVerifySuccess()
    {
        $this->markTestIncomplete();
    }

    public function testVerifyFailure()
    {
        $this->markTestIncomplete();
    }

    public function testSeed()
    {
        $this->markTestIncomplete();
    }

    public function code()
    {
        $this->markTestIncomplete();
    }

    public function testDbh()
    {
        $this->markTestIncomplete();
    }

    public function testMultipleFactors()
    {
        $this->markTestIncomplete();
    }

    public function testRead()
    {
        $this->markTestIncomplete();
    }

    public function testWrite()
    {
        $this->markTestIncomplete();
    }

    public function testRemove()
    {
        $this->markTestIncomplete();
    }

    public function testGetFactors()
    {
        $this->markTestIncomplete();
    }

    public function testKey2property()
    {
        $this->markTestIncomplete();
    }

    public function testGetPrefs()
    {
        $this->markTestIncomplete();
    }

    public function testSavePrefs()
    {
        $this->markTestIncomplete();
    }
}

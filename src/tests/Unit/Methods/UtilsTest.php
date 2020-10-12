<?php

namespace Tests\Unit\Methods;

use Carbon\Carbon;
use Tests\TestCase;

class UtilsTest extends TestCase
{
    public function setUp(): void
    {
        // nothing to do here
    }

    public function tearDown(): void
    {
        // nothing to do here
    }

    public function testDaysInLastMonth()
    {
        $numDays = \App\Utils::daysInLastMonth();

        $this->assertIsInt($numDays);

        $this->assertTrue($numDays >= 28);
        $this->assertTrue($numDays <= 31);
    }

    /**
     * Verify that the result for the function is within boundaries per the expected.
     *
     * A leap year is expected to happen on any anno domini divisible by 4, but not on any anno domini divisible by
     * 100, with the exception of those also divisible by 400.
     *
     * For those that read this documentation within the lifetime of this code, this means 2000 AD to 2400 AD, I hope
     * you'll find this test to be accurate, if not useful.
     *
     * We're not validating specifically whether or not \Carbon\Carbon does its job OK. We're validating the assertion
     * it does per our own limited comprehension of things, so our other tests can enjoy the benefits of such
     * validation having happened here.
     */
    public function testDaysInLastMonthForAllFortyEightMonthsPast()
    {
        $today = Carbon::now();

        $found29 = false;
        $needFound29 = false;

        // anywhere within the past 9 years, a leap year must have happened.
        $numMonths = 12 * 9;

        for ($x = 0; $x < $numMonths; $x++) {
            $testMonth = $today->copy()->subMonthsWithoutOverflow($x);
            if ($testMonth->isLeapYear()) {
                $needFound29 = true;
            }
        }

        for ($x = 0; $x < $numMonths; $x++) {
            $testMonth = $today->copy()->subMonthsWithoutOverflow($x);
            $testDate = Carbon::create($testMonth->year, $testMonth->month, 1, 12);
            Carbon::setTestNow($testDate);

            $numDays = \App\Utils::daysInLastMonth();

            if ($numDays == 29) {
                $found29 = true;
            }

            $this->assertIsInt($numDays);

            $this->assertTrue($numDays >= 28);
            $this->assertTrue($numDays <= 31);
        }

        $this->assertTrue($found29 && $needFound29);
    }

    public function testGeneratePassphrase()
    {
        $passphrases = [];

        for ($x = 0; $x < pow(2, 10); $x++) {
            $passphrase = \App\Utils::generatePassPhrase();

            $this->assertFalse(in_array($passphrase, $passphrases));

            $passphrases[] = $passphrase;
        }
    }
}

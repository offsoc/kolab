<?php

namespace Tests\Unit;

use App\Utils;
use Tests\TestCase;

class UtilsTest extends TestCase
{
    /**
     * Test for Utils::emailToLower()
     */
    public function testEmailToLower(): void
    {
        $this->assertSame('test@test.tld', \App\Utils::emailToLower('test@Test.Tld'));
        $this->assertSame('test@test.tld', \App\Utils::emailToLower('Test@Test.Tld'));
        $this->assertSame('shared+shared/Test@test.tld', \App\Utils::emailToLower('shared+shared/Test@Test.Tld'));
    }

    /**
     * Test for Utils::normalizeAddress()
     */
    public function testNormalizeAddress(): void
    {
        $this->assertSame('', \App\Utils::normalizeAddress(''));
        $this->assertSame('', \App\Utils::normalizeAddress(null));
        $this->assertSame('test', \App\Utils::normalizeAddress('TEST'));
        $this->assertSame('test@domain.tld', \App\Utils::normalizeAddress('Test@Domain.TLD'));
        $this->assertSame('test@domain.tld', \App\Utils::normalizeAddress('Test+Trash@Domain.TLD'));

        $this->assertSame(['', ''], \App\Utils::normalizeAddress('', true));
        $this->assertSame(['', ''], \App\Utils::normalizeAddress(null, true));
        $this->assertSame(['test', ''], \App\Utils::normalizeAddress('TEST', true));
        $this->assertSame(['test', 'domain.tld'], \App\Utils::normalizeAddress('Test@Domain.TLD', true));
        $this->assertSame(['test', 'domain.tld'], \App\Utils::normalizeAddress('Test+Trash@Domain.TLD', true));
    }

    /**
     * Test for Utils::powerSet()
     */
    public function testPowerSet(): void
    {
        $set = [];

        $result = \App\Utils::powerSet($set);

        $this->assertIsArray($result);
        $this->assertCount(0, $result);

        $set = ["a1"];

        $result = \App\Utils::powerSet($set);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertTrue(in_array(["a1"], $result));

        $set = ["a1", "a2"];

        $result = \App\Utils::powerSet($set);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertTrue(in_array(["a1"], $result));
        $this->assertTrue(in_array(["a2"], $result));
        $this->assertTrue(in_array(["a1", "a2"], $result));

        $set = ["a1", "a2", "a3"];

        $result = \App\Utils::powerSet($set);

        $this->assertIsArray($result);
        $this->assertCount(7, $result);
        $this->assertTrue(in_array(["a1"], $result));
        $this->assertTrue(in_array(["a2"], $result));
        $this->assertTrue(in_array(["a3"], $result));
        $this->assertTrue(in_array(["a1", "a2"], $result));
        $this->assertTrue(in_array(["a1", "a3"], $result));
        $this->assertTrue(in_array(["a2", "a3"], $result));
        $this->assertTrue(in_array(["a1", "a2", "a3"], $result));
    }

    /**
     * Test for Utils::serviceUrl()
     */
    public function testServiceUrl(): void
    {
        $public_href = 'https://public.url/cockpit';
        $local_href = 'https://local.url/cockpit';

        \config([
            'app.url' => $local_href,
            'app.public_url' => '',
        ]);

        $this->assertSame($local_href, Utils::serviceUrl(''));
        $this->assertSame($local_href . '/unknown', Utils::serviceUrl('unknown'));
        $this->assertSame($local_href . '/unknown', Utils::serviceUrl('/unknown'));

        \config([
            'app.url' => $local_href,
            'app.public_url' => $public_href,
        ]);

        $this->assertSame($public_href, Utils::serviceUrl(''));
        $this->assertSame($public_href . '/unknown', Utils::serviceUrl('unknown'));
        $this->assertSame($public_href . '/unknown', Utils::serviceUrl('/unknown'));
    }

    /**
     * Test for Utils::uuidInt()
     */
    public function testUuidInt(): void
    {
        $result = Utils::uuidInt();

        $this->assertTrue(is_int($result));
        $this->assertTrue($result > 0);
    }

    /**
     * Test for Utils::uuidStr()
     */
    public function testUuidStr(): void
    {
        $result = Utils::uuidStr();

        $this->assertTrue(is_string($result));
        $this->assertTrue(strlen($result) === 36);
        $this->assertTrue(preg_match('/[^a-f0-9-]/i', $result) === 0);
    }

    /**
     * Test for Utils::exchangeRate()
     */
    public function testExchangeRate(): void
    {
        $this->assertSame(1.0, Utils::exchangeRate("DUMMY", "dummy"));

        // Exchange rates are volatile, can't test with high accuracy.

        $this->assertTrue(Utils::exchangeRate("CHF", "EUR") >= 0.88);
        //$this->assertEqualsWithDelta(0.90503424978382, Utils::exchangeRate("CHF", "EUR"), PHP_FLOAT_EPSILON);

        $this->assertTrue(Utils::exchangeRate("EUR", "CHF") <= 1.12);
        //$this->assertEqualsWithDelta(1.1049305595217682, Utils::exchangeRate("EUR", "CHF"), PHP_FLOAT_EPSILON);

        $this->expectException(\Exception::class);
        $this->assertSame(1.0, Utils::exchangeRate("CHF", "FOO"));
        $this->expectException(\Exception::class);
        $this->assertSame(1.0, Utils::exchangeRate("FOO", "CHF"));
    }
}

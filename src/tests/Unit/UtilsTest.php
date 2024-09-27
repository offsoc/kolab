<?php

namespace Tests\Unit;

use App\Utils;
use Tests\TestCase;

class UtilsTest extends TestCase
{
    /**
     * Test for Utils::countryForIP()
     */
    public function testCountryForIP(): void
    {
        // Create some network records, the tables might be empty
        \App\IP4Net::where('net_number', inet_pton('127.0.0.0'))->delete();
        \App\IP6Net::where('net_number', inet_pton('2001:db8::ff00:42:0'))->delete();

        $this->assertSame('', Utils::countryForIP('127.0.0.1', ''));
        $this->assertSame('CH', Utils::countryForIP('127.0.0.1'));
        $this->assertSame('', Utils::countryForIP('2001:db8::ff00:42:1', ''));
        $this->assertSame('CH', Utils::countryForIP('2001:db8::ff00:42:1'));

        \App\IP4Net::create([
                'net_number' => '127.0.0.0',
                'net_broadcast' => '127.255.255.255',
                'net_mask' => 8,
                'country' => 'US',
                'rir_name' => 'test',
                'serial' => 1,
        ]);

        \App\IP6Net::create([
                'net_number' => '2001:db8::ff00:42:0',
                'net_broadcast' => \App\Utils::ip6Broadcast('2001:db8::ff00:42:0', 8),
                'net_mask' => 8,
                'country' => 'PL',
                'rir_name' => 'test',
                'serial' => 1,
        ]);

        $this->assertSame('US', Utils::countryForIP('127.0.0.1', ''));
        $this->assertSame('US', Utils::countryForIP('127.0.0.1'));
        $this->assertSame('PL', Utils::countryForIP('2001:db8::ff00:42:1', ''));
        $this->assertSame('PL', Utils::countryForIP('2001:db8::ff00:42:1'));

        \App\IP4Net::where('net_number', inet_pton('127.0.0.0'))->delete();
        \App\IP6Net::where('net_number', inet_pton('2001:db8::ff00:42:0'))->delete();
    }

    /**
     * Test for Utils::defaultView()
     */
    public function testDefaultView(): void
    {
        // Non existing resources or api routes
        $this->get('js/test.js')->assertNotFound()->assertContent('');
        $this->get('vendor/test.js')->assertNotFound()->assertContent('');
        $this->get('themes/unknown/app.css')->assertNotFound()->assertContent('');
        $this->get('api/unknown')->assertNotFound()->assertContent('');

        // Expect a view
        $this->get('dashboard')->assertOk()->assertViewIs('root')->assertViewHas('env');
        $this->get('unknown')->assertOk()->assertViewIs('root')->assertViewHas('env');
    }

    /**
     * Test for Utils::emailToLower()
     */
    public function testEmailToLower(): void
    {
        $this->assertSame('test@test.tld', Utils::emailToLower('test@Test.Tld'));
        $this->assertSame('test@test.tld', Utils::emailToLower('Test@Test.Tld'));
        $this->assertSame('shared+shared/Test@test.tld', Utils::emailToLower('shared+shared/Test@Test.Tld'));
    }

    /**
     * Test for Utils::ensureAclPostPermission()
     */
    public function testEnsureAclPostPermission(): void
    {
        $acl = [];
        $this->assertSame(['anyone, p'], Utils::ensureAclPostPermission($acl));

        $acl = ['anyone, full'];
        $this->assertSame(['anyone, full'], Utils::ensureAclPostPermission($acl));

        $acl = ['anyone, read-only'];
        $this->assertSame(['anyone, lrsp'], Utils::ensureAclPostPermission($acl));

        $acl = ['anyone, read-write'];
        $this->assertSame(['anyone, lrswitednp'], Utils::ensureAclPostPermission($acl));
    }

    /**
     * Test for Utils::isSoftDeletable()
     */
    public function testIsSoftDeletable(): void
    {
        $this->assertTrue(Utils::isSoftDeletable(\App\User::class));
        $this->assertFalse(Utils::isSoftDeletable(\App\Wallet::class));

        $this->assertTrue(Utils::isSoftDeletable(new \App\User()));
        $this->assertFalse(Utils::isSoftDeletable(new \App\Wallet()));
    }

    /**
     * Test for Utils::money()
     */
    public function testMoney(): void
    {
        $this->assertSame('-0,01 CHF', Utils::money(-1, 'CHF'));
        $this->assertSame('0,00 CHF', Utils::money(0, 'CHF'));
        $this->assertSame('1,11 €', Utils::money(111, 'EUR'));
        $this->assertSame('1,00 CHF', Utils::money(100, 'CHF'));
        $this->assertSame('€0.00', Utils::money(0, 'EUR', 'en_US'));
    }

    /**
     * Test for Utils::percent()
     */
    public function testPercent(): void
    {
        $this->assertSame('0 %', Utils::percent(0));
        $this->assertSame('10 %', Utils::percent(10.0));
        $this->assertSame('10,12 %', Utils::percent(10.12));
    }

    /**
     * Test for Utils::normalizeAddress()
     */
    public function testNormalizeAddress(): void
    {
        $this->assertSame('', Utils::normalizeAddress(''));
        $this->assertSame('', Utils::normalizeAddress(null));
        $this->assertSame('test', Utils::normalizeAddress('TEST'));
        $this->assertSame('test@domain.tld', Utils::normalizeAddress('Test@Domain.TLD'));
        $this->assertSame('test@domain.tld', Utils::normalizeAddress('Test+Trash@Domain.TLD'));

        $this->assertSame(['', ''], Utils::normalizeAddress('', true));
        $this->assertSame(['', ''], Utils::normalizeAddress(null, true));
        $this->assertSame(['test', ''], Utils::normalizeAddress('TEST', true));
        $this->assertSame(['test', 'domain.tld'], Utils::normalizeAddress('Test@Domain.TLD', true));
        $this->assertSame(['test', 'domain.tld'], Utils::normalizeAddress('Test+Trash@Domain.TLD', true));
    }

    /**
     * Test for Tests\Utils::powerSet()
     */
    public function testPowerSet(): void
    {
        $set = [];

        $result = \Tests\Utils::powerSet($set);

        $this->assertIsArray($result);
        $this->assertCount(0, $result);

        $set = ["a1"];

        $result = \Tests\Utils::powerSet($set);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertTrue(in_array(["a1"], $result));

        $set = ["a1", "a2"];

        $result = \Tests\Utils::powerSet($set);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertTrue(in_array(["a1"], $result));
        $this->assertTrue(in_array(["a2"], $result));
        $this->assertTrue(in_array(["a1", "a2"], $result));

        $set = ["a1", "a2", "a3"];

        $result = \Tests\Utils::powerSet($set);

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

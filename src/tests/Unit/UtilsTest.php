<?php

namespace Tests\Unit;

use App\Utils;
use Tests\TestCase;

class UtilsTest extends TestCase
{
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
}

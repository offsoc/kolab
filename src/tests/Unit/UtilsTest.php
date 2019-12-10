<?php

namespace Tests\Unit;

use App\Utils;

use Tests\TestCase;

class UtilsTest extends TestCase
{
    /**
     * Test for Utils::powerSet()
     *
     * @return void
     */
    public function testPowerSet()
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
     * Test for Utils::uuidInt()
     *
     * @return void
     */
    public function testUuidInt()
    {
        $result = Utils::uuidInt();

        $this->assertTrue(is_int($result));
        $this->assertTrue($result > 0);
    }

    /**
     * Test for Utils::uuidStr()
     *
     * @return void
     */
    public function testUuidStr()
    {
        $result = Utils::uuidStr();

        $this->assertTrue(is_string($result));
        $this->assertTrue(strlen($result) === 36);
        $this->assertTrue(preg_match('/[^a-f0-9-]/i', $result) === 0);
    }
}

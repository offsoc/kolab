<?php

namespace Tests\Unit\Rules;

use App\Rules\ResourceName;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ResourceNameTest extends TestCase
{
    /**
     * Tests the resource name validator
     */
    public function testValidation(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $rules = ['name' => ['present', new ResourceName($user, 'kolab.org')]];

        // Empty/invalid input
        $v = Validator::make(['name' => null], $rules);
        $this->assertSame(['name' => ["The specified name is invalid."]], $v->errors()->toArray());

        $v = Validator::make(['name' => []], $rules);
        $this->assertSame(['name' => ["The specified name is invalid."]], $v->errors()->toArray());

        // Forbidden chars
        $v = Validator::make(['name' => 'Test@'], $rules);
        $this->assertSame(['name' => ["The specified name is invalid."]], $v->errors()->toArray());

        // Length limit
        $v = Validator::make(['name' => str_repeat('a', 192)], $rules);
        $this->assertSame(['name' => ["The name may not be greater than 191 characters."]], $v->errors()->toArray());

        // Existing resource
        $v = Validator::make(['name' => 'Conference Room #1'], $rules);
        $this->assertSame(['name' => ["The specified name is not available."]], $v->errors()->toArray());

        // Valid name
        $v = Validator::make(['name' => 'TestRule'], $rules);
        $this->assertSame([], $v->errors()->toArray());

        // Invalid domain
        $rules = ['name' => ['present', new ResourceName($user, 'kolabnow.com')]];
        $v = Validator::make(['name' => 'TestRule'], $rules);
        $this->assertSame(['name' => ["The specified domain is invalid."]], $v->errors()->toArray());
    }
}

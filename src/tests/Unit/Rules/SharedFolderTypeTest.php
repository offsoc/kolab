<?php

namespace Tests\Unit\Rules;

use App\Rules\SharedFolderType;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SharedFolderTypeTest extends TestCase
{
    /**
     * Tests the shared folder type validator
     */
    public function testValidation(): void
    {
        $rules = ['type' => ['present', new SharedFolderType()]];

        // Empty/invalid input
        $v = Validator::make(['type' => null], $rules);
        $this->assertSame(['type' => ["The specified type is invalid."]], $v->errors()->toArray());

        $v = Validator::make(['type' => []], $rules);
        $this->assertSame(['type' => ["The specified type is invalid."]], $v->errors()->toArray());

        $v = Validator::make(['type' => 'Test'], $rules);
        $this->assertSame(['type' => ["The specified type is invalid."]], $v->errors()->toArray());

        // Valid type
        foreach (\App\SharedFolder::SUPPORTED_TYPES as $type) {
            $v = Validator::make(['type' => $type], $rules);
            $this->assertSame([], $v->errors()->toArray());
        }
    }
}

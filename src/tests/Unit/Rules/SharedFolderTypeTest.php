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

        // Types list configuration
        \config(['app.shared_folder_types' => ['mail']]);
        $v = Validator::make(['type' => 'mail'], $rules);
        $this->assertSame([], $v->errors()->toArray());
        $v = Validator::make(['type' => 'event'], $rules);
        $this->assertSame(['type' => ["The specified type is invalid."]], $v->errors()->toArray());

        \config(['app.shared_folder_types' => ['mail', 'event']]);
        $v = Validator::make(['type' => 'event'], $rules);
        $this->assertSame([], $v->errors()->toArray());
    }
}

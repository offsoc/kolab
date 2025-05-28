<?php

namespace Tests\Unit\Rules;

use App\Rules\ExternalEmail;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ExternalEmailTest extends TestCase
{
    /**
     * Test external email validation
     *
     * @dataProvider provideExternalEmailCases
     */
    public function testExternalEmail($email, $expected_result): void
    {
        // Instead of doing direct tests, we use validator to make sure
        // it works with the framework api
        $v = Validator::make(
            ['email' => $email],
            ['email' => [new ExternalEmail()]]
        );

        $result = null;
        if ($v->fails()) {
            $result = $v->errors()->toArray()['email'][0];
        }

        $this->assertSame($expected_result, $result);
    }

    /**
     * List of email address validation cases for testExternalEmail()
     *
     * @return array Arguments for testExternalEmail()
     */
    public static function provideExternalEmailCases(): iterable
    {
        return [
            // invalid
            ['example.org', 'The specified email address is invalid.'],
            ['@example.org', 'The specified email address is invalid.'],
            ['test@localhost', 'The specified email address is invalid.'],
            ['nick@mykolab..com', 'The specified email address is invalid.'],
            ['nick..test@mykolab.com', 'The specified email address is invalid.'],
            ['test@123.123.123.123', 'The specified email address is invalid.'],
            ['test@2001:db8::8a2e:370:7334', 'The specified email address is invalid.'],
            // FIXME: empty - valid ??????
            ['', null],
            // valid
            ['test@domain.tld', null],
            ['&@example.org', null],
            ['test@example123.com', null],
            ['test@123.com', null],
        ];
    }
}

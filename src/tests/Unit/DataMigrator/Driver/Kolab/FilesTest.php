<?php

namespace Tests\Unit\DataMigrator\Driver\Kolab;

use App\DataMigrator\Driver\Kolab\Files;
use Tests\TestCase;

class FilesTest extends TestCase
{
    /**
     * Test attachment part headers processing
     */
    public function testParseHeaders(): void
    {
        $files = new Files();

        $headers = "Content-ID: <ko.1732537341.48230.odt>\r\n"
            . "Content-Transfer-Encoding: base64\r\n"
            . "Content-Type: application/vnd.oasis.opendocument.odt;\r\n"
            . " name*=UTF-8''&ko%C5%9B%C4%87.txt\r\n"
            . "Content-Disposition: attachment;\r\n"
            . " filename*=UTF-8''&ko%C5%9B%C4%87.odt;\r\n"
            . " size=30068\r\n";

        $result = $this->invokeMethod($files, 'parseHeaders', [$headers]);
        $this->assertSame('application/vnd.oasis.opendocument.odt', $result[0]);
        $this->assertSame('&kość.odt', $result[1]);
        $this->assertSame(30068, $result[2]);
        $this->assertSame('base64', $result[3]);

        $headers = "Content-ID: <ko.1732537341.48230.odt>\r\n"
            . "Content-Transfer-Encoding: quoted-printable\r\n"
            . "Content-Type: text/plain;\r\n"
            . " name*0*=UTF-8''a%20very%20long%20name%20for%20the%20attachment%20to%20tes;\r\n"
            . " name*1*=t%20%C4%87%C4%87%C4%87%20will%20see%20how%20it%20goes%20with%20so;\r\n"
            . " name*2*=me%20non-ascii%20ko%C5%9B%C4%87%20characters.txt\r\n"
            . "Content-Disposition: attachment;\r\n"
            . " filename*0*=UTF-8''a%20very%20long%20name%20for%20the%20attachment%20to;\r\n"
            . " filename*1*=%20test%20%C4%87%C4%87%C4%87%20will%20see%20how%20it%20goes;\r\n"
            . " filename*2*=%20with%20some%20non-ascii%20ko%C5%9B%C4%87%20characters.txt\r\n";

        $result = $this->invokeMethod($files, 'parseHeaders', [$headers]);
        $this->assertSame('text/plain', $result[0]);
        $this->assertSame('a very long name for the attachment to test ććć will see how '
            . 'it goes with some non-ascii kość characters.txt', $result[1]);
        $this->assertSame(null, $result[2]);
        $this->assertSame('quoted-printable', $result[3]);

        $headers = "Content-Type: text/plain; name=test.txt\r\n"
            . "Content-Disposition: attachment; filename=test.txt\r\n";

        $result = $this->invokeMethod($files, 'parseHeaders', [$headers]);
        $this->assertSame('text/plain', $result[0]);
        $this->assertSame('test.txt', $result[1]);
        $this->assertSame(null, $result[2]);
        $this->assertSame(null, $result[3]);
    }
}

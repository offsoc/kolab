<?php

namespace Tests\Feature\Backends;

use App\Backends\IMAP;
use Tests\TestCase;

class IMAPTest extends TestCase
{
    /**
     * Test verifying IMAP account existence (existing account)
     *
     * @group imap
     */
    public function testVerifyAccountExisting(): void
    {
        // existing user
        $result = IMAP::verifyAccount('john@kolab.org');
        $this->assertTrue($result);

        // non-existing user
        $this->expectException(\Exception::class);
        IMAP::verifyAccount('non-existing@domain.tld');
    }

    /**
     * Test verifying IMAP shared folder existence
     *
     * @group imap
     */
    public function testVerifySharedFolder(): void
    {
        // non-existing
        $result = IMAP::verifySharedFolder('shared/Resources/UnknownResource@kolab.org');
        $this->assertFalse($result);

        // existing
        $result = IMAP::verifySharedFolder('shared/Calendar@kolab.org');
        $this->assertTrue($result);
    }
}

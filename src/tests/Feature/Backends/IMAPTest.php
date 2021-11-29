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
        $result = IMAP::verifyAccount('john@kolab.org');

        // TODO: Mocking rcube_imap_generic is not that nice,
        //       Find a way to be sure some testing account has folders
        //       initialized, and some other not, so we can make assertions
        //       on the verifyAccount() result

        $this->markTestIncomplete();
    }

    /**
     * Test verifying IMAP account existence (non-existing account)
     *
     * @group imap
     */
    public function testVerifyAccountNonExisting(): void
    {
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
        $result = IMAP::verifySharedFolder('shared/Resources/UnknownResource@kolab.org');
        $this->assertFalse($result);

        // TODO: Test with an existing shared folder
        $this->markTestIncomplete();
    }
}

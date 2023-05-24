<?php

namespace Tests\Infrastructure;

use App\Backends\IMAP;
use Tests\TestCase;

class IMAPTest extends TestCase
{
    private static ?\App\User $user = null;
    private $imap = null;

    /**
     * Get configured/initialized rcube_imap_generic instance
     */
    private function getImap()
    {
        if ($this->imap) {
            return $this->imap;
        }

        $class = new \ReflectionClass(IMAP::class);
        $init = $class->getMethod('initIMAP');
        $config = $class->getMethod('getConfig');
        $init->setAccessible(true);
        $config->setAccessible(true);

        $config = $config->invoke(null);

        return $this->imap = $init->invokeArgs(null, [$config]);
    }

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        if (!self::$user) {
            self::$user = $this->getTestUser('imaptest@kolab.org', ['password' => 'simple123'], true);
        }
    }

    public function testCreateDeleteFolder()
    {
        $imap = $this->getImap();

        $email = self::$user->email;
        $mailbox = "user/{$email}/test";

        // $this->assertTrue($imap->createFolder($mailbox));
        $imap->createFolder($mailbox);
        $this->assertEquals(1, count($imap->listMailboxes('', $mailbox)));
        $imap->setACL($mailbox, 'cyrus-admin', 'c');
        $this->assertTrue($imap->setMetadata($mailbox, [
            '/shared/vendor/kolab/folder-type' => 'event',
            '/private/vendor/kolab/folder-type' => 'event'
        ]));


        $metadata = $imap->getMetadata($mailbox, [
            '/shared/vendor/kolab/folder-type',
            '/private/vendor/kolab/folder-type'
        ]);

        print_r($metadata);
        $this->assertEquals('event', $metadata[$mailbox]['/shared/vendor/kolab/folder-type']);
        $this->assertEquals('event', $metadata[$mailbox]['/private/vendor/kolab/folder-type']);

        $this->assertTrue($imap->deleteFolder($mailbox));
        $this->assertEquals(0, count($imap->listMailboxes('', $mailbox)));
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testCleanup(): void
    {
        $this->deleteTestUser(self::$user->email);
    }
}

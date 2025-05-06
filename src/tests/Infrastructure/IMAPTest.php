<?php

namespace Tests\Infrastructure;

use App\Backends\IMAP;
use App\User;
use Tests\TestCase;

/**
 * @group imap
 */
class IMAPTest extends TestCase
{
    private static ?User $user = null;
    private $imap;

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

    protected function setUp(): void
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

        $imap->createFolder($mailbox);
        $this->assertSame(1, count($imap->listMailboxes('', $mailbox)));
        $imap->setACL($mailbox, 'cyrus-admin', 'c');
        $this->assertTrue($imap->setMetadata($mailbox, [
            '/shared/vendor/kolab/folder-type' => 'event',
            '/private/vendor/kolab/folder-type' => 'event',
        ]));

        $metadata = $imap->getMetadata($mailbox, [
            '/shared/vendor/kolab/folder-type',
            '/private/vendor/kolab/folder-type',
        ]);

        $this->assertSame('event', $metadata[$mailbox]['/shared/vendor/kolab/folder-type']);
        $this->assertSame('event', $metadata[$mailbox]['/private/vendor/kolab/folder-type']);

        $this->assertTrue($imap->deleteFolder($mailbox));
        $this->assertSame(0, count($imap->listMailboxes('', $mailbox)));
    }

    /**
     * Test for a Cyrus proxy bug regarding VANISHED modifier
     * that is required by Kolab cache in webmail libkolab plugin
     */
    public function testFetchVanished()
    {
        $imap = $this->getImap();

        $imap->createFolder('Test');

        $this->assertTrue($imap->select('Test'));
        $this->assertSame(['CONDSTORE', 'QRESYNC'], $imap->enable('QRESYNC'));

        $result = $imap->fetch('Test', '1:*', true, ['FLAGS'], $imap->data['HIGHESTMODSEQ'], true);

        // TODO: We check the command failure only, we would have to put some
        // messages in the folder to test the command response is correct
        $this->assertSame([], $result);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testCleanup(): void
    {
        $this->deleteTestUser(self::$user->email);
    }
}

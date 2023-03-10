<?php

namespace Tests\Feature\Backends;

use App\Backends\IMAP;
use App\Backends\LDAP;
use Tests\TestCase;

class IMAPTest extends TestCase
{
    private $imap;
    private $user;
    private $group;
    private $resource;
    private $folder;

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        if ($this->imap) {
            $this->imap->closeConnection();
            $this->imap = null;
        }

        if ($this->user) {
            $this->deleteTestUser($this->user->email);
        }
        if ($this->group) {
            $this->deleteTestGroup($this->group->email);
        }
        if ($this->resource) {
            $this->deleteTestResource($this->resource->email);
        }
        if ($this->folder) {
            $this->deleteTestSharedFolder($this->folder->email);
        }

        parent::tearDown();
    }

    /**
     * Test aclCleanup()
     *
     * @group imap
     * @group ldap
     */
    public function testAclCleanup(): void
    {
        $this->user = $user = $this->getTestUser('test-' . time() . '@kolab.org');
        $this->group = $group = $this->getTestGroup('test-group-' . time() . '@kolab.org');

        // SETACL requires that the user/group exists in LDAP
        LDAP::createUser($user);
        // LDAP::createGroup($group);

        // First, set some ACLs that we'll expect to be removed later
        $imap = $this->getImap();

        $this->assertTrue($imap->setACL('user/john@kolab.org', $user->email, 'lrs'));
        $this->assertTrue($imap->setACL('shared/Resources/Conference Room #1@kolab.org', $user->email, 'lrs'));
/*
        $this->assertTrue($imap->setACL('user/john@kolab.org', $group->name, 'lrs'));
        $this->assertTrue($imap->setACL('shared/Resources/Conference Room #1@kolab.org', $group->name, 'lrs'));
*/
        // Cleanup ACL of a user
        IMAP::aclCleanup($user->email);

        $acl = $imap->getACL('user/john@kolab.org');
        $this->assertTrue(is_array($acl) && !isset($acl[$user->email]));
        $acl = $imap->getACL('shared/Resources/Conference Room #1@kolab.org');
        $this->assertTrue(is_array($acl) && !isset($acl[$user->email]));

/*
        // Cleanup ACL of a group
        IMAP::aclCleanup($group->name, 'kolab.org');

        $acl = $imap->getACL('user/john@kolab.org');
        $this->assertTrue(is_array($acl) && !isset($acl[$user->email]));
        $acl = $imap->getACL('shared/Resources/Conference Room #1@kolab.org');
        $this->assertTrue(is_array($acl) && !isset($acl[$user->email]));
*/
    }

    /**
     * Test creating/updating/deleting an IMAP account
     *
     * @group imap
     */
    public function testUsers(): void
    {
        $this->user = $user = $this->getTestUser('test-' . time() . '@' . \config('app.domain'));
        $storage = \App\Sku::withEnvTenantContext()->where('title', 'storage')->first();
        $user->assignSku($storage, 1, $user->wallets->first());

        // User must be in ldap, so imap auth works
        if (\config('app.with_ldap')) {
            LDAP::createUser($user);
        }

        $expectedQuota = [
            'user/' . $user->email => [
                'storage' => [
                    'used' => 0,
                    'total' => 1048576
                ]
            ]
        ];

        // Create the mailbox
        $result = IMAP::createUser($user);
        $this->assertTrue($result);
        $this->assertTrue(IMAP::verifyAccount($user->email));
        $this->assertTrue(IMAP::verifyDefaultFolders($user->email));

        $imap = $this->getImap();
        $quota = $imap->getQuota('user/' . $user->email);
        $this->assertSame($expectedQuota, $quota['all']);

        // Update the mailbox (increase quota)
        $user->assignSku($storage, 1, $user->wallets->first());
        $expectedQuota['user/' . $user->email]['storage']['total'] = 1048576 * 2;

        $result = IMAP::updateUser($user);
        $this->assertTrue($result);

        $quota = $imap->getQuota('user/' . $user->email);
        $this->assertSame($expectedQuota, $quota['all']);

        // Delete the mailbox
        $result = IMAP::deleteUser($user);
        $this->assertTrue($result);

        $result = IMAP::verifyAccount($user->email);
        $this->assertFalse($result);
        $this->assertFalse(IMAP::verifyDefaultFolders($user->email));
    }

    /**
     * Test creating/updating/deleting a resource
     *
     * @group imap
     */
    public function testResources(): void
    {
        $this->resource = $resource = $this->getTestResource(
            'test-resource-' . time() . '@kolab.org',
            ['name' => 'Resource ©' . time()]
        );

        $resource->setSetting('invitation_policy', 'manual:john@kolab.org');

        // Create the resource
        $this->assertTrue(IMAP::createResource($resource));
        $this->assertTrue(IMAP::verifySharedFolder($imapFolder = $resource->getSetting('folder')));

        $imap = $this->getImap();
        $expectedAcl = ['john@kolab.org' => str_split('lrswipkxtecdn')];
        $acl = $imap->getACL(IMAP::toUTF7($imapFolder));
        $this->assertTrue(is_array($acl) && isset($acl['john@kolab.org']));
        $this->assertSame($expectedAcl['john@kolab.org'], $acl['john@kolab.org']);

        // Update the resource (rename)
        $resource->name = 'Resource1 ©' . time();
        $resource->save();
        $newImapFolder = $resource->getSetting('folder');

        $this->assertTrue(IMAP::updateResource($resource, ['folder' => $imapFolder]));
        $this->assertTrue($imapFolder != $newImapFolder);
        $this->assertTrue(IMAP::verifySharedFolder($newImapFolder));
        $acl = $imap->getACL(IMAP::toUTF7($newImapFolder));
        $this->assertTrue(is_array($acl) && isset($acl['john@kolab.org']));
        $this->assertSame($expectedAcl['john@kolab.org'], $acl['john@kolab.org']);

        // Update the resource (acl change)
        $resource->setSetting('invitation_policy', 'accept');
        $this->assertTrue(IMAP::updateResource($resource));
        $this->assertSame([], $imap->getACL(IMAP::toUTF7($newImapFolder)));

        // Delete the resource
        $this->assertTrue(IMAP::deleteResource($resource));
        $this->assertFalse(IMAP::verifySharedFolder($newImapFolder));
    }

    /**
     * Test creating/updating/deleting a shared folder
     *
     * @group imap
     */
    public function testSharedFolders(): void
    {
        $this->folder = $folder = $this->getTestSharedFolder(
            'test-folder-' . time() . '@kolab.org',
            ['name' => 'SharedFolder ©' . time()]
        );

        $folder->setSetting('acl', json_encode(['john@kolab.org, full', 'jack@kolab.org, read-only']));

        // Create the shared folder
        $this->assertTrue(IMAP::createSharedFolder($folder));
        $this->assertTrue(IMAP::verifySharedFolder($imapFolder = $folder->getSetting('folder')));

        $imap = $this->getImap();
        $expectedAcl = [
            'john@kolab.org' => str_split('lrswipkxtecdn'),
            'jack@kolab.org' => str_split('lrs')
        ];

        $acl = $imap->getACL(IMAP::toUTF7($imapFolder));
        $this->assertTrue(is_array($acl) && isset($acl['john@kolab.org']));
        $this->assertSame($expectedAcl['john@kolab.org'], $acl['john@kolab.org']);
        $this->assertTrue(is_array($acl) && isset($acl['jack@kolab.org']));
        $this->assertSame($expectedAcl['jack@kolab.org'], $acl['jack@kolab.org']);

        // Update shared folder (acl)
        $folder->setSetting('acl', json_encode(['jack@kolab.org, read-only']));

        $this->assertTrue(IMAP::updateSharedFolder($folder));

        $expectedAcl = ['jack@kolab.org' => str_split('lrs')];

        $acl = $imap->getACL(IMAP::toUTF7($imapFolder));
        $this->assertTrue(is_array($acl) && isset($acl['jack@kolab.org']));
        $this->assertSame($expectedAcl['jack@kolab.org'], $acl['jack@kolab.org']);
        $this->assertTrue(!isset($acl['john@kolab.org']));

        // Update the shared folder (rename)
        $folder->name = 'SharedFolder1 ©' . time();
        $folder->save();
        $newImapFolder = $folder->getSetting('folder');

        $this->assertTrue(IMAP::updateSharedFolder($folder, ['folder' => $imapFolder]));
        $this->assertTrue($imapFolder != $newImapFolder);
        $this->assertTrue(IMAP::verifySharedFolder($newImapFolder));

        $acl = $imap->getACL(IMAP::toUTF7($newImapFolder));
        $this->assertTrue(is_array($acl) && isset($acl['jack@kolab.org']));
        $this->assertSame($expectedAcl['jack@kolab.org'], $acl['jack@kolab.org']);

        // Delete the shared folder
        $this->assertTrue(IMAP::deleteSharedFolder($folder));
        $this->assertFalse(IMAP::verifySharedFolder($newImapFolder));
    }

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
        $result = IMAP::verifyAccount('non-existing@domain.tld');
        $this->assertFalse($result);
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
}

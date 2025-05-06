<?php

namespace Tests\Feature;

use App\Entitlement;
use App\Jobs\SharedFolder\CreateJob;
use App\Jobs\SharedFolder\DeleteJob;
use App\Jobs\SharedFolder\UpdateJob;
use App\SharedFolder;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SharedFolderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('user-test@kolabnow.com');
        SharedFolder::withTrashed()->where('email', 'like', '%@kolabnow.com')->each(function ($folder) {
            $this->deleteTestSharedFolder($folder->email);
        });
    }

    protected function tearDown(): void
    {
        $this->deleteTestUser('user-test@kolabnow.com');
        SharedFolder::withTrashed()->where('email', 'like', '%@kolabnow.com')->each(function ($folder) {
            $this->deleteTestSharedFolder($folder->email);
        });

        parent::tearDown();
    }

    /**
     * Tests for AliasesTrait methods
     */
    public function testAliases(): void
    {
        Queue::fake();
        Queue::assertNothingPushed();

        $folder = $this->getTestSharedFolder('folder-test@kolabnow.com');

        $this->assertCount(0, $folder->aliases->all());

        // Add an alias
        $folder->setAliases(['FolderAlias1@kolabnow.com']);

        Queue::assertPushed(UpdateJob::class, 1);

        $aliases = $folder->aliases()->get();

        $this->assertCount(1, $aliases);
        $this->assertSame('folderalias1@kolabnow.com', $aliases[0]->alias);
        $this->assertTrue(SharedFolder::aliasExists('folderalias1@kolabnow.com'));

        // Add another alias
        $folder->setAliases(['FolderAlias1@kolabnow.com', 'FolderAlias2@kolabnow.com']);

        Queue::assertPushed(UpdateJob::class, 2);

        $aliases = $folder->aliases()->orderBy('alias')->get();
        $this->assertCount(2, $aliases);
        $this->assertSame('folderalias1@kolabnow.com', $aliases[0]->alias);
        $this->assertSame('folderalias2@kolabnow.com', $aliases[1]->alias);

        // Remove an alias
        $folder->setAliases(['FolderAlias1@kolabnow.com']);

        Queue::assertPushed(UpdateJob::class, 3);

        $aliases = $folder->aliases()->get();

        $this->assertCount(1, $aliases);
        $this->assertSame('folderalias1@kolabnow.com', $aliases[0]->alias);
        $this->assertFalse(SharedFolder::aliasExists('folderalias2@kolabnow.com'));

        // Remove all aliases
        $folder->setAliases([]);

        Queue::assertPushed(UpdateJob::class, 4);

        $this->assertCount(0, $folder->aliases()->get());
        $this->assertFalse(SharedFolder::aliasExists('folderalias1@kolabnow.com'));
        $this->assertFalse(SharedFolder::aliasExists('folderalias2@kolabnow.com'));
    }

    /**
     * Tests for SharedFolder::assignToWallet()
     */
    public function testAssignToWallet(): void
    {
        $user = $this->getTestUser('user-test@kolabnow.com');
        $folder = $this->getTestSharedFolder('folder-test@kolabnow.com');

        $result = $folder->assignToWallet($user->wallets->first());

        $this->assertSame($folder, $result);
        $this->assertSame(1, $folder->entitlements()->count());
        $this->assertSame('shared-folder', $folder->entitlements()->first()->sku->title);

        // Can't be done twice on the same folder
        $this->expectException(\Exception::class);
        $result->assignToWallet($user->wallets->first());
    }

    /**
     * Test SharedFolder::getConfig() and setConfig() methods
     */
    public function testConfigTrait(): void
    {
        Queue::fake();

        $folder = new SharedFolder();
        $folder->email = 'folder-test@kolabnow.com';
        $folder->name = 'Test';
        $folder->save();
        $john = $this->getTestUser('john@kolab.org');
        $folder->assignToWallet($john->wallets->first());

        $this->assertSame(['acl' => []], $folder->getConfig());

        $result = $folder->setConfig(['acl' => ['anyone, read-only'], 'unknown' => false]);

        $this->assertSame(['acl' => ['anyone, read-only']], $folder->getConfig());
        $this->assertSame('["anyone, read-only"]', $folder->getSetting('acl'));
        $this->assertSame(['unknown' => "The requested configuration parameter is not supported."], $result);

        $result = $folder->setConfig(['acl' => ['anyone, unknown']]);

        $this->assertSame(['acl' => ['anyone, read-only']], $folder->getConfig());
        $this->assertSame('["anyone, read-only"]', $folder->getSetting('acl'));
        $this->assertSame(['acl' => ["The entry format is invalid. Expected an email address."]], $result);

        // Test valid user for ACL
        $result = $folder->setConfig(['acl' => ['john@kolab.org, full']]);

        $this->assertSame(['acl' => ['john@kolab.org, full']], $folder->getConfig());
        $this->assertSame('["john@kolab.org, full"]', $folder->getSetting('acl'));
        $this->assertSame([], $result);

        // Test invalid user for ACL
        $result = $folder->setConfig(['acl' => ['john, full']]);

        $this->assertSame(['acl' => ['john@kolab.org, full']], $folder->getConfig());
        $this->assertSame('["john@kolab.org, full"]', $folder->getSetting('acl'));
        $this->assertSame(['acl' => ["The specified email address is invalid."]], $result);

        // Other invalid entries
        $acl = [
            // Test non-existing user for ACL
            'unknown@kolab.org, full',
            // Test existing user from a different wallet
            'user@sample-tenant.dev-local, read-only',
            // Valid entry
            'john@kolab.org, read-write',
        ];

        $result = $folder->setConfig(['acl' => $acl]);
        $this->assertCount(2, $result['acl']);
        $this->assertSame("The specified email address does not exist.", $result['acl'][0]);
        $this->assertSame("The specified email address does not exist.", $result['acl'][1]);
        $this->assertSame(['acl' => ['john@kolab.org, full']], $folder->getConfig());
        $this->assertSame('["john@kolab.org, full"]', $folder->getSetting('acl'));
    }

    /**
     * Test creating a shared folder
     */
    public function testCreate(): void
    {
        Queue::fake();

        $folder = new SharedFolder();
        $folder->name = 'Reśo';
        $folder->domainName = 'kolabnow.com';
        $folder->save();

        $this->assertMatchesRegularExpression('/^[0-9]{1,20}$/', (string) $folder->id);
        $this->assertMatchesRegularExpression('/^mail-[0-9]{1,20}@kolabnow\.com$/', $folder->email);
        $this->assertSame('Reśo', $folder->name);
        $this->assertTrue($folder->isNew());
        $this->assertFalse($folder->isActive());
        $this->assertFalse($folder->isDeleted());
        $this->assertFalse($folder->isLdapReady());
        $this->assertFalse($folder->isImapReady());

        $settings = $folder->settings()->get();
        $this->assertCount(1, $settings);
        $this->assertSame('folder', $settings[0]->key);
        $this->assertSame('shared/Reśo@kolabnow.com', $settings[0]->value);

        Queue::assertPushed(
            CreateJob::class,
            static function ($job) use ($folder) {
                $folderEmail = TestCase::getObjectProperty($job, 'folderEmail');
                $folderId = TestCase::getObjectProperty($job, 'folderId');

                return $folderEmail === $folder->email
                    && $folderId === $folder->id;
            }
        );
    }

    /**
     * Test a shared folder deletion and force-deletion
     */
    public function testDelete(): void
    {
        Queue::fake();

        $user = $this->getTestUser('user-test@kolabnow.com');
        $folder = $this->getTestSharedFolder('folder-test@kolabnow.com');
        $folder->assignToWallet($user->wallets->first());

        $entitlements = Entitlement::where('entitleable_id', $folder->id);

        $this->assertSame(1, $entitlements->count());

        $folder->delete();

        $this->assertTrue($folder->fresh()->trashed());
        $this->assertSame(0, $entitlements->count());
        $this->assertSame(1, $entitlements->withTrashed()->count());

        Queue::assertPushed(UpdateJob::class, 0);
        Queue::assertPushed(DeleteJob::class, 1);
        Queue::assertPushed(
            DeleteJob::class,
            static function ($job) use ($folder) {
                $folderEmail = TestCase::getObjectProperty($job, 'folderEmail');
                $folderId = TestCase::getObjectProperty($job, 'folderId');

                return $folderEmail === $folder->email
                    && $folderId === $folder->id;
            }
        );

        Queue::fake();

        $folder->forceDelete();

        $this->assertSame(0, $entitlements->withTrashed()->count());
        $this->assertCount(0, SharedFolder::withTrashed()->where('id', $folder->id)->get());

        Queue::assertPushed(UpdateJob::class, 0);
        Queue::assertPushed(DeleteJob::class, 0);
    }

    /**
     * Tests for SharedFolder::emailExists()
     */
    public function testEmailExists(): void
    {
        Queue::fake();

        $folder = $this->getTestSharedFolder('folder-test@kolabnow.com');

        $this->assertFalse(SharedFolder::emailExists('unknown@domain.tld'));
        $this->assertTrue(SharedFolder::emailExists($folder->email));

        $result = SharedFolder::emailExists($folder->email, true);
        $this->assertSame($result->id, $folder->id);

        $folder->delete();

        $this->assertTrue(SharedFolder::emailExists($folder->email));

        $result = SharedFolder::emailExists($folder->email, true);
        $this->assertSame($result->id, $folder->id);
    }

    /**
     * Tests for SettingsTrait functionality and SharedFolderSettingObserver
     */
    public function testSettings(): void
    {
        Queue::fake();
        Queue::assertNothingPushed();

        $folder = $this->getTestSharedFolder('folder-test@kolabnow.com');

        Queue::assertPushed(UpdateJob::class, 0);

        // Add a setting
        $folder->setSetting('unknown', 'test');

        Queue::assertPushed(UpdateJob::class, 0);

        // Add a setting that is synced to LDAP
        $folder->setSetting('acl', 'test');

        Queue::assertPushed(UpdateJob::class, 1);
        Queue::assertPushed(
            UpdateJob::class,
            static function ($job) use ($folder) {
                return $folder->id === TestCase::getObjectProperty($job, 'folderId')
                    && ['acl' => null] === TestCase::getObjectProperty($job, 'properties');
            }
        );

        // Note: We test both current folder as well as fresh folder object
        //       to make sure cache works as expected
        $this->assertSame('test', $folder->getSetting('unknown'));
        $this->assertSame('test', $folder->fresh()->getSetting('acl'));

        Queue::fake();

        // Update a setting
        $folder->setSetting('unknown', 'test1');

        Queue::assertPushed(UpdateJob::class, 0);

        // Update a setting that is synced to LDAP
        $folder->setSetting('acl', 'test1');

        Queue::assertPushed(UpdateJob::class, 1);
        Queue::assertPushed(
            UpdateJob::class,
            static function ($job) use ($folder) {
                return $folder->id === TestCase::getObjectProperty($job, 'folderId')
                    && ['acl' => 'test'] === TestCase::getObjectProperty($job, 'properties');
            }
        );

        $this->assertSame('test1', $folder->getSetting('unknown'));
        $this->assertSame('test1', $folder->fresh()->getSetting('acl'));

        Queue::fake();

        // Delete a setting (null)
        $folder->setSetting('unknown', null);

        Queue::assertPushed(UpdateJob::class, 0);

        // Delete a setting that is synced to LDAP
        $folder->setSetting('acl', null);

        Queue::assertPushed(UpdateJob::class, 1);
        Queue::assertPushed(
            UpdateJob::class,
            static function ($job) use ($folder) {
                return $folder->id === TestCase::getObjectProperty($job, 'folderId')
                    && ['acl' => 'test1'] === TestCase::getObjectProperty($job, 'properties');
            }
        );

        $this->assertNull($folder->getSetting('unknown'));
        $this->assertNull($folder->fresh()->getSetting('acl'));
    }

    /**
     * Test updating a shared folder
     */
    public function testUpdate(): void
    {
        Queue::fake();

        $folder = $this->getTestSharedFolder('folder-test@kolabnow.com');

        $folder->name = 'New';
        $folder->save();

        // Assert the imap folder changes on a folder name change
        $settings = $folder->settings()->where('key', 'folder')->get();
        $this->assertCount(1, $settings);
        $this->assertSame('shared/New@kolabnow.com', $settings[0]->value);

        Queue::assertPushed(UpdateJob::class, 1);
        Queue::assertPushed(
            UpdateJob::class,
            static function ($job) use ($folder) {
                $folderEmail = TestCase::getObjectProperty($job, 'folderEmail');
                $folderId = TestCase::getObjectProperty($job, 'folderId');

                return $folderEmail === $folder->email
                    && $folderId === $folder->id;
            }
        );
    }
}

<?php

namespace Tests\Feature;

use App\Resource;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ResourceTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('user-test@kolabnow.com');
        Resource::withTrashed()->where('email', 'like', '%@kolabnow.com')->each(function ($resource) {
            $this->deleteTestResource($resource->email);
        });
    }

    public function tearDown(): void
    {
        $this->deleteTestUser('user-test@kolabnow.com');
        Resource::withTrashed()->where('email', 'like', '%@kolabnow.com')->each(function ($resource) {
            $this->deleteTestResource($resource->email);
        });

        parent::tearDown();
    }

    /**
     * Tests for Resource::assignToWallet()
     */
    public function testAssignToWallet(): void
    {
        $user = $this->getTestUser('user-test@kolabnow.com');
        $resource = $this->getTestResource('resource-test@kolabnow.com');

        $result = $resource->assignToWallet($user->wallets->first());

        $this->assertSame($resource, $result);
        $this->assertSame(1, $resource->entitlements()->count());

        // Can't be done twice on the same resource
        $this->expectException(\Exception::class);
        $result->assignToWallet($user->wallets->first());
    }

    /**
     * Test Resource::getConfig() and setConfig() methods
     */
    public function testConfigTrait(): void
    {
        Queue::fake();

        $resource = new Resource();
        $resource->email = 'resource-test@kolabnow.com';
        $resource->name = 'Test';
        $resource->save();
        $john = $this->getTestUser('john@kolab.org');
        $resource->assignToWallet($john->wallets->first());

        $this->assertSame(['invitation_policy' => 'accept'], $resource->getConfig());

        $result = $resource->setConfig(['invitation_policy' => 'reject', 'unknown' => false]);

        $this->assertSame(['invitation_policy' => 'reject'], $resource->getConfig());
        $this->assertSame('reject', $resource->getSetting('invitation_policy'));
        $this->assertSame(['unknown' => "The requested configuration parameter is not supported."], $result);

        $result = $resource->setConfig(['invitation_policy' => 'unknown']);

        $this->assertSame(['invitation_policy' => 'reject'], $resource->getConfig());
        $this->assertSame('reject', $resource->getSetting('invitation_policy'));
        $this->assertSame(['invitation_policy' => "The specified invitation policy is invalid."], $result);

        // Test valid user for manual invitation policy
        $result = $resource->setConfig(['invitation_policy' => 'manual:john@kolab.org']);

        $this->assertSame(['invitation_policy' => 'manual:john@kolab.org'], $resource->getConfig());
        $this->assertSame('manual:john@kolab.org', $resource->getSetting('invitation_policy'));
        $this->assertSame([], $result);

        // Test invalid user email for manual invitation policy
        $result = $resource->setConfig(['invitation_policy' => 'manual:john']);

        $this->assertSame(['invitation_policy' => 'manual:john@kolab.org'], $resource->getConfig());
        $this->assertSame('manual:john@kolab.org', $resource->getSetting('invitation_policy'));
        $this->assertSame(['invitation_policy' => "The specified email address is invalid."], $result);

        // Test non-existing user for manual invitation policy
        $result = $resource->setConfig(['invitation_policy' => 'manual:unknown@kolab.org']);
        $this->assertSame(['invitation_policy' => "The specified email address does not exist."], $result);

        // Test existing user from a different wallet, for manual invitation policy
        $result = $resource->setConfig(['invitation_policy' => 'manual:user@sample-tenant.dev-local']);
        $this->assertSame(['invitation_policy' => "The specified email address does not exist."], $result);
    }

    /**
     * Test creating a resource
     */
    public function testCreate(): void
    {
        Queue::fake();

        $resource = new Resource();
        $resource->name = 'Reśo';
        $resource->domainName = 'kolabnow.com';
        $resource->save();

        $this->assertMatchesRegularExpression('/^[0-9]{1,20}$/', (string) $resource->id);
        $this->assertMatchesRegularExpression('/^resource-[0-9]{1,20}@kolabnow\.com$/', $resource->email);
        $this->assertSame('Reśo', $resource->name);
        $this->assertTrue($resource->isNew());
        $this->assertFalse($resource->isActive());
        $this->assertFalse($resource->isDeleted());
        $this->assertFalse($resource->isLdapReady());
        $this->assertFalse($resource->isImapReady());

        $settings = $resource->settings()->get();
        $this->assertCount(1, $settings);
        $this->assertSame('folder', $settings[0]->key);
        $this->assertSame('shared/Resources/Reśo@kolabnow.com', $settings[0]->value);

        Queue::assertPushed(
            \App\Jobs\Resource\CreateJob::class,
            function ($job) use ($resource) {
                $resourceEmail = TestCase::getObjectProperty($job, 'resourceEmail');
                $resourceId = TestCase::getObjectProperty($job, 'resourceId');

                return $resourceEmail === $resource->email
                    && $resourceId === $resource->id;
            }
        );
    }

    /**
     * Test resource deletion and force-deletion
     */
    public function testDelete(): void
    {
        Queue::fake();

        $user = $this->getTestUser('user-test@kolabnow.com');
        $resource = $this->getTestResource('resource-test@kolabnow.com');
        $resource->assignToWallet($user->wallets->first());

        $entitlements = \App\Entitlement::where('entitleable_id', $resource->id);

        $this->assertSame(1, $entitlements->count());

        $resource->delete();

        $this->assertTrue($resource->fresh()->trashed());
        $this->assertSame(0, $entitlements->count());
        $this->assertSame(1, $entitlements->withTrashed()->count());

        $resource->forceDelete();

        $this->assertSame(0, $entitlements->withTrashed()->count());
        $this->assertCount(0, Resource::withTrashed()->where('id', $resource->id)->get());

        Queue::assertPushed(\App\Jobs\Resource\DeleteJob::class, 1);
        Queue::assertPushed(
            \App\Jobs\Resource\DeleteJob::class,
            function ($job) use ($resource) {
                $resourceEmail = TestCase::getObjectProperty($job, 'resourceEmail');
                $resourceId = TestCase::getObjectProperty($job, 'resourceId');

                return $resourceEmail === $resource->email
                    && $resourceId === $resource->id;
            }
        );
    }

    /**
     * Tests for Resource::emailExists()
     */
    public function testEmailExists(): void
    {
        Queue::fake();

        $resource = $this->getTestResource('resource-test@kolabnow.com');

        $this->assertFalse(Resource::emailExists('unknown@domain.tld'));
        $this->assertTrue(Resource::emailExists($resource->email));

        $result = Resource::emailExists($resource->email, true);
        $this->assertSame($result->id, $resource->id);

        $resource->delete();

        $this->assertTrue(Resource::emailExists($resource->email));

        $result = Resource::emailExists($resource->email, true);
        $this->assertSame($result->id, $resource->id);
    }

    /**
     * Tests for SettingsTrait functionality and ResourceSettingObserver
     */
    public function testSettings(): void
    {
        Queue::fake();
        Queue::assertNothingPushed();

        $resource = $this->getTestResource('resource-test@kolabnow.com');

        Queue::assertPushed(\App\Jobs\Resource\UpdateJob::class, 0);

        // Add a setting
        $resource->setSetting('unknown', 'test');

        Queue::assertPushed(\App\Jobs\Resource\UpdateJob::class, 0);

        // Add a setting that is synced to LDAP
        $resource->setSetting('invitation_policy', 'accept');

        Queue::assertPushed(\App\Jobs\Resource\UpdateJob::class, 1);
        Queue::assertPushed(
            \App\Jobs\Resource\UpdateJob::class,
            function ($job) use ($resource) {
                return $resource->id === TestCase::getObjectProperty($job, 'resourceId')
                    && ['invitation_policy' => null] === TestCase::getObjectProperty($job, 'properties');
            }
        );

        // Note: We test both current resource as well as fresh resource object
        //       to make sure cache works as expected
        $this->assertSame('test', $resource->getSetting('unknown'));
        $this->assertSame('accept', $resource->fresh()->getSetting('invitation_policy'));

        Queue::fake();

        // Update a setting
        $resource->setSetting('unknown', 'test1');

        Queue::assertPushed(\App\Jobs\Resource\UpdateJob::class, 0);

        // Update a setting that is synced to LDAP
        $resource->setSetting('invitation_policy', 'reject');

        Queue::assertPushed(\App\Jobs\Resource\UpdateJob::class, 1);
        Queue::assertPushed(
            \App\Jobs\Resource\UpdateJob::class,
            function ($job) use ($resource) {
                return $resource->id === TestCase::getObjectProperty($job, 'resourceId')
                    && ['invitation_policy' => 'accept'] === TestCase::getObjectProperty($job, 'properties');
            }
        );

        $this->assertSame('test1', $resource->getSetting('unknown'));
        $this->assertSame('reject', $resource->fresh()->getSetting('invitation_policy'));

        Queue::fake();

        // Delete a setting (null)
        $resource->setSetting('unknown', null);

        Queue::assertPushed(\App\Jobs\Resource\UpdateJob::class, 0);

        // Delete a setting that is synced to LDAP
        $resource->setSetting('invitation_policy', null);

        Queue::assertPushed(\App\Jobs\Resource\UpdateJob::class, 1);
        Queue::assertPushed(
            \App\Jobs\Resource\UpdateJob::class,
            function ($job) use ($resource) {
                return $resource->id === TestCase::getObjectProperty($job, 'resourceId')
                    && ['invitation_policy' => 'reject'] === TestCase::getObjectProperty($job, 'properties');
            }
        );

        $this->assertSame(null, $resource->getSetting('unknown'));
        $this->assertSame(null, $resource->fresh()->getSetting('invitation_policy'));
    }

    /**
     * Test resource status assignment and is*() methods
     */
    public function testStatus(): void
    {
        $resource = new Resource();

        $this->assertSame(false, $resource->isNew());
        $this->assertSame(false, $resource->isActive());
        $this->assertSame(false, $resource->isDeleted());
        $this->assertSame(false, $resource->isLdapReady());
        $this->assertSame(false, $resource->isImapReady());

        $resource->status = Resource::STATUS_NEW;

        $this->assertSame(true, $resource->isNew());
        $this->assertSame(false, $resource->isActive());
        $this->assertSame(false, $resource->isDeleted());
        $this->assertSame(false, $resource->isLdapReady());
        $this->assertSame(false, $resource->isImapReady());

        $resource->status |= Resource::STATUS_ACTIVE;

        $this->assertSame(true, $resource->isNew());
        $this->assertSame(true, $resource->isActive());
        $this->assertSame(false, $resource->isDeleted());
        $this->assertSame(false, $resource->isLdapReady());
        $this->assertSame(false, $resource->isImapReady());

        $resource->status |= Resource::STATUS_LDAP_READY;

        $this->assertSame(true, $resource->isNew());
        $this->assertSame(true, $resource->isActive());
        $this->assertSame(false, $resource->isDeleted());
        $this->assertSame(true, $resource->isLdapReady());
        $this->assertSame(false, $resource->isImapReady());

        $resource->status |= Resource::STATUS_DELETED;

        $this->assertSame(true, $resource->isNew());
        $this->assertSame(true, $resource->isActive());
        $this->assertSame(true, $resource->isDeleted());
        $this->assertSame(true, $resource->isLdapReady());
        $this->assertSame(false, $resource->isImapReady());

        $resource->status |= Resource::STATUS_IMAP_READY;

        $this->assertSame(true, $resource->isNew());
        $this->assertSame(true, $resource->isActive());
        $this->assertSame(true, $resource->isDeleted());
        $this->assertSame(true, $resource->isLdapReady());
        $this->assertSame(true, $resource->isImapReady());

        // Unknown status value
        $this->expectException(\Exception::class);
        $resource->status = 111;
    }

    /**
     * Test updating a resource
     */
    public function testUpdate(): void
    {
        Queue::fake();

        $resource = $this->getTestResource('resource-test@kolabnow.com');

        $resource->name = 'New';
        $resource->save();

        // Assert the folder changes on a resource name change
        $settings = $resource->settings()->where('key', 'folder')->get();
        $this->assertCount(1, $settings);
        $this->assertSame('shared/Resources/New@kolabnow.com', $settings[0]->value);

        Queue::assertPushed(\App\Jobs\Resource\UpdateJob::class, 1);
        Queue::assertPushed(
            \App\Jobs\Resource\UpdateJob::class,
            function ($job) use ($resource) {
                $resourceEmail = TestCase::getObjectProperty($job, 'resourceEmail');
                $resourceId = TestCase::getObjectProperty($job, 'resourceId');

                return $resourceEmail === $resource->email
                    && $resourceId === $resource->id;
            }
        );
    }
}

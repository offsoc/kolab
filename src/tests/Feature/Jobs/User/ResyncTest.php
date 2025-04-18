<?php

namespace Tests\Feature\Jobs\User;

use App\Domain;
use App\Support\Facades\IMAP;
use App\Support\Facades\LDAP;
use App\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ResyncTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('user@resync-job.com');
        $this->deleteTestDomain('resync-job.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('user@resync-job.com');
        $this->deleteTestDomain('resync-job.com');

        parent::tearDown();
    }

    /**
     * Test the job execution
     */
    public function testHandle(): void
    {
        $domain = $this->getTestDomain('resync-job.com', [
            'status' => Domain::STATUS_LDAP_READY | Domain::STATUS_ACTIVE,
            'type' => Domain::TYPE_EXTERNAL,
        ]);

        $user = $this->getTestUser('user@resync-job.com', [
            'status' => User::STATUS_LDAP_READY | User::STATUS_IMAP_READY | User::STATUS_ACTIVE,
        ]);

        \config(['app.with_ldap' => true]);
        \config(['app.with_imap' => true]);

        $this->assertTrue($user->isLdapReady());
        $this->assertTrue($user->isImapReady());
        $this->assertTrue($domain->isLdapReady());

        Queue::fake();

        // Test a user (and custom domain) that both aren't in ldap (despite their status)
        LDAP::shouldReceive('getDomain')->once()->with($domain->namespace)->andReturn(false);
        LDAP::shouldReceive('getUser')->once()->with($user->email)->andReturn(false);
        IMAP::shouldReceive('verifyAccount')->once()->with($user->email)->andReturn(false);

        $job = new \App\Jobs\User\ResyncJob($user->id);
        $job->handle();

        $user->refresh();
        $domain->refresh();

        $this->assertFalse($user->isLdapReady());
        $this->assertFalse($user->isImapReady());
        $this->assertFalse($domain->isLdapReady());

        Queue::assertPushed(\App\Jobs\Domain\CreateJob::class, 1);
        Queue::assertPushed(
            \App\Jobs\Domain\CreateJob::class,
            function ($job) use ($domain) {
                return $domain->id == TestCase::getObjectProperty($job, 'domainId');
            }
        );

        Queue::assertPushed(\App\Jobs\User\CreateJob::class, 1);
        Queue::assertPushed(
            \App\Jobs\User\CreateJob::class,
            function ($job) use ($user) {
                return $user->id == TestCase::getObjectProperty($job, 'userId');
            }
        );

        // TODO: More cases
    }
}

<?php

namespace Tests\Feature\Jobs\User;

use App\Backends\IMAP;
use App\Backends\LDAP;
use App\Domain;
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
     *
     * @group ldap
     * @group imap
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

        if (\config('app.with_ldap')) {
            $this->assertTrue(empty(LDAP::getDomain($domain->namespace)));
            $this->assertTrue(empty(LDAP::getUser($user->email)));
        }

        Queue::fake();

        // Test a user (and custom domain) that both aren't in ldap (despite their status)
        $job = new \App\Jobs\User\ResyncJob($user->id);
        $job->handle();

        $user->refresh();
        $domain->refresh();

        if (\config('app.with_ldap')) {
            $this->assertFalse($user->isLdapReady());
            $this->assertFalse($domain->isLdapReady());

            Queue::assertPushed(\App\Jobs\Domain\CreateJob::class, 1);
            Queue::assertPushed(
                \App\Jobs\Domain\CreateJob::class,
                function ($job) use ($domain) {
                    return $domain->id == TestCase::getObjectProperty($job, 'domainId');
                }
            );
        }

        $this->assertFalse($user->isImapReady());

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

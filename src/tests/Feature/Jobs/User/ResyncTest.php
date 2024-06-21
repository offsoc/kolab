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

        // TODO: Make the test working with various with_imap/with_ldap combinations
        \config(['app.with_imap' => true]);
        \config(['app.with_ldap' => true]);

        $this->assertTrue(empty(LDAP::getDomain($domain->namespace)));
        $this->assertTrue(empty(LDAP::getUser($user->email)));

        // Test a user (and custom domain) that both aren't in ldap (despite their status)
        $job = new \App\Jobs\User\ResyncJob($user->id);
        $job->handle();

        $user->refresh();
        $domain->refresh();

        $this->assertTrue($user->isLdapReady());
        $this->assertTrue($user->isImapReady());
        $this->assertTrue($domain->isLdapReady());

        $this->assertTrue(!empty(LDAP::getDomain($domain->namespace)));
        $this->assertTrue(!empty(LDAP::getUser($user->email)));
        $this->assertTrue(IMAP::verifyAccount($user->email));

        // TODO: More tests cases
    }
}

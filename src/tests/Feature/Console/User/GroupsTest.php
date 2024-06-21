<?php

namespace Tests\Feature\Console\User;

use Tests\TestCase;

class GroupsTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestGroup('group-test@kolab.org');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestGroup('group-test@kolab.org');

        parent::tearDown();
    }

    /**
     * Test command runs
     */
    public function testHandle(): void
    {
        $code = \Artisan::call("user:groups unknown");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("No such user unknown", $output);

        $john = $this->getTestUser('john@kolab.org');
        $group = $this->getTestGroup('group-test@kolab.org');
        $group->assignToWallet($john->wallets->first());

        $code = \Artisan::call("user:groups john@kolab.org --attr=name");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertSame("{$group->id} {$group->name}", $output);
    }
}

<?php

namespace Tests\Feature\Console\Data\Import;

use Tests\TestCase;

class LdifTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('owner@kolab3.com');
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('owner@kolab3.com');

        parent::tearDown();
    }

    /**
     * Test the command
     */
    public function testHandle(): void
    {
        $code = \Artisan::call("data:import:ldif tests/data/kolab3.ldif owner@kolab3.com");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);

        $this->assertStringNotContainsString("Importing", $output);
        $this->assertStringNotContainsString("WARNING", $output);
        $this->assertStringContainsString(
            "ERROR cn=error,ou=groups,ou=kolab3.com,dc=hosted,dc=com: Missing 'mail' attribute",
            $output
        );
        $this->assertStringContainsString(
            "ERROR cn=error,ou=resources,ou=kolab3.com,dc=hosted,dc=com: Missing 'mail' attribute",
            $output
        );

        $code = \Artisan::call("data:import:ldif tests/data/kolab3.ldif owner@kolab3.com --force");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertStringContainsString("Importing domains... DONE", $output);
        $this->assertStringContainsString("Importing users... DONE", $output);
        $this->assertStringContainsString("Importing resources... DONE", $output);
        $this->assertStringContainsString("Importing shared folders... DONE", $output);
        $this->assertStringContainsString("Importing groups... DONE", $output);
        $this->assertStringNotContainsString("ERROR", $output);
        $this->assertStringContainsString(
            "WARNING cn=unknowndomain,ou=groups,ou=kolab3.org,dc=hosted,dc=com: Domain not found",
            $output
        );

        $owner = \App\User::where('email', 'owner@kolab3.com')->first();

        $this->assertNull($owner->password);
        $this->assertSame(
            '{SSHA512}g74+SECTLsM1x0aYkSrTG9sOFzEp8wjCflhshr2DjE7mi1G3iNb4ClH3ljorPRlTgZ105PsQGEpNtNr+XRjigg==',
            $owner->password_ldap
        );

        // User settings
        $this->assertSame('Aleksander', $owner->getSetting('first_name'));
        $this->assertSame('Machniak', $owner->getSetting('last_name'));
        $this->assertSame('123456789', $owner->getSetting('phone'));
        $this->assertSame('external@gmail.com', $owner->getSetting('external_email'));
        $this->assertSame('Organization AG', $owner->getSetting('organization'));

        // User aliases
        $aliases = $owner->aliases()->orderBy('alias')->pluck('alias')->all();
        $this->assertSame(['alias@kolab3-alias.com', 'alias@kolab3.com'], $aliases);

        // Wallet, entitlements
        $wallet = $owner->wallets->first();

        $this->assertEntitlements($owner, [
            'groupware',
            'mailbox',
            'storage', 'storage', 'storage', 'storage', 'storage', 'storage', 'storage', 'storage',
        ]);

        // Users
        $this->assertSame(2, $owner->users(false)->count());
        /** @var \App\User $user */
        $user = $owner->users(false)->where('email', 'user@kolab3.com')->first();

        // User settings
        $this->assertSame('Jane', $user->getSetting('first_name'));
        $this->assertSame('Doe', $user->getSetting('last_name'));
        $this->assertSame('1234567890', $user->getSetting('phone'));
        $this->assertSame('ext@gmail.com', $user->getSetting('external_email'));
        $this->assertSame('Org AG', $user->getSetting('organization'));

        // User aliases
        $aliases = $user->aliases()->orderBy('alias')->pluck('alias')->all();
        $this->assertSame(['alias2@kolab3.com'], $aliases);

        $this->assertEntitlements($user, [
            'groupware',
            'mailbox',
            'storage', 'storage', 'storage', 'storage', 'storage',
        ]);

        // Domains
        /** @var \App\Domain[] $domains */
        $domains = $owner->domains(false, false)->orderBy('namespace')->get();

        $this->assertCount(2, $domains);
        $this->assertSame('kolab3-alias.com', $domains[0]->namespace);
        $this->assertSame('kolab3.com', $domains[1]->namespace);
        $this->assertSame(\App\Domain::TYPE_EXTERNAL, $domains[0]->type);
        $this->assertSame(\App\Domain::TYPE_EXTERNAL, $domains[1]->type);

        $this->assertEntitlements($domains[0], ['domain-hosting']);
        $this->assertEntitlements($domains[1], ['domain-hosting']);

        // Shared folders
        /** @var \App\SharedFolder[] $folders */
        $folders = $owner->sharedFolders(false)->orderBy('email')->get();

        $this->assertCount(2, $folders);
        $this->assertMatchesRegularExpression('/^event-[0-9]+@kolab3\.com$/', $folders[0]->email);
        $this->assertMatchesRegularExpression('/^mail-[0-9]+@kolab3\.com$/', $folders[1]->email);
        $this->assertSame('Folder2', $folders[0]->name);
        $this->assertSame('Folder1', $folders[1]->name);
        $this->assertSame('event', $folders[0]->type);
        $this->assertSame('mail', $folders[1]->type);
        $this->assertSame('["anyone, read-only"]', $folders[0]->getSetting('acl'));
        $this->assertSame('shared/Folder2@kolab3.com', $folders[0]->getSetting('folder'));
        $this->assertSame('["anyone, read-write","owner@kolab3.com, full"]', $folders[1]->getSetting('acl'));
        $this->assertSame('shared/Folder1@kolab3.com', $folders[1]->getSetting('folder'));
        $this->assertSame([], $folders[0]->aliases()->orderBy('alias')->pluck('alias')->all());
        $this->assertSame(
            ['folder-alias1@kolab3.com', 'folder-alias2@kolab3.com'],
            $folders[1]->aliases()->orderBy('alias')->pluck('alias')->all()
        );

        // Groups
        /** @var \App\Group[] $groups */
        $groups = $owner->groups(false)->orderBy('email')->get();

        $this->assertCount(1, $groups);
        $this->assertSame('Group', $groups[0]->name);
        $this->assertSame('group@kolab3.com', $groups[0]->email);
        $this->assertSame(['owner@kolab3.com', 'user@kolab3.com'], $groups[0]->members);
        $this->assertSame('["sender@gmail.com","-"]', $groups[0]->getSetting('sender_policy'));

        // Resources
        /** @var \App\Resource[] $resources */
        $resources = $owner->resources(false)->orderBy('email')->get();

        $this->assertCount(1, $resources);
        $this->assertSame('Resource', $resources[0]->name);
        $this->assertMatchesRegularExpression('/^resource-[0-9]+@kolab3\.com$/', $resources[0]->email);
        $this->assertSame('shared/Resource@kolab3.com', $resources[0]->getSetting('folder'));
        $this->assertSame('manual:user@kolab3.com', $resources[0]->getSetting('invitation_policy'));
    }

    /**
     * Test parseACL() method
     */
    public function testParseACL(): void
    {
        $command = new \App\Console\Commands\Data\Import\LdifCommand();

        $result = $this->invokeMethod($command, 'parseACL', [[]]);
        $this->assertSame([], $result);

        $acl = [
            'anyone, read-write',
            'read-only@kolab3.com, read-only',
            'read-only@kolab3.com, read',
            'full@kolab3.com,full',
            'lrswipkxtecdn@kolab3.com, lrswipkxtecdn', // full
            'lrs@kolab3.com, lrs', // read-only
            'lrswitedn@kolab3.com, lrswitedn', // read-write
            // unsupported:
            'anonymous, read-only',
            'group:test, lrs',
            'test@kolab3.com, lrspkxtdn',
        ];

        $expected = [
            'anyone, read-write',
            'read-only@kolab3.com, read-only',
            'read-only@kolab3.com, read-only',
            'full@kolab3.com, full',
            'lrswipkxtecdn@kolab3.com, full',
            'lrs@kolab3.com, read-only',
            'lrswitedn@kolab3.com, read-write',
        ];

        $result = $this->invokeMethod($command, 'parseACL', [$acl]);
        $this->assertSame($expected, $result);
    }

    /**
     * Test parseInvitationPolicy() method
     */
    public function testParseInvitationPolicy(): void
    {
        $command = new \App\Console\Commands\Data\Import\LdifCommand();

        $result = $this->invokeMethod($command, 'parseInvitationPolicy', [[]]);
        $this->assertSame(null, $result);

        $result = $this->invokeMethod($command, 'parseInvitationPolicy', [['UNKNOWN']]);
        $this->assertSame(null, $result);

        $result = $this->invokeMethod($command, 'parseInvitationPolicy', [['ACT_ACCEPT']]);
        $this->assertSame(null, $result);

        $result = $this->invokeMethod($command, 'parseInvitationPolicy', [['ACT_MANUAL']]);
        $this->assertSame('manual', $result);

        $result = $this->invokeMethod($command, 'parseInvitationPolicy', [['ACT_REJECT']]);
        $this->assertSame('reject', $result);

        $result = $this->invokeMethod($command, 'parseInvitationPolicy', [['ACT_ACCEPT_AND_NOTIFY', 'ACT_REJECT']]);
        $this->assertSame(null, $result);
    }

    /**
     * Test parseSenderPolicy() method
     */
    public function testParseSenderPolicy(): void
    {
        $command = new \App\Console\Commands\Data\Import\LdifCommand();

        $result = $this->invokeMethod($command, 'parseSenderPolicy', [[]]);
        $this->assertSame([], $result);

        $result = $this->invokeMethod($command, 'parseSenderPolicy', [['test']]);
        $this->assertSame(['test', '-'], $result);

        $result = $this->invokeMethod($command, 'parseSenderPolicy', [['test', '-test2', 'test3', '']]);
        $this->assertSame(['test', 'test3', '-'], $result);
    }

    /**
     * Test parseLDAPDomain() method
     */
    public function testParseLDAPDomain(): void
    {
        $command = new \App\Console\Commands\Data\Import\LdifCommand();

        $entry = [];
        $result = $this->invokeMethod($command, 'parseLDAPDomain', [$entry]);
        $this->assertSame([], $result[0]);
        $this->assertSame("Missing 'associatedDomain' attribute", $result[1]);

        $entry = ['associateddomain' => 'test.com'];
        $result = $this->invokeMethod($command, 'parseLDAPDomain', [$entry]);
        $this->assertSame(['namespace' => 'test.com'], $result[0]);
        $this->assertSame(null, $result[1]);

        $entry = ['associateddomain' => 'test.com', 'inetdomainstatus' => 'deleted'];
        $result = $this->invokeMethod($command, 'parseLDAPDomain', [$entry]);
        $this->assertSame([], $result[0]);
        $this->assertSame("Domain deleted", $result[1]);
    }

    /**
     * Test parseLDAPGroup() method
     */
    public function testParseLDAPGroup(): void
    {
        $command = new \App\Console\Commands\Data\Import\LdifCommand();

        $entry = [];
        $result = $this->invokeMethod($command, 'parseLDAPGroup', [$entry]);
        $this->assertSame([], $result[0]);
        $this->assertSame("Missing 'cn' attribute", $result[1]);

        $entry = ['cn' => 'Test'];
        $result = $this->invokeMethod($command, 'parseLDAPGroup', [$entry]);
        $this->assertSame([], $result[0]);
        $this->assertSame("Missing 'mail' attribute", $result[1]);

        $entry = ['cn' => 'Test', 'mail' => 'test@domain.tld'];
        $result = $this->invokeMethod($command, 'parseLDAPGroup', [$entry]);
        $this->assertSame([], $result[0]);
        $this->assertSame("Missing 'uniqueMember' attribute", $result[1]);

        $entry = [
            'cn' => 'Test',
            'mail' => 'Test@domain.tld',
            'uniquemember' => 'uid=user@kolab3.com,ou=People,ou=kolab3.com,dc=hosted,dc=com',
            'kolaballowsmtpsender' => ['sender1@gmail.com', 'sender2@gmail.com'],
        ];

        $expected = [
            'name' => 'Test',
            'email' => 'test@domain.tld',
            'members' => ['uid=user@kolab3.com,ou=People,ou=kolab3.com,dc=hosted,dc=com'],
            'sender_policy' => ['sender1@gmail.com', 'sender2@gmail.com', '-'],
        ];

        $result = $this->invokeMethod($command, 'parseLDAPGroup', [$entry]);
        $this->assertSame($expected, $result[0]);
        $this->assertSame(null, $result[1]);
    }

    /**
     * Test parseLDAPResource() method
     */
    public function testParseLDAPResource(): void
    {
        $command = new \App\Console\Commands\Data\Import\LdifCommand();

        $entry = [];
        $result = $this->invokeMethod($command, 'parseLDAPResource', [$entry]);
        $this->assertSame([], $result[0]);
        $this->assertSame("Missing 'cn' attribute", $result[1]);

        $entry = ['cn' => 'Test'];
        $result = $this->invokeMethod($command, 'parseLDAPResource', [$entry]);
        $this->assertSame([], $result[0]);
        $this->assertSame("Missing 'mail' attribute", $result[1]);

        $entry = [
            'cn' => 'Test',
            'mail' => 'Test@domain.tld',
            'owner' => 'uid=user@kolab3.com,ou=People,ou=kolab3.com,dc=hosted,dc=com',
            'kolabtargetfolder' => 'Folder',
            'kolabinvitationpolicy' => 'ACT_REJECT'
        ];

        $expected = [
            'name' => 'Test',
            'email' => 'test@domain.tld',
            'folder' => 'Folder',
            'owner' => 'uid=user@kolab3.com,ou=People,ou=kolab3.com,dc=hosted,dc=com',
            'invitation_policy' => 'reject',
        ];

        $result = $this->invokeMethod($command, 'parseLDAPResource', [$entry]);
        $this->assertSame($expected, $result[0]);
        $this->assertSame(null, $result[1]);
    }

    /**
     * Test parseLDAPSharedFolder() method
     */
    public function testParseLDAPSharedFolder(): void
    {
        $command = new \App\Console\Commands\Data\Import\LdifCommand();

        $entry = [];
        $result = $this->invokeMethod($command, 'parseLDAPSharedFolder', [$entry]);
        $this->assertSame([], $result[0]);
        $this->assertSame("Missing 'cn' attribute", $result[1]);

        $entry = ['cn' => 'Test'];
        $result = $this->invokeMethod($command, 'parseLDAPSharedFolder', [$entry]);
        $this->assertSame([], $result[0]);
        $this->assertSame("Missing 'mail' attribute", $result[1]);

        $entry = [
            'cn' => 'Test',
            'mail' => 'Test@domain.tld',
            'kolabtargetfolder' => 'Folder',
            'kolabfoldertype' => 'event',
            'acl' => 'anyone, read-write',
            'alias' => ['test1@domain.tld', 'test2@domain.tld'],
        ];

        $expected = [
            'name' => 'Test',
            'email' => 'test@domain.tld',
            'type' => 'event',
            'folder' => 'Folder',
            'acl' => ['anyone, read-write'],
            'aliases' => ['test1@domain.tld', 'test2@domain.tld'],
        ];

        $result = $this->invokeMethod($command, 'parseLDAPSharedFolder', [$entry]);
        $this->assertSame($expected, $result[0]);
        $this->assertSame(null, $result[1]);
    }

    /**
     * Test parseLDAPUser() method
     */
    public function testParseLDAPUser(): void
    {
        // Note: If we do not initialize the command input we'll get an error
        $args = [
            'file' => 'test.ldif',
            'owner' => 'test@domain.tld',
        ];

        $command = new \App\Console\Commands\Data\Import\LdifCommand();
        $command->setInput(new \Symfony\Component\Console\Input\ArrayInput($args, $command->getDefinition()));

        $entry = ['cn' => 'Test'];
        $result = $this->invokeMethod($command, 'parseLDAPUser', [$entry]);
        $this->assertSame([], $result[0]);
        $this->assertSame("Missing 'mail' attribute", $result[1]);

        $entry = [
            'dn' => 'user dn',
            'givenname' => 'Given',
            'mail' => 'Test@domain.tld',
            'sn' => 'Surname',
            'telephonenumber' => '123',
            'o' => 'Org',
            'mailalternateaddress' => 'test@ext.com',
            'alias' => ['test1@domain.tld', 'test2@domain.tld'],
            'userpassword' => 'pass',
            'mailquota' => '12345678',
        ];

        $expected = [
            'email' => 'test@domain.tld',
            'settings' => [
                'first_name' => 'Given',
                'last_name' => 'Surname',
                'phone' => '123',
                'external_email' => 'test@ext.com',
                'organization' => 'Org',
            ],
            'aliases' => ['test1@domain.tld', 'test2@domain.tld'],
            'password' => 'pass',
            'quota' => '12345678',
        ];

        $result = $this->invokeMethod($command, 'parseLDAPUser', [$entry]);
        $this->assertSame($expected, $result[0]);
        $this->assertSame(null, $result[1]);
        $this->assertSame($entry['dn'], $this->getObjectProperty($command, 'ownerDN'));
    }
}

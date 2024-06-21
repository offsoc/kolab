<?php

namespace Tests\Feature\Stories;

use App\Domain;
use App\Policy\Greylist;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * @group data
 * @group greylist
 */
class GreylistTest extends TestCase
{
    private $clientAddress;
    private $net;

    public function setUp(): void
    {
        parent::setUp();

        $this->clientAddress = '212.103.80.148';
        $this->net = \App\IP4Net::getNet($this->clientAddress);

        $this->domainHosted = $this->getTestDomain('test.domain', [
                'type' => Domain::TYPE_EXTERNAL,
                'status' => Domain::STATUS_ACTIVE | Domain::STATUS_CONFIRMED | Domain::STATUS_VERIFIED
        ]);

        $this->domainOwner = $this->getTestUser('john@test.domain');

        Greylist\Connect::where('sender_domain', 'sender.domain')->delete();
        Greylist\Whitelist::where('sender_domain', 'sender.domain')->delete();
    }

    public function tearDown(): void
    {
        Greylist\Connect::where('sender_domain', 'sender.domain')->delete();
        Greylist\Whitelist::where('sender_domain', 'sender.domain')->delete();

        parent::tearDown();
    }

    public function testWithTimestamp()
    {
        $request = new Greylist\Request(
            [
                'sender' => 'someone@sender.domain',
                'recipient' => $this->domainOwner->email,
                'client_address' => $this->clientAddress,
                'client_name' => 'some.mx',
                'timestamp' => \Carbon\Carbon::now()->subDays(7)->toString()
            ]
        );

        $timestamp = $this->getObjectProperty($request, 'timestamp');

        $this->assertTrue(
            \Carbon\Carbon::parse($timestamp, 'UTC') < \Carbon\Carbon::now()
        );
    }

    public function testNoNet()
    {
        $request = new Greylist\Request(
            [
                'sender' => 'someone@sender.domain',
                'recipient' => $this->domainOwner->email,
                'client_address' => '127.128.129.130',
                'client_name' => 'some.mx'
            ]
        );

        $this->assertTrue($request->shouldDefer());
    }

    public function testIp6Net()
    {
        $request = new Greylist\Request(
            [
                'sender' => 'someone@sender.domain',
                'recipient' => $this->domainOwner->email,
                'client_address' => '2a00:1450:400a:803::2005',
                'client_name' => 'some.mx'
            ]
        );

        $this->assertTrue($request->shouldDefer());
    }

    // public function testMultiRecipientThroughAlias() {}

    public function testWhitelistNew()
    {
        $whitelist = Greylist\Whitelist::where('sender_domain', 'sender.domain')->first();

        $this->assertNull($whitelist);

        for ($i = 0; $i < 5; $i++) {
            $request = new Greylist\Request(
                [
                    'sender' => "someone{$i}@sender.domain",
                    'recipient' => $this->domainOwner->email,
                    'client_address' => $this->clientAddress,
                    'client_name' => 'some.mx',
                    'timestamp' => \Carbon\Carbon::now()->subDays(1)
                ]
            );

            $this->assertTrue($request->shouldDefer());
        }

        $whitelist = Greylist\Whitelist::where('sender_domain', 'sender.domain')->first();

        $this->assertNotNull($whitelist);

        $request = new Greylist\Request(
            [
                'sender' => "someone5@sender.domain",
                'recipient' => $this->domainOwner->email,
                'client_address' => $this->clientAddress,
                'client_name' => 'some.mx',
                'timestamp' => \Carbon\Carbon::now()->subDays(1)
            ]
        );

        $this->assertFalse($request->shouldDefer());
    }

    // public function testWhitelistedHit() {}

    public function testWhitelistStale()
    {
        $whitelist = Greylist\Whitelist::where('sender_domain', 'sender.domain')->first();

        $this->assertNull($whitelist);

        for ($i = 0; $i < 5; $i++) {
            $request = new Greylist\Request(
                [
                    'sender' => "someone{$i}@sender.domain",
                    'recipient' => $this->domainOwner->email,
                    'client_address' => $this->clientAddress,
                    'client_name' => 'some.mx',
                    'timestamp' => \Carbon\Carbon::now()->subDays(1)
                ]
            );

            $this->assertTrue($request->shouldDefer());
        }

        $whitelist = Greylist\Whitelist::where('sender_domain', 'sender.domain')->first();

        $this->assertNotNull($whitelist);

        $request = new Greylist\Request(
            [
                'sender' => "someone5@sender.domain",
                'recipient' => $this->domainOwner->email,
                'client_address' => $this->clientAddress,
                'client_name' => 'some.mx',
                'timestamp' => \Carbon\Carbon::now()->subDays(1)
            ]
        );

        $this->assertFalse($request->shouldDefer());

        $whitelist->updated_at = \Carbon\Carbon::now()->subMonthsWithoutOverflow(2);
        $whitelist->save(['timestamps' => false]);

        $this->assertTrue($request->shouldDefer());
    }

    // public function testWhitelistUpdate() {}

    public function testRetry()
    {
        $connect = Greylist\Connect::create(
            [
                'sender_local' => 'someone',
                'sender_domain' => 'sender.domain',
                'recipient_hash' => hash('sha256', $this->domainOwner->email),
                'recipient_id' => $this->domainOwner->id,
                'recipient_type' => \App\User::class,
                'connect_count' => 1,
                'net_id' => $this->net->id,
                'net_type' => \App\IP4Net::class
            ]
        );

        $connect->created_at = \Carbon\Carbon::now()->subMinutes(6);
        $connect->save();

        $request = new Greylist\Request(
            [
                'sender' => 'someone@sender.domain',
                'recipient' => $this->domainOwner->email,
                'client_address' => $this->clientAddress
            ]
        );

        $this->assertFalse($request->shouldDefer());
    }

    public function testInvalidRecipient()
    {
        $connect = Greylist\Connect::create(
            [
                'sender_local' => 'someone',
                'sender_domain' => 'sender.domain',
                'recipient_hash' => hash('sha256', $this->domainOwner->email),
                'recipient_id' => 1234,
                'recipient_type' => \App\Domain::class,
                'connect_count' => 1,
                'net_id' => $this->net->id,
                'net_type' => \App\IP4Net::class
            ]
        );

        $request = new Greylist\Request(
            [
                'sender' => 'someone@sender.domain',
                'recipient' => 'not.someone@that.exists',
                'client_address' => $this->clientAddress
            ]
        );

        $this->assertTrue($request->shouldDefer());
    }

    public function testUserDisabled()
    {
        $connect = Greylist\Connect::create(
            [
                'sender_local' => 'someone',
                'sender_domain' => 'sender.domain',
                'recipient_hash' => hash('sha256', $this->domainOwner->email),
                'recipient_id' => $this->domainOwner->id,
                'recipient_type' => \App\User::class,
                'connect_count' => 1,
                'net_id' => $this->net->id,
                'net_type' => \App\IP4Net::class
            ]
        );

        $this->domainOwner->setSetting('greylist_enabled', 'false');

        $request = new Greylist\Request(
            [
                'sender' => 'someone@sender.domain',
                'recipient' => $this->domainOwner->email,
                'client_address' => $this->clientAddress
            ]
        );

        $this->assertFalse($request->shouldDefer());

        // Ensure we also find the setting by alias
        $this->domainOwner->setAliases(['alias1@test2.domain2']);

        $request = new Greylist\Request(
            [
                'sender' => 'someone@sender.domain',
                'recipient' => 'alias1@test2.domain2',
                'client_address' => $this->clientAddress
            ]
        );

        $this->assertFalse($request->shouldDefer());
    }

    public function testUserEnabled()
    {
        $connect = Greylist\Connect::create(
            [
                'sender_local' => 'someone',
                'sender_domain' => 'sender.domain',
                'recipient_hash' => hash('sha256', $this->domainOwner->email),
                'recipient_id' => $this->domainOwner->id,
                'recipient_type' => \App\User::class,
                'connect_count' => 1,
                'net_id' => $this->net->id,
                'net_type' => \App\IP4Net::class
            ]
        );

        $this->domainOwner->setSetting('greylist_enabled', 'true');

        $request = new Greylist\Request(
            [
                'sender' => 'someone@sender.domain',
                'recipient' => $this->domainOwner->email,
                'client_address' => $this->clientAddress
            ]
        );

        $this->assertTrue($request->shouldDefer());

        $connect->created_at = \Carbon\Carbon::now()->subMinutes(6);
        $connect->save();

        $this->assertFalse($request->shouldDefer());
    }

    /**
     * @group slow
     */
    public function testMultipleUsersAllDisabled()
    {
        $this->setUpTest();

        $request = new Greylist\Request(
            [
                'sender' => 'someone@sender.domain',
                'recipient' => $this->domainOwner->email,
                'client_address' => $this->clientAddress
            ]
        );

        foreach ($this->domainUsers as $user) {
            Greylist\Connect::create(
                [
                    'sender_local' => 'someone',
                    'sender_domain' => 'sender.domain',
                    'recipient_hash' => hash('sha256', $user->email),
                    'recipient_id' => $user->id,
                    'recipient_type' => \App\User::class,
                    'connect_count' => 1,
                    'net_id' => $this->net->id,
                    'net_type' => \App\IP4Net::class
                ]
            );

            $user->setSetting('greylist_enabled', 'false');

            if ($user->email == $this->domainOwner->email) {
                continue;
            }

            $request = new Greylist\Request(
                [
                    'sender' => 'someone@sender.domain',
                    'recipient' => $user->email,
                    'client_address' => $this->clientAddress
                ]
            );

            $this->assertFalse($request->shouldDefer());
        }
    }

    /**
     * @group slow
     */
    public function testMultipleUsersAnyEnabled()
    {
        $this->setUpTest();

        $request = new Greylist\Request(
            [
                'sender' => 'someone@sender.domain',
                'recipient' => $this->domainOwner->email,
                'client_address' => $this->clientAddress
            ]
        );

        foreach ($this->domainUsers as $user) {
            Greylist\Connect::create(
                [
                    'sender_local' => 'someone',
                    'sender_domain' => 'sender.domain',
                    'recipient_hash' => hash('sha256', $user->email),
                    'recipient_id' => $user->id,
                    'recipient_type' => \App\User::class,
                    'connect_count' => 1,
                    'net_id' => $this->net->id,
                    'net_type' => \App\IP4Net::class
                ]
            );

            $user->setSetting('greylist_enabled', ($user->id == $this->jack->id) ? 'true' : 'false');

            if ($user->email == $this->domainOwner->email) {
                continue;
            }

            $request = new Greylist\Request(
                [
                    'sender' => 'someone@sender.domain',
                    'recipient' => $user->email,
                    'client_address' => $this->clientAddress
                ]
            );

            if ($user->id == $this->jack->id) {
                $this->assertTrue($request->shouldDefer());
            } else {
                $this->assertFalse($request->shouldDefer());
            }
        }
    }

    public function testControllerNew()
    {
        $request = new Greylist\Request([
            'sender' => 'someone@sender.domain',
            'recipient' => $this->domainOwner->email,
            'client_address' => $this->clientAddress,
            'client_name' => 'some.mx'
        ]);

        $this->assertTrue($request->shouldDefer());
    }

    public function testControllerNotNew()
    {
        $connect = Greylist\Connect::create(
            [
                'sender_local' => 'someone',
                'sender_domain' => 'sender.domain',
                'recipient_hash' => hash('sha256', $this->domainOwner->email),
                'recipient_id' => $this->domainOwner->id,
                'recipient_type' => \App\User::class,
                'connect_count' => 1,
                'net_id' => $this->net->id,
                'net_type' => \App\IP4Net::class
            ]
        );

        $connect->created_at = \Carbon\Carbon::now()->subMinutes(6);
        $connect->save();

        $request = new Greylist\Request([
            'sender' => 'someone@sender.domain',
            'recipient' => $this->domainOwner->email,
            'client_address' => $this->clientAddress,
            'client_name' => 'some.mx'
        ]);

        $this->assertFalse($request->shouldDefer());
    }
}

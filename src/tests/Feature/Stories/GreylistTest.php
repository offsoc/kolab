<?php

namespace Tests\Feature\Stories;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GreylistTest extends TestCase
{
    private $requests = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->instance = $this->generateInstanceId();
        $this->clientAddress = '212.103.80.148';

        $this->net = \App\IP4Net::getNet($this->clientAddress);

        DB::delete("DELETE FROM greylist_connect WHERE sender_domain = 'sender.domain';");
        DB::delete("DELETE FROM greylist_settings;");
        DB::delete("DELETE FROM greylist_whitelist WHERE sender_domain = 'sender.domain';");
    }

    public function tearDown(): void
    {
        DB::delete("DELETE FROM greylist_connect WHERE sender_domain = 'sender.domain';");
        DB::delete("DELETE FROM greylist_settings;");
        DB::delete("DELETE FROM greylist_whitelist WHERE sender_domain = 'sender.domain';");

        parent::tearDown();
    }

    public function testWithTimestamp()
    {
        $request = new \App\Greylist\Request(
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
        $request = new \App\Greylist\Request(
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
        $request = new \App\Greylist\Request(
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
        $whitelist = \App\Greylist\Whitelist::where('sender_domain', 'sender.domain')->first();

        $this->assertNull($whitelist);

        for ($i = 0; $i < 5; $i++) {
            $request = new \App\Greylist\Request(
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

        $whitelist = \App\Greylist\Whitelist::where('sender_domain', 'sender.domain')->first();

        $this->assertNotNull($whitelist);

        $request = new \App\Greylist\Request(
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
        $whitelist = \App\Greylist\Whitelist::where('sender_domain', 'sender.domain')->first();

        $this->assertNull($whitelist);

        for ($i = 0; $i < 5; $i++) {
            $request = new \App\Greylist\Request(
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

        $whitelist = \App\Greylist\Whitelist::where('sender_domain', 'sender.domain')->first();

        $this->assertNotNull($whitelist);

        $request = new \App\Greylist\Request(
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

    public function testNew()
    {
        $data = [
            'sender' => 'someone@sender.domain',
            'recipient' => $this->domainOwner->email,
            'client_address' => $this->clientAddress,
            'client_name' => 'some.mx'
        ];

        $response = $this->post('/api/webhooks/greylist', $data);

        $response->assertStatus(403);
    }

    public function testRetry()
    {
        $connect = \App\Greylist\Connect::create(
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

        $request = new \App\Greylist\Request(
            [
                'sender' => 'someone@sender.domain',
                'recipient' => $this->domainOwner->email,
                'client_address' => $this->clientAddress
            ]
        );

        $this->assertFalse($request->shouldDefer());
    }

    public function testDomainDisabled()
    {
        $setting = \App\Greylist\Setting::create(
            [
                'object_id' => $this->domainHosted->id,
                'object_type' => \App\Domain::class,
                'key' => 'greylist_enabled',
                'value' => 'false'
            ]
        );

        $request = new \App\Greylist\Request(
            [
                'sender' => 'someone@sender.domain',
                'recipient' => $this->domainOwner->email,
                'client_address' => $this->clientAddress
            ]
        );

        $this->assertFalse($request->shouldDefer());
    }

    public function testDomainEnabled()
    {
        $connect = \App\Greylist\Connect::create(
            [
                'sender_local' => 'someone',
                'sender_domain' => 'sender.domain',
                'recipient_hash' => hash('sha256', $this->domainOwner->email),
                'recipient_id' => $this->domainOwner->id,
                'recipient_type' => \App\User::class,
                'connect_count' => 1,
                'net_id' => \App\IP4Net::getNet('212.103.80.148')->id,
                'net_type' => \App\IP4Net::class
            ]
        );

        $setting = \App\Greylist\Setting::create(
            [
                'object_id' => $this->domainHosted->id,
                'object_type' => \App\Domain::class,
                'key' => 'greylist_enabled',
                'value' => 'true'
            ]
        );

        $request = new \App\Greylist\Request(
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

    public function testDomainDisabledUserDisabled()
    {
        $connect = \App\Greylist\Connect::create(
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

        $settingDomain = \App\Greylist\Setting::create(
            [
                'object_id' => $this->domainHosted->id,
                'object_type' => \App\Domain::class,
                'key' => 'greylist_enabled',
                'value' => 'false'
            ]
        );

        $settingUser = \App\Greylist\Setting::create(
            [
                'object_id' => $this->domainOwner->id,
                'object_type' => \App\User::class,
                'key' => 'greylist_enabled',
                'value' => 'false'
            ]
        );

        $request = new \App\Greylist\Request(
            [
                'sender' => 'someone@sender.domain',
                'recipient' => $this->domainOwner->email,
                'client_address' => $this->clientAddress
            ]
        );

        $this->assertFalse($request->shouldDefer());
    }

    public function testDomainDisabledUserEnabled()
    {
        $connect = \App\Greylist\Connect::create(
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

        $settingDomain = \App\Greylist\Setting::create(
            [
                'object_id' => $this->domainHosted->id,
                'object_type' => \App\Domain::class,
                'key' => 'greylist_enabled',
                'value' => 'false'
            ]
        );

        $settingUser = \App\Greylist\Setting::create(
            [
                'object_id' => $this->domainOwner->id,
                'object_type' => \App\User::class,
                'key' => 'greylist_enabled',
                'value' => 'true'
            ]
        );

        $request = new \App\Greylist\Request(
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

    public function testInvalidDomain()
    {
        $connect = \App\Greylist\Connect::create(
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

        $request = new \App\Greylist\Request(
            [
                'sender' => 'someone@sender.domain',
                'recipient' => 'not.someone@that.exists',
                'client_address' => $this->clientAddress
            ]
        );

        $this->assertTrue($request->shouldDefer());
    }

    public function testInvalidUser()
    {
        $connect = \App\Greylist\Connect::create(
            [
                'sender_local' => 'someone',
                'sender_domain' => 'sender.domain',
                'recipient_hash' => hash('sha256', $this->domainOwner->email),
                'recipient_id' => 1234,
                'recipient_type' => \App\User::class,
                'connect_count' => 1,
                'net_id' => $this->net->id,
                'net_type' => \App\IP4Net::class
            ]
        );

        $request = new \App\Greylist\Request(
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
        $connect = \App\Greylist\Connect::create(
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

        $setting = \App\Greylist\Setting::create(
            [
                'object_id' => $this->domainOwner->id,
                'object_type' => \App\User::class,
                'key' => 'greylist_enabled',
                'value' => 'false'
            ]
        );

        $request = new \App\Greylist\Request(
            [
                'sender' => 'someone@sender.domain',
                'recipient' => $this->domainOwner->email,
                'client_address' => $this->clientAddress
            ]
        );

        $this->assertFalse($request->shouldDefer());
    }

    public function testUserEnabled()
    {
        $connect = \App\Greylist\Connect::create(
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

        $setting = \App\Greylist\Setting::create(
            [
                'object_id' => $this->domainOwner->id,
                'object_type' => \App\User::class,
                'key' => 'greylist_enabled',
                'value' => 'true'
            ]
        );

        $request = new \App\Greylist\Request(
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

    public function testMultipleUsersAllDisabled()
    {
        $request = new \App\Greylist\Request(
            [
                'sender' => 'someone@sender.domain',
                'recipient' => $this->domainOwner->email,
                'client_address' => $this->clientAddress
            ]
        );

        foreach ($this->domainUsers as $user) {
            \App\Greylist\Connect::create(
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

            \App\Greylist\Setting::create(
                [
                    'object_id' => $user->id,
                    'object_type' => \App\User::class,
                    'key' => 'greylist_enabled',
                    'value' => 'false'
                ]
            );

            if ($user->email == $this->domainOwner->email) {
                continue;
            }

            $request = new \App\Greylist\Request(
                [
                    'sender' => 'someone@sender.domain',
                    'recipient' => $user->email,
                    'client_address' => $this->clientAddress
                ]
            );

            $this->assertFalse($request->shouldDefer());
        }
    }

    public function testMultipleUsersAnyEnabled()
    {
        $request = new \App\Greylist\Request(
            [
                'sender' => 'someone@sender.domain',
                'recipient' => $this->domainOwner->email,
                'client_address' => $this->clientAddress
            ]
        );

        foreach ($this->domainUsers as $user) {
            \App\Greylist\Connect::create(
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

            \App\Greylist\Setting::create(
                [
                    'object_id' => $user->id,
                    'object_type' => \App\User::class,
                    'key' => 'greylist_enabled',
                    'value' => ($user->id == $this->jack->id) ? 'true' : 'false'
                ]
            );

            if ($user->email == $this->domainOwner->email) {
                continue;
            }

            $request = new \App\Greylist\Request(
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

    private function generateInstanceId()
    {
        $instance = [];

        for ($x = 0; $x < 3; $x++) {
            for ($y = 0; $y < 3; $y++) {
                $instance[] .= substr('01234567889', rand(0, 9), 1);
            }
        }

        return implode('.', $instance);
    }
}

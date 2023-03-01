<?php

namespace Tests\Feature\Stories;

use App\Payment;
use App\Policy\RateLimit;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * @group slow
 * @group data
 * @group ratelimit
 */
class RateLimitTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->setUpTest();
        $this->useServicesUrl();

        Payment::query()->delete();
    }

    public function tearDown(): void
    {
        Payment::query()->delete();

        parent::tearDown();
    }

    /**
     * Verify an individual can send an email unrestricted, so long as the account is active.
     */
    public function testIndividualDunno()
    {
        $request = [
            'sender' => $this->publicDomainUser->email,
            'recipients' => [ 'someone@test.domain' ]
        ];

        $response = $this->post('api/webhooks/policy/ratelimit', $request);

        $response->assertStatus(200);
    }

    /**
     * Verify a whitelisted individual account is in fact whitelisted
     */
    public function testIndividualWhitelist()
    {
        \App\Policy\RateLimitWhitelist::create(
            [
                'whitelistable_id' => $this->publicDomainUser->id,
                'whitelistable_type' => \App\User::class
            ]
        );

        $request = [
            'sender' => $this->publicDomainUser->email,
            'recipients' => []
        ];

        // first 9 requests
        for ($i = 1; $i <= 9; $i++) {
            $request['recipients'] = [sprintf("%04d@test.domain", $i)];
            $response = $this->post('api/webhooks/policy/ratelimit', $request);

            $response->assertStatus(200);
        }

        // normally, request #10 would get blocked
        $request['recipients'] = ['0010@test.domain'];
        $response = $this->post('api/webhooks/policy/ratelimit', $request);
        $response->assertStatus(200);

        // requests 11 through 26
        for ($i = 11; $i <= 26; $i++) {
            $request['recipients'] = [sprintf("%04d@test.domain", $i)];
            $response = $this->post('api/webhooks/policy/ratelimit', $request);

            $response->assertStatus(200);
        }
    }

    /**
     * Verify an individual trial user is automatically suspended.
     */
    public function testIndividualAutoSuspendMessages()
    {
        $request = [
            'sender' => $this->publicDomainUser->email,
            'recipients' => []
        ];

        // first 9 requests
        for ($i = 1; $i <= 9; $i++) {
            $request['recipients'] = [sprintf("%04d@test.domain", $i)];
            $response = $this->post('api/webhooks/policy/ratelimit', $request);

            $response->assertStatus(200);
        }

        // the next 16 requests for 25 total
        for ($i = 10; $i <= 25; $i++) {
            $request['recipients'] = [sprintf("%04d@test.domain", $i)];
            $response = $this->post('api/webhooks/policy/ratelimit', $request);

            $response->assertStatus(403);
        }

        $this->assertTrue($this->publicDomainUser->fresh()->isSuspended());
    }

    /**
     * Verify a suspended individual can not send an email
     */
    public function testIndividualSuspended()
    {
        $this->publicDomainUser->suspend();

        $request = [
            'sender' => $this->publicDomainUser->email,
            'recipients' => ['someone@test.domain']
        ];

        $response = $this->post('api/webhooks/policy/ratelimit', $request);

        $response->assertStatus(403);
    }

    /**
     * Verify an individual can run out of messages per hour
     */
    public function testIndividualTrialMessages()
    {
        $request = [
            'sender' => $this->publicDomainUser->email,
            'recipients' => []
        ];

        // first 9 requests
        for ($i = 1; $i <= 9; $i++) {
            $request['recipients'] = [sprintf("%04d@test.domain", $i)];
            $response = $this->post('api/webhooks/policy/ratelimit', $request);

            $response->assertStatus(200);
        }

        // the tenth request should be blocked
        $request['recipients'] = ['0010@test.domain'];
        $response = $this->post('api/webhooks/policy/ratelimit', $request);

        $response->assertStatus(403);
    }

    /**
     * Verify a paid for individual account does not simply run out of messages
     */
    public function testIndividualPaidMessages()
    {
        $wallet = $this->publicDomainUser->wallets()->first();

        // Ensure there are no payments for the wallet
        Payment::where('wallet_id', $wallet->id)->delete();

        $payment = [
            'id' => \App\Utils::uuidInt(),
            'status' => Payment::STATUS_PAID,
            'type' => Payment::TYPE_ONEOFF,
            'description' => 'Paid in March',
            'wallet_id' => $wallet->id,
            'provider' => 'stripe',
            'amount' => 1111,
            'credit_amount' => 1111,
            'currency_amount' => 1111,
            'currency' => 'CHF',
        ];

        Payment::create($payment);
        $wallet->credit(1111);

        $request = [
            'sender' => $this->publicDomainUser->email,
            'recipients' => ['someone@test.domain']
        ];

        // first 9 requests
        for ($i = 1; $i <= 9; $i++) {
            $request['recipients'] = [sprintf("%04d@test.domain", $i)];
            $response = $this->post('api/webhooks/policy/ratelimit', $request);

            $response->assertStatus(200);
        }

        // the tenth request should be blocked
        $request['recipients'] = ['0010@test.domain'];
        $response = $this->post('api/webhooks/policy/ratelimit', $request);
        $response->assertStatus(403);

        // create a second payment
        $payment['id'] = \App\Utils::uuidInt();
        Payment::create($payment);
        $wallet->credit(1111);

        // the tenth request should now be allowed
        $response = $this->post('api/webhooks/policy/ratelimit', $request);
        $response->assertStatus(200);
    }

    /**
     * Verify that an individual user in its trial can run out of recipients.
     */
    public function testIndividualTrialRecipients()
    {
        $request = [
            'sender' => $this->publicDomainUser->email,
            'recipients' => []
        ];

        // first 2 requests (34 recipients each)
        for ($x = 1; $x <= 2; $x++) {
            $request['recipients'] = [];

            for ($y = 1; $y <= 34; $y++) {
                $request['recipients'][] = sprintf("%04d@test.domain", $x * $y);
            }

            $response = $this->post('api/webhooks/policy/ratelimit', $request);

            $response->assertStatus(200);
        }

        // on to the third request, resulting in 102 recipients total
        $request['recipients'] = [];

        for ($y = 1; $y <= 34; $y++) {
            $request['recipients'][] = sprintf("%04d@test.domain", 3 * $y);
        }

        $response = $this->post('api/webhooks/policy/ratelimit', $request);
        $response->assertStatus(403);
    }

    /**
     * Verify that an individual user that has paid for its account doesn't run out of recipients.
     */
    public function testIndividualPaidRecipients()
    {
        $wallet = $this->publicDomainUser->wallets()->first();

        // Ensure there are no payments for the wallet
        Payment::where('wallet_id', $wallet->id)->delete();

        $payment = [
            'id' => \App\Utils::uuidInt(),
            'status' => Payment::STATUS_PAID,
            'type' => Payment::TYPE_ONEOFF,
            'description' => 'Paid in March',
            'wallet_id' => $wallet->id,
            'provider' => 'stripe',
            'amount' => 1111,
            'credit_amount' => 1111,
            'currency_amount' => 1111,
            'currency' => 'CHF',
        ];

        Payment::create($payment);
        $wallet->credit(1111);

        $request = [
            'sender' => $this->publicDomainUser->email,
            'recipients' => []
        ];

        // first 2 requests (34 recipients each)
        for ($x = 0; $x < 2; $x++) {
            $request['recipients'] = [];

            for ($y = 0; $y < 34; $y++) {
                $request['recipients'][] = sprintf("%04d@test.domain", $x * $y);
            }

            $response = $this->post('api/webhooks/policy/ratelimit', $request);

            $response->assertStatus(200);
        }

        // on to the third request, resulting in 102 recipients total
        $request['recipients'] = [];

        for ($y = 0; $y < 34; $y++) {
            $request['recipients'][] = sprintf("%04d@test.domain", 2 * $y);
        }

        $response = $this->post('api/webhooks/policy/ratelimit', $request);

        $response->assertStatus(403);

        $payment['id'] = \App\Utils::uuidInt();

        Payment::create($payment);
        $wallet->credit(1111);

        // the tenth request should now be allowed
        $response = $this->post('api/webhooks/policy/ratelimit', $request);

        $response->assertStatus(200);
    }

    /**
     * Verify that a group owner can send email
     */
    public function testGroupOwnerDunno()
    {
        $request = [
            'sender' => $this->domainOwner->email,
            'recipients' => [ 'someone@test.domain' ]
        ];

        $response = $this->post('api/webhooks/policy/ratelimit', $request);

        $response->assertStatus(200);
    }

    /**
     * Verify that a domain owner can run out of messages
     */
    public function testGroupTrialOwnerMessages()
    {
        $request = [
            'sender' => $this->domainOwner->email,
            'recipients' => []
        ];

        // first 9 requests
        for ($i = 0; $i < 9; $i++) {
            $request['recipients'] = [sprintf("%04d@test.domain", $i)];
            $response = $this->post('api/webhooks/policy/ratelimit', $request);

            $response->assertStatus(200);
        }

        // the tenth request should be blocked
        $request['recipients'] = ['0010@test.domain'];
        $response = $this->post('api/webhooks/policy/ratelimit', $request);

        $response->assertStatus(403);

        $this->assertFalse($this->domainOwner->fresh()->isSuspended());
    }

    /**
     * Verify that a domain owner can run out of recipients
     */
    public function testGroupTrialOwnerRecipients()
    {
        $request = [
            'sender' => $this->domainOwner->email,
            'recipients' => []
        ];

        // first 2 requests (34 recipients each)
        for ($x = 0; $x < 2; $x++) {
            $request['recipients'] = [];

            for ($y = 0; $y < 34; $y++) {
                $request['recipients'][] = sprintf("%04d@test.domain", $x * $y);
            }

            $response = $this->post('api/webhooks/policy/ratelimit', $request);

            $response->assertStatus(200);
        }

        // on to the third request, resulting in 102 recipients total
        $request['recipients'] = [];

        for ($y = 0; $y < 34; $y++) {
            $request['recipients'][] = sprintf("%04d@test.domain", 2 * $y);
        }

        $response = $this->post('api/webhooks/policy/ratelimit', $request);
        $response->assertStatus(403);

        $this->assertFalse($this->domainOwner->fresh()->isSuspended());
    }

    /**
     * Verify that a paid for group account can send messages.
     */
    public function testGroupPaidOwnerRecipients()
    {
        $wallet = $this->domainOwner->wallets()->first();

        // Ensure there are no payments for the wallet
        Payment::where('wallet_id', $wallet->id)->delete();

        $payment = [
            'id' => \App\Utils::uuidInt(),
            'status' => Payment::STATUS_PAID,
            'type' => Payment::TYPE_ONEOFF,
            'description' => 'Paid in March',
            'wallet_id' => $wallet->id,
            'provider' => 'stripe',
            'amount' => 1111,
            'credit_amount' => 1111,
            'currency_amount' => 1111,
            'currency' => 'CHF',
        ];

        Payment::create($payment);
        $wallet->credit(1111);

        $request = [
            'sender' => $this->domainOwner->email,
            'recipients' => []
        ];

        // first 2 requests (34 recipients each)
        for ($x = 0; $x < 2; $x++) {
            $request['recipients'] = [];

            for ($y = 0; $y < 34; $y++) {
                $request['recipients'][] = sprintf("%04d@test.domain", $x * $y);
            }

            $response = $this->post('api/webhooks/policy/ratelimit', $request);

            $response->assertStatus(200);
        }

        // on to the third request, resulting in 102 recipients total
        $request['recipients'] = [];

        for ($y = 0; $y < 34; $y++) {
            $request['recipients'][] = sprintf("%04d@test.domain", 2 * $y);
        }

        $response = $this->post('api/webhooks/policy/ratelimit', $request);
        $response->assertStatus(403);

        // create a second payment
        $payment['id'] = \App\Utils::uuidInt();
        Payment::create($payment);

        $response = $this->post('api/webhooks/policy/ratelimit', $request);
        $response->assertStatus(200);
    }

    /**
     * Verify that a user for a domain owner can send email.
     */
    public function testGroupUserDunno()
    {
        $request = [
            'sender' => $this->domainUsers[0]->email,
            'recipients' => [ 'someone@test.domain' ]
        ];

        $response = $this->post('api/webhooks/policy/ratelimit', $request);

        $response->assertStatus(200);
    }

    /**
     * Verify that the users in a group account can be limited.
     */
    public function testGroupTrialUserMessages()
    {
        $user = $this->domainUsers[0];

        $request = [
            'sender' => $user->email,
            'recipients' => []
        ];

        // the first eight requests should be accepted
        for ($i = 0; $i < 8; $i++) {
            $request['recipients'] = [sprintf("%04d@test.domain", $i)];

            $response = $this->post('api/webhooks/policy/ratelimit', $request);
            $response->assertStatus(200);
        }

        $request['sender'] = $this->domainUsers[1]->email;

        // the ninth request from another group user should also be accepted
        $request['recipients'] = ['0009@test.domain'];
        $response = $this->post('api/webhooks/policy/ratelimit', $request);
        $response->assertStatus(200);

        // the tenth request from another group user should be rejected
        $request['recipients'] = ['0010@test.domain'];
        $response = $this->post('api/webhooks/policy/ratelimit', $request);
        $response->assertStatus(403);
    }

    public function testGroupTrialUserRecipients()
    {
        $request = [
            'sender' => $this->domainUsers[0]->email,
            'recipients' => []
        ];

        // first 2 requests (34 recipients each)
        for ($x = 0; $x < 2; $x++) {
            $request['recipients'] = [];

            for ($y = 0; $y < 34; $y++) {
                $request['recipients'][] = sprintf("%04d@test.domain", $x * $y);
            }

            $response = $this->post('api/webhooks/policy/ratelimit', $request);

            $response->assertStatus(200);
        }

        // on to the third request, resulting in 102 recipients total
        $request['recipients'] = [];

        for ($y = 0; $y < 34; $y++) {
            $request['recipients'][] = sprintf("%04d@test.domain", 2 * $y);
        }

        $response = $this->post('api/webhooks/policy/ratelimit', $request);
        $response->assertStatus(403);
    }

    /**
     * Verify a whitelisted group domain is in fact whitelisted
     */
    public function testGroupDomainWhitelist()
    {
        \App\Policy\RateLimitWhitelist::create(
            [
                'whitelistable_id' => $this->domainHosted->id,
                'whitelistable_type' => \App\Domain::class
            ]
        );

        $request = [
            'sender' => $this->domainUsers[0]->email,
            'recipients' => []
        ];

        // first 9 requests
        for ($i = 1; $i <= 9; $i++) {
            $request['recipients'] = [sprintf("%04d@test.domain", $i)];
            $response = $this->post('api/webhooks/policy/ratelimit', $request);

            $response->assertStatus(200);
        }

        // normally, request #10 would get blocked
        $request['recipients'] = ['0010@test.domain'];
        $response = $this->post('api/webhooks/policy/ratelimit', $request);
        $response->assertStatus(200);

        // requests 11 through 26
        for ($i = 11; $i <= 26; $i++) {
            $request['recipients'] = [sprintf("%04d@test.domain", $i)];
            $response = $this->post('api/webhooks/policy/ratelimit', $request);

            $response->assertStatus(200);
        }
    }
}

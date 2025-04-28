<?php

namespace Tests\Feature\Policy;

use App\Policy\RateLimit;
use App\Policy\Response;
use App\Transaction;
use App\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * @group data
 */
class RateLimitTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->setUpTest();

        RateLimit::query()->delete();
        Transaction::query()->delete();
    }

    public function tearDown(): void
    {
        RateLimit::query()->delete();
        Transaction::query()->delete();

        parent::tearDown();
    }

    /**
     * Test verifyRequest() method for an individual account cases
     */
    public function testVerifyRequestIndividualAccount()
    {
        // Verify an individual can send an email unrestricted, so long as the account is active.
        $result = RateLimit::verifyRequest($this->publicDomainUser, ['someone@test.domain']);

        $this->assertSame(200, $result->code);
        $this->assertSame(Response::ACTION_DUNNO, $result->action);
        $this->assertSame('', $result->reason);

        // Verify a whitelisted individual account is in fact whitelisted
        RateLimit::truncate();
        RateLimit\Whitelist::create([
                'whitelistable_id' => $this->publicDomainUser->id,
                'whitelistable_type' => User::class
        ]);

        // first 9 requests
        for ($i = 1; $i <= 9; $i++) {
            $result = RateLimit::verifyRequest($this->publicDomainUser, [sprintf("%04d@test.domain", $i)]);
            $this->assertSame(200, $result->code);
            $this->assertSame(Response::ACTION_DUNNO, $result->action);
            $this->assertSame('', $result->reason);
        }

        // normally, request #10 would get blocked
        $result = RateLimit::verifyRequest($this->publicDomainUser, ['0010@test.domain']);
        $this->assertSame(200, $result->code);
        $this->assertSame(Response::ACTION_DUNNO, $result->action);
        $this->assertSame('', $result->reason);

        // requests 11 through 26
        for ($i = 11; $i <= 26; $i++) {
            $result = RateLimit::verifyRequest($this->publicDomainUser, [sprintf("%04d@test.domain", $i)]);
            $this->assertSame(200, $result->code);
            $this->assertSame(Response::ACTION_DUNNO, $result->action);
            $this->assertSame('', $result->reason);
        }

        // Verify an individual trial user is automatically suspended.
        RateLimit::truncate();
        RateLimit\Whitelist::truncate();

        // first 9 requests
        for ($i = 1; $i <= 9; $i++) {
            $result = RateLimit::verifyRequest($this->publicDomainUser, [sprintf("%04d@test.domain", $i)]);
            $this->assertSame(200, $result->code);
            $this->assertSame(Response::ACTION_DUNNO, $result->action);
            $this->assertSame('', $result->reason);
        }

        // the next 16 requests for 25 total
        for ($i = 10; $i <= 25; $i++) {
            $result = RateLimit::verifyRequest($this->publicDomainUser, [sprintf("%04d@test.domain", $i)]);
            $this->assertSame(403, $result->code);
            $this->assertSame(Response::ACTION_DEFER_IF_PERMIT, $result->action);
            $this->assertSame('The account is at 10 messages per hour, cool down.', $result->reason);
        }

        $this->publicDomainUser->refresh();
        $this->assertTrue($this->publicDomainUser->isSuspended());

        // Verify a suspended individual can not send an email
        RateLimit::truncate();

        $result = RateLimit::verifyRequest($this->publicDomainUser, ['someone@test.domain']);
        $this->assertSame(403, $result->code);
        $this->assertSame(Response::ACTION_HOLD, $result->action);
        $this->assertSame('Sender deleted or suspended', $result->reason);

        // Verify an individual can run out of messages per hour
        RateLimit::truncate();
        $this->publicDomainUser->unsuspend();

        // first 9 requests
        for ($i = 1; $i <= 9; $i++) {
            $result = RateLimit::verifyRequest($this->publicDomainUser, [sprintf("%04d@test.domain", $i)]);
            $this->assertSame(200, $result->code);
            $this->assertSame(Response::ACTION_DUNNO, $result->action);
            $this->assertSame('', $result->reason);
        }

        // the tenth request should be blocked
        $result = RateLimit::verifyRequest($this->publicDomainUser, ['0010@test.domain']);
        $this->assertSame(403, $result->code);
        $this->assertSame(Response::ACTION_DEFER_IF_PERMIT, $result->action);
        $this->assertSame('The account is at 10 messages per hour, cool down.', $result->reason);

        // Verify a paid for individual account does not simply run out of messages
        RateLimit::truncate();

        // first 9 requests
        for ($i = 1; $i <= 9; $i++) {
            $result = RateLimit::verifyRequest($this->publicDomainUser, [sprintf("%04d@test.domain", $i)]);
            $this->assertSame(200, $result->code);
            $this->assertSame(Response::ACTION_DUNNO, $result->action);
            $this->assertSame('', $result->reason);
        }

        // the tenth request should be blocked
        $result = RateLimit::verifyRequest($this->publicDomainUser, ['0010@test.domain']);
        $this->assertSame(403, $result->code);
        $this->assertSame(Response::ACTION_DEFER_IF_PERMIT, $result->action);
        $this->assertSame('The account is at 10 messages per hour, cool down.', $result->reason);

        // create a credit transaction
        $this->publicDomainUser->wallets()->first()->credit(1111);

        // the next request should now be allowed
        $result = RateLimit::verifyRequest($this->publicDomainUser, ['0010@test.domain']);
        $this->assertSame(200, $result->code);
        $this->assertSame(Response::ACTION_DUNNO, $result->action);
        $this->assertSame('', $result->reason);

        // Verify a 100% discount for individual account does not simply run out of messages
        RateLimit::truncate();
        $wallet = $this->publicDomainUser->wallets()->first();
        $wallet->discount()->associate(\App\Discount::where('description', 'Free Account')->first());
        $wallet->save();

        // first 9 requests
        for ($i = 1; $i <= 9; $i++) {
            $result = RateLimit::verifyRequest($this->publicDomainUser, [sprintf("%04d@test.domain", $i)]);
            $this->assertSame(200, $result->code);
            $this->assertSame(Response::ACTION_DUNNO, $result->action);
            $this->assertSame('', $result->reason);
        }

        // the tenth request should now be allowed
        $result = RateLimit::verifyRequest($this->publicDomainUser, ['someone@test.domain']);
        $this->assertSame(200, $result->code);
        $this->assertSame(Response::ACTION_DUNNO, $result->action);
        $this->assertSame('', $result->reason);

        // Verify that an individual user in its trial can run out of recipients.
        RateLimit::truncate();
        $wallet->discount_id = null;
        $wallet->balance = 0;
        $wallet->save();

        // first 2 requests (34 recipients each)
        for ($x = 1; $x <= 2; $x++) {
            $recipients = [];
            for ($y = 1; $y <= 34; $y++) {
                $recipients[] = sprintf('%04d@test.domain', $x * $y);
            }

            $result = RateLimit::verifyRequest($this->publicDomainUser, $recipients);
            $this->assertSame(200, $result->code);
            $this->assertSame(Response::ACTION_DUNNO, $result->action);
            $this->assertSame('', $result->reason);
        }

        // on to the third request, resulting in 102 recipients total
        $recipients = [];
        for ($y = 1; $y <= 34; $y++) {
            $recipients[] = sprintf('%04d@test.domain', 3 * $y);
        }

        $result = RateLimit::verifyRequest($this->publicDomainUser, $recipients);
        $this->assertSame(403, $result->code);
        $this->assertSame(Response::ACTION_DEFER_IF_PERMIT, $result->action);
        $this->assertSame('The account is at 100 recipients per hour, cool down.', $result->reason);

        // Verify that an individual user that has paid for its account doesn't run out of recipients.
        RateLimit::truncate();
        $wallet->balance = 0;
        $wallet->save();

        // first 2 requests (34 recipients each)
        for ($x = 0; $x < 2; $x++) {
            $recipients = [];
            for ($y = 0; $y < 34; $y++) {
                $recipients[] = sprintf("%04d@test.domain", $x * $y);
            }

            $result = RateLimit::verifyRequest($this->publicDomainUser, $recipients);
            $this->assertSame(200, $result->code);
            $this->assertSame(Response::ACTION_DUNNO, $result->action);
            $this->assertSame('', $result->reason);
        }

        // on to the third request, resulting in 102 recipients total
        $recipients = [];
        for ($y = 0; $y < 34; $y++) {
            $recipients[] = sprintf("%04d@test.domain", 2 * $y);
        }

        $result = RateLimit::verifyRequest($this->publicDomainUser, $recipients);
        $this->assertSame(403, $result->code);
        $this->assertSame(Response::ACTION_DEFER_IF_PERMIT, $result->action);
        $this->assertSame('The account is at 100 recipients per hour, cool down.', $result->reason);

        $wallet->award(11111);

        // the tenth request should now be allowed
        $result = RateLimit::verifyRequest($this->publicDomainUser, $recipients);
        $this->assertSame(200, $result->code);
        $this->assertSame(Response::ACTION_DUNNO, $result->action);
        $this->assertSame('', $result->reason);
    }

    /**
     * Test verifyRequest() with group account cases
     */
    public function testVerifyRequestGroupAccount()
    {
        // Verify that a group owner can send email
        $result = RateLimit::verifyRequest($this->domainOwner, ['someone@test.domain']);
        $this->assertSame(200, $result->code);
        $this->assertSame(Response::ACTION_DUNNO, $result->action);
        $this->assertSame('', $result->reason);

        // Verify that a domain owner can run out of messages
        RateLimit::truncate();

        // first 9 requests
        for ($i = 0; $i < 9; $i++) {
            $result = RateLimit::verifyRequest($this->domainOwner, [sprintf("%04d@test.domain", $i)]);
            $this->assertSame(200, $result->code);
            $this->assertSame(Response::ACTION_DUNNO, $result->action);
            $this->assertSame('', $result->reason);
        }

        // the tenth request should be blocked
        $result = RateLimit::verifyRequest($this->domainOwner, ['0010@test.domain']);
        $this->assertSame(403, $result->code);
        $this->assertSame(Response::ACTION_DEFER_IF_PERMIT, $result->action);
        $this->assertSame('The account is at 10 messages per hour, cool down.', $result->reason);

        $this->domainOwner->refresh();
        $this->assertFalse($this->domainOwner->isSuspended());

        // Verify that a domain owner can run out of recipients
        RateLimit::truncate();
        $this->domainOwner->unsuspend();

        // first 2 requests (34 recipients each)
        for ($x = 0; $x < 2; $x++) {
            $recipients = [];
            for ($y = 0; $y < 34; $y++) {
                $recipients[] = sprintf("%04d@test.domain", $x * $y);
            }

            $result = RateLimit::verifyRequest($this->domainOwner, $recipients);
            $this->assertSame(200, $result->code);
            $this->assertSame(Response::ACTION_DUNNO, $result->action);
            $this->assertSame('', $result->reason);
        }

        // on to the third request, resulting in 102 recipients total
        $recipients = [];
        for ($y = 0; $y < 34; $y++) {
            $recipients[] = sprintf("%04d@test.domain", 2 * $y);
        }

        $result = RateLimit::verifyRequest($this->domainOwner, $recipients);
        $this->assertSame(403, $result->code);
        $this->assertSame(Response::ACTION_DEFER_IF_PERMIT, $result->action);
        $this->assertSame('The account is at 100 recipients per hour, cool down.', $result->reason);

        $this->domainOwner->refresh();
        $this->assertFalse($this->domainOwner->isSuspended());

        // Verify that a paid for group account can send messages.
        RateLimit::truncate();

        // first 2 requests (34 recipients each)
        for ($x = 0; $x < 2; $x++) {
            $recipients = [];
            for ($y = 0; $y < 34; $y++) {
                $recipients[] = sprintf("%04d@test.domain", $x * $y);
            }

            $result = RateLimit::verifyRequest($this->domainOwner, $recipients);
            $this->assertSame(200, $result->code);
            $this->assertSame(Response::ACTION_DUNNO, $result->action);
            $this->assertSame('', $result->reason);
        }

        // on to the third request, resulting in 102 recipients total
        $recipients = [];
        for ($y = 0; $y < 34; $y++) {
            $recipients[] = sprintf("%04d@test.domain", 2 * $y);
        }

        $result = RateLimit::verifyRequest($this->domainOwner, $recipients);
        $this->assertSame(403, $result->code);
        $this->assertSame(Response::ACTION_DEFER_IF_PERMIT, $result->action);
        $this->assertSame('The account is at 100 recipients per hour, cool down.', $result->reason);

        $wallet = $this->domainOwner->wallets()->first();
        $wallet->credit(1111);

        $result = RateLimit::verifyRequest($this->domainOwner, $recipients);
        $this->assertSame(200, $result->code);
        $this->assertSame(Response::ACTION_DUNNO, $result->action);
        $this->assertSame('', $result->reason);

        // Verify that a user for a domain owner can send email.
        RateLimit::truncate();

        $result = RateLimit::verifyRequest($this->domainUsers[0], ['someone@test.domain']);
        $this->assertSame(200, $result->code);
        $this->assertSame(Response::ACTION_DUNNO, $result->action);
        $this->assertSame('', $result->reason);

        // Verify that the users in a group account can be limited.
        RateLimit::truncate();
        $wallet->balance = 0;
        $wallet->save();

        // the first eight requests should be accepted
        for ($i = 0; $i < 8; $i++) {
            $result = RateLimit::verifyRequest($this->domainUsers[0], [sprintf("%04d@test.domain", $i)]);
            $this->assertSame(200, $result->code);
            $this->assertSame(Response::ACTION_DUNNO, $result->action);
            $this->assertSame('', $result->reason);
        }

        // the ninth request from another group user should also be accepted
        $result = RateLimit::verifyRequest($this->domainUsers[1], ['0009@test.domain']);
        $this->assertSame(200, $result->code);
        $this->assertSame(Response::ACTION_DUNNO, $result->action);
        $this->assertSame('', $result->reason);

        // the tenth request from another group user should be rejected
        $result = RateLimit::verifyRequest($this->domainUsers[1], ['0010@test.domain']);
        $this->assertSame(403, $result->code);
        $this->assertSame(Response::ACTION_DEFER_IF_PERMIT, $result->action);
        $this->assertSame('The account is at 10 messages per hour, cool down.', $result->reason);

        // Test a trial user
        RateLimit::truncate();

        // first 2 requests (34 recipients each)
        for ($x = 0; $x < 2; $x++) {
            $recipients = [];
            for ($y = 0; $y < 34; $y++) {
                $recipients[] = sprintf("%04d@test.domain", $x * $y);
            }

            $result = RateLimit::verifyRequest($this->domainUsers[0], $recipients);
            $this->assertSame(200, $result->code);
            $this->assertSame(Response::ACTION_DUNNO, $result->action);
            $this->assertSame('', $result->reason);
        }

        // on to the third request, resulting in 102 recipients total
        $recipients = [];
        for ($y = 0; $y < 34; $y++) {
            $recipients[] = sprintf("%04d@test.domain", 2 * $y);
        }

        $result = RateLimit::verifyRequest($this->domainUsers[0], $recipients);
        $this->assertSame(403, $result->code);
        $this->assertSame(Response::ACTION_DEFER_IF_PERMIT, $result->action);
        $this->assertSame('The account is at 100 recipients per hour, cool down.', $result->reason);

        // Verify a whitelisted group domain is in fact whitelisted
        RateLimit::truncate();
        RateLimit\Whitelist::create([
                'whitelistable_id' => $this->domainHosted->id,
                'whitelistable_type' => \App\Domain::class
        ]);

        $request = [
            'sender' => $this->domainUsers[0]->email,
            'recipients' => []
        ];

        // first 9 requests
        for ($i = 1; $i <= 9; $i++) {
            $result = RateLimit::verifyRequest($this->domainUsers[0], [sprintf("%04d@test.domain", $i)]);
            $this->assertSame(200, $result->code);
            $this->assertSame(Response::ACTION_DUNNO, $result->action);
            $this->assertSame('', $result->reason);
        }

        // normally, request #10 would get blocked
        $result = RateLimit::verifyRequest($this->domainUsers[0], ['0010@test.domain']);
        $this->assertSame(200, $result->code);
        $this->assertSame(Response::ACTION_DUNNO, $result->action);
        $this->assertSame('', $result->reason);

        // requests 11 through 26
        for ($i = 11; $i <= 26; $i++) {
            $result = RateLimit::verifyRequest($this->domainUsers[0], [sprintf("%04d@test.domain", $i)]);
            $this->assertSame(200, $result->code);
            $this->assertSame(Response::ACTION_DUNNO, $result->action);
            $this->assertSame('', $result->reason);
        }
    }
}

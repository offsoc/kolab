<?php

namespace Tests\Unit\DataMigrator;

use App\DataMigrator\Account;
use Tests\TestCase;

class AccountTest extends TestCase
{
    /**
     * Test input
     */
    public function testConstructor(): void
    {
        $uri = 'imap://user:pass%40word@host.tld:143?client_id=123&client_secret=456';
        $account = new Account($uri);

        $this->assertSame($uri, (string) $account);
        $this->assertSame('user', $account->username);
        $this->assertSame('pass@word', $account->password);
        $this->assertSame('host.tld', $account->host);
        $this->assertSame('imap', $account->scheme);
        $this->assertSame(143, $account->port);
        $this->assertSame('imap://host.tld:143', $account->uri);
        $this->assertSame(['client_id' => '123', 'client_secret' => '456'], $account->params);
        $this->assertNull($account->email);
        $this->assertNull($account->loginas);

        // Invalid input
        $this->expectException(\Exception::class);
        $account = new Account(str_replace('imap://', '', $uri));

        // Local file URI
        $uri = 'takeout://' . ($file = self::BASE_DIR . '/data/takeout.zip');
        $account = new Account($uri);

        $this->assertSame($uri, (string) $account);
        $this->assertSame('takeout', $account->scheme);
        $this->assertSame($file, $account->uri);
        $this->assertNull($account->username);
        $this->assertNull($account->password);
        $this->assertNull($account->host);
    }
}

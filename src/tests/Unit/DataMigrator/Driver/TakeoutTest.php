<?php

namespace Tests\Unit\DataMigrator\Driver;

use App\DataMigrator\Account;
use App\DataMigrator\Driver\Takeout;
use App\DataMigrator\Driver\Test;
use App\DataMigrator\Engine;
use App\DataMigrator\Interface\Folder;
use Tests\TestCase;

class TakeoutTest extends TestCase
{
    protected function tearDown(): void
    {
        exec('rm -rf ' . storage_path('export/unit@gmail.com'));

        parent::tearDown();
    }

    /**
     * Test processing content of an mbox file from Takeout archive
     */
    public function testMboxParsing(): void
    {
        $folder = Folder::fromArray(['fullname' => 'Trash', 'type' => Engine::TYPE_MAIL]);
        [$takeout, $importer] = $this->init();

        $result = [];
        $callback = static function ($item) use (&$result) {
            // Note: Small items don't use temp files, so we can just read the content
            // Remove line-wrapping for easier testing
            $result[$item->id] = $item->content;
        };

        // Parse "All mail Including Spam and Trash.mbox" file from tests/data/takeout-unit.zip
        $takeout->fetchItemList($folder, $callback, $importer);

        $this->assertCount(1, $result);
        $this->assertSame(0, preg_match('/[^\r]\n/', $result['<2@google.com>']));
        $this->assertTrue(str_starts_with($result['<2@google.com>'], 'X-GM-THRID:'));
        $this->assertTrue(str_ends_with($result['<2@google.com>'], "Message 2\r\n\r\n"));
    }

    /**
     * Test processing content of an ics file from Takeout archive
     */
    public function testVCalendarParsing(): void
    {
        $folder = Folder::fromArray(['fullname' => 'Test', 'type' => Engine::TYPE_EVENT]);
        [$takeout, $importer] = $this->init();

        $result = [];
        $callback = static function ($item) use (&$result) {
            // Note: Small items don't use temp files, so we can just read the content
            // Remove line-wrapping for easier testing
            $content = str_replace(["\r\n ", "\r\n  "], '', $item->content);
            $result[preg_replace('/\.ics$/', '', $item->filename)] = $content;
        };

        // Parse Test.ics file from tests/data/takeout-unit.zip
        $takeout->fetchItemList($folder, $callback, $importer);

        $this->assertCount(4, $result);

        foreach (['1111', '2222', '3333'] as $uid) {
            $this->assertStringContainsString("UID:{$uid}", $result[$uid], "UID:{$uid}"); // @phpstan-ignore-line
            $this->assertSame(1, preg_match_all('/BEGIN:VEVENT/', $result[$uid]), "UID:{$uid}"); // @phpstan-ignore-line
            $this->assertTrue(str_starts_with($result[$uid], 'BEGIN:VCALENDAR'), "UID:{$uid}"); // @phpstan-ignore-line
            $this->assertTrue(str_ends_with($result[$uid], "END:VCALENDAR\r\n"), "UID:{$uid}"); // @phpstan-ignore-line
        }

        $this->assertSame(3, preg_match_all('/UID:recur/', $result['recur']));
        $this->assertSame(3, preg_match_all('/BEGIN:VEVENT/', $result['recur']));
        $this->assertStringContainsString('RECURRENCE-ID;VALUE=DATE:20250410', $result['recur']);
        $this->assertStringContainsString('RECURRENCE-ID;VALUE=DATE:20250510', $result['recur']);
        $this->assertTrue(str_starts_with($result['recur'], 'BEGIN:VCALENDAR'));
        $this->assertTrue(str_ends_with($result['recur'], "END:VCALENDAR\r\n"));

        // TODO: We could also use App\Backends\DAV\Vevent to parse the output and do more assertions
    }

    /**
     * Init common objects for tests
     */
    private function init()
    {
        $source = new Account('takeout://' . self::BASE_DIR . '/data/takeout-unit.zip?user=unit@gmail.com');
        $destination = new Account('test://test%40kolab.org:test@test');
        $folder = Folder::fromArray(['fullname' => 'Trash', 'type' => Engine::TYPE_MAIL]);
        $engine = new Engine();

        return [
            new Takeout($source, $engine),
            new Test($destination, $engine),
        ];
    }
}

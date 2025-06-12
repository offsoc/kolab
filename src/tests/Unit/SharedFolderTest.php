<?php

namespace Tests\Unit;

use App\SharedFolder;
use Tests\TestCase;
use Tests\Utils;

class SharedFolderTest extends TestCase
{
    /**
     * Test SharedFolder status property and is*() methods
     */
    public function testSharedFolderStatus(): void
    {
        $statuses = [
            SharedFolder::STATUS_NEW,
            SharedFolder::STATUS_ACTIVE,
            SharedFolder::STATUS_DELETED,
            SharedFolder::STATUS_LDAP_READY,
            SharedFolder::STATUS_IMAP_READY,
        ];

        $folders = Utils::powerSet($statuses);

        $folder = new SharedFolder(['name' => 'test']);

        foreach ($folders as $folderStatuses) {
            $folder->status = \array_sum($folderStatuses);

            $folderStatuses = [];

            foreach ($statuses as $status) {
                if ($folder->status & $status) {
                    $folderStatuses[] = $status;
                }
            }

            $this->assertSame($folder->status, \array_sum($folderStatuses));

            // either one is true, but not both
            $this->assertSame(
                $folder->isNew() === in_array(SharedFolder::STATUS_NEW, $folderStatuses),
                $folder->isActive() === in_array(SharedFolder::STATUS_ACTIVE, $folderStatuses)
            );

            $this->assertTrue(
                $folder->isNew() === in_array(SharedFolder::STATUS_NEW, $folderStatuses)
            );

            $this->assertTrue(
                $folder->isActive() === in_array(SharedFolder::STATUS_ACTIVE, $folderStatuses)
            );

            $this->assertTrue(
                $folder->isDeleted() === in_array(SharedFolder::STATUS_DELETED, $folderStatuses)
            );

            $this->assertTrue(
                $folder->isLdapReady() === in_array(SharedFolder::STATUS_LDAP_READY, $folderStatuses)
            );

            $this->assertTrue(
                $folder->isImapReady() === in_array(SharedFolder::STATUS_IMAP_READY, $folderStatuses)
            );
        }

        $this->expectException(\Exception::class);
        $folder->status = 111;
    }

    /**
     * Test basic SharedFolder funtionality
     */
    public function testSharedFolderType(): void
    {
        $folder = new SharedFolder(['name' => 'test']);

        foreach (\config('app.shared_folder_types') as $type) {
            $folder->type = $type;
        }

        $this->expectException(\Exception::class);
        $folder->type = 'unknown';
    }
}

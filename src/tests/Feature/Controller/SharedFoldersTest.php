<?php

namespace Tests\Feature\Controller;

use App\SharedFolder;
use App\Http\Controllers\API\V4\SharedFoldersController;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SharedFoldersTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestSharedFolder('folder-test@kolab.org');
        SharedFolder::where('name', 'like', 'Test_Folder')->forceDelete();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestSharedFolder('folder-test@kolab.org');
        SharedFolder::where('name', 'like', 'Test_Folder')->forceDelete();

        parent::tearDown();
    }

    /**
     * Test resource deleting (DELETE /api/v4/resources/<id>)
     */
    public function testDestroy(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');
        $folder = $this->getTestSharedFolder('folder-test@kolab.org');
        $folder->assignToWallet($john->wallets->first());

        // Test unauth access
        $response = $this->delete("api/v4/shared-folders/{$folder->id}");
        $response->assertStatus(401);

        // Test non-existing folder
        $response = $this->actingAs($john)->delete("api/v4/shared-folders/abc");
        $response->assertStatus(404);

        // Test access to other user's folder
        $response = $this->actingAs($jack)->delete("api/v4/shared-folders/{$folder->id}");
        $response->assertStatus(403);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertSame("Access denied", $json['message']);
        $this->assertCount(2, $json);

        // Test removing a folder
        $response = $this->actingAs($john)->delete("api/v4/shared-folders/{$folder->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertEquals('success', $json['status']);
        $this->assertEquals("Shared folder deleted successfully.", $json['message']);
    }

    /**
     * Test shared folders listing (GET /api/v4/shared-folders)
     */
    public function testIndex(): void
    {
        $jack = $this->getTestUser('jack@kolab.org');
        $john = $this->getTestUser('john@kolab.org');
        $ned = $this->getTestUser('ned@kolab.org');

        // Test unauth access
        $response = $this->get("api/v4/shared-folders");
        $response->assertStatus(401);

        // Test a user with no shared folders
        $response = $this->actingAs($jack)->get("/api/v4/shared-folders");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(4, $json);
        $this->assertSame(0, $json['count']);
        $this->assertSame(false, $json['hasMore']);
        $this->assertSame("0 shared folders have been found.", $json['message']);
        $this->assertSame([], $json['list']);

        // Test a user with two shared folders
        $response = $this->actingAs($john)->get("/api/v4/shared-folders");
        $response->assertStatus(200);

        $json = $response->json();

        $folder = SharedFolder::where('name', 'Calendar')->first();

        $this->assertCount(4, $json);
        $this->assertSame(2, $json['count']);
        $this->assertSame(false, $json['hasMore']);
        $this->assertSame("2 shared folders have been found.", $json['message']);
        $this->assertCount(2, $json['list']);
        $this->assertSame($folder->id, $json['list'][0]['id']);
        $this->assertSame($folder->email, $json['list'][0]['email']);
        $this->assertSame($folder->name, $json['list'][0]['name']);
        $this->assertSame($folder->type, $json['list'][0]['type']);
        $this->assertArrayHasKey('isDeleted', $json['list'][0]);
        $this->assertArrayHasKey('isActive', $json['list'][0]);
        $this->assertArrayHasKey('isLdapReady', $json['list'][0]);
        $this->assertArrayHasKey('isImapReady', $json['list'][0]);

        // Test that another wallet controller has access to shared folders
        $response = $this->actingAs($ned)->get("/api/v4/shared-folders");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(4, $json);
        $this->assertSame(2, $json['count']);
        $this->assertSame(false, $json['hasMore']);
        $this->assertSame("2 shared folders have been found.", $json['message']);
        $this->assertCount(2, $json['list']);
        $this->assertSame($folder->email, $json['list'][0]['email']);
    }

    /**
     * Test shared folder config update (POST /api/v4/shared-folders/<folder>/config)
     */
    public function testSetConfig(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');
        $folder = $this->getTestSharedFolder('folder-test@kolab.org');
        $folder->assignToWallet($john->wallets->first());

        // Test unknown resource id
        $post = ['acl' => ['john@kolab.org, full']];
        $response = $this->actingAs($john)->post("/api/v4/shared-folders/123/config", $post);
        $json = $response->json();

        $response->assertStatus(404);

        // Test access by user not being a wallet controller
        $response = $this->actingAs($jack)->post("/api/v4/shared-folders/{$folder->id}/config", $post);
        $json = $response->json();

        $response->assertStatus(403);

        $this->assertSame('error', $json['status']);
        $this->assertSame("Access denied", $json['message']);
        $this->assertCount(2, $json);

        // Test some invalid data
        $post = ['test' => 1];
        $response = $this->actingAs($john)->post("/api/v4/shared-folders/{$folder->id}/config", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertCount(2, $json);
        $this->assertCount(1, $json['errors']);
        $this->assertSame('The requested configuration parameter is not supported.', $json['errors']['test']);

        $folder->refresh();

        $this->assertNull($folder->getSetting('test'));
        $this->assertNull($folder->getSetting('acl'));

        // Test some valid data
        $post = ['acl' => ['john@kolab.org, full']];
        $response = $this->actingAs($john)->post("/api/v4/shared-folders/{$folder->id}/config", $post);

        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(2, $json);
        $this->assertSame('success', $json['status']);
        $this->assertSame("Shared folder settings updated successfully.", $json['message']);

        $this->assertSame(['acl' => $post['acl']], $folder->fresh()->getConfig());

        // Test input validation
        $post = ['acl' => ['john@kolab.org, full', 'test, full']];
        $response = $this->actingAs($john)->post("/api/v4/shared-folders/{$folder->id}/config", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertCount(2, $json);
        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $this->assertCount(1, $json['errors']['acl']);
        $this->assertSame(
            "The specified email address is invalid.",
            $json['errors']['acl'][1]
        );

        $this->assertSame(['acl' => ['john@kolab.org, full']], $folder->fresh()->getConfig());
    }

    /**
     * Test fetching shared folder data/profile (GET /api/v4/shared-folders/<folder>)
     */
    public function testShow(): void
    {
        $jack = $this->getTestUser('jack@kolab.org');
        $john = $this->getTestUser('john@kolab.org');
        $ned = $this->getTestUser('ned@kolab.org');

        $folder = $this->getTestSharedFolder('folder-test@kolab.org');
        $folder->assignToWallet($john->wallets->first());
        $folder->setSetting('acl', '["anyone, full"]');
        $folder->setAliases(['folder-alias@kolab.org']);

        // Test unauthenticated access
        $response = $this->get("/api/v4/shared-folders/{$folder->id}");
        $response->assertStatus(401);

        // Test unauthorized access to a shared folder of another user
        $response = $this->actingAs($jack)->get("/api/v4/shared-folders/{$folder->id}");
        $response->assertStatus(403);

        // John: Account owner - non-existing folder
        $response = $this->actingAs($john)->get("/api/v4/shared-folders/abc");
        $response->assertStatus(404);

        // John: Account owner
        $response = $this->actingAs($john)->get("/api/v4/shared-folders/{$folder->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame($folder->id, $json['id']);
        $this->assertSame($folder->email, $json['email']);
        $this->assertSame($folder->name, $json['name']);
        $this->assertSame($folder->type, $json['type']);
        $this->assertSame(['folder-alias@kolab.org'], $json['aliases']);
        $this->assertTrue(!empty($json['statusInfo']));
        $this->assertArrayHasKey('isDeleted', $json);
        $this->assertArrayHasKey('isActive', $json);
        $this->assertArrayHasKey('isLdapReady', $json);
        $this->assertArrayHasKey('isImapReady', $json);
        $this->assertSame(['acl' => ['anyone, full']], $json['config']);
    }

    /**
     * Test fetching a shared folder status (GET /api/v4/shared-folders/<folder>/status)
     * and forcing setup process update (?refresh=1)
     */
    public function testStatus(): void
    {
        Queue::fake();

        $john = $this->getTestUser('john@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');

        $folder = $this->getTestSharedFolder('folder-test@kolab.org');
        $folder->assignToWallet($john->wallets->first());

        // Test unauthorized access
        $response = $this->get("/api/v4/shared-folders/abc/status");
        $response->assertStatus(401);

        // Test unauthorized access
        $response = $this->actingAs($jack)->get("/api/v4/shared-folders/{$folder->id}/status");
        $response->assertStatus(403);

        $folder->status = SharedFolder::STATUS_NEW | SharedFolder::STATUS_ACTIVE;
        $folder->save();

        // Get resource status
        $response = $this->actingAs($john)->get("/api/v4/shared-folders/{$folder->id}/status");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertFalse($json['isLdapReady']);
        $this->assertFalse($json['isImapReady']);
        $this->assertFalse($json['isReady']);
        $this->assertFalse($json['isDeleted']);
        $this->assertTrue($json['isActive']);
        $this->assertCount(7, $json['process']);
        $this->assertSame('shared-folder-new', $json['process'][0]['label']);
        $this->assertSame(true, $json['process'][0]['state']);
        $this->assertSame('shared-folder-ldap-ready', $json['process'][1]['label']);
        $this->assertSame(false, $json['process'][1]['state']);
        $this->assertTrue(empty($json['status']));
        $this->assertTrue(empty($json['message']));
        $this->assertSame('running', $json['processState']);

        // Make sure the domain is confirmed (other test might unset that status)
        $domain = $this->getTestDomain('kolab.org');
        $domain->status |= \App\Domain::STATUS_CONFIRMED;
        $domain->save();
        $folder->status |= SharedFolder::STATUS_IMAP_READY;
        $folder->save();

        // Now "reboot" the process and get the folder status
        $response = $this->actingAs($john)->get("/api/v4/shared-folders/{$folder->id}/status?refresh=1");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertTrue($json['isLdapReady']);
        $this->assertTrue($json['isImapReady']);
        $this->assertTrue($json['isReady']);
        $this->assertCount(7, $json['process']);
        $this->assertSame('shared-folder-ldap-ready', $json['process'][1]['label']);
        $this->assertSame(true, $json['process'][1]['state']);
        $this->assertSame('shared-folder-imap-ready', $json['process'][2]['label']);
        $this->assertSame(true, $json['process'][2]['state']);
        $this->assertSame('success', $json['status']);
        $this->assertSame('Setup process finished successfully.', $json['message']);
        $this->assertSame('done', $json['processState']);

        // Test a case when a domain is not ready
        $domain->status ^= \App\Domain::STATUS_CONFIRMED;
        $domain->save();

        $response = $this->actingAs($john)->get("/api/v4/shared-folders/{$folder->id}/status?refresh=1");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertTrue($json['isLdapReady']);
        $this->assertTrue($json['isReady']);
        $this->assertCount(7, $json['process']);
        $this->assertSame('shared-folder-ldap-ready', $json['process'][1]['label']);
        $this->assertSame(true, $json['process'][1]['state']);
        $this->assertSame('success', $json['status']);
        $this->assertSame('Setup process finished successfully.', $json['message']);
    }

    /**
     * Test SharedFoldersController::statusInfo()
     */
    public function testStatusInfo(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $folder = $this->getTestSharedFolder('folder-test@kolab.org');
        $folder->assignToWallet($john->wallets->first());
        $folder->status = SharedFolder::STATUS_NEW | SharedFolder::STATUS_ACTIVE;
        $folder->save();
        $domain = $this->getTestDomain('kolab.org');
        $domain->status |= \App\Domain::STATUS_CONFIRMED;
        $domain->save();

        $result = SharedFoldersController::statusInfo($folder);

        $this->assertFalse($result['isReady']);
        $this->assertCount(7, $result['process']);
        $this->assertSame('shared-folder-new', $result['process'][0]['label']);
        $this->assertSame(true, $result['process'][0]['state']);
        $this->assertSame('shared-folder-ldap-ready', $result['process'][1]['label']);
        $this->assertSame(false, $result['process'][1]['state']);
        $this->assertSame('running', $result['processState']);

        $folder->created_at = Carbon::now()->subSeconds(181);
        $folder->save();

        $result = SharedFoldersController::statusInfo($folder);

        $this->assertSame('failed', $result['processState']);

        $folder->status |= SharedFolder::STATUS_LDAP_READY | SharedFolder::STATUS_IMAP_READY;
        $folder->save();

        $result = SharedFoldersController::statusInfo($folder);

        $this->assertTrue($result['isReady']);
        $this->assertCount(7, $result['process']);
        $this->assertSame('shared-folder-new', $result['process'][0]['label']);
        $this->assertSame(true, $result['process'][0]['state']);
        $this->assertSame('shared-folder-ldap-ready', $result['process'][1]['label']);
        $this->assertSame(true, $result['process'][1]['state']);
        $this->assertSame('shared-folder-ldap-ready', $result['process'][1]['label']);
        $this->assertSame(true, $result['process'][1]['state']);
        $this->assertSame('done', $result['processState']);
    }

    /**
     * Test shared folder creation (POST /api/v4/shared-folders)
     */
    public function testStore(): void
    {
        Queue::fake();

        $jack = $this->getTestUser('jack@kolab.org');
        $john = $this->getTestUser('john@kolab.org');

        // Test unauth request
        $response = $this->post("/api/v4/shared-folders", []);
        $response->assertStatus(401);

        // Test non-controller user
        $response = $this->actingAs($jack)->post("/api/v4/shared-folders", []);
        $response->assertStatus(403);

        // Test empty request
        $response = $this->actingAs($john)->post("/api/v4/shared-folders", []);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertSame("The name field is required.", $json['errors']['name'][0]);
        $this->assertSame("The type field is required.", $json['errors']['type'][0]);
        $this->assertCount(2, $json);
        $this->assertCount(2, $json['errors']);

        // Test too long name, invalid alias domain
        $post = [
            'domain' => 'kolab.org',
            'name' => str_repeat('A', 192),
            'type' => 'unknown',
            'aliases' => ['folder-alias@unknown.org'],
        ];

        $response = $this->actingAs($john)->post("/api/v4/shared-folders", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertCount(2, $json);
        $this->assertSame(["The name may not be greater than 191 characters."], $json['errors']['name']);
        $this->assertSame(["The specified type is invalid."], $json['errors']['type']);
        $this->assertSame(["The specified domain is invalid."], $json['errors']['aliases']);
        $this->assertCount(3, $json['errors']);

        // Test successful folder creation
        $post['name'] = 'Test Folder';
        $post['type'] = 'event';
        $post['aliases'] = [];

        $response = $this->actingAs($john)->post("/api/v4/shared-folders", $post);
        $json = $response->json();

        $response->assertStatus(200);

        $this->assertSame('success', $json['status']);
        $this->assertSame("Shared folder created successfully.", $json['message']);
        $this->assertCount(2, $json);

        $folder = SharedFolder::where('name', $post['name'])->first();
        $this->assertInstanceOf(SharedFolder::class, $folder);
        $this->assertSame($post['type'], $folder->type);
        $this->assertTrue($john->sharedFolders()->get()->contains($folder));
        $this->assertSame([], $folder->aliases()->pluck('alias')->all());

        // Shared folder name must be unique within a domain
        $post['type'] = 'mail';
        $response = $this->actingAs($john)->post("/api/v4/shared-folders", $post);
        $response->assertStatus(422);
        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertCount(2, $json);
        $this->assertCount(1, $json['errors']);
        $this->assertSame("The specified name is not available.", $json['errors']['name'][0]);

        $folder->forceDelete();

        // Test successful folder creation with aliases
        $post['name'] = 'Test Folder';
        $post['type'] = 'mail';
        $post['aliases'] = ['folder-alias@kolab.org'];
        $response = $this->actingAs($john)->post("/api/v4/shared-folders", $post);
        $json = $response->json();

        $response->assertStatus(200);

        $folder = SharedFolder::where('name', $post['name'])->first();
        $this->assertSame(['folder-alias@kolab.org'], $folder->aliases()->pluck('alias')->all());

        $folder->forceDelete();

        // Test handling subfolders and lmtp alias email
        $post['name'] = 'Test/Folder';
        $post['type'] = 'mail';
        $post['aliases'] = ['shared+shared/Test/Folder@kolab.org'];
        $response = $this->actingAs($john)->post("/api/v4/shared-folders", $post);
        $json = $response->json();

        $response->assertStatus(200);

        $folder = SharedFolder::where('name', $post['name'])->first();
        $this->assertSame(['shared+shared/Test/Folder@kolab.org'], $folder->aliases()->pluck('alias')->all());
    }

    /**
     * Test shared folder update (PUT /api/v4/shared-folders/<folder)
     */
    public function testUpdate(): void
    {
        Queue::fake();

        $jack = $this->getTestUser('jack@kolab.org');
        $john = $this->getTestUser('john@kolab.org');
        $ned = $this->getTestUser('ned@kolab.org');

        $folder = $this->getTestSharedFolder('folder-test@kolab.org');
        $folder->assignToWallet($john->wallets->first());

        // Test unauthorized update
        $response = $this->get("/api/v4/shared-folders/{$folder->id}", []);
        $response->assertStatus(401);

        // Test unauthorized update
        $response = $this->actingAs($jack)->get("/api/v4/shared-folders/{$folder->id}", []);
        $response->assertStatus(403);

        // Name change
        $post = [
            'name' => 'Test Res',
        ];

        $response = $this->actingAs($john)->put("/api/v4/shared-folders/{$folder->id}", $post);
        $json = $response->json();

        $response->assertStatus(200);

        $this->assertSame('success', $json['status']);
        $this->assertSame("Shared folder updated successfully.", $json['message']);
        $this->assertCount(2, $json);

        $folder->refresh();
        $this->assertSame($post['name'], $folder->name);

        // Aliases with error
        $post['aliases'] = ['folder-alias1@kolab.org', 'folder-alias2@unknown.com'];

        $response = $this->actingAs($john)->put("/api/v4/shared-folders/{$folder->id}", $post);
        $json = $response->json();

        $response->assertStatus(422);

        $this->assertSame('error', $json['status']);
        $this->assertCount(2, $json);
        $this->assertCount(1, $json['errors']);
        $this->assertCount(1, $json['errors']['aliases']);
        $this->assertSame("The specified domain is invalid.", $json['errors']['aliases'][1]);
        $this->assertSame([], $folder->aliases()->pluck('alias')->all());

        // Aliases with success expected
        $post['aliases'] = ['folder-alias1@kolab.org', 'folder-alias2@kolab.org'];

        $response = $this->actingAs($john)->put("/api/v4/shared-folders/{$folder->id}", $post);
        $json = $response->json();

        $response->assertStatus(200);

        $this->assertSame('success', $json['status']);
        $this->assertSame("Shared folder updated successfully.", $json['message']);
        $this->assertCount(2, $json);
        $this->assertSame($post['aliases'], $folder->aliases()->pluck('alias')->all());

        // All aliases removal
        $post['aliases'] = [];

        $response = $this->actingAs($john)->put("/api/v4/shared-folders/{$folder->id}", $post);
        $response->assertStatus(200);

        $this->assertSame($post['aliases'], $folder->aliases()->pluck('alias')->all());
    }
}

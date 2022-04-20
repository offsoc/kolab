<?php

namespace Tests\Feature\Controller;

use App\Backends\Storage;
use App\Fs\Item;
use App\Fs\Property;
use App\User;
use Illuminate\Support\Facades\Storage as LaravelStorage;
use Tests\TestCase;

/**
 * @group files
 */
class FilesTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        Item::query()->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        Item::query()->delete();

        $disk = LaravelStorage::disk('files');
        foreach ($disk->listContents('') as $dir) {
            $disk->deleteDirectory($dir->path());
        }

        parent::tearDown();
    }

    /**
     * Test deleting files (DELETE /api/v4/files/<file-id>)
     */
    public function testDelete(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');
        $file = $this->getTestFile($john, 'teśt.txt', 'Teśt content');

        // Unauth access
        $response = $this->delete("api/v4/files/{$file->id}");
        $response->assertStatus(401);

        // Unauth access
        $response = $this->actingAs($jack)->delete("api/v4/files/{$file->id}");
        $response->assertStatus(403);

        // Non-existing file
        $response = $this->actingAs($john)->delete("api/v4/files/123");
        $response->assertStatus(404);

        // File owner access
        $response = $this->actingAs($john)->delete("api/v4/files/{$file->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame("File deleted successfully.", $json['message']);
        $this->assertSame(null, Item::find($file->id));

        // Note: The file is expected to stay still in the filesystem, we're not testing this here.

        // TODO: Test acting as another user with permissions
    }

    /**
     * Test file downloads (GET /api/v4/files/downloads/<id>)
     */
    public function testDownload(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');
        $file = $this->getTestFile($john, 'teśt.txt', 'Teśt content');

        // Unauth access
        $response = $this->get("api/v4/files/{$file->id}?downloadUrl=1");
        $response->assertStatus(401);

        $response = $this->actingAs($jack)->get("api/v4/files/{$file->id}?downloadUrl=1");
        $response->assertStatus(403);

        // Non-existing file
        $response = $this->actingAs($john)->get("api/v4/files/123456?downloadUrl=1");
        $response->assertStatus(404);

        // Get downloadLink for the file
        $response = $this->actingAs($john)->get("api/v4/files/{$file->id}?downloadUrl=1");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame($file->id, $json['id']);
        $link = $json['downloadUrl'];

        // Fetch the file content
        $response = $this->get(substr($link, strpos($link, '/api/') + 1));
        $response->assertStatus(200)
            ->assertHeader('Content-Disposition', "attachment; filename=test.txt; filename*=utf-8''te%C5%9Bt.txt")
            ->assertHeader('Content-Length', $file->getProperty('size'))
            ->assertHeader('Content-Type', $file->getProperty('mimetype') . '; charset=UTF-8');

        $this->assertSame('Teśt content', $response->streamedContent());

        // Test acting as another user with read permission
        $permission = $this->getTestFilePermission($file, $jack, 'r');
        $response = $this->actingAs($jack)->get("api/v4/files/{$permission->key}?downloadUrl=1");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame($file->id, $json['id']);
        $link = $json['downloadUrl'];

        // Fetch the file content
        $response = $this->get(substr($link, strpos($link, '/api/') + 1));
        $response->assertStatus(200)
            ->assertHeader('Content-Disposition', "attachment; filename=test.txt; filename*=utf-8''te%C5%9Bt.txt")
            ->assertHeader('Content-Length', $file->getProperty('size'))
            ->assertHeader('Content-Type', $file->getProperty('mimetype') . '; charset=UTF-8');

        $this->assertSame('Teśt content', $response->streamedContent());

        // Test downloading a multi-chunk file
        $file = $this->getTestFile($john, 'test2.txt', ['T1', 'T2']);
        $response = $this->actingAs($john)->get("api/v4/files/{$file->id}?downloadUrl=1");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame($file->id, $json['id']);
        $link = $json['downloadUrl'];

        // Fetch the file content
        $response = $this->get(substr($link, strpos($link, '/api/') + 1));
        $response->assertStatus(200)
            ->assertHeader('Content-Disposition', "attachment; filename=test2.txt")
            ->assertHeader('Content-Length', $file->getProperty('size'))
            ->assertHeader('Content-Type', $file->getProperty('mimetype'));

        $this->assertSame('T1T2', $response->streamedContent());
    }

    /**
     * Test fetching/creating/updaing/deleting file permissions (GET|POST|PUT /api/v4/files/<file-id>/permissions)
     */
    public function testPermissions(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');
        $file = $this->getTestFile($john, 'test1.txt', []);

        // Unauth access not allowed
        $response = $this->get("api/v4/files/{$file->id}/permissions");
        $response->assertStatus(401);
        $response = $this->post("api/v4/files/{$file->id}/permissions", []);
        $response->assertStatus(401);

        // Non-existing file
        $response = $this->actingAs($john)->get("api/v4/files/1234/permissions");
        $response->assertStatus(404);
        $response = $this->actingAs($john)->post("api/v4/files/1234/permissions", []);
        $response->assertStatus(404);

        // No permissions to the file
        $response = $this->actingAs($jack)->get("api/v4/files/{$file->id}/permissions");
        $response->assertStatus(403);
        $response = $this->actingAs($jack)->post("api/v4/files/{$file->id}/permissions", []);
        $response->assertStatus(403);

        // Expect an empty list of permissions
        $response = $this->actingAs($john)->get("api/v4/files/{$file->id}/permissions");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(2, $json);
        $this->assertSame([], $json['list']);
        $this->assertSame(0, $json['count']);

        // Empty input
        $response = $this->actingAs($john)->post("api/v4/files/{$file->id}/permissions", []);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertCount(2, $json['errors']);
        $this->assertSame(["The user field is required."], $json['errors']['user']);
        $this->assertSame(["The permissions field is required."], $json['errors']['permissions']);

        // Test more input validation
        $post = ['user' => 'user', 'permissions' => 'read'];
        $response = $this->actingAs($john)->post("api/v4/files/{$file->id}/permissions", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertCount(2, $json['errors']);
        $this->assertSame(["The user must be a valid email address."], $json['errors']['user']);
        $this->assertSame("The file permission is invalid.", $json['errors']['permissions']);

        // Let's add some permission
        $post = ['user' => 'jack@kolab.org', 'permissions' => 'read-only'];
        $response = $this->actingAs($john)->post("api/v4/files/{$file->id}/permissions", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame("File permissions created successfully.", $json['message']);

        $permission = $file->properties()->where('key', 'like', 'share-%')->orderBy('value')->first();

        $this->assertSame("{$jack->email}:r", $permission->value);
        $this->assertSame($permission->key, $json['id']);
        $this->assertSame($jack->email, $json['user']);
        $this->assertSame('read-only', $json['permissions']);
        $this->assertSame(\App\Utils::serviceUrl('file/' . $permission->key), $json['link']);

        // Error handling on use of the same user
        $post = ['user' => 'jack@kolab.org', 'permissions' => 'read-only'];
        $response = $this->actingAs($john)->post("api/v4/files/{$file->id}/permissions", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertSame("File permission already exists.", $json['errors']['user']);

        // Test update
        $response = $this->actingAs($john)->put("api/v4/files/{$file->id}/permissions/1234", $post);
        $response->assertStatus(404);

        $post = ['user' => 'jack@kolab.org', 'permissions' => 'read-write'];
        $response = $this->actingAs($john)->put("api/v4/files/{$file->id}/permissions/{$permission->key}", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame("File permissions updated successfully.", $json['message']);

        $permission->refresh();

        $this->assertSame("{$jack->email}:rw", $permission->value);
        $this->assertSame($permission->key, $json['id']);
        $this->assertSame($jack->email, $json['user']);
        $this->assertSame('read-write', $json['permissions']);
        $this->assertSame(\App\Utils::serviceUrl('file/' . $permission->key), $json['link']);

        // Input validation on update
        $post = ['user' => 'jack@kolab.org', 'permissions' => 'read'];
        $response = $this->actingAs($john)->put("api/v4/files/{$file->id}/permissions/{$permission->key}", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertSame("The file permission is invalid.", $json['errors']['permissions']);

        // Test GET with existing permissions
        $response = $this->actingAs($john)->get("api/v4/files/{$file->id}/permissions");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(2, $json);
        $this->assertCount(1, $json['list']);
        $this->assertSame(1, $json['count']);

        $this->assertSame($permission->key, $json['list'][0]['id']);
        $this->assertSame($jack->email, $json['list'][0]['user']);
        $this->assertSame('read-write', $json['list'][0]['permissions']);
        $this->assertSame(\App\Utils::serviceUrl('file/' . $permission->key), $json['list'][0]['link']);

        // Delete permission
        $response = $this->actingAs($john)->delete("api/v4/files/{$file->id}/permissions/1234");
        $response->assertStatus(404);

        $response = $this->actingAs($john)->delete("api/v4/files/{$file->id}/permissions/{$permission->key}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame("File permissions deleted successfully.", $json['message']);

        $this->assertCount(0, $file->properties()->where('key', 'like', 'share-%')->get());
    }

    /**
     * Test fetching files/folders list (GET /api/v4/files)
     */
    public function testIndex(): void
    {
        // Unauth access not allowed
        $response = $this->get("api/v4/files");
        $response->assertStatus(401);

        $user = $this->getTestUser('john@kolab.org');

        // Expect an empty list
        $response = $this->actingAs($user)->get("api/v4/files");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(3, $json);
        $this->assertSame([], $json['list']);
        $this->assertSame(0, $json['count']);
        $this->assertSame(false, $json['hasMore']);

        // Create some files and test again
        $file1 = $this->getTestFile($user, 'test1.txt', [], ['mimetype' => 'text/plain', 'size' => 12345]);
        $file2 = $this->getTestFile($user, 'test2.gif', [], ['mimetype' => 'image/gif', 'size' => 10000]);

        $response = $this->actingAs($user)->get("api/v4/files");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(3, $json);
        $this->assertSame(2, $json['count']);
        $this->assertSame(false, $json['hasMore']);
        $this->assertCount(2, $json['list']);
        $this->assertSame('test1.txt', $json['list'][0]['name']);
        $this->assertSame($file1->id, $json['list'][0]['id']);
        $this->assertSame('test2.gif', $json['list'][1]['name']);
        $this->assertSame($file2->id, $json['list'][1]['id']);

        // Searching
        $response = $this->actingAs($user)->get("api/v4/files?search=t2");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(3, $json);
        $this->assertSame(1, $json['count']);
        $this->assertSame(false, $json['hasMore']);
        $this->assertCount(1, $json['list']);
        $this->assertSame('test2.gif', $json['list'][0]['name']);
        $this->assertSame($file2->id, $json['list'][0]['id']);

        // TODO: Test paging
    }

    /**
     * Test fetching file metadata (GET /api/v4/files/<file-id>)
     */
    public function testShow(): void
    {
        // Unauth access not allowed
        $response = $this->get("api/v4/files/1234");
        $response->assertStatus(401);

        $john = $this->getTestUser('john@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');
        $file = $this->getTestFile($john, 'teśt.txt', 'Teśt content');

        // Non-existing file
        $response = $this->actingAs($jack)->get("api/v4/files/1234");
        $response->assertStatus(404);

        // Unauthorized access
        $response = $this->actingAs($jack)->get("api/v4/files/{$file->id}");
        $response->assertStatus(403);

        // Get file metadata
        $response = $this->actingAs($john)->get("api/v4/files/{$file->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame($file->id, $json['id']);
        $this->assertSame($file->getProperty('mimetype'), $json['mimetype']);
        $this->assertSame((int) $file->getProperty('size'), $json['size']);
        $this->assertSame($file->getProperty('name'), $json['name']);
        $this->assertSame(true, $json['isOwner']);
        $this->assertSame(true, $json['canUpdate']);
        $this->assertSame(true, $json['canDelete']);

        // Get file content
        $response = $this->actingAs($john)->get("api/v4/files/{$file->id}?download=1");
        $response->assertStatus(200)
            ->assertHeader('Content-Disposition', "attachment; filename=test.txt; filename*=utf-8''te%C5%9Bt.txt")
            ->assertHeader('Content-Length', $file->getProperty('size'))
            ->assertHeader('Content-Type', $file->getProperty('mimetype') . '; charset=UTF-8');

        $this->assertSame('Teśt content', $response->streamedContent());

        // Test acting as a user with file permissions
        $permission = $this->getTestFilePermission($file, $jack, 'r');
        $response = $this->actingAs($jack)->get("api/v4/files/{$permission->key}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame($file->id, $json['id']);
        $this->assertSame(false, $json['isOwner']);
        $this->assertSame(false, $json['canUpdate']);
        $this->assertSame(false, $json['canDelete']);
    }

    /**
     * Test creating files (POST /api/v4/files)
     */
    public function testStore(): void
    {
        // Unauth access not allowed
        $response = $this->post("api/v4/files");
        $response->assertStatus(401);

        $john = $this->getTestUser('john@kolab.org');

        // Test input validation
        $response = $this->sendRawBody($john, 'POST', "api/v4/files", [], '');
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertSame(["The name field is required."], $json['errors']['name']);

        $response = $this->sendRawBody($john, 'POST', "api/v4/files?name=*.txt", [], '');
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertSame(["The file name is invalid."], $json['errors']['name']);

        // Create a file - the simple method
        $body = "test content";
        $headers = [];
        $response = $this->sendRawBody($john, 'POST', "api/v4/files?name=test.txt", $headers, $body);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame("File created successfully.", $json['message']);
        $this->assertSame('text/plain', $json['mimetype']);
        $this->assertSame(strlen($body), $json['size']);
        $this->assertSame('test.txt', $json['name']);

        $file = Item::find($json['id']);

        $this->assertSame(Item::TYPE_FILE, $file->type);
        $this->assertSame($json['mimetype'], $file->getProperty('mimetype'));
        $this->assertSame($json['size'], (int) $file->getProperty('size'));
        $this->assertSame($json['name'], $file->getProperty('name'));
        $this->assertSame($body, $this->getTestFileContent($file));
    }

    /**
     * Test creating files - resumable (POST /api/v4/files)
     */
    public function testStoreResumable(): void
    {
        $john = $this->getTestUser('john@kolab.org');

        $response = $this->actingAs($john)->post("api/v4/files?name=test2.txt&media=resumable&size=400");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['uploaded']);
        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/',
            $json['uploadId']
        );

        $uploadId = $json['uploadId'];
        $size = 0;
        $fileContent = '';

        for ($x = 0; $x <= 2; $x++) {
            $body = str_repeat("$x", 100);
            $response = $this->sendRawBody(null, 'POST', "api/v4/files/uploads/{$uploadId}?from={$size}", [], $body);
            $response->assertStatus(200);

            $json = $response->json();
            $size += 100;
            $fileContent .= $body;

            $this->assertSame($size, $json['uploaded']);
            $this->assertSame($uploadId, $json['uploadId']);
        }

        $body = str_repeat("$x", 100);
        $response = $this->sendRawBody(null, 'POST', "api/v4/files/uploads/{$uploadId}?from={$size}", [], $body);
        $response->assertStatus(200);

        $json = $response->json();
        $size += 100;
        $fileContent .= $body;

        $this->assertSame('success', $json['status']);
        // $this->assertSame("", $json['message']);
        $this->assertSame('text/plain', $json['mimetype']);
        $this->assertSame($size, $json['size']);
        $this->assertSame('test2.txt', $json['name']);

        $file = Item::find($json['id']);

        $this->assertSame(Item::TYPE_FILE, $file->type);
        $this->assertSame($json['mimetype'], $file->getProperty('mimetype'));
        $this->assertSame($json['size'], (int) $file->getProperty('size'));
        $this->assertSame($json['name'], $file->getProperty('name'));
        $this->assertSame($fileContent, $this->getTestFileContent($file));
    }

    /**
     * Test updating files (PUT /api/v4/files/<file-id>)
     */
    public function testUpdate(): void
    {
        // Unauth access not allowed
        $response = $this->put("api/v4/files/1234");
        $response->assertStatus(401);

        $john = $this->getTestUser('john@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');
        $file = $this->getTestFile($john, 'teśt.txt', 'Teśt content');

        // Non-existing file
        $response = $this->actingAs($john)->put("api/v4/files/1234", []);
        $response->assertStatus(404);

        // Unauthorized access
        $response = $this->actingAs($jack)->put("api/v4/files/{$file->id}", []);
        $response->assertStatus(403);

        // Test name validation
        $post = ['name' => 'test/test.txt'];
        $response = $this->actingAs($john)->put("api/v4/files/{$file->id}", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertSame(["The file name is invalid."], $json['errors']['name']);

        $post = ['name' => 'new name.txt', 'media' => 'test'];
        $response = $this->actingAs($john)->put("api/v4/files/{$file->id}", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertSame("The specified media is invalid.", $json['errors']['media']);

        // Rename a file
        $post = ['name' => 'new namś.txt'];
        $response = $this->actingAs($john)->put("api/v4/files/{$file->id}", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame("File updated successfully.", $json['message']);

        $file->refresh();

        $this->assertSame($post['name'], $file->getProperty('name'));

        // Update file content
        $body = "Test1\nTest2";
        $response = $this->sendRawBody($john, 'PUT', "api/v4/files/{$file->id}?media=content", [], $body);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame("File updated successfully.", $json['message']);

        $file->refresh();

        $this->assertSame($body, $this->getTestFileContent($file));
        $this->assertSame('text/plain', $file->getProperty('mimetype'));
        $this->assertSame(strlen($body), (int) $file->getProperty('size'));

        // TODO: Test acting as another user with file permissions
        // TODO: Test media=resumable
    }

    /**
     * Create a test file.
     *
     * @param \App\User    $user    File owner
     * @param string       $name    File name
     * @param string|array $content File content
     * @param array        $props   Extra file properties
     *
     * @return \App\Fs\Item
     */
    protected function getTestFile(User $user, string $name, $content = [], $props = []): Item
    {
        $disk = LaravelStorage::disk('files');

        $file = $user->fsItems()->create(['type' => Item::TYPE_FILE]);
        $size = 0;
        $mimetype = '';

        if (is_array($content) && empty($content)) {
            // do nothing, we don't need the body here
        } else {
            foreach ((array) $content as $idx => $chunk) {
                $chunkId = \App\Utils::uuidStr();
                $path = Storage::chunkLocation($chunkId, $file);

                $disk->write($path, $chunk);

                if (!$size) {
                    $mimetype = $disk->mimeType($path);
                }

                $size += strlen($chunk);

                $file->chunks()->create([
                        'chunk_id' => $chunkId,
                        'sequence' => $idx,
                        'size' => strlen($chunk),
                ]);
            }
        }

        $properties = [
            'name' => $name,
            'size' => $size,
            'mimetype' => $mimetype ?: 'application/octet-stream',
        ];

        $file->setProperties($props + $properties);

        return $file;
    }

    /**
     * Get contents of a test file.
     *
     * @param \App\Fs\Item $file File record
     *
     * @return string
     */
    protected function getTestFileContent(Item $file): string
    {
        $content = '';

        $file->chunks()->orderBy('sequence')->get()->each(function ($chunk) use ($file, &$content) {
            $disk = LaravelStorage::disk('files');
            $path = Storage::chunkLocation($chunk->chunk_id, $file);

            $content .= $disk->read($path);
        });

        return $content;
    }

    /**
     * Create a test file permission.
     *
     * @param \App\Fs\Item $file       The file
     * @param \App\User    $user       File owner
     * @param string       $permission File permission
     *
     * @return \App\Fs\Property File permission property
     */
    protected function getTestFilePermission(Item $file, User $user, string $permission): Property
    {
        $shareId = 'share-' . \App\Utils::uuidStr();

        return $file->properties()->create([
                'key' => $shareId,
                'value' => "{$user->email}:{$permission}",
        ]);
    }

    /**
     * Invoke a HTTP request with a custom raw body
     *
     * @param ?\App\User $user    Authenticated user
     * @param string     $method  Request method (POST,  PUT)
     * @param string     $uri     Request URL
     * @param array      $headers Request headers
     * @param string     $content Raw body content
     *
     * @return \Illuminate\Testing\TestResponse HTTP Response object
     */
    protected function sendRawBody(?User $user, string $method, string $uri, array $headers, string $content)
    {
        $headers['Content-Length'] = strlen($content);

        $server = $this->transformHeadersToServerVars($headers);
        $cookies = $this->prepareCookiesForRequest();

        if ($user) {
            return $this->actingAs($user)->call($method, $uri, [], $cookies, [], $server, $content);
        } else {
            // TODO: Make sure this does not use "acting user" set earlier
            return $this->call($method, $uri, [], $cookies, [], $server, $content);
        }
    }
}

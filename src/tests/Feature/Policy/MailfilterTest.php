<?php

namespace Tests\Feature\Policy;

use App\Policy\Mailfilter;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MailfilterTest extends TestCase
{
    private $keys = [
        'externalsender_config',
        'externalsender_policy',
        'itip_config',
        'itip_policy',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $john = $this->getTestUser('john@kolab.org');
        $john->settings()->whereIn('key', $this->keys)->delete();
        $jack = $this->getTestUser('jack@kolab.org');
        $jack->settings()->whereIn('key', $this->keys)->delete();
    }

    protected function tearDown(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $john->settings()->whereIn('key', $this->keys)->delete();
        $jack = $this->getTestUser('jack@kolab.org');
        $jack->settings()->whereIn('key', $this->keys)->delete();

        parent::tearDown();
    }

    /**
     * Test mail filter basic functionality
     */
    public function testHandle()
    {
        $mail = file_get_contents(self::BASE_DIR . '/data/mail/1.eml');
        $mail = str_replace("\n", "\r\n", $mail);

        // Test unknown recipient
        $get = ['recipient' => 'unknown@domain.tld', 'sender' => 'jack@kolab.org'];
        $request = new Request($get, [], [], [], [], [], $mail);
        $response = Mailfilter::handle($request);

        $this->assertSame(Mailfilter::CODE_ACCEPT_EMPTY, $response->status());
        $this->assertSame('', $response->content());

        $john = $this->getTestUser('john@kolab.org');

        // No modules enabled, no changes to the mail content
        $get = ['recipient' => $john->email, 'sender' => 'jack@kolab.org'];
        $request = new Request($get, [], [], [], [], [], $mail);
        $response = Mailfilter::handle($request);

        $this->assertSame(Mailfilter::CODE_ACCEPT_EMPTY, $response->status());
        $this->assertSame('', $response->content());

        // Note: We using HTTP controller here for easier use of Laravel request/response
        $this->useServicesUrl();

        // Test returning (modified) mail content
        $john->setConfig(['externalsender_policy' => true]);
        $url = '/api/webhooks/policy/mail/filter?recipient=john@kolab.org&sender=jack@external.tld';
        $content = $this->call('POST', $url, [], [], [], [], $mail)
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'message/rfc822')
            ->streamedContent();

        $this->assertStringContainsString('Subject: [EXTERNAL] test sync', $content);
        $this->assertStringContainsString('ZWVlYQ==', $content);

        // Test multipart/form-data request
        $file = UploadedFile::fake()->createWithContent('mail.eml', $mail);
        $content = $this->call('POST', $url, ['file' => $file], [], [], [])
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'message/rfc822')
            ->streamedContent();

        $this->assertStringContainsString('Subject: [EXTERNAL] test sync', $content);
        $this->assertStringContainsString('ZWVlYQ==', $content);

        // TODO: Test rejecting mail
        // TODO: Test two modules that both modify the mail content
        $this->markTestIncomplete();
    }

    /**
     * Test reading modules configuration/policy
     */
    public function testGetModulesConfig()
    {
        $john = $this->getTestUser('john@kolab.org');
        $jack = $this->getTestUser('jack@kolab.org');
        $filter = new Mailfilter();

        // No module configured yet, no policy, no config
        $this->assertSame([], $this->invokeMethod($filter, 'getModulesConfig', [$john]));
        $this->assertSame([], $this->invokeMethod($filter, 'getModulesConfig', [$jack]));

        // Enable account policies
        $john->setConfig(['externalsender_policy' => true, 'itip_policy' => true]);
        $expected = [
            Mailfilter\Modules\ItipModule::class => [],
            Mailfilter\Modules\ExternalSenderModule::class => [],
        ];

        $this->assertSame($expected, $this->invokeMethod($filter, 'getModulesConfig', [$john]));
        $this->assertSame($expected, $this->invokeMethod($filter, 'getModulesConfig', [$jack]));

        // Enabled account policies, and enabled per-user config
        $jack->setConfig(['externalsender_config' => true, 'itip_config' => true]);
        $this->assertSame($expected, $this->invokeMethod($filter, 'getModulesConfig', [$john]));
        $this->assertSame($expected, $this->invokeMethod($filter, 'getModulesConfig', [$jack]));

        // Enabled account policies, and disabled per-user config
        $jack->setConfig(['externalsender_config' => false, 'itip_config' => false]);
        $this->assertSame($expected, $this->invokeMethod($filter, 'getModulesConfig', [$john]));
        $this->assertSame([], $this->invokeMethod($filter, 'getModulesConfig', [$jack]));

        // Disabled account policies, and disabled per-user config
        $john->setConfig(['externalsender_policy' => false, 'itip_policy' => false]);
        $this->assertSame([], $this->invokeMethod($filter, 'getModulesConfig', [$john]));
        $this->assertSame([], $this->invokeMethod($filter, 'getModulesConfig', [$jack]));

        // Disabled account policies, and enabled per-user config
        $jack->setConfig(['externalsender_config' => true, 'itip_config' => true]);
        $this->assertSame([], $this->invokeMethod($filter, 'getModulesConfig', [$john]));
        $this->assertSame($expected, $this->invokeMethod($filter, 'getModulesConfig', [$jack]));

        // As the last one, but for account owner
        $john->setConfig(['externalsender_config' => true, 'itip_config' => true]);
        $this->assertSame($expected, $this->invokeMethod($filter, 'getModulesConfig', [$john]));
        $this->assertSame($expected, $this->invokeMethod($filter, 'getModulesConfig', [$jack]));
    }
}

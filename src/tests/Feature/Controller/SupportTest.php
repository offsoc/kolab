<?php

namespace Tests\Feature\Controller;

use App\Http\Controllers\API\SupportController;
use Tests\TestCase;

class SupportTest extends TestCase
{
    /**
     * Test submitting a support request (POST /support/request)
     */
    public function testRequest(): void
    {
        $support_email = \config('app.support_email');
        if (empty($support_email)) {
            $support_email = 'support@email.tld';
            \config(['app.support_email' => $support_email]);
        }

        // Empty request
        $response = $this->post("api/v4/support/request", []);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertCount(2, $json);
        $this->assertSame('error', $json['status']);
        $this->assertCount(3, $json['errors']);
        $this->assertSame(['The email field is required.'], $json['errors']['email']);
        $this->assertSame(['The summary field is required.'], $json['errors']['summary']);
        $this->assertSame(['The body field is required.'], $json['errors']['body']);

        // Invalid email
        $post = [
            'email' => '@test.com',
            'summary' => 'Test summary',
            'body' => 'Test body',
        ];
        $response = $this->post("api/v4/support/request", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertCount(2, $json);
        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $this->assertSame(['The email must be a valid email address.'], $json['errors']['email']);

        $this->assertCount(0, $this->app->make('swift.transport')->driver()->messages());

        // Valid input
        $post = [
            'email' => 'test@test.com',
            'summary' => 'Test summary',
            'body' => 'Test body',
            'user' => '1234567',
            'name' => 'Username',
        ];
        $response = $this->post("api/v4/support/request", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(2, $json);
        $this->assertSame('success', $json['status']);
        $this->assertSame('Support request submitted successfully.', $json['message']);

        $emails = $this->app->make('swift.transport')->driver()->messages();

        $expected_body = "ID: 1234567\nName: Username\nWorking email address: test@test.com\n"
            . "Subject: Test summary\n\nTest body";

        $this->assertCount(1, $emails);
        $this->assertSame('Test summary', $emails[0]->getSubject());
        $this->assertSame(['test@test.com' => 'Username'], $emails[0]->getFrom());
        $this->assertSame(['test@test.com' => 'Username'], $emails[0]->getReplyTo());
        $this->assertNull($emails[0]->getCc());
        $this->assertSame([$support_email => null], $emails[0]->getTo());
        $this->assertSame($expected_body, trim($emails[0]->getBody()));
    }
}

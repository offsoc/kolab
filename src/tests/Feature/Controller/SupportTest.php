<?php

namespace Tests\Feature\Controller;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
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

        $this->assertCount(0, $this->getSentMessages());

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

        $emails = $this->getSentMessages();

        $this->assertCount(1, $emails);

        $to = $emails[0]->getTo();
        $from = $emails[0]->getFrom();
        $replyTo = $emails[0]->getReplyTo();
        $expectedBody = "ID: 1234567\nName: Username\nWorking email address: test@test.com\n"
            . "Subject: Test summary\n\nTest body";

        $this->assertCount(1, $to);
        $this->assertCount(1, $from);
        $this->assertCount(1, $replyTo);
        $this->assertSame('Test summary', $emails[0]->getSubject());
        $this->assertSame('test@test.com', $from[0]->getAddress());
        $this->assertSame('Username', $from[0]->getName());
        $this->assertSame('test@test.com', $replyTo[0]->getAddress());
        $this->assertSame('Username', $replyTo[0]->getName());
        $this->assertSame([], $emails[0]->getCc());
        $this->assertSame($support_email, $to[0]->getAddress());
        $this->assertSame('', $to[0]->getName());
        $this->assertSame($expectedBody, trim($emails[0]->getTextBody()));
        $this->assertSame('', trim($emails[0]->getHtmlBody()));
    }

    /**
     * Get all messages that have been sent
     *
     * @return Email[]
     */
    protected function getSentMessages(): array
    {
        $transport = $this->app->make('mail.manager')->mailer()->getSymfonyTransport();

        return $this->getObjectProperty($transport, 'messages')
            ->map(static function (SentMessage $item) {
                return $item->getOriginalMessage();
            })
            ->all();
    }
}

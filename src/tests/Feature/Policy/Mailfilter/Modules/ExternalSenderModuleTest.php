<?php

namespace Tests\Feature\Policy\Mailfilter\Modules;

use App\Policy\Mailfilter\Modules\ExternalSenderModule;
use Tests\TestCase;
use Tests\Unit\Policy\Mailfilter\MailParserTest;

class ExternalSenderModuleTest extends TestCase
{
    /**
     * Test the module
     */
    public function testHandle(): void
    {
        $domain = \config('app.domain');

        // Test an email from an external sender
        $parser = MailParserTest::getParserForFile('mail/1.eml', 'john@kolab.org', 'external@sender.tld');
        $module = new ExternalSenderModule();
        $result = $module->handle($parser);

        $this->assertNull($result);
        $this->assertSame('[EXTERNAL] test sync', $parser->getHeader('subject'));

        // Test an email from an external sender (public domain)
        $parser = MailParserTest::getParserForFile('mail/1.eml', 'john@kolab.org', "joe@{$domain}");
        $module = new ExternalSenderModule();
        $result = $module->handle($parser);

        $this->assertNull($result);
        $this->assertSame('[EXTERNAL] test sync', $parser->getHeader('subject'));

        // Test an email from an internal sender (same domain)
        $parser = MailParserTest::getParserForFile('mail/1.eml', 'john@kolab.org', 'jack@kolab.org');
        $module = new ExternalSenderModule();
        $result = $module->handle($parser);

        $this->assertNull($result);
        $this->assertSame('test sync', $parser->getHeader('subject'));

        // Test an email from an internal sender (public domain)
        $parser = MailParserTest::getParserForFile('mail/1.eml', "fred@{$domain}", "joe@{$domain}");
        $module = new ExternalSenderModule();
        $result = $module->handle($parser);

        $this->assertNull($result);
        $this->assertSame('test sync', $parser->getHeader('subject'));

        // TODO: Test other account domains
    }
}

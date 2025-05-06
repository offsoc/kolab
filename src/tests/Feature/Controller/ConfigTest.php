<?php

namespace Tests\Feature\Controller;

use Tests\TestCase;

class ConfigTest extends TestCase
{
    /**
     * Test webmail configuration (GET /api/v4/config/webmail)
     */
    public function testWebmail(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $joe = $this->getTestUser('joe@kolab.org');
        $ned = $this->getTestUser('ned@kolab.org');

        $response = $this->get('api/v4/config/webmail');
        $response->assertStatus(401);

        $response = $this->actingAs($john)->get('api/v4/config/webmail');
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertContains('kolab_files', $json['plugins']);
        $this->assertContains('kolab_tags', $json['plugins']);
        $this->assertContains('calendar', $json['plugins']);
        $this->assertContains('tasklist', $json['plugins']);

        $response = $this->actingAs($ned)->get('api/v4/config/webmail');
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertContains('kolab_2fa', $json['plugins']);
        $this->assertContains('kolab_activesync', $json['plugins']);
        $this->assertContains('kolab_files', $json['plugins']);
        $this->assertContains('kolab_tags', $json['plugins']);
        $this->assertContains('calendar', $json['plugins']);
        $this->assertContains('tasklist', $json['plugins']);

        $response = $this->actingAs($joe)->get('api/v4/config/webmail');
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame([], $json['plugins']);
        $this->assertTrue($json['calendar_disabled']);
        $this->assertTrue($json['kolab_files_disabled']);
        $this->assertTrue($json['kolab_tags_disabled']);
        $this->assertTrue($json['tasklist_disabled']);
    }
}

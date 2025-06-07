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

        $this->assertSame(['kolab4', 'groupware'], $json['kolab-configuration-overlays']);

        // Ned has groupware, activesync and 2FA
        $response = $this->actingAs($ned)->get('api/v4/config/webmail');
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(['kolab4', 'activesync', '2fa', 'groupware'], $json['kolab-configuration-overlays']);

        // Joe has no groupware subscription
        $response = $this->actingAs($joe)->get('api/v4/config/webmail');
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(['kolab4'], $json['kolab-configuration-overlays']);
    }
}

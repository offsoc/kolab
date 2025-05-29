<?php

namespace Tests\Feature\Controller;

use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    /**
     * Test password policy check
     */
    public function testCheck(): void
    {
        $jack = $this->getTestUser('jack@kolab.org');
        $john = $this->getTestUser('john@kolab.org');
        $john->setSetting('password_policy', 'min:8,max:100,upper,digit');

        // Empty password
        $post = ['user' => $john->id];
        $response = $this->post('/api/auth/password-policy/check', $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(3, $json);
        $this->assertSame('error', $json['status']);
        $this->assertSame(4, $json['count']);
        $this->assertFalse($json['list'][0]['status']);
        $this->assertSame('min', $json['list'][0]['label']);
        $this->assertFalse($json['list'][1]['status']);
        $this->assertSame('max', $json['list'][1]['label']);
        $this->assertFalse($json['list'][2]['status']);
        $this->assertSame('upper', $json['list'][2]['label']);
        $this->assertFalse($json['list'][3]['status']);
        $this->assertSame('digit', $json['list'][3]['label']);

        // Test acting as Jack, password non-compliant
        $post = ['password' => '9999999', 'user' => $jack->id];
        $response = $this->post('/api/auth/password-policy/check', $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(3, $json);
        $this->assertSame('error', $json['status']);
        $this->assertSame(4, $json['count']);
        $this->assertFalse($json['list'][0]['status']); // min
        $this->assertTrue($json['list'][1]['status']); // max
        $this->assertFalse($json['list'][2]['status']); // upper
        $this->assertTrue($json['list'][3]['status']); // digit

        // Test with no user context, expect use of the default policy
        $post = ['password' => '9'];
        $response = $this->post('/api/auth/password-policy/check', $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertCount(3, $json);
        $this->assertSame('error', $json['status']);
        $this->assertSame(2, $json['count']);
        $this->assertFalse($json['list'][0]['status']);
        $this->assertSame('min', $json['list'][0]['label']);
        $this->assertTrue($json['list'][1]['status']);
        $this->assertSame('max', $json['list'][1]['label']);
    }
}

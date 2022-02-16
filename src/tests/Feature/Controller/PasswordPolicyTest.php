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

    /**
     * Test password-policy listing
     */
    public function testIndex(): void
    {
        // Unauth access not allowed
        $response = $this->get('/api/v4/password-policy');
        $response->assertStatus(401);

        $jack = $this->getTestUser('jack@kolab.org');
        $john = $this->getTestUser('john@kolab.org');
        $john->setSetting('password_policy', 'min:8,max:255,special');

        // Get available policy rules
        $response = $this->actingAs($john)->get('/api/v4/password-policy');
        $json = $response->json();

        $response->assertStatus(200);

        $this->assertCount(2, $json);
        $this->assertSame(7, $json['count']);
        $this->assertCount(7, $json['list']);
        $this->assertSame('Minimum password length: 8 characters', $json['list'][0]['name']);
        $this->assertSame('min', $json['list'][0]['label']);
        $this->assertSame('8', $json['list'][0]['param']);
        $this->assertSame(true, $json['list'][0]['enabled']);
        $this->assertSame('Maximum password length: 255 characters', $json['list'][1]['name']);
        $this->assertSame('max', $json['list'][1]['label']);
        $this->assertSame('255', $json['list'][1]['param']);
        $this->assertSame(true, $json['list'][1]['enabled']);
        $this->assertSame('lower', $json['list'][2]['label']);
        $this->assertSame(false, $json['list'][2]['enabled']);
        $this->assertSame('upper', $json['list'][3]['label']);
        $this->assertSame(false, $json['list'][3]['enabled']);
        $this->assertSame('digit', $json['list'][4]['label']);
        $this->assertSame(false, $json['list'][4]['enabled']);
        $this->assertSame('special', $json['list'][5]['label']);
        $this->assertSame(true, $json['list'][5]['enabled']);
        $this->assertSame('last', $json['list'][6]['label']);
        $this->assertSame(false, $json['list'][6]['enabled']);

        // Test acting as Jack
        $response = $this->actingAs($jack)->get('/api/v4/password-policy');
        $json = $response->json();

        $response->assertStatus(200);

        $this->assertCount(2, $json);
        $this->assertSame(7, $json['count']);
        $this->assertCount(7, $json['list']);
        $this->assertSame('Minimum password length: 8 characters', $json['list'][0]['name']);
        $this->assertSame('min', $json['list'][0]['label']);
        $this->assertSame('8', $json['list'][0]['param']);
        $this->assertSame(true, $json['list'][0]['enabled']);
        $this->assertSame('Maximum password length: 255 characters', $json['list'][1]['name']);
        $this->assertSame('max', $json['list'][1]['label']);
        $this->assertSame('255', $json['list'][1]['param']);
        $this->assertSame(true, $json['list'][1]['enabled']);
        $this->assertSame('lower', $json['list'][2]['label']);
        $this->assertSame(false, $json['list'][2]['enabled']);
        $this->assertSame('upper', $json['list'][3]['label']);
        $this->assertSame(false, $json['list'][3]['enabled']);
        $this->assertSame('digit', $json['list'][4]['label']);
        $this->assertSame(false, $json['list'][4]['enabled']);
        $this->assertSame('special', $json['list'][5]['label']);
        $this->assertSame(true, $json['list'][5]['enabled']);
        $this->assertSame('last', $json['list'][6]['label']);
        $this->assertSame(false, $json['list'][6]['enabled']);
    }
}

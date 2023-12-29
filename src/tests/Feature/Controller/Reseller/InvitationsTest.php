<?php

namespace Tests\Feature\Controller\Reseller;

use App\SignupInvitation;
use App\Tenant;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InvitationsTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        SignupInvitation::truncate();

        self::useResellerUrl();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        SignupInvitation::truncate();

        parent::tearDown();
    }

    /**
     * Test deleting invitations (DELETE /api/v4/invitations/<id>)
     */
    public function testDestroy(): void
    {
        Queue::fake();

        $user = $this->getTestUser('john@kolab.org');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $reseller = $this->getTestUser('reseller@sample-tenant.dev-local');
        $reseller2 = $this->getTestUser('reseller@' . \config('app.domain'));

        $inv = SignupInvitation::create(['email' => 'email1@ext.com']);
        $inv->tenant_id = $reseller->tenant_id;
        $inv->save();

        // Non-admin user
        $response = $this->actingAs($user)->delete("api/v4/invitations/{$inv->id}");
        $response->assertStatus(403);

        // Admin user
        $response = $this->actingAs($admin)->delete("api/v4/invitations/{$inv->id}");
        $response->assertStatus(403);

        // Reseller user, but different tenant
        $response = $this->actingAs($reseller2)->delete("api/v4/invitations/{$inv->id}");
        $response->assertStatus(404);

        // Reseller - non-existing invitation identifier
        $response = $this->actingAs($reseller)->delete("api/v4/invitations/abd");
        $response->assertStatus(404);

        // Reseller - existing invitation
        $response = $this->actingAs($reseller)->delete("api/v4/invitations/{$inv->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame("Invitation deleted successfully.", $json['message']);
        $this->assertSame(null, SignupInvitation::find($inv->id));
    }

    /**
     * Test listing invitations (GET /api/v4/invitations)
     */
    public function testIndex(): void
    {
        Queue::fake();

        $user = $this->getTestUser('john@kolab.org');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $reseller = $this->getTestUser('reseller@' . \config('app.domain'));
        $reseller2 = $this->getTestUser('reseller@sample-tenant.dev-local');
        $tenant = Tenant::where('title', 'Sample Tenant')->first();

        // Non-admin user
        $response = $this->actingAs($user)->get("api/v4/invitations");
        $response->assertStatus(403);

        // Admin user
        $response = $this->actingAs($admin)->get("api/v4/invitations");
        $response->assertStatus(403);

        // Reseller (empty list)
        $response = $this->actingAs($reseller)->get("api/v4/invitations");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(0, $json['count']);
        $this->assertSame([], $json['list']);
        $this->assertSame(1, $json['page']);
        $this->assertFalse($json['hasMore']);

        // Add some invitations
        $i1 = SignupInvitation::create(['email' => 'email1@ext.com']);
        $i2 = SignupInvitation::create(['email' => 'email2@ext.com']);
        $i3 = SignupInvitation::create(['email' => 'email3@ext.com']);
        $i4 = SignupInvitation::create(['email' => 'email4@other.com']);
        $i5 = SignupInvitation::create(['email' => 'email5@other.com']);
        $i6 = SignupInvitation::create(['email' => 'email6@other.com']);
        $i7 = SignupInvitation::create(['email' => 'email7@other.com']);
        $i8 = SignupInvitation::create(['email' => 'email8@other.com']);
        $i9 = SignupInvitation::create(['email' => 'email9@other.com']);
        $i10 = SignupInvitation::create(['email' => 'email10@other.com']);
        $i11 = SignupInvitation::create(['email' => 'email11@other.com']);
        $i12 = SignupInvitation::create(['email' => 'email12@test.com']);
        $i13 = SignupInvitation::create(['email' => 'email13@ext.com']);

        SignupInvitation::query()->update(['created_at' => now()->subDays(1)]);

        SignupInvitation::where('id', $i1->id)
            ->update(['created_at' => now()->subHours(2), 'status' => SignupInvitation::STATUS_FAILED]);

        SignupInvitation::where('id', $i2->id)
            ->update(['created_at' => now()->subHours(3), 'status' => SignupInvitation::STATUS_SENT]);

        SignupInvitation::where('id', $i11->id)->update(['created_at' => now()->subDays(3)]);
        SignupInvitation::where('id', $i12->id)->update(['tenant_id' => $reseller2->tenant_id]);
        SignupInvitation::where('id', $i13->id)->update(['tenant_id' => $reseller2->tenant_id]);

        $response = $this->actingAs($reseller)->get("api/v4/invitations");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(10, $json['count']);
        $this->assertSame(1, $json['page']);
        $this->assertTrue($json['hasMore']);
        $this->assertSame($i1->id, $json['list'][0]['id']);
        $this->assertSame($i1->email, $json['list'][0]['email']);
        $this->assertSame(true, $json['list'][0]['isFailed']);
        $this->assertSame(false, $json['list'][0]['isNew']);
        $this->assertSame(false, $json['list'][0]['isSent']);
        $this->assertSame(false, $json['list'][0]['isCompleted']);
        $this->assertSame($i2->id, $json['list'][1]['id']);
        $this->assertSame($i2->email, $json['list'][1]['email']);
        $this->assertFalse(in_array($i12->email, array_column($json['list'], 'email')));
        $this->assertFalse(in_array($i13->email, array_column($json['list'], 'email')));

        $response = $this->actingAs($reseller)->get("api/v4/invitations?page=2");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertSame(2, $json['page']);
        $this->assertFalse($json['hasMore']);
        $this->assertSame($i11->id, $json['list'][0]['id']);

        // Test searching (email address)
        $response = $this->actingAs($reseller)->get("api/v4/invitations?search=email3@ext.com");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(1, $json['count']);
        $this->assertSame(1, $json['page']);
        $this->assertFalse($json['hasMore']);
        $this->assertSame($i3->id, $json['list'][0]['id']);

        // Test searching (domain)
        $response = $this->actingAs($reseller)->get("api/v4/invitations?search=ext.com");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(3, $json['count']);
        $this->assertSame(1, $json['page']);
        $this->assertFalse($json['hasMore']);
        $this->assertSame($i1->id, $json['list'][0]['id']);

        // Reseller user, but different tenant
        $response = $this->actingAs($reseller2)->get("api/v4/invitations");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame(2, $json['count']);
    }

    /**
     * Test resending invitations (POST /api/v4/invitations/<id>/resend)
     */
    public function testResend(): void
    {
        Queue::fake();

        $user = $this->getTestUser('john@kolab.org');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $reseller = $this->getTestUser('reseller@sample-tenant.dev-local');
        $reseller2 = $this->getTestUser('reseller@' . \config('app.domain'));
        $tenant = Tenant::where('title', 'Sample Tenant')->first();

        $inv = SignupInvitation::create(['email' => 'email1@ext.com']);
        $inv->tenant_id = $reseller->tenant_id;
        $inv->save();

        SignupInvitation::where('id', $inv->id)->update(['status' => SignupInvitation::STATUS_FAILED]);

        // Non-admin user
        $response = $this->actingAs($user)->post("api/v4/invitations/{$inv->id}/resend");
        $response->assertStatus(403);

        // Admin user
        $response = $this->actingAs($admin)->post("api/v4/invitations/{$inv->id}/resend");
        $response->assertStatus(403);

        // Reseller user, but different tenant
        $response = $this->actingAs($reseller2)->post("api/v4/invitations/{$inv->id}/resend");
        $response->assertStatus(404);

        // Reseller - non-existing invitation identifier
        $response = $this->actingAs($reseller)->post("api/v4/invitations/abd/resend");
        $response->assertStatus(404);

        // Reseller - existing invitation
        $response = $this->actingAs($reseller)->post("api/v4/invitations/{$inv->id}/resend");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame("Invitation added to the sending queue successfully.", $json['message']);
        $this->assertTrue($inv->fresh()->isNew());
    }

    /**
     * Test creating invitations (POST /api/v4/invitations)
     */
    public function testStore(): void
    {
        Queue::fake();

        $user = $this->getTestUser('john@kolab.org');
        $admin = $this->getTestUser('jeroen@jeroen.jeroen');
        $reseller = $this->getTestUser('reseller@sample-tenant.dev-local');
        $reseller2 = $this->getTestUser('reseller@' . \config('app.domain'));
        $tenant = Tenant::where('title', 'Sample Tenant')->first();

        // Non-admin user
        $response = $this->actingAs($user)->post("api/v4/invitations", []);
        $response->assertStatus(403);

        // Admin user
        $response = $this->actingAs($admin)->post("api/v4/invitations", []);
        $response->assertStatus(403);

        // Reseller (empty post)
        $response = $this->actingAs($reseller)->post("api/v4/invitations", []);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $this->assertSame("The email field is required.", $json['errors']['email'][0]);

        // Invalid email address
        $post = ['email' => 'test'];
        $response = $this->actingAs($reseller)->post("api/v4/invitations", $post);
        $response->assertStatus(422);

        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertCount(1, $json['errors']);
        $this->assertSame("The email must be a valid email address.", $json['errors']['email'][0]);

        // Valid email address
        $post = ['email' => 'test@external.org'];
        $response = $this->actingAs($reseller)->post("api/v4/invitations", $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('success', $json['status']);
        $this->assertSame("The invitation has been created.", $json['message']);
        $this->assertSame(1, $json['count']);
        $this->assertSame(1, SignupInvitation::count());

        $invitation = SignupInvitation::first();
        $this->assertSame($reseller->tenant_id, $invitation->tenant_id);
        $this->assertSame($post['email'], $invitation->email);

        // Test file input (empty file)
        $tmpfile = tmpfile();
        fwrite($tmpfile, "");
        $file = new File('test.csv', $tmpfile);
        $post = ['file' => $file];
        $response = $this->actingAs($reseller)->post("api/v4/invitations", $post);

        fclose($tmpfile);

        $response->assertStatus(422);
        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertSame("Failed to find any valid email addresses in the uploaded file.", $json['errors']['file']);

        // Test file input with an invalid email address
        $tmpfile = tmpfile();
        fwrite($tmpfile, "t1@domain.tld\r\nt2@domain");
        $file = new File('test.csv', $tmpfile);
        $post = ['file' => $file];
        $response = $this->actingAs($reseller)->post("api/v4/invitations", $post);

        fclose($tmpfile);

        $response->assertStatus(422);
        $json = $response->json();

        $this->assertSame('error', $json['status']);
        $this->assertSame("Found an invalid email address (t2@domain) on line 2.", $json['errors']['file']);

        // Test file input (two addresses)
        $tmpfile = tmpfile();
        fwrite($tmpfile, "t1@domain.tld\r\nt2@domain.tld");
        $file = new File('test.csv', $tmpfile);
        $post = ['file' => $file];
        $response = $this->actingAs($reseller)->post("api/v4/invitations", $post);

        fclose($tmpfile);

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertSame(1, SignupInvitation::where('email', 't1@domain.tld')->count());
        $this->assertSame(1, SignupInvitation::where('email', 't2@domain.tld')->count());
        $this->assertSame('success', $json['status']);
        $this->assertSame("2 invitations has been created.", $json['message']);
        $this->assertSame(2, $json['count']);

        // Reseller user, but different tenant
        $post = ['email' => 'test-reseller2@external.org'];
        $response = $this->actingAs($reseller2)->post("api/v4/invitations", $post);
        $response->assertStatus(200);

        $invitation = SignupInvitation::where('email', $post['email'])->first();
        $this->assertSame($reseller2->tenant_id, $invitation->tenant_id);
    }
}

<?php

namespace Tests\Feature;

use App\Entitlement;
use App\Sku;
use App\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserEntitlementTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $owner = User::firstOrCreate(
            ['email' => 'UserEntitlement1@UserEntitlement.com']
        );

        $user = User::firstOrCreate(
            ['email' => 'UserEntitled1@UserEntitlement.com']
        );

        $entitlement = Entitlement::firstOrCreate(
            [
                'owner_id' => $owner->id,
                'user_id' => $user->id
            ]
        );

        $entitlement->delete();
        $user->delete();
        $owner->delete();
    }

    public function testUserAddEntitlement()
    {
        $sku = Sku::firstOrCreate(
            ['title' => 'individual']
        );

        $owner = User::firstOrCreate(
            ['email' => 'UserEntitlement1@UserEntitlement.com']
        );

        $user = User::firstOrCreate(
            ['email' => 'UserEntitled1@UserEntitlement.com']
        );

        $wallets = $owner->wallets()->get();

        $entitlement = Entitlement::firstOrCreate(
            [
                'owner_id' => $owner->id,
                'user_id' => $user->id,
                'wallet_id' => $wallets[0]->id,
                'sku_id' => $sku->id,
                'description' => "User Entitlement Test"
            ]
        );

        $owner->addEntitlement($entitlement);

        $this->assertTrue($owner->entitlements()->count() == 1);
        $this->assertTrue($sku->entitlements()->count() == 1);
        $this->assertTrue($wallets[0]->entitlements()->count() == 1);

        $this->assertTrue($wallets[0]->fresh()->balance < 0.00);
    }

    public function testUserEntitlements()
    {
        $userA = User::firstOrCreate(
            [
                'email' => 'UserEntitlement2A@UserEntitlement.com'
            ]
        );

        $response = $this->actingAs($userA, 'api')->get("/api/v4/users/{$userA->id}");

        $response->assertStatus(200);
        $response->assertJson(['id' => $userA->id]);

        $user = factory(User::class)->create();
        $response = $this->actingAs($user)->get("/api/v4/users/{$userA->id}");
        $response->assertStatus(404);
    }
}
